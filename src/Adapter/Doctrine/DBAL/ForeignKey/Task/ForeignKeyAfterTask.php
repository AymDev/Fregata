<?php

namespace Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\TableDiff;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\CopyColumnHelper;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\ForeignKey;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Migrator\HasForeignKeysInterface;
use Fregata\Migration\MigrationContext;
use Fregata\Migration\TaskInterface;

/**
 * Add this task if at least one migrator implements HasForeignKeysInterface
 */
class ForeignKeyAfterTask implements TaskInterface
{
    private MigrationContext $context;
    private CopyColumnHelper $columnHelper;

    public function __construct(MigrationContext $context, CopyColumnHelper $columnHelper)
    {
        $this->context = $context;
        $this->columnHelper = $columnHelper;
    }

    public function execute(): ?string
    {
        $savedRelations = 0;

        foreach ($this->context->getMigration()->getMigrators() as $migrator) {
            if ($migrator instanceof HasForeignKeysInterface) {
                foreach ($migrator->getForeignKeys() as $foreignKey) {
                    // Fixes the relations with the correct values
                    $savedRelations += $this->updateForeignKeyValues($migrator->getConnection(), $foreignKey);

                    // Drops copy columns on referenced tables
                    $this->dropReferencedCopyColumns($migrator->getConnection(), $foreignKey);

                    // Drops copy columns on referencing tables and reset NOT NULL on edited columns
                    $this->dropReferencingCopyColumns($migrator->getConnection(), $foreignKey);
                }
            }
        }

        return sprintf(
            '%d relations saved !',
            number_format($savedRelations, 0, '.', ' ')
        );
    }

    /**
     * @return int number of updated rows
     */
    private function updateForeignKeyValues(Connection $connection, ForeignKey $foreignKey): int
    {
        // Create combinations of local/foreign columns/copies used in every query versions
        $columnCombinations = array_map(
            fn(string $local, string $foreign) => [
                'local' => $local,
                'foreign' => $foreign,
                'local_copy' => $this->columnHelper->localColumn($foreignKey->getTableName(), $local),
                'foreign_copy' => $this->columnHelper->foreignColumn(
                    $foreignKey->getConstraint()->getForeignTableName(),
                    $foreign
                )
            ],
            $foreignKey->getConstraint()->getLocalColumns(),
            $foreignKey->getConstraint()->getForeignColumns()
        );

        /*
         * If possible, perform an UPDATE JOIN query which has a better performance.
         * As it is not supported by Doctrine as it is not cross-platform, query is built manually
         *
         * Common UPDATE JOIN query parts:
         */

        // Common SET clause
        $setClause = implode(', ', array_map(
            fn(array $colNames) => sprintf('_l.%s = _f.%s', $colNames['local'], $colNames['foreign']),
            $columnCombinations
        ));

        // Common JOIN conditions (not always in the FROM clause)
        $joinConditions = implode(' AND ', array_map(
            fn(array $colNames) => sprintf('_l.%s = _f.%s', $colNames['local_copy'], $colNames['foreign_copy']),
            $columnCombinations
        ));

        if ($connection->getDatabasePlatform() instanceof MySqlPlatform) {
            /*
             * Example MySQL query with 2 local/foreign columns:
             *      UPDATE local _l
             *          INNER JOIN foreign _f ON
             *              _l.local_copy_1 = _f.foreign_copy_1
             *              AND _l.local_copy_2 = _f.foreign_copy_2
             *      SET _l.local_col_1 = _f.foreign_col_1,
             *          _l.local_col_2 = _f.foreign_col_2
             */

            $updateQuery = sprintf(
                'UPDATE %s _l INNER JOIN %s _f ON %s SET %s',
                $foreignKey->getTableName(),
                $foreignKey->getConstraint()->getForeignTableName(),
                $joinConditions,
                $setClause
            );
        } elseif ($connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
            /*
             * Example PostgreSQL query with 2 local/foreign columns:
             *      UPDATE local _l SET
             *          _l.local_col_1 = _f.foreign_col_1,
             *          _l.local_col_2 = _f.foreign_col_2
             *      FROM foreign _f
             *      WHERE _l.local_copy_1 = _f.foreign_copy_1
             *          AND _l.local_copy_2 = _f.foreign_copy_2
             */

            // PostgreSQL does not support table aliasing in the left side of a SET clause
            $postgresSetClause = implode(', ', array_map(
                fn(array $colNames) => sprintf('%s = _f.%s', $colNames['local'], $colNames['foreign']),
                $columnCombinations
            ));

            $updateQuery = sprintf(
                'UPDATE %s _l SET %s FROM %s _f WHERE %s',
                $foreignKey->getTableName(),
                $postgresSetClause,
                $foreignKey->getConstraint()->getForeignTableName(),
                $joinConditions
            );
        } elseif ($connection->getDatabasePlatform() instanceof SQLServerPlatform) {
            /*
             * Example SQL Server query with 2 local/foreign columns:
             *      UPDATE _l SET
             *          _l.local_col_1 = _f.foreign_col_1,
             *          _l.local_col_2 = _f.foreign_col_2
             *      FROM local _l
             *          INNER JOIN foreign _f ON
             *              _l.local_copy_1 = _f.foreign_copy_1
             *              AND _l.local_copy_2 = _f.foreign_copy_2
             */

            $updateQuery = sprintf(
                'UPDATE _l SET %s FROM %s _l INNER JOIN %s _f ON %s',
                $setClause,
                $foreignKey->getTableName(),
                $foreignKey->getConstraint()->getForeignTableName(),
                $joinConditions
            );
        } else {
            /*
             * Example query for DBMS not supporting UPDATE JOIN, with 2 local/foreign columns:
             *      UPDATE local _l
             *      SET _l.local_col_1 = (
             *              SELECT _f.foreign_col_1
             *              FROM foreign _f
             *              WHERE _l.local_copy_1 = _f.foreign_copy_1
             *                  AND _l.local_copy_2 = _f.foreign_copy_2
             *          ),
             *          _l.local_col_2 = (
             *              SELECT _f.foreign_col_2
             *              FROM foreign _f
             *              WHERE _l.local_copy_1 = _f.foreign_copy_1
             *                  AND _l.local_copy_2 = _f.foreign_copy_2
             *          ),
             *      WHERE _l.local_copy_1 IS NOT NULL
             *          OR _l.local_copy_2 IS NOT NULL
             */

            // Start UPDATE query
            $updateQuery = $connection->createQueryBuilder()
                ->update($foreignKey->getTableName(), '_l');

            // Add to the SET clause per foreign key local columns
            foreach ($columnCombinations as $colNames) {
                // Start the SELECT subquery
                $selectSubQuery = $connection->createQueryBuilder()
                    ->select(sprintf('_f.%s', $colNames['foreign']))
                    ->from($foreignKey->getConstraint()->getForeignTableName(), '_f');

                foreach ($columnCombinations as $whereClause) {
                    // Add the WHERE clause to the subquery, acting as would do a join condition in an UPDATE JOIN query
                    $selectSubQuery->andWhere(sprintf(
                        '_l.%s = _f.%s',
                        $whereClause['local_copy'],
                        $whereClause['foreign_copy']
                    ));

                    /* WHERE clause of the main query will prevent updating rows that existed before the migration and
                       resetting their relations to NULL: only update migrated rows */
                    $updateQuery->orWhere(sprintf('_l.%s IS NOT NULL', $whereClause['local_copy']));
                }

                // Finalized SET clause for the current column
                $updateQuery->set(sprintf('_l.%s', $colNames['local']), sprintf('(%s)', $selectSubQuery->getSQL()));
            }

            // Get QueryBuilder SQL
            $updateQuery = $updateQuery->getSQL();
        }

        return (int) $connection->executeStatement($updateQuery);
    }

    private function dropReferencedCopyColumns(Connection $connection, ForeignKey $foreignKey): void
    {
        $foreignTableName = $foreignKey->getConstraint()->getForeignTableName();
        $originalTable = $connection->getSchemaManager()->listTableDetails($foreignTableName);
        $changedTable = clone $originalTable;

        foreach ($foreignKey->getConstraint()->getForeignColumns() as $columnName) {
            $changedTable->dropColumn($this->columnHelper->foreignColumn($changedTable->getName(), $columnName));
            $changedTable->dropIndex($this->columnHelper->foreignColumnIndex($changedTable->getName(), $columnName));
        }

        $comparator = new Comparator();
        /** @var TableDiff $tableDiff */
        $tableDiff = $comparator->diffTable($originalTable, $changedTable);

        $connection->getSchemaManager()->alterTable($tableDiff);
    }

    private function dropReferencingCopyColumns(Connection $connection, ForeignKey $foreignKey): void
    {
        $originalTable = $connection->getSchemaManager()->listTableDetails($foreignKey->getTableName());
        $changedTable = clone $originalTable;

        // Remove copy columns and indexes
        foreach ($foreignKey->getConstraint()->getLocalColumns() as $columnName) {
            $changedTable->dropColumn($this->columnHelper->localColumn($changedTable->getName(), $columnName));
            $changedTable->dropIndex($this->columnHelper->localColumnIndex($changedTable->getName(), $columnName));

            // Reset NOT NULL on edited columns
            if (in_array($columnName, $foreignKey->getAllowNull())) {
                $changedTable->changeColumn($columnName, ['notnull' => true]);
            }
        }

        $comparator = new Comparator();
        /** @var TableDiff $tableDiff */
        $tableDiff = $comparator->diffTable($originalTable, $changedTable);

        $connection->getSchemaManager()->alterTable($tableDiff);
    }
}

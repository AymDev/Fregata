<?php

namespace Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\CopyColumnHelper;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\ForeignKey;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\ForeignKeyException;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Migrator\HasForeignKeysInterface;
use Fregata\Migration\MigrationContext;
use Fregata\Migration\TaskInterface;

/**
 * Add this task if at least one migrator implements HasForeignKeysInterface
 */
class ForeignKeyBeforeTask implements TaskInterface
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
        foreach ($this->context->getMigration()->getMigrators() as $migrator) {
            if ($migrator instanceof HasForeignKeysInterface) {
                if (false === $migrator->getConnection()->getDatabasePlatform()->supportsForeignKeyConstraints()) {
                    throw ForeignKeyException::incompatiblePlatform($migrator->getConnection()->getDatabasePlatform());
                }

                foreach ($migrator->getForeignKeys() as $foreignKey) {
                    // Create columns in the referenced table for the primary key columns
                    $this->createReferencedColumnCopy($migrator->getConnection(), $foreignKey->getConstraint());

                    // Create columns in the referencing table for the foreign key columns
                    // and drop real referencing columns NOT NULL
                    $this->createReferencingColumnCopy($migrator->getConnection(), $foreignKey);
                }
            }
        }

        return null;
    }

    private function createReferencedColumnCopy(Connection $connection, ForeignKeyConstraint $constraint): void
    {
        $originalTable = $connection->getSchemaManager()->listTableDetails($constraint->getForeignTableName());
        $changedTable = clone $originalTable;

        $columns = array_map(function (string $columnName) use ($originalTable) {
            return [
                'original' => $columnName,
                'copy'     => $this->columnHelper->foreignColumn($originalTable->getName(), $columnName),
                'index'    => $this->columnHelper->foreignColumnIndex($originalTable->getName(), $columnName),
            ];
        }, $constraint->getForeignColumns());

        $this->createCopyColumns($changedTable, $columns);

        $comparator = new Comparator();
        /** @var TableDiff $tableDiff */
        $tableDiff = $comparator->diffTable($originalTable, $changedTable);

        $connection->getSchemaManager()->alterTable($tableDiff);
    }

    private function createReferencingColumnCopy(Connection $connection, ForeignKey $foreignKey): void
    {
        // Create copy columns
        $originalTable = $connection->getSchemaManager()->listTableDetails($foreignKey->getTableName());
        $changedTable = clone $originalTable;

        $columns = array_map(function (string $columnName) use ($originalTable) {
            return [
                'original' => $columnName,
                'copy'     => $this->columnHelper->localColumn($originalTable->getName(), $columnName),
                'index'    => $this->columnHelper->localColumnIndex($originalTable->getName(), $columnName),
            ];
        }, $foreignKey->getConstraint()->getLocalColumns());

        $this->createCopyColumns($changedTable, $columns);

        // Drops given columns NOT NULL
        $nullableColumnNames = array_intersect(
            $foreignKey->getConstraint()->getLocalColumns(),
            $foreignKey->getAllowNull()
        );

        foreach ($nullableColumnNames as $columnName) {
            $changedTable->changeColumn($columnName, ['notnull' => false]);
        }

        $comparator = new Comparator();
        /** @var TableDiff $tableDiff */
        $tableDiff = $comparator->diffTable($originalTable, $changedTable);

        $connection->getSchemaManager()->alterTable($tableDiff);
    }

    /**
     * @param string[][] $columnList
     * @throws SchemaException
     */
    private function createCopyColumns(Table $table, array $columnList): void
    {
        foreach ($columnList as $column) {
            // Stop if copy column already exists
            if ($table->hasColumn($column['copy'])) {
                continue;
            }

            $originalColumn = $table->getColumn($column['original']);

            // Create column
            $table->addColumn($column['copy'], $originalColumn->getType()->getName(), [
                'length' => $originalColumn->getLength(),
                'precision' => $originalColumn->getPrecision(),
                'scale' => $originalColumn->getScale(),
                'fixed' => $originalColumn->getFixed(),
                'unsigned' => $originalColumn->getUnsigned(),
                'notnull' => false,
                'default' => null,
            ]);

            // Create index for copy column
            $table->addIndex([$column['copy']], $column['index']);
        }
    }
}

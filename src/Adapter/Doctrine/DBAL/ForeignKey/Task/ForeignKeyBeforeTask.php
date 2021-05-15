<?php

namespace Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
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
        $tableDiff = $comparator->diffTable($originalTable, $changedTable);

        $connection->getSchemaManager()->alterTable($tableDiff);
    }

//    private function createReferencingColumnCopy(Connection $connection, ForeignKey $foreignKey): void
//    {
//        // Create copy columns
//        $table = $connection->getSchemaManager()->listTableDetails($foreignKey->getTableName());
//        $columns = array_map(function (string $columnName) use ($table) {
//            return [
//                'original' => $columnName,
//                'copy'     => $this->columnHelper->buildNameForReferencingColumn($table->getName(), $columnName),
//                'index'    => $this->columnHelper->buildNameForReferencingColumnIndex($table->getName(), $columnName),
//            ];
//        }, $foreignKey->getConstraint()->getLocalColumns());
//
//        $tableDiff = $this->createCopyColumns($table, $columns);
//        $connection->getSchemaManager()->alterTable($tableDiff);
//
//        // Drops given columns NOT NULL
//        $nullableColumnNames = array_intersect($foreignKey->getConstraint()->getLocalColumns(), $foreignKey->getAllowNull());
//
//        foreach ($nullableColumnNames as $columnName) {
//            $originalColumn = $table->getColumn($columnName);
//            $changedColumn = clone $originalColumn;
//
//            $changedColumn->setNotnull(false);
//            $tableDiff->changedColumns[] = new ColumnDiff($columnName, $changedColumn, ['notnull'], $originalColumn);
//        }
//
//        $connection->getSchemaManager()->alterTable($tableDiff);
//    }

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
        $tableDiff = $comparator->diffTable($originalTable, $changedTable);

        $connection->getSchemaManager()->alterTable($tableDiff);
    }

//    private function createCopyColumns(Table $table, array $columnList): TableDiff
//    {
//        $addedColumns = [];
//        $addedIndexes = [];
//
//        // Create copy columns
//        foreach ($columnList as $column) {
//            // Stop if copy column already exists
//            if ($table->hasColumn($column['copy'])) {
//                continue;
//            }
//
//            $originalColumn = $table->getColumn($column['original']);
//
//            // Create column
//            $copyColumn = (new Column($column['copy'], $originalColumn->getType()))
//                ->setLength($originalColumn->getLength())
//                ->setPrecision($originalColumn->getPrecision())
//                ->setScale($originalColumn->getScale())
//                ->setFixed($originalColumn->getFixed())
//                ->setUnsigned($originalColumn->getUnsigned())
//                ->setNotnull(false)
//                ->setDefault(null);
//            $addedColumns[] = $copyColumn;
//
//            // Create index for copy column
//            $copyIndex = new Index($column['index'], [$column['copy']]);
//            $addedIndexes[] = $copyIndex;
//        }
//
//        return new TableDiff(
//            $table->getName(),
//            $addedColumns,
//            [],
//            [],
//            $addedIndexes,
//            [],
//            [],
//            $table
//        );
//    }

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

<?php


namespace Fregata\Tests;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\StringType;
use Fregata\Connection\AbstractConnection;
use Fregata\Fregata;
use Fregata\Migrator\MigratorException;
use Fregata\Migrator\MigratorInterface;
use Fregata\Migrator\PreservedKeyMigratorInterface;

class FregataTest extends FregataTestCase
{
    /**
     * We must be able to add a migrator
     */
    public function testCanAddMigrator()
    {
        $migrator = $this->getMigratorInterfaceConcretion();

        $fregata = (new Fregata())
            ->addMigrator(get_class($migrator));

        // Just to be sure no exception is thrown
        self::assertInstanceOf(Fregata::class, $fregata);
    }

    /**
     * Any migrator must be a child of MigratorInterface
     */
    public function testAddInvalidMigrator()
    {
        self::expectException(MigratorException::class);
        $fregata = (new Fregata())
            ->addMigrator(get_class(new class {}));
    }

    /**
     * There must be at least 1 registered migrator
     */
    public function testRunWithoutMigrator()
    {
        $fregata = new Fregata();

        self::expectException(\LogicException::class);
        $fregata->run()->next();
    }

    /**
     * Run the migration with a single migrator
     */
    public function testRunWithMigrator()
    {
        $migrator = $this->getMigratorInterfaceConcretion();
        $migratorClassname = get_class($migrator);

        $fregata = (new Fregata())
            ->addMigrator($migratorClassname);

        $migrators = [];
        foreach ($fregata->run() as $registeredMigrator) {
            $migrators[] = $registeredMigrator;
        }

        self::assertCount(1, $migrators);
        self::assertInstanceOf(MigratorInterface::class, $migrators[0]);
    }

    /**
     * Run with a migrator with preserved keys, temporary columns must be deleted after migration
     */
    public function testPreservedKeysColumnsAreDeleted()
    {
        // Create table in both databases (no dataset needed)
        $table = new Table(
            'fregata_test',
            [
                new Column('id', new IntegerType(), ['autoincrement' => true]),
                new Column('col_str', new StringType(), ['notNull' => false]),
            ],
            [new Index('pk', ['id'], false, true)]
        );

        $this->createTargetTable($this->getMySQLConnection(), $table);
        $this->createTargetTable($this->getPgSQLConnection(), $table);

        // Create migrator
        $migrator = new class implements MigratorInterface, PreservedKeyMigratorInterface {
            public function getSourceConnection(): AbstractConnection
            {
                return new class extends AbstractConnection {
                    public string $url = 'mysql://root:root@127.0.0.1:3306/fregata_source';
                };
            }

            public function getTargetConnection(): AbstractConnection
            {
                return new class extends AbstractConnection {
                    public string $url = 'pgsql://postgres:postgres@127.0.0.1:5432/fregata_target';
                };
            }

            public function getTotalRows(Connection $source): int
            {
                return 1;
            }

            public function migrate(Connection $source, Connection $target): \Generator
            {
                yield 1;
            }

            public function getPrimaryKeyColumnName(): string
            {
                return 'id';
            }

            public function getSourceTable(): string
            {
                return 'fregata_test';
            }

            public function getTargetTable(): string
            {
                return 'fregata_test';
            }
        };

        $schemaManager = $this->getPgSQLConnection()
            ->getConnection()
            ->getSchemaManager();

        // Before migrations
        $columnsBefore = $schemaManager->listTableColumns('fregata_test');
        $indexesBefore = $schemaManager->listTableIndexes('fregata_test');

        // Run migration
        $fregata = (new Fregata())
            ->addMigrator(get_class($migrator));

        foreach ($fregata->run() as $migration) {
            /* just run it */
        }

        // After migrations
        $columnsAfter = $schemaManager->listTableColumns('fregata_test');
        $indexesAfter = $schemaManager->listTableIndexes('fregata_test');

        self::assertSame(array_keys($columnsBefore), array_keys($columnsAfter));
        self::assertSame(array_keys($indexesBefore), array_keys($indexesAfter));
    }
}
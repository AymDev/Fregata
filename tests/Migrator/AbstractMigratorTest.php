<?php

namespace Fregata\Tests\Migrator;

use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\StringType;
use Fregata\Connection\AbstractConnection;
use Fregata\Fregata;
use Fregata\Migrator\AbstractMigrator;
use Fregata\Migrator\MigratorException;
use Fregata\Migrator\MigratorInterface;
use Fregata\Migrator\PreservedKeyMigratorInterface;
use Fregata\Tests\FregataTestCase;

class AbstractMigratorTest extends FregataTestCase
{
    /**
     * Set database initial state
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->createSourceTable($this->getMySQLConnection(), $this->getTable(), [
            ['col_str' => 'foo', 'col_int' => 42],
            ['col_str' => null, 'col_int' => 53],
            ['col_str' => 'bar', 'col_int' => 31],
        ]);

        $this->createTargetTable($this->getPgSQLConnection(), $this->getTable());
    }

    /**
     * Get the test table
     */
    private function getTable(): Table
    {
        return new Table(
            'abstract_migrator',
            [
                new Column('id', new IntegerType(), ['autoincrement' => true]),
                new Column('col_str', new StringType(), ['notNull' => false]),
                new Column('col_int', new IntegerType()),
            ],
            [new Index('pk', ['id'], false, true)]
        );
    }

    /**
     * Data must be copied from source to target
     */
    public function testMigrate()
    {
        $migrator = new class extends AbstractMigrator {
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

            protected function pullFromSource(QueryBuilder $queryBuilder): QueryBuilder
            {
                return $queryBuilder
                    ->select('col_str, col_int')
                    ->from('abstract_migrator');
            }

            protected function pushToTarget(QueryBuilder $queryBuilder, array $row): QueryBuilder
            {
                return $queryBuilder
                    ->insert('abstract_migrator')
                    ->setValue('col_str', '?')
                    ->setValue('col_int', '?')
                    ->setParameter(0, $row['col_str'])
                    ->setParameter(1, $row['col_int']);
            }
        };

        // Get the total number of rows
        $total = $migrator->getTotalRows($migrator->getSourceConnection()->getConnection());
        self::assertSame(3, $total);

        $migration = $migrator->migrate(
            $migrator->getSourceConnection()->getConnection(),
            $migrator->getTargetConnection()->getConnection()
        );
        $result = 0;
        foreach ($migration as $iteration) {
            $result = $iteration;
        }
        self::assertSame(3, $result);
    }

    /**
     * The pull operation must be a SELECT query
     */
    public function testPullOperationIsSelect()
    {
        $migrator = new class extends AbstractMigrator {
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

            protected function pullFromSource(QueryBuilder $queryBuilder): QueryBuilder
            {
                return $queryBuilder->delete('abstract_migrator');
            }

            protected function pushToTarget(QueryBuilder $queryBuilder, array $row): QueryBuilder
            {
                return $queryBuilder;
            }
        };

        self::expectException(MigratorException::class);
        $migration = $migrator->migrate(
            $migrator->getSourceConnection()->getConnection(),
            $migrator->getTargetConnection()->getConnection()
        );
        $migration->next();
    }

    /**
     * The push operation must be a INSERT query
     */
    public function testPushOperationIsInsert()
    {
        $migrator = new class extends AbstractMigrator {
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

            protected function pullFromSource(QueryBuilder $queryBuilder): QueryBuilder
            {
                return $queryBuilder
                    ->select('col_str, col_int')
                    ->from('abstract_migrator');
            }

            protected function pushToTarget(QueryBuilder $queryBuilder, array $row): QueryBuilder
            {
                return $queryBuilder->delete('abstract_migrator');
            }
        };

        self::expectException(MigratorException::class);
        $migration = $migrator->migrate(
            $migrator->getSourceConnection()->getConnection(),
            $migrator->getTargetConnection()->getConnection()
        );
        $migration->next();
    }

    /**
     * Checks consistency for fetching a foreign key new value
     */
    public function testForeignKeys()
    {
        // Referenced table
        $referencedTable = new Table(
            'abstract_migrator_referenced',
            [
                new Column('id', new IntegerType(), ['autoincrement' => true]),
                new Column('value', new StringType(), ['notNull' => false]),
            ],
            [new Index('pk', ['id'], false, true)]
        );

        $this->createSourceTable($this->getMySQLConnection(), $referencedTable, [
            ['id' => 15, 'value' => 'foo'],
            ['id' => 30, 'value' => 'bar'],
            ['id' => 45, 'value' => 'baz'],
        ]);
        $this->createTargetTable($this->getPgSQLConnection(), $referencedTable);

        // Referenced table migrator
        $referencedMigrator = new class extends AbstractMigrator implements PreservedKeyMigratorInterface {
            protected function pullFromSource(QueryBuilder $queryBuilder): QueryBuilder
            {
                return $queryBuilder->select('id', 'value')->from('abstract_migrator_referenced');
            }

            protected function pushToTarget(QueryBuilder $queryBuilder, array $row): QueryBuilder
            {
                return $queryBuilder
                    ->insert('abstract_migrator_referenced')
                    ->setValue('value', ':value')
                    ->setParameter('value', $row['value']);
            }

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

            public function getPrimaryKeyColumnName(): string
            {
                return 'id';
            }

            public function getSourceTable(): string
            {
                return 'abstract_migrator_referenced';
            }

            public function getTargetTable(): string
            {
                return 'abstract_migrator_referenced';
            }
        };

        // Referencing table

        // Referenced table
        $referencingTable = new Table(
            'abstract_migrator_referencing',
            [
                new Column('id', new IntegerType(), ['autoincrement' => true]),
                new Column('fk', new IntegerType(), ['notNull' => false]),
            ],
            [new Index('pk', ['id'], false, true)]
        );

        $this->createSourceTable($this->getMySQLConnection(), $referencingTable, [
            ['fk' => 45],
            ['fk' => 15],
            ['fk' => 45],
            ['fk' => 30],
        ]);
        $this->createTargetTable($this->getPgSQLConnection(), $referencingTable);

        // Referencing migrator
        $referencingMigrator = new class extends AbstractMigrator {
            protected function pullFromSource(QueryBuilder $queryBuilder): QueryBuilder
            {
                return $queryBuilder->select('id', 'fk')->from('abstract_migrator_referencing');
            }

            protected function pushToTarget(QueryBuilder $queryBuilder, array $row): QueryBuilder
            {
                return $queryBuilder
                    ->insert('abstract_migrator_referencing')
                    ->setValue('fk', $this->getForeignKey($row['fk'], 'abstract_migrator_referenced'));
            }

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
        };

        // Fregata setup
        $fregata = new Fregata();
        $fregata
            ->addMigrator(get_class($referencedMigrator))
            ->addMigrator(get_class($referencingMigrator))
        ;

        // Migration
        foreach ($fregata->run() as $migration) {
            /** @var MigratorInterface $migration */

            $source = $migration->getSourceConnection()->getConnection();
            $target = $migration->getTargetConnection()->getConnection();

            foreach ($migration->migrate($source, $target) as $insert) {
                /* just run it ! */
            }
        }

        // Check foreign key columns
        $data = $referencingMigrator
            ->getTargetConnection()
            ->getConnection()
            ->executeQuery('SELECT id, fk FROM abstract_migrator_referencing ORDER BY id')
            ->fetchAll(FetchMode::ASSOCIATIVE);

        self::assertSame(
            [
                ['id' => 1, 'fk' => 3],
                ['id' => 2, 'fk' => 1],
                ['id' => 3, 'fk' => 3],
                ['id' => 4, 'fk' => 2],
            ],
            $data
        );
    }
}
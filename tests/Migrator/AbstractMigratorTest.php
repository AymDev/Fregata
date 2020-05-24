<?php

namespace Fregata\Tests\Migrator;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\StringType;
use Fregata\Connection\AbstractConnection;
use Fregata\Migrator\AbstractMigrator;
use Fregata\Migrator\MigratorException;
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
}
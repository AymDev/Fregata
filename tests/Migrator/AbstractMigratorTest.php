<?php

namespace Fregata\Tests\Migrator;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\StringType;
use Fregata\Migrator\AbstractMigrator;
use Fregata\Migrator\MigratorException;
use Fregata\Tests\DatabaseTestCase;
use Fregata\Tests\TestHelper;

class AbstractMigratorTest extends DatabaseTestCase
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

            public function getSourceConnection(): string
            {
                return '';
            }

            public function getTargetConnection(): string
            {
                return '';
            }
        };

        $result = $migrator->migrate(
            $this->getMySQLConnection()->getConnection(),
            $this->getPgSQLConnection()->getConnection()
        );
        self::assertSame(3, $result);
    }

    /**
     * The pull operation must be a SELECT query
     */
    public function testPullOperationIsSelect()
    {
        $migrator = new class extends AbstractMigrator {
            protected function pullFromSource(QueryBuilder $queryBuilder): QueryBuilder
            {
                return $queryBuilder->delete('abstract_migrator');
            }

            protected function pushToTarget(QueryBuilder $queryBuilder, array $row): QueryBuilder
            {
                return $queryBuilder;
            }

            public function getSourceConnection(): string
            {
                return '';
            }

            public function getTargetConnection(): string
            {
                return '';
            }
        };

        self::expectException(MigratorException::class);
        $migrator->migrate(
            $this->getMySQLConnection()->getConnection(),
            $this->getPgSQLConnection()->getConnection()
        );
    }

    /**
     * The push operation must be a INSERT query
     */
    public function testPushOperationIsInsert()
    {
        $migrator = new class extends AbstractMigrator {
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

            public function getSourceConnection(): string
            {
                return '';
            }

            public function getTargetConnection(): string
            {
                return '';
            }
        };

        self::expectException(MigratorException::class);
        $migrator->migrate(
            $this->getMySQLConnection()->getConnection(),
            $this->getPgSQLConnection()->getConnection()
        );
    }
}
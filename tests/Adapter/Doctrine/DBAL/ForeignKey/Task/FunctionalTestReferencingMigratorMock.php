<?php

namespace Fregata\Tests\Adapter\Doctrine\DBAL\ForeignKey\Task;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\CopyColumnHelper;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\ForeignKey;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Migrator\HasForeignKeysInterface;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\DependentMigratorInterface;
use Fregata\Migration\Migrator\MigratorInterface;

class FunctionalTestReferencingMigratorMock implements HasForeignKeysInterface, DependentMigratorInterface
{
    private Connection $connection;
    private CopyColumnHelper $columnHelper;

    public function __construct(Connection $connection, CopyColumnHelper $columnHelper)
    {
        $this->connection = $connection;
        $this->columnHelper = $columnHelper;
    }

    public function getPuller(): PullerInterface
    {
        return new class($this->connection) implements PullerInterface {
            private Connection $connection;

            public function __construct(Connection $connection)
            {
                $this->connection = $connection;
            }

            public function pull()
            {
                return $this->connection->createQueryBuilder()
                    ->select('*')
                    ->from('source_referencing')
                    ->execute()
                    ->fetchAll(FetchMode::ASSOCIATIVE);
            }

            public function count(): ?int
            {
                return null;
            }
        };
    }

    public function getPusher(): PusherInterface
    {
        return new class($this->connection, $this->columnHelper) implements PusherInterface {
            private Connection $connection;
            private CopyColumnHelper $columnHelper;

            public function __construct(Connection $connection, CopyColumnHelper $columnHelper)
            {
                $this->connection = $connection;
                $this->columnHelper = $columnHelper;
            }

            public function push($data): int
            {
                $columnName = $this->columnHelper->localColumn('target_referencing', 'fk');
                return $this->connection->createQueryBuilder()
                    ->insert('target_referencing')
                    ->values([
                        $columnName => $data['fk']
                    ])
                    ->execute();
            }
        };
    }

    public function getExecutor(): Executor
    {
        return new Executor();
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getForeignKeys(): array
    {
        return array_map(
            fn(ForeignKeyConstraint $constraint) => new ForeignKey($constraint, 'target_referencing', ['fk']),
            $this->connection->getSchemaManager()->listTableForeignKeys('target_referencing')
        );
    }

    public function getDependencies(): array
    {
        return [
            FunctionalTestReferencedMigratorMock::class,
        ];
    }
}

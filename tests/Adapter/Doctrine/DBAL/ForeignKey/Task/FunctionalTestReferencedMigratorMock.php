<?php

namespace Fregata\Tests\Adapter\Doctrine\DBAL\ForeignKey\Task;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\CopyColumnHelper;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\MigratorInterface;

class FunctionalTestReferencedMigratorMock implements MigratorInterface
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
        return new class ($this->connection) implements PullerInterface {
            private Connection $connection;

            public function __construct(Connection $connection)
            {
                $this->connection = $connection;
            }

            public function pull()
            {
                return $this->connection->createQueryBuilder()
                    ->select('*')
                    ->from('source_referenced')
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
        return new class ($this->connection, $this->columnHelper) implements PusherInterface {
            private Connection $connection;
            private CopyColumnHelper $columnHelper;

            public function __construct(Connection $connection, CopyColumnHelper $columnHelper)
            {
                $this->connection = $connection;
                $this->columnHelper = $columnHelper;
            }

            public function push($data): int
            {
                $columnName = $this->columnHelper->foreignColumn('target_referenced', 'pk');
                return $this->connection->createQueryBuilder()
                    ->insert('target_referenced')
                    ->values([
                        $columnName => $data['pk']
                    ])
                    ->execute();
            }
        };
    }

    public function getExecutor(): Executor
    {
        return new Executor();
    }
}

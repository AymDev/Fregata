<?php

namespace Fregata\Tests\Adapter\Doctrine\DBAL\Fixtures;

use Doctrine\DBAL\Connection;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\CopyColumnHelper;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\MigratorInterface;

/**
 * @internal for testing purposes only.
 */
final class TestReferencedMigrator implements MigratorInterface
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
        return new TestForeignKeyPuller($this->connection, 'source_referenced');
    }

    public function getPusher(): PusherInterface
    {
        return new TestReferencedPusher($this->connection, $this->columnHelper, 'target_referenced');
    }

    public function getExecutor(): Executor
    {
        return new Executor();
    }
}

<?php

namespace Fregata\Tests\Adapter\Doctrine\DBAL\Fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\CopyColumnHelper;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\ForeignKey;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Migrator\HasForeignKeysInterface;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\DependentMigratorInterface;

/**
 * @internal for testing purposes only.
 */
final class TestReferencingMigrator implements HasForeignKeysInterface, DependentMigratorInterface
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
        return new TestForeignKeyPuller($this->connection, 'source_referencing');
    }

    public function getPusher(): PusherInterface
    {
        return new TestReferencingPusher($this->connection, $this->columnHelper, 'target_referencing');
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
            TestReferencedMigrator::class,
        ];
    }
}

<?php

namespace Fregata\Tests\Adapter\Doctrine\DBAL\Fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ForwardCompatibility\DriverStatement;
use Fregata\Migration\Migrator\Component\PullerInterface;

/**
 * @internal for testing purposes only.
 */
final class TestForeignKeyPuller implements PullerInterface
{
    private Connection $connection;
    private string $tableName;

    public function __construct(Connection $connection, string $tableName)
    {
        $this->connection = $connection;
        $this->tableName = $tableName;
    }

    public function pull()
    {
        /** @var DriverStatement<mixed> $result */
        $result = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->execute();
        return $result->fetchAll(FetchMode::ASSOCIATIVE);
    }

    public function count(): ?int
    {
        return null;
    }
}

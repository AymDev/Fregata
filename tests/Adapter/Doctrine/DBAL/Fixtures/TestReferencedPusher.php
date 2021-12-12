<?php

namespace Fregata\Tests\Adapter\Doctrine\DBAL\Fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\CopyColumnHelper;
use Fregata\Migration\Migrator\Component\PusherInterface;

/**
 * @internal for testing purposes only.
 */
final class TestReferencedPusher implements PusherInterface
{
    private Connection $connection;
    private CopyColumnHelper $columnHelper;
    private string $tableName;

    public function __construct(Connection $connection, CopyColumnHelper $columnHelper, string $tableName)
    {
        $this->connection = $connection;
        $this->columnHelper = $columnHelper;
        $this->tableName = $tableName;
    }

    /**
     * @param string[] $data
     * @throws Exception
     */
    public function push($data): int
    {
        $columnName = $this->columnHelper->foreignColumn($this->tableName, 'pk');

        /** @var int $insertCount */
        $insertCount = $this->connection->createQueryBuilder()
            ->insert($this->tableName)
            ->values([
                $columnName => $data['pk']
            ])
            ->execute();
        return $insertCount;
    }
}

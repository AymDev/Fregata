<?php

namespace Fregata\Tests\Adapter\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

abstract class AbstractDbalTestCase extends TestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'url' => 'mysql://root:root@127.0.0.1:3306/fregata_source'
        ]);

        $this->dropTables();
    }

    protected function tearDown(): void
    {
        $this->dropTables();

        $this->connection->close();
    }

    protected function dropTables(): void
    {
        $dropTables = implode('', array_map(
            fn(string $tableName) => sprintf('DROP TABLE IF EXISTS %s;', $tableName),
            $this->getTables()
        ));

        $this->connection->exec(<<<SQL
            SET FOREIGN_KEY_CHECKS=0;
            $dropTables
            SET FOREIGN_KEY_CHECKS=1;
        SQL);
    }

    /**
     * @return string[]
     */
    abstract protected function getTables(): array;
}

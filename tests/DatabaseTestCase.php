<?php

namespace Fregata\Tests;

use Doctrine\DBAL\Schema\Table;
use Fregata\Connection\AbstractConnection;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    /**
     * Get a MySQL connection
     */
    public function getMySQLConnection(): AbstractConnection
    {
        return new class extends AbstractConnection {
            public string $url = 'mysql://root:root@127.0.0.1:3306/fregata_source';
        };
    }

    /**
     * Get a Postgres connection
     */
    public function getPgSQLConnection(): AbstractConnection
    {
        return new class extends AbstractConnection {
            public string $url = 'pgsql://postgres:postgres@127.0.0.1:5432/fregata_target';
        };
    }
}
<?php


namespace Fregata\Tests\Connection;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Fregata\Connection\AbstractConnection;
use PHPUnit\Framework\TestCase;

class AbstractConnectionTest extends TestCase
{
    /**
     * Extending AbstractConnection classes must declare properties
     * matching Doctrine connection param names.
     */
    public function testConfiguredConnectionReturnsDoctrineConnection()
    {
        // Child class is configured
        $connection = new class extends AbstractConnection {
            public string $url = 'mysql://root:root@127.0.0.1/fregata_source';
        };
        $doctrineConnection = $connection->getConnection();
        self::assertInstanceOf(Connection::class, $doctrineConnection);

        // Child class has not defined any parameter
        $connection = new class extends AbstractConnection {};
        self::expectException(DBALException::class);
        $connection->getConnection();
    }
}
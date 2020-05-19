<?php


namespace Fregata\Tests;


use Fregata\Connection\AbstractConnection;
use Fregata\Connection\ConnectionException;
use Fregata\Fregata;
use PHPUnit\Framework\TestCase;

class FregataTest extends TestCase
{
    /**
     * Valid connections must be sent successfully
     */
    public function testAddValidConnections()
    {
        $source = $this->getMockForAbstractClass(AbstractConnection::class);
        $target = $this->getMockForAbstractClass(AbstractConnection::class);

        $fregata = new Fregata();
        $result = $fregata
            ->addSource(get_class($source))
            ->addTarget(get_class($target));

        // Just to be sure no exception is thrown
        self::assertSame($fregata, $result);
    }
    /**
     * Invalid connections must throw an Exception
     */
    public function testAddInvalidConnections()
    {
        $source = new class {};
        $target = new class {};

        $fregata = new Fregata();

        self::expectException(ConnectionException::class);
        $fregata
            ->addSource(get_class($source))
            ->addTarget(get_class($target));
    }
}
<?php


namespace Fregata\Tests;


use Fregata\Connection\AbstractConnection;
use Fregata\Connection\ConnectionException;
use Fregata\Fregata;
use Fregata\Migrator\MigratorInterface;
use PHPUnit\Framework\TestCase;

class FregataTest extends TestCase
{
    /**
     * Migrators implementing MigratorInterface with valid connections must be added successfully
     */
    public function testAddValidMigrator()
    {
        $sourceClassName = 'SourceConnection';
        $source = $this->getMockForAbstractClass(AbstractConnection::class, [], $sourceClassName);
        $targetClassName = 'TargetConnection';
        $target = $this->getMockForAbstractClass(AbstractConnection::class, [], $targetClassName);

        $migrator = $this->createMock(MigratorInterface::class);
        $migrator->method('getSourceConnection')->willReturn($sourceClassName);
        $migrator->method('getTargetConnection')->willReturn($targetClassName);

        $fregata = (new Fregata())
            ->addMigrator($migrator);

        // Just to be sure no exception is thrown
        self::assertInstanceOf(Fregata::class, $fregata);
    }

    /**
     * Any migrator must return connection class names extending AbstractConnection
     */
    public function testAddInvalidMigrator()
    {
        $invalidSource = new class {};
        $invalidTarget = new class {};

        $migrator = $this->createMock(MigratorInterface::class);
        $migrator->method('getSourceConnection')->willReturn(get_class($invalidSource));
        $migrator->method('getTargetConnection')->willReturn(get_class($invalidTarget));

        $fregata = new Fregata();

        self::expectException(ConnectionException::class);
        $fregata->addMigrator($migrator);
    }
}
<?php


namespace Fregata\Tests;


use Fregata\Connection\ConnectionException;
use Fregata\Fregata;
use Fregata\Migrator\MigratorInterface;

class FregataTest extends FregataTestCase
{
    /**
     * Migrators implementing MigratorInterface with valid connections must be added successfully
     */
    public function testAddValidMigrator()
    {
        $source = $this->getMySQLConnection();
        $target = $this->getPgSQLConnection();

        $migrator = $this->createMock(MigratorInterface::class);
        $migrator->method('getSourceConnection')->willReturn(get_class($source));
        $migrator->method('getTargetConnection')->willReturn(get_class($target));

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
        $migrator = $this->createMock(MigratorInterface::class);
        $migrator->method('getSourceConnection')->willReturn(get_class(new class {}));
        $migrator->method('getTargetConnection')->willReturn(get_class(new class {}));

        $fregata = new Fregata();

        self::expectException(ConnectionException::class);
        $fregata->addMigrator($migrator);
    }

    /**
     * There must be at least 1 registered migrator
     */
    public function testRunWithoutMigrator()
    {
        $fregata = new Fregata();

        self::expectException(\LogicException::class);
        $fregata->run();
    }

    /**
     * Run the migration with a single migrator
     */
    public function testRunWithMigrator()
    {
        $source = $this->getMySQLConnection();
        $target = $this->getPgSQLConnection();

        $migrator = $this->createMock(MigratorInterface::class);
        $migrator->method('getSourceConnection')->willReturn(get_class($source));
        $migrator->method('getTargetConnection')->willReturn(get_class($target));

        $fregata = (new Fregata())
            ->addMigrator($migrator);

        $result = $fregata->run();
        self::assertNull($result);
    }
}
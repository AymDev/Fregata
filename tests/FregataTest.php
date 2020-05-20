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
        $source = new class extends AbstractConnection {
            public string $url = 'mysql://root:root@127.0.0.1:3306/fregata_source';
        };
        $target = new class extends AbstractConnection {
            public string $url = 'pgsql://postgres:postgres@127.0.0.1:5432/fregata_target';
        };

        $migrator = $this->createMock(MigratorInterface::class);
        $migrator->method('getSourceConnection')->willReturn(get_class($source));
        $migrator->method('getTargetConnection')->willReturn(get_class($target));

        $fregata = (new Fregata())
            ->addMigrator($migrator);

        $result = $fregata->run();
        self::assertNull($result);
    }
}
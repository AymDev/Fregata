<?php


namespace Fregata\Tests;


use Fregata\Fregata;
use Fregata\Migrator\MigratorException;
use Fregata\Migrator\MigratorInterface;

class FregataTest extends FregataTestCase
{
    /**
     * We must be able to add a migrator
     */
    public function testCanAddMigrator()
    {
        $migrator = $this->getMigratorInterfaceConcretion();

        $fregata = (new Fregata())
            ->addMigrator(get_class($migrator));

        // Just to be sure no exception is thrown
        self::assertInstanceOf(Fregata::class, $fregata);
    }

    /**
     * Any migrator must be a child of MigratorInterface
     */
    public function testAddInvalidMigrator()
    {
        self::expectException(MigratorException::class);
        $fregata = (new Fregata())
            ->addMigrator(get_class(new class {}));
    }

    /**
     * There must be at least 1 registered migrator
     */
    public function testRunWithoutMigrator()
    {
        $fregata = new Fregata();

        self::expectException(\LogicException::class);
        $fregata->run()->next();
    }

    /**
     * Run the migration with a single migrator
     */
    public function testRunWithMigrator()
    {
        $migrator = $this->getMigratorInterfaceConcretion();
        $migratorClassname = get_class($migrator);

        $fregata = (new Fregata())
            ->addMigrator($migratorClassname);

        $migrators = [];
        foreach ($fregata->run() as $registeredMigrator) {
            $migrators[] = $registeredMigrator;
        }

        self::assertCount(1, $migrators);
        self::assertInstanceOf(MigratorInterface::class, $migrators[0]);
    }
}
<?php

namespace Fregata\Tests\Migration;

use Fregata\Migration\Migration;
use Fregata\Migration\MigrationException;
use Fregata\Migration\Migrator\DependentMigratorInterface;
use Fregata\Migration\Migrator\MigratorInterface;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\TaskInterface;
use PHPUnit\Framework\TestCase;

class MigrationTest extends TestCase
{
    /**
     * Migrators can be added and listed
     */
    public function testCanAddMigrator()
    {
        $migration = new Migration();

        self::assertIsArray($migration->getMigrators());
        self::assertCount(0, $migration->getMigrators());

        /** @var MigratorInterface $migrator */
        $migrator = $this->createMock(MigratorInterface::class);

        $migration->add($migrator);
        self::assertCount(1, $migration->getMigrators());
        self::assertContains($migrator, $migration->getMigrators());
    }

    /**
     * Migrators must be sorted by their dependencies (topological sorting)
     */
    public function testMigratorsAreTolologicallySorted()
    {
        $migration = new Migration();

        $migrator = $this->createMock(MigratorInterface::class);
        $dependentMigrator = $this->createMock(DependentMigratorInterface::class);
        $dependentMigrator->method('getDependencies')->willReturn([get_class($migrator)]);

        // Add in reverse order
        $migration->add($dependentMigrator);
        $migration->add($migrator);

        $sortedMigrators = $migration->getMigrators();
        self::assertSame($migrator, $sortedMigrators[0]);
        self::assertSame($dependentMigrator, $sortedMigrators[1]);
    }

    /**
     * Migrators must be unique in a migration
     */
    public function testMigratorCannotBeAddedTwice()
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionCode(1619907353293);

        $migration = new Migration();
        $migrator = $this->createMock(MigratorInterface::class);

        $migration->add($migrator);
        $migration->add($migrator);
    }

    /**
     * Circular dependencies must be detected
     */
    public function testCircularDependencyDetection()
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionCode(1619911058924);

        $migration = new Migration();

        $migration->add(new CircularFirstMigrator());
        $migration->add(new CircularSecondMigrator());

        $migration->getMigrators();
    }

    /**
     * Unregistered dependencies must be detected
     */
    public function testUnregisteredDependencyDetection()
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionCode(1619911058924);

        $migrator = $this->createMock(DependentMigratorInterface::class);
        $migrator->method('getDependencies')->willReturn(['unknown dependency']);

        $migration = new Migration();
        $migration->add($migrator);

        $migration->getMigrators();
    }

    /**
     * Tasks management
     */
    public function testCanAddTasks()
    {
        $migration = new Migration();
        self::assertCount(0, $migration->getBeforeTasks());
        self::assertCount(0, $migration->getAfterTasks());

        $migration->addBeforeTask($this->createMock(TaskInterface::class));
        self::assertCount(1, $migration->getBeforeTasks());
        self::assertCount(0, $migration->getAfterTasks());

        $migration->addAfterTask($this->createMock(TaskInterface::class));
        self::assertCount(1, $migration->getBeforeTasks());
        self::assertCount(1, $migration->getAfterTasks());
    }
}

/**
 * Circular dependency mocks
 * @see MigrationTest::testCircularDependencyDetection()
 */
class CircularFirstMigrator implements DependentMigratorInterface
{
    public function getDependencies(): array
    {
        return [CircularSecondMigrator::class];
    }

    public function getPuller(): PullerInterface {}
    public function getPusher(): PusherInterface {}
    public function getExecutor(): Executor {}
}

class CircularSecondMigrator implements DependentMigratorInterface
{
    public function getDependencies(): array
    {
        return [CircularFirstMigrator::class];
    }

    public function getPuller(): PullerInterface {}
    public function getPusher(): PusherInterface {}
    public function getExecutor(): Executor {}
}

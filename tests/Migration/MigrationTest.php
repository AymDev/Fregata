<?php

namespace Fregata\Tests\Migration;

use Fregata\Migration\Migration;
use Fregata\Migration\MigrationException;
use Fregata\Migration\Migrator\DependentMigratorInterface;
use Fregata\Migration\Migrator\MigratorInterface;
use Fregata\Migration\TaskInterface;
use PHPUnit\Framework\TestCase;

class MigrationTest extends TestCase
{
    /**
     * Migrators can be added and listed
     */
    public function testCanAddMigrator(): void
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
    public function testMigratorsAreTolologicallySorted(): void
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
    public function testMigratorCannotBeAddedTwice(): void
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
    public function testCircularDependencyDetection(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionCode(1619911058924);

        // Mocks are created in different ways to get different class names
        $circularFirstMigrator = $this->getMockBuilder(DependentMigratorInterface::class)->getMockForAbstractClass();
        $circularSecondMigrator = $this->createMock(DependentMigratorInterface::class);
        $circularFirstMigrator->method('getDependencies')->willReturn([get_class($circularSecondMigrator)]);
        $circularSecondMigrator->method('getDependencies')->willReturn([get_class($circularFirstMigrator)]);

        $migration = new Migration();

        $migration->add($circularFirstMigrator);
        $migration->add($circularSecondMigrator);

        $migration->getMigrators();
    }

    /**
     * Unregistered dependencies must be detected
     */
    public function testUnregisteredDependencyDetection(): void
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
    public function testCanAddTasks(): void
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

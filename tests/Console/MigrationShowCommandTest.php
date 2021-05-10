<?php

namespace Fregata\Tests\Console;

use Fregata\Console\CommandHelper;
use Fregata\Console\MigrationShowCommand;
use Fregata\Migration\Migration;
use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\MigratorInterface;
use Fregata\Migration\TaskInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class MigrationShowCommandTest extends TestCase
{
    /**
     * The migration:show command lists migrators of a migration
     */
    public function testListMigrationMigrators()
    {
        $migration = new Migration();
        $migration->add(new MigrationShowCommandFirstMigrator());
        $migration->add(new MigrationShowCommandSecondMigrator());

        $registry = new MigrationRegistry();
        $registry->add('foo', $migration);

        $command = new MigrationShowCommand($registry, new CommandHelper());
        $tester = new CommandTester($command);

        $tester->execute([
            'migration' => 'foo',
        ]);
        $display = $tester->getDisplay();

        // Number of migrators:
        $firstLine = strtok($display, "\n");
        self::assertSame('foo : 2 migrators', $firstLine);

        // Get table data lines
        $firstClass = preg_quote(MigrationShowCommandFirstMigrator::class, '~');
        $secondClass = preg_quote(MigrationShowCommandSecondMigrator::class, '~');
        self::assertMatchesRegularExpression(
            '~0\s+' . $firstClass . '\s+\R\s+1\s+' . $secondClass . '\s+\R~',
            $display
        );
    }

    /**
     * Get an error for unknown migration
     */
    public function testErrorOnUnknownMigration()
    {
        $command = new MigrationShowCommand(new MigrationRegistry(), new CommandHelper());
        $tester = new CommandTester($command);

        $tester->execute([
            'migration' => 'unknown',
        ]);

        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringContainsString('[ERROR]', $tester->getDisplay());
    }

    /**
     * The --with-tasks option must list before and after tasks
     */
    public function testListMigrationsWithTasks()
    {
        $migration = new Migration();
        $migration->addBeforeTask(new MigrationShowCommandBeforeTask());
        $migration->addAfterTask(new MigrationShowCommandAfterTask());

        $registry = new MigrationRegistry();
        $registry->add('foo', $migration);

        $command = new MigrationShowCommand($registry, new CommandHelper());
        $tester = new CommandTester($command);

        $tester->execute([
            'migration' => 'foo',
            '--with-tasks' => null,
        ]);
        $display = $tester->getDisplay();

        // Get table data lines
        $firstClass = preg_quote(MigrationShowCommandBeforeTask::class, '~');
        $secondClass = preg_quote(MigrationShowCommandAfterTask::class, '~');
        self::assertMatchesRegularExpression(
            '~0\s+' . $firstClass . '\s+\R.+0\s+' . $secondClass . '\s+\R~s',
            $display
        );
    }
}

/**
 * Mocks
 * @see MigrationShowCommandTest::testListMigrationMigrators()
 */
class MigrationShowCommandFirstMigrator implements MigratorInterface {
    public function getPuller(): ?PullerInterface {}
    public function getPusher(): PusherInterface {}
    public function getExecutor(): Executor {}
}

class MigrationShowCommandSecondMigrator extends MigrationShowCommandFirstMigrator {}

/**
 * Mocks
 * @see MigrationShowCommandTest::testListMigrationsWithTasks()
 */
class MigrationShowCommandBeforeTask implements TaskInterface {
    public function execute(): ?string {}
}

class MigrationShowCommandAfterTask implements TaskInterface {
    public function execute(): ?string {}
}

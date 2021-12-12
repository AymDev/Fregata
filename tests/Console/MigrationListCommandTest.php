<?php

namespace Fregata\Tests\Console;

use Fregata\Console\CommandHelper;
use Fregata\Console\MigrationListCommand;
use Fregata\Migration\Migration;
use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\MigratorInterface;
use Fregata\Migration\TaskInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class MigrationListCommandTest extends TestCase
{
    /**
     * The migration:list command must display
     *  - number of migrations
     *  - name of each migration
     */
    public function testListRegisteredMigrations(): void
    {
        $registry = new MigrationRegistry();
        $registry->add('foo', new Migration());
        $registry->add('bar', new Migration());

        $command = new MigrationListCommand($registry, new CommandHelper());
        $tester = new CommandTester($command);

        $tester->execute([]);
        $display = $tester->getDisplay();

        // Number of migrations:
        $firstLine = strval(strtok($display, "\n"));
        self::assertStringEndsWith('2', $firstLine);

        // Migration names
        self::assertStringContainsString('foo', $display);
        self::assertStringContainsString('bar', $display);

        // No migrators table
        self::assertStringNotContainsString('#   Class name', $display);
    }

    /**
     * The --with-migrators option must list migrators
     */
    public function testListMigrationsWithMigrators(): void
    {
        $firstMigrator = self::getMockBuilder(MigratorInterface::class)
            ->setMockClassName('TestFirstMigrator')
            ->getMockForAbstractClass();
        $secondMigrator = self::getMockBuilder(MigratorInterface::class)
            ->setMockClassName('TestSecondMigrator')
            ->getMockForAbstractClass();

        $migration = new Migration();
        $migration->add($firstMigrator);
        $migration->add($secondMigrator);

        $registry = new MigrationRegistry();
        $registry->add('foo', $migration);

        $command = new MigrationListCommand($registry, new CommandHelper());
        $tester = new CommandTester($command);

        $tester->execute([
            '--with-migrators' => null,
        ]);
        $display = $tester->getDisplay();

        // Get table data lines
        $firstClass = preg_quote(get_class($firstMigrator), '~');
        $secondClass = preg_quote(get_class($secondMigrator), '~');
        self::assertMatchesRegularExpression(
            '~0\s+' . $firstClass . '\s+\R\s+1\s+' . $secondClass . '\s+\R~',
            $display
        );
    }

    /**
     * The --with-tasks option must list before and after tasks
     */
    public function testListMigrationsWithTasks(): void
    {
        $beforeTask = self::getMockForAbstractClass(TaskInterface::class);
        $afterTask = self::getMockForAbstractClass(TaskInterface::class);

        $migration = new Migration();
        $migration->addBeforeTask($beforeTask);
        $migration->addAfterTask($afterTask);

        $registry = new MigrationRegistry();
        $registry->add('foo', $migration);

        $command = new MigrationListCommand($registry, new CommandHelper());
        $tester = new CommandTester($command);

        $tester->execute([
            '--with-tasks' => null,
        ]);
        $display = $tester->getDisplay();

        // Get table data lines
        $firstClass = preg_quote(get_class($beforeTask), '~');
        $secondClass = preg_quote(get_class($afterTask), '~');
        self::assertMatchesRegularExpression(
            '~0\s+' . $firstClass . '\s+\R.+0\s+' . $secondClass . '\s+\R~s',
            $display
        );
    }
}

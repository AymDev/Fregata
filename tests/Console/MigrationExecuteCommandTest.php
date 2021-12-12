<?php

namespace Fregata\Tests\Console;

use Fregata\Console\MigrationExecuteCommand;
use Fregata\Migration\Migration;
use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\MigratorInterface;
use Fregata\Migration\TaskInterface;
use Fregata\Tests\Migration\Migrator\Component\Fixtures\TestItemPuller;
use Fregata\Tests\Migration\Migrator\Component\Fixtures\TestPusher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class MigrationExecuteCommandTest extends TestCase
{
    /**
     * Executes without interaction
     */
    public function testExecution(): void
    {
        $task = self::getMockForAbstractClass(TaskInterface::class);
        $puller = new TestItemPuller();
        $pusher = new TestPusher();
        $migrator = self::getMockForAbstractClass(MigratorInterface::class);
        $migrator->method('getPuller')->willReturn($puller);
        $migrator->method('getPusher')->willReturn($pusher);
        $migrator->method('getExecutor')->willReturn(new Executor());

        $migration = new Migration();
        $migration->add($migrator);
        $migration->addBeforeTask($task);
        $migration->addAfterTask($task);

        $registry = new MigrationRegistry();
        $registry->add('test-migration', $migration);

        $command = new MigrationExecuteCommand($registry);
        $tester = new CommandTester($command);

        $tester->execute([
            'migration' => 'test-migration',
        ]);

        // Command is successful
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('[OK]', $tester->getDisplay());

        // Migration progress is shown
        self::assertStringContainsString(get_class($migrator), $tester->getDisplay());
        self::assertStringContainsString(
            sprintf('%1$d / %1$d', count($puller->getItems())),
            $tester->getDisplay()
        );

        // Tasks are shown in order
        self::assertMatchesRegularExpression(
            sprintf(
                '~Before.+%1$s.+Migrators.+%2$s.+%1$s~is',
                preg_quote(get_class($task)),
                preg_quote(get_class($migrator))
            ),
            $tester->getDisplay()
        );

        // Data has been migrated
        self::assertSame($puller->getItems(), $pusher->getData());
    }

    /**
     * Get an error for unknown migration
     */
    public function testErrorOnUnknownMigration(): void
    {
        $command = new MigrationExecuteCommand(new MigrationRegistry());
        $tester = new CommandTester($command);

        $tester->execute(
            [
                'migration' => 'unknown',
            ],
            [
                // To get a ConsoleOutput
                'capture_stderr_separately' => true,
            ]
        );

        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringContainsString('[ERROR]', $tester->getDisplay());
    }
}

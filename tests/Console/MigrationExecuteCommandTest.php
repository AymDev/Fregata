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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class MigrationExecuteCommandTest extends TestCase
{
    /**
     * Executes without interaction
     */
    public function testExecution()
    {
        $task = new MigrationExecuteCommandTask();
        $migrator = new MigrationExecuteCommandMigrator();

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
        self::assertStringContainsString(MigrationExecuteCommandMigrator::class, $tester->getDisplay());
        self::assertStringContainsString(
            sprintf('%1$d / %1$d', count($migrator->getPuller()->data)),
            $tester->getDisplay()
        );

        // Tasks are shown in order
        $taskClass = preg_quote(MigrationExecuteCommandTask::class);
        $migratorClass = preg_quote(MigrationExecuteCommandMigrator::class);
        self::assertMatchesRegularExpression(
            '~Before.+' . $taskClass . '.+Migrators.+' . $migratorClass . '.+' . $taskClass . '~is',
            $tester->getDisplay()
        );

        // Data has been migrated
        self::assertSame($migrator->getPuller()->data, $migrator->getPusher()->data);
    }

    /**
     * Get an error for unknown migration
     */
    public function testErrorOnUnknownMigration()
    {
        $command = new MigrationExecuteCommand(new MigrationRegistry());
        $tester = new CommandTester($command);

        $tester->execute(
            [
                'migration' => 'unknown',
            ], [
                // To get a ConsoleOutput
                'capture_stderr_separately' => true,
            ]
        );

        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringContainsString('[ERROR]', $tester->getDisplay());
    }
}

/**
 * Mocks
 * @see MigrationExecuteCommandTest::testExecution()
 */
class MigrationExecuteCommandMigrator implements MigratorInterface {
    private ?PusherInterface $pusher = null;

    public function getPuller(): ?PullerInterface
    {
        return new class implements PullerInterface {
            public array $data = ['foo', 'bar', 'baz'];

            public function pull()
            {
                return $this->data;
            }

            public function count(): ?int
            {
                return count($this->data);
            }
        };
    }

    public function getPusher(): PusherInterface
    {
        $this->pusher ??= new class implements PusherInterface {
            public array $data = [];

            public function push($data): int
            {
                $this->data[] = $data;
                return 1;
            }
        };
        return $this->pusher;
    }

    public function getExecutor(): Executor
    {
        return new Executor();
    }
}

class MigrationExecuteCommandTask implements TaskInterface {
    public function execute(): ?string
    {
        return '[EXECUTED]';
    }
}

<?php

namespace Fregata\Tests\Configuration;

use Fregata\Configuration\CommandsCompilerPass;
use Fregata\Console\CommandHelper;
use Fregata\Console\MigrationExecuteCommand;
use Fregata\Console\MigrationListCommand;
use Fregata\Console\MigrationShowCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CommandsCompilerPassTest extends TestCase
{
    /**
     * Application and commands must be defined in the container
     */
    public function testCommandsDefinitions()
    {
        $container = new ContainerBuilder();
        $compilerPass = new CommandsCompilerPass();

        $compilerPass->process($container);

        self::assertTrue($container->has(CommandHelper::class));
        self::assertTrue($container->has(Application::class));
        self::assertTrue($container->has(MigrationListCommand::class));
        self::assertTrue($container->has(MigrationShowCommand::class));
        self::assertTrue($container->has(MigrationExecuteCommand::class));

        $methodCalls = $container->getDefinition(Application::class)->getMethodCalls();
        $methodCalls = array_map(function (array $call) {
            /** @var Reference $reference */
            $reference = $call[1][0];
            $call[1] = (string)$reference;
            return $call;
        }, $methodCalls);

        self::assertSame(['add'], array_unique(array_column($methodCalls, 0)));
        self::assertEqualsCanonicalizing(
            [MigrationListCommand::class, MigrationShowCommand::class, MigrationExecuteCommand::class],
            array_column($methodCalls, 1)
        );
    }
}

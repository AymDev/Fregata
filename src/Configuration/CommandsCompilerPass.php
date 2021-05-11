<?php

namespace Fregata\Configuration;

use Fregata\Console\MigrationExecuteCommand;
use Fregata\Console\MigrationListCommand;
use Fregata\Console\MigrationShowCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
class CommandsCompilerPass implements CompilerPassInterface
{
    /** @var class-string[] */
    private const COMMAND_CLASSES = [
        MigrationListCommand::class,
        MigrationShowCommand::class,
        MigrationExecuteCommand::class,
    ];

    public function process(ContainerBuilder $container)
    {
        $applicationDefinition = new Definition(Application::class);
        $applicationDefinition->setPublic(true);

        foreach (self::COMMAND_CLASSES as $commandClass) {
            $container->setDefinition($commandClass, new Definition($commandClass));
            $applicationDefinition->addMethodCall('add', [new Reference($commandClass)]);
        }

        $container->setDefinition(Application::class, $applicationDefinition);
    }
}

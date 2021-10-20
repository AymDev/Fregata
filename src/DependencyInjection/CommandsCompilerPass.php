<?php

namespace Fregata\DependencyInjection;

use Fregata\Command\MigrationExecuteCommand;
use Fregata\Command\MigrationListCommand;
use Fregata\Command\MigrationShowCommand;
use Fregata\Configuration\AbstractFregataKernel;
use Fregata\Console\CommandHelper;
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
        // Helpers
        $commandHelperDefinition = new Definition(CommandHelper::class);
        $container->setDefinition(CommandHelper::class, $commandHelperDefinition);

        // Application
        $applicationDefinition = new Definition(Application::class);
        $applicationDefinition
            ->setPublic(true)
            ->addMethodCall('setName', ['Fregata CLI'])
            ->addMethodCall('setVersion', [AbstractFregataKernel::VERSION]);
        ;
        $container->setDefinition(Application::class, $applicationDefinition);

        // Commands
        foreach (self::COMMAND_CLASSES as $commandClass) {
            $commandDefinition = new Definition($commandClass);
            $commandDefinition->setAutowired(true);

            $container->setDefinition($commandClass, $commandDefinition);
            $applicationDefinition->addMethodCall('add', [new Reference($commandClass)]);
        }
    }
}

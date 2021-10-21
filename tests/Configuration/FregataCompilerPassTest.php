<?php

namespace Fregata\Tests\Configuration;

use Fregata\Adapter\Doctrine\DBAL\ForeignKey\CopyColumnHelper;
use Fregata\DependencyInjection\FregataCompilerPass;
use Fregata\Migration\Migration;
use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\Component\Executor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class FregataCompilerPassTest extends TestCase
{
    /**
     * Migration registry & executor must be defined in the container
     */
    public function testDefaultDefinitions()
    {
        $container = new ContainerBuilder();
        $compilerPass = new FregataCompilerPass();

        $compilerPass->process($container);

        self::assertTrue($container->has(MigrationRegistry::class));
        self::assertTrue($container->has(Executor::class));
        self::assertTrue($container->has(CopyColumnHelper::class));
    }

    /**
     * Migrations must be registered in the registry
     */
    public function testMigrationRegistrationInRegistry()
    {
        $migrationDefinition = new Definition(Migration::class);
        $migrationDefinition->addTag('fregata.migration', ['name' => 'test']);

        $container = new ContainerBuilder();
        $container->setDefinition('fregata.migration.test', $migrationDefinition);

        $compilerPass = new FregataCompilerPass();
        $compilerPass->process($container);

        // Should call "add" method once
        $methodCalls = $container->getDefinition(MigrationRegistry::class)->getMethodCalls();

        $method = $methodCalls[0][0];
        self::assertSame('add', $method);

        $nameArg = $methodCalls[0][1][0];
        self::assertSame('test', $nameArg);

        /** @var Reference $migrationArg */
        $migrationArg = $methodCalls[0][1][1];
        $migrationId = $migrationArg->__toString();
        self::assertSame('fregata.migration.test', $migrationId);
    }
}

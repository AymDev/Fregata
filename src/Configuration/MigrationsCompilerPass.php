<?php

namespace Fregata\Configuration;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
class MigrationsCompilerPass implements CompilerPassInterface
{
    public const PARAMETER_MIGRATIONS = 'fregata.migrations';

    public function process(ContainerBuilder $container)
    {
        // TODO: Register dynamic services based on framework configuration
    }
}

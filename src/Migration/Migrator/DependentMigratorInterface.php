<?php

namespace Fregata\Migration\Migrator;

/**
 * A dependent migrator is a migrator which needs an other migrator to be executed before itself
 *
 * If migrator A needs to be executed before migrator B, migrator B must implement this interface and define migrator A
 * as a dependency.
 */
interface DependentMigratorInterface extends MigratorInterface
{
    /**
     * Lists the migrators that must be executed before the current one
     * @return class-string[]
     */
    public function getDependencies(): array;
}

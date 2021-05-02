<?php

namespace Fregata\Migration\Migrator;

/**
 * A migrator pulls data from a source and pushes it to a target
 * Any migrator must implement this interface
 */
interface MigratorInterface
{
    /**
     * Executes the migration process
     * @return \Generator|int[] number of items migrated
     */
    public function migrate(): \Generator;
}

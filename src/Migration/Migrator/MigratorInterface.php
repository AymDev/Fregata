<?php

namespace Fregata\Migration\Migrator;

use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;

/**
 * A migrator holds component to pull data from a source and to push it to a target
 * Any migrator must implement this interface
 */
interface MigratorInterface
{
    /**
     * Return the puller responsible of the data retrieval
     */
    public function getPuller(): PullerInterface;

    /**
     * Return the pusher responsible for the data insertion
     */
    public function getPusher(): PusherInterface;

    /**
     * Return the executor responsible for the migration process
     */
    public function getExecutor(): Executor;
}

<?php

namespace Fregata\Tests\Configuration\Fixtures;

use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\MigratorInterface;

class ExtensionTestDirectoryMigrator implements MigratorInterface
{
    public function getPuller(): PullerInterface
    {
    }
    public function getPusher(): PusherInterface
    {
    }
    public function getExecutor(): Executor
    {
    }
}

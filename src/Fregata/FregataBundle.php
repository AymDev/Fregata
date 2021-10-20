<?php

declare(strict_types=1);

namespace Fregata\Fregata;

use Fregata\Console\MigrationExecuteCommand;
use Fregata\Console\MigrationListCommand;
use Fregata\Console\MigrationShowCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class FregataBundle extends Bundle
{

    public function registerCommands(Application $application)
    {
        parent::registerCommands($application);
        $application->register(MigrationExecuteCommand::class);
        $application->register(MigrationShowCommand::class);
        $application->register(MigrationListCommand::class);
    }
}

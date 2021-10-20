<?php

declare(strict_types=1);

namespace Fregata;

use Fregata\Command\MigrationExecuteCommand;
use Fregata\Command\MigrationListCommand;
use Fregata\Command\MigrationShowCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class FregataBundle extends Bundle
{

    const NAME = 'fregata';

    public function __construct()
    {
        $this->name = self::NAME;
    }

    public function registerCommands(Application $application)
    {
        parent::registerCommands($application);
        $application->register(MigrationExecuteCommand::class);
        $application->register(MigrationShowCommand::class);
        $application->register(MigrationListCommand::class);
    }
}

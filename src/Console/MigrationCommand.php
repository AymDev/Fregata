<?php

namespace Fregata\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationCommand extends Command
{
    protected static $defaultName = 'migrate';

    protected function configure()
    {
        $this
            ->setDescription('Execute migration for any registered migrators (default command).')
            ->setHelp('Executes all registered migrators.')
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_REQUIRED,
                'path to the YAML configuration file',
                'fregata.yaml'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        // Load configuration
        $configPath = $input->getOption('config');
        $io->success($configPath);

        return 0;
    }
}
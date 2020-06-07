<?php

namespace Fregata\Console;

use Fregata\Fregata;
use Fregata\Migrator\MigratorInterface;
use Symfony\Component\Console\Command\Command;
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
                'configuration',
                'c',
                InputOption::VALUE_REQUIRED,
                'path to the YAML configuration file',
                'fregata.yaml'
            )
            ->addOption(
                'migrator',
                'm',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'the migrator classes to run'
            )
            ->addOption(
                'migrators-dir',
                'd',
                InputOption::VALUE_REQUIRED,
                'path to a directory containing migrators'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isInteractive = $input->hasOption('no-interaction') && false === $input->getOption('no-interaction');
        $io = new SymfonyStyle($input, $output);
        $fregata = new Fregata();

        // Load configuration
        try {
            $configuration = file_exists($input->getOption('configuration'))
                ? Configuration::createFromFile($input->getOption('configuration'))
                : new Configuration();

            foreach ($input->getOption('migrator') as $migrator) {
                $configuration->addMigrator($migrator);
            }

            if (null !== $input->getOption('migrators-dir')) {
                $configuration->setMigratorsDirectory($input->getOption('migrators-dir'));
            }

        } catch (\Exception $e) {
            $io->error(sprintf('Configuration: %s', $e->getMessage()));
            return 1;
        }

        // Register migrators
        $migrators = $configuration->getMigrators();
        foreach ($migrators as $migrator) {
            if (false === class_exists($migrator)) {
                $io->error(sprintf('Migrator class "%s" not found.', $migrator));
                exit(1);
            }
            $fregata->addMigrator($migrator);
        }
        $io->success(sprintf('Configuration has been loaded for %d migrators.', count($migrators)));

        // Confirm the exeution
        if ($isInteractive) {
            $io->warning('It is recommended to run the migration on testing databases before migrating the production databases.');
            $confirmation = $io->confirm('You are about to execute a Fregata migration. Do you wish to continue ?', false);

            if (false === $confirmation && false === $input->getOption('no-interaction')) {
                $io->text('Aborting.');
                return 0;
            }
        }

        foreach ($fregata->run() as $migrator) {
            /** @var MigratorInterface $migrator */
            $source = $migrator->getSourceConnection()->getConnection();
            $target = $migrator->getTargetConnection()->getConnection();

            $io->title(sprintf(
                'Executing "%s" [%d rows]',
                get_class($migrator),
                $migrator->getTotalRows($source)
            ));

            $progressBar = $io->createProgressBar($migrator->getTotalRows($source));
            $progressBar->start();

            foreach ($migrator->migrate($source, $target) as $amountInserted) {
                $progressBar->setProgress($amountInserted);
            }

            $progressBar->finish();
            $io->newLine(2);
        }

        $io->success('Migrated successfully !');
        return 0;
    }
}
<?php

namespace Fregata\Console;

use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\MigratorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationShowCommand extends Command
{
    protected static $defaultName = 'migration:show';
    private MigrationRegistry $migrationRegistry;

    public function __construct(MigrationRegistry $migrationRegistry)
    {
        $this->migrationRegistry = $migrationRegistry;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('List all registered migrators sorted for a given migrations.')
            ->setHelp('List migrators of a migration.')
            ->addArgument(
                'migration',
                InputArgument::REQUIRED,
                'The name of the migration.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $migrationName = $input->getArgument('migration');
        $migration = $this->migrationRegistry->get($migrationName);

        if (null === $migration) {
            $io->error(sprintf('No migration registered with the name "%s".', $migrationName));
            return 1;
        }

        $migrators = $migration->getMigrators();
        $io->title(sprintf('%s : %d migrators', $migrationName, count($migrators)));

        $migrators = array_map(
            fn(int $key, MigratorInterface $migrator) => [$key, get_class($migrator)],
            array_keys($migration->getMigrators()),
            $migration->getMigrators()
        );

        $io->table(
            ['#', 'Class name'],
            $migrators
        );

        $io->newLine();
        return 0;
    }
}

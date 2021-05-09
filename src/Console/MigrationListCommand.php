<?php

namespace Fregata\Console;

use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\MigratorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationListCommand extends Command
{
    protected static $defaultName = 'migration:list';
    private MigrationRegistry $migrationRegistry;

    public function __construct(MigrationRegistry $migrationRegistry)
    {
        $this->migrationRegistry = $migrationRegistry;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('List all registered migrations with additional informations.')
            ->setHelp('List all registered migrations.')
            ->addOption(
                'with-migrators',
                'm',
                InputOption::VALUE_NONE,
                'Lists the migrators associated with each migration.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $migrations = $this->migrationRegistry->getAll();
        $io->title(sprintf('Registered migrations: %d', count($migrations)));

        foreach ($migrations as $name => $migration) {
            $io->writeln($name);

            if ($input->getOption('with-migrators')) {
                $migrators = array_map(
                    fn(int $key, MigratorInterface $migrator) => [$key, get_class($migrator)],
                    array_keys($migration->getMigrators()),
                    $migration->getMigrators()
                );

                $io->table(
                    ['#', 'Class name'],
                    $migrators
                );
            }
        }

        $io->newLine();
        return 0;
    }
}

<?php

namespace Fregata;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\TableDiff;
use Fregata\Migrator\MigratorException;
use Fregata\Migrator\MigratorInterface;
use Fregata\Migrator\PreservedKeyMigratorInterface;
use Psr\Container\ContainerInterface;

class Fregata
{
    /**
     * Service container (PHP-DI by default)
     *
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * The migration classes
     *
     * @var MigratorInterface[]
     */
    private array $migrators = [];

    /**
     * @var string[] old primary keys column & index names in targets per migrator
     */
    private array $preservedKeys = [];

    /**
     * Fregata constructor
     */
    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container ?? new Container();
    }

    /**
     * Register a new Migrator
     *
     * @param string $migratorClassName the name of the migrator class
     *
     * @return Fregata
     * @throws MigratorException
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function addMigrator(string $migratorClassName): self
    {
        // Must be an implementation of MigratorInterface
        if (false === is_subclass_of($migratorClassName, MigratorInterface::class)) {
            throw MigratorException::wrongMigrator($migratorClassName);
        }

        // Register the migrator
        $this->migrators[$migratorClassName] = $this->container->get($migratorClassName);
        return $this;
    }

    /**
     * Get the registered migrators
     *
     * @throws MigratorException
     */
    public function run(): \Generator
    {
        if (count($this->migrators) === 0) {
            throw new \LogicException('No migrators registered.');
        }

        // Create temporary columns & indexes for foreign keys preservation
        $this->createPreservedKeys();

        // Run migration
        foreach ($this->migrators as $migrator) {
            yield $migrator;
        }

        // Delete temporary columns & indexes
        $this->deletePreservedKeys();
    }

    /**
     * Create temporary columns & indexes in target tables for foreign keys preservation
     */
    private function createPreservedKeys(): void
    {
        foreach ($this->migrators as $migrator) {
            if ($migrator instanceof PreservedKeyMigratorInterface) {
                // Create and save column name for future deletion
                $columnName = sprintf('fregata_pk_%s', $migrator->getSourceTable());
                $this->preservedKeys[get_class($migrator)]['column'] = $columnName;

                // Create index for temporary column
                $indexName = sprintf('fregata_idx_%s', $migrator->getSourceTable());
                $this->preservedKeys[get_class($migrator)]['index'] = $indexName;
                $index = new Index($indexName, [$columnName]);

                // Get original column
                $originalColumn = $migrator
                    ->getSourceConnection()
                    ->getConnection()
                    ->getSchemaManager()
                    ->listTableDetails($migrator->getSourceTable())
                    ->getColumn($migrator->getPrimaryKeyColumnName());

                // Change column settings as it won't be a primary key anymore
                $temporaryColumn = (new Column($columnName, $originalColumn->getType()))
                    ->setLength($originalColumn->getLength())
                    ->setPrecision($originalColumn->getPrecision())
                    ->setScale($originalColumn->getScale())
                    ->setFixed($originalColumn->getFixed())
                    ->setUnsigned($originalColumn->getUnsigned())
                    ->setAutoincrement(false)
                    ->setNotnull(false)
                    ->setDefault(null);

                // Add column into target table
                $migrator
                    ->getTargetConnection()
                    ->getConnection()
                    ->getSchemaManager()
                    ->alterTable(new TableDiff(
                        $migrator->getTargetTable(),
                        [$temporaryColumn],
                        [],
                        [],
                        [$index]
                    ));
            }
        }
    }

    /**
     * Create temporary columns & indexes in target tables
     */
    private function deletePreservedKeys(): void
    {
        foreach ($this->preservedKeys as $migratorClass => $key) {
            /** @var MigratorInterface & PreservedKeyMigratorInterface $migrator */
            $migrator = $this->migrators[$migratorClass];

            $table = $migrator
                ->getTargetConnection()
                ->getConnection()
                ->getSchemaManager()
                ->listTableDetails($migrator->getTargetTable());

            $temporaryIndex = $table->getIndex($key['index']);
            $temporaryColumn = $table->getColumn($key['column']);

            // Delete column and index
            $migrator
                ->getTargetConnection()
                ->getConnection()
                ->getSchemaManager()
                ->alterTable(new TableDiff(
                    $migrator->getTargetTable(),
                    [],
                    [],
                    [$temporaryColumn],
                    [],
                    [],
                    [$temporaryIndex],
                    $table
                ));
        }
    }
}
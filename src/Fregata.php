<?php

namespace Fregata;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Fregata\Migrator\MigratorException;
use Fregata\Migrator\MigratorInterface;
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
     */
    public function run(): \Generator
    {
        if (count($this->migrators) === 0) {
            throw new \LogicException('No migrators registered.');
        }

        foreach ($this->migrators as $migrator) {
            yield $migrator;
        }
    }
}
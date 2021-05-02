<?php

namespace Fregata\Migration;

use Fregata\Migration\Migrator\DependentMigratorInterface;
use Fregata\Migration\Migrator\MigratorInterface;
use MJS\TopSort\CircularDependencyException;
use MJS\TopSort\ElementNotFoundException;
use MJS\TopSort\Implementations\FixedArraySort;

/**
 * A migration holds migrators and represent a full migration project
 */
class Migration
{
    private bool $sorted = false;

    /** @var MigratorInterface[] */
    private array $migrators = [];

    /**
     * Register a new migrator
     * @throws MigrationException
     */
    public function add(MigratorInterface $migrator): void
    {
        if (in_array(get_class($migrator), array_map('get_class', $this->migrators))) {
            throw MigrationException::duplicateMigrator($migrator);
        }

        $this->sorted = false;
        $this->migrators[] = $migrator;
    }

    /**
     * List sorted Migrators
     * @return MigratorInterface[]
     * @throws MigrationException
     */
    public function getMigrators(): array
    {
        if (false === $this->sorted) {
            $this->sort();
        }
        return $this->migrators;
    }

    /**
     * Yields the migrators without executing them
     * The migration process must executed outside the class (CLI command, async message, ...)
     * @return \Generator|MigratorInterface[]
     * @throws MigrationException
     */
    public function process(): \Generator
    {
        if (false === $this->sorted) {
            $this->sort();
        }

        foreach ($this->migrators as $migrator) {
            yield $migrator;
        }
    }

    /**
     * Sort migrators according to their dependencies
     * @throws MigrationException
     * @see DependentMigratorInterface
     */
    private function sort(): void
    {
        $sorter = new FixedArraySort();

        // Register migrators to sort (with strings only)
        foreach ($this->migrators as $migrator) {
            $sorter->add(
                get_class($migrator),
                $migrator instanceof DependentMigratorInterface ? $migrator->getDependencies() : []
            );
        }

        // Sort
        try {
            $sortedMigrators = $sorter->sort();
        } catch (CircularDependencyException|ElementNotFoundException $exception) {
            throw MigrationException::invalidMigratorDependencies($exception);
        }

        // Index by class name to remap
        $migrators = array_combine(
            array_map('get_class', $this->migrators),
            $this->migrators
        );

        // Remap sorted classes with migrator objects
        $this->migrators = array_map(
            fn(string $migratorClass) => $migrators[$migratorClass],
            $sortedMigrators
        );
        $this->sorted = true;
    }
}

<?php

namespace Fregata\Console;

use Fregata\Migrator\MigratorInterface;
use hanneskod\classtools\Iterator\ClassIterator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Fregata console configuration class
 */
class Configuration
{
    /**
     * @var string[] migrators classes
     */
    private ?array $migrators;

    /**
     * @var string migrators directory path
     */
    private ?string $migrators_directory;

    /**
     * Instantiate from a YAML file
     */
    public static function createFromFile(string $filePath): self
    {
        $config = Yaml::parseFile($filePath);
        if (false === is_array($config)) {
            throw new \LogicException('The configuration file should contain an array.');
        }

        return new self($config);
    }

    /**
     * Configuration constructor.
     */
    public function __construct(array $configuration = [])
    {
        $this->migrators           = $configuration['migrators'] ?? null;
        $this->migrators_directory = $configuration['migrators_directory'] ?? null;
    }

    /**
     * Get the Migrators to register
     */
    public function getMigrators(): array
    {
        if (null !== $this->migrators) {
            return $this->getMigratorList();
        }

        if (null !== $this->migrators_directory) {
            return $this->getMigratorsInDirectory();
        }

        return [];
    }

    /**
     * Add a migrator class name to the "migrators" config key
     */
    public function addMigrator(string $className): void
    {
        $this->migrators[] = $className;
    }

    /**
     * Get migrators list declared in "migrators" key
     */
    private function getMigratorList(): array
    {
        return $this->migrators;
    }

    /**
     * Define the migrators parent directory
     */
    public function setMigratorsDirectory(string $path): void
    {
        $this->migrators_directory = $path;
    }

    /**
     * Get migrators in class files in specific directory
     */
    private function getMigratorsInDirectory(): array
    {
        $finder = new Finder();
        $iterator = new ClassIterator($finder->in($this->migrators_directory));
        $iterator->enableAutoloading();

        $iterator = $iterator->type(MigratorInterface::class);

        /** @var ClassIterator $iterator */
        $iterator = $iterator->where('isInstantiable', true);

        $classes = [];

        /** @var \ReflectionClass $class */
        foreach ($iterator as $class) {
            $classes[] = $class->getName();
        }

        return $classes;
    }
}
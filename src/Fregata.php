<?php


namespace Fregata;


use Doctrine\DBAL\Connection;
use Fregata\Connection\AbstractConnection;
use Fregata\Connection\ConnectionException;
use Fregata\Migrator\MigratorInterface;

class Fregata
{
    /**
     * The database connection to get data from
     *
     * @var AbstractConnection[]
     */
    private array $sources = [];

    /**
     * The database connection to save data to
     *
     * @var AbstractConnection[]
     */
    private array $targets = [];

    /**
     * The migration classes
     *
     * @var MigratorInterface[]
     */
    private array $migrators = [];

    /**
     * Register a new database connection as a source
     *
     * @param string $connectionClassName the \Fregata\Connection\AbstractConnection child class name
     *
     * @return Fregata the instance itself
     * @throws ConnectionException if the class is not a child of \Fregata\Connection\AbstractConnection
     */
    private function addSource(string $connectionClassName): self
    {
        // Must be an implementation of AbstractConnection
        if (false === is_subclass_of($connectionClassName, AbstractConnection::class)) {
            throw ConnectionException::wrongConnectionType($connectionClassName);
        }

        $this->sources[$connectionClassName] = null;
        return $this;
    }

    /**
     * Get a source connection instance
     */
    private function getSource(string $connectionClassName): Connection
    {
        if (false === array_key_exists($connectionClassName, $this->sources)) {
            throw new \LogicException(sprintf(
                'The "%s" class could not be found in the source connections.',
                $connectionClassName
            ));
        }

        // Instantiate the connection if needed
        if (null === $this->sources["${connectionClassName}"]) {
            /** @var AbstractConnection $connectionWrapper */
            $connectionWrapper = new $connectionClassName();
            $this->sources[$connectionClassName] = $connectionWrapper->getConnection();
        }

        return $this->sources[$connectionClassName];
    }

    /**
     * Register a new database connection as a target
     *
     * @param string $connectionClassName the \Fregata\Connection\AbstractConnection child class name
     *
     * @return Fregata the instance itself
     * @throws ConnectionException if the class is not a child of \Fregata\Connection\AbstractConnection
     */
    private function addTarget(string $connectionClassName): self
    {
        // Must be an implementation of AbstractConnection
        if (false === is_subclass_of($connectionClassName, AbstractConnection::class)) {
            throw ConnectionException::wrongConnectionType($connectionClassName);
        }

        $this->targets[$connectionClassName] = null;
        return $this;
    }

    /**
     * Get a target connection instance
     */
    private function getTarget(string $connectionClassName): Connection
    {
        if (false === array_key_exists($connectionClassName, $this->targets)) {
            throw new \LogicException(sprintf(
                'The "%s" class could not be found in the target connections.',
                $connectionClassName
            ));
        }

        // Instantiate the connection if needed
        if (null === $this->targets[$connectionClassName]) {
            /** @var AbstractConnection $connectionWrapper */
            $connectionWrapper = new $connectionClassName();
            $this->targets[$connectionClassName] = $connectionWrapper->getConnection();
        }

        return $this->targets[$connectionClassName];
    }

    /**
     * Register a new Migrator
     *
     * @throws ConnectionException
     */
    public function addMigrator(MigratorInterface $migrator): self
    {
        // Register the source connection
        if (false === array_key_exists($migrator->getSourceConnection(), $this->sources)) {
            $this->addSource($migrator->getSourceConnection());
        }

        // Register the target connection
        if (false === array_key_exists($migrator->getTargetConnection(), $this->targets)) {
            $this->addTarget($migrator->getTargetConnection());
        }

        $this->migrators[] = $migrator;
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
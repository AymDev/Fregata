<?php


namespace Fregata;


use Doctrine\DBAL\Connection;
use Fregata\Connection\AbstractConnection;
use Fregata\Connection\ConnectionException;

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
     * Register a new database connection as a source
     *
     * @param string $connectionClassName the \Fregata\Connection\AbstractConnection child class name
     *
     * @return Fregata the instance itself
     * @throws ConnectionException if the class is not a child of \Fregata\Connection\AbstractConnection
     */
    public function addSource(string $connectionClassName): self
    {
        // Must be an implementation of AbstractConnection
        if (false === is_subclass_of($connectionClassName, AbstractConnection::class)) {
            throw ConnectionException::wrongConnectionType($connectionClassName);
        }

        $this->sources[$connectionClassName] = null;
        return $this;
    }

    /**
     * Register a new database connection as a target
     *
     * @param string $connectionClassName the \Fregata\Connection\AbstractConnection child class name
     *
     * @return Fregata the instance itself
     * @throws ConnectionException if the class is not a child of \Fregata\Connection\AbstractConnection
     */
    public function addTarget(string $connectionClassName): self
    {
        // Must be an implementation of AbstractConnection
        if (false === is_subclass_of($connectionClassName, AbstractConnection::class)) {
            throw ConnectionException::wrongConnectionType($connectionClassName);
        }

        $this->targets[$connectionClassName] = null;
        return $this;
    }
}
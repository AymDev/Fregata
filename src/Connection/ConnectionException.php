<?php


namespace Fregata\Connection;


class ConnectionException extends \Exception
{
    public static function wrongConnectionType(string $className): self
    {
        return new self(sprintf(
            'A connection must extend "%s", instance of "%s" provided.',
            AbstractConnection::class,
            $className
        ));
    }
}
<?php

namespace Fregata\Adapter\Doctrine\DBAL\ForeignKey;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class ForeignKeyException extends \Exception
{
    /**
     * Thrown when a migrator implements HasForeignKeysInterface but the connection's platform does not
     * support foreign key constraints.
     */
    public static function incompatiblePlatform(AbstractPlatform $platform, \Throwable $previous = null): self
    {
        $message = 'The "%s" platform does not support foreign key constraints';
        $message .= ' and is therefore incompatible with the foreign key feature';
        $message .= ' as you would lose your existing foreign key constraints.';
        $message .= ' Consider creating your own task to keep the relations.';
        return new self(sprintf($message, get_class($platform)), 1621088365786, $previous);
    }
}

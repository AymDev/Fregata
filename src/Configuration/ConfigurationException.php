<?php

namespace Fregata\Configuration;

/**
 * @internal
 */
class ConfigurationException extends \Exception
{
    /**
     * Thrown when the given cache directory does not exists
     */
    public static function invalidCacheDirectory(string $path, \Throwable $previous = null): self
    {
        return new self(
            sprintf('The cache directory "%s" is not a valid directory path.', $path),
            1619874486570,
            $previous
        );
    }

    /**
     * Thrown when the given configuration directory does not exists
     */
    public static function invalidConfigurationDirectory(string $path, \Throwable $previous = null): self
    {
        return new self(
            sprintf('The configuration directory "%s" is not a valid directory path.', $path),
            1619865822238,
            $previous
        );
    }
}

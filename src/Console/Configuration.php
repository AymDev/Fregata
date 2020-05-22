<?php

namespace Fregata\Console;

use Fregata\Migrator\MigratorInterface;
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
    public function __construct(array $configuration)
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
     * Get migrators list declared in "migrators" key
     */
    private function getMigratorList(): array
    {
        return $this->migrators;
    }

    /**
     * Get migrators in class files in specific directory
     */
    private function getMigratorsInDirectory(): array
    {
        // Get all PHP files in given directory
        $directory = new \RecursiveDirectoryIterator($this->migrators_directory);
        $iterator = new \RecursiveIteratorIterator($directory);
        $regex = new \RegexIterator($iterator, '~^.+\.php$~i', \RecursiveRegexIterator::GET_MATCH);

        $classes = [];
        foreach ($regex as $file) {
            $filepath = $file[0];
            $className = $this->getClassInFile($filepath);

            // Save class name if it implements MigratorInterface
            if (null !== $className && in_array(MigratorInterface::class, class_implements($className))) {
                $classes[] = $className;
            }
        }

        return $classes;
    }

    /**
     * Checks if a file contains a class and returns its name
     */
    private function getClassInFile(string $filepath): ?string
    {
        // PHP Tokens for given file, excluding single chars
        $tokens = token_get_all(file_get_contents($filepath));
        $tokens = array_values(array_filter($tokens, 'is_array'));

        /**
         * States for namespace parsing:
         *      T_NAMESPACE --> T_WHITESPACE --> T_STRING --> END
         *                                         ^    |
         *                                         |    v
         *                                       T_NS_SEPARATOR
         */
        $parsing_namespace = false;
        $namespace = [];

        /**
         * States for class parsing:
         *      ! T_ABSTRACT --> ? T_WHITESPACE --> T_CLASS --> T_WHITESPACE --> T_STRING --> END
         */
        $class = null;

        for ($i = 1; $i < count($tokens); $i++) {
            $prev = $tokens[$i - 1];
            $token = $tokens[$i];

            // Start parsing namespace
            if ($token[0] === T_WHITESPACE && $prev[0] === T_NAMESPACE) {
                $parsing_namespace = true;
                continue;
            }

            // Parse namespace
            if ($parsing_namespace) {
                // Token is namespace or separator
                if ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR) {
                    $namespace[] = $token[1];
                } else {
                    // Finished parsing namespace
                    $parsing_namespace = false;
                }
                continue;
            }

            // Get class
            $is_before_classname = $token[0] === T_WHITESPACE
                && $prev[0] === T_CLASS;

            $is_not_abstract_class = false === isset($tokens[$i - 3])
                || $tokens[$i - 3][0] !== T_ABSTRACT;

            $has_classname_after = isset($tokens[$i + 1])
                && $tokens[$i + 1][0] === T_STRING;

            if ($is_before_classname && $is_not_abstract_class && $has_classname_after) {
                $class = $tokens[$i + 1][1];
                break;
            }
        }

        if (null !== $class && [] !== $namespace) {
            $namespace = trim(implode('', $namespace), '\\');
            $class = implode('\\', [$namespace, $class]);
        }

        return $class;
    }
}
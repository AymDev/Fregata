<?php

namespace Fregata\Configuration;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * The Fregata application Kernel handles service container configuration
 * You must extend this class as App\FregataKernel in order to use the Fregata binary
 */
abstract class AbstractFregataKernel
{
    public const VERSION = 'v1.0.0';
    private const CONTAINER_CLASS_NAME = 'FregataCachedContainer';
    private ?Container $container = null;
    private ?string $rootDir = null;

    /**
     * Get the configuration directory location
     */
    abstract protected function getConfigurationDirectory(): string;

    /**
     * Get the cache directory location
     */
    abstract protected function getCacheDirectory(): string;

    /**
     * Get project's root directory location
     */
    public function getRootDirectory(): string
    {
        if (null === $this->rootDir) {
            $reflection = new \ReflectionClass(ClassLoader::class);
            $this->rootDir = dirname($reflection->getFileName(), 3);
        }

        return $this->rootDir;
    }

    /**
     * Get the container class name. Overriden in the framework tests.
     * @internal
     */
    protected function getContainerClassName(): string
    {
        return self::CONTAINER_CLASS_NAME;
    }


    /**
     * Creates a cached service container to use in a standalone Fregata project where no other container exists
     * @throws ConfigurationException
     * @throws \Exception
     */
    public function getContainer(): Container
    {
        if (null === $this->container) {
            // TODO: manage environments to make the debug mode dynamic
            $containerLocation = $this->getCachedContainerLocation();
            $containerConfigCache = new ConfigCache($containerLocation, true);

            // Create the container
            if (false === $containerConfigCache->isFresh()) {
                $containerBuilder = $this->createContainer();
                $this->dumpCachedContainer($containerBuilder);
            }

            // Load and start the container
            require_once $containerLocation;
            $containerClassName = sprintf('\Fregata\%s', $this->getContainerClassName());
            $this->container =  new $containerClassName();
        }

        return $this->container;
    }

    /**
     * Builds the path to the cached service container and checks the cache directory path
     * @throws ConfigurationException
     */
    private function getCachedContainerLocation(): string
    {
        if (false === is_dir($this->getCacheDirectory())) {
            $cacheDirectory = realpath($this->getCacheDirectory()) ?: $this->getCacheDirectory();
            throw ConfigurationException::invalidCacheDirectory($cacheDirectory);
        }

        return $this->getCacheDirectory() . DIRECTORY_SEPARATOR . $this->getContainerClassName() . '.php';
    }

    /**
     * Creates a service container
     * @throws ConfigurationException
     * @throws \Exception
     */
    private function createContainer(): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();

        // Set configuration directory of the application
        if (false === is_dir($this->getConfigurationDirectory())) {
            $configurationDirectory = realpath($this->getConfigurationDirectory()) ?: $this->getConfigurationDirectory();
            throw ConfigurationException::invalidConfigurationDirectory($configurationDirectory);
        }
        $containerBuilder->setParameter('fregata.root_dir', $this->getRootDirectory());
        $containerBuilder->setParameter('fregata.config_dir', $this->getConfigurationDirectory());

        // register migration services
        $containerBuilder->registerExtension(new FregataExtension());
        $containerBuilder->addCompilerPass(new FregataCompilerPass());
        $containerBuilder->addCompilerPass(new CommandsCompilerPass());

        // Register main services
        $this->loadMainServices($containerBuilder);

        return $containerBuilder;
    }

    /**
     * Load the main services definitions file of the application if it exists
     * @throws \Exception
     */
    private function loadMainServices(ContainerBuilder $container): void
    {
        $directory = $container->getParameter('fregata.config_dir');
        $fileLocator = new FileLocator($directory);

        $loader = new YamlFileLoader($container, $fileLocator);
        $loader->import('services.yaml', null, 'not_found');
        $loader->import('fregata.yaml', null, 'not_found');
    }

    /**
     * Dumps the container
     * @throws ConfigurationException
     */
    private function dumpCachedContainer(ContainerBuilder $container): void
    {
        $container->compile();

        // Dump the cache version
        $dumper = new PhpDumper($container);
        $containerContent = $dumper->dump([
            'namespace' => 'Fregata',
            'class'     => $this->getContainerClassName(),
        ]);

        file_put_contents($this->getCachedContainerLocation(), $containerContent);
    }

    /**
     * Create a kernel with default configuration
     */
    public static function createDefaultKernel(): self
    {
        return new class extends AbstractFregataKernel {
            protected function getConfigurationDirectory(): string
            {
                return $this->getRootDirectory() . DIRECTORY_SEPARATOR . 'config';
            }

            protected function getCacheDirectory(): string
            {
                return $this->getRootDirectory() . DIRECTORY_SEPARATOR . 'cache';
            }
        };
    }
}

<?php

namespace Fregata\Configuration;

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * @internal
 */
class ContainerFactory
{
    /**
     * Creates a cached service container to use in a standalone Fregata project where no other container exists
     * @throws ConfigurationException
     * @throws \Exception
     */
    public function getContainer(?string $configurationDirectory = null, ?string $cacheDirectory = null ): Container
    {
        // TODO: manage environments to make the debug mode dynamic
        $containerLocation = $this->getCachedContainerLocation($cacheDirectory);
        $containerConfigCache = new ConfigCache($containerLocation, true);

        // Create the container
        if (false === $containerConfigCache->isFresh()) {
            $containerBuilder = $this->createContainer($configurationDirectory);
            $this->dumpCachedContainer($containerBuilder, $containerLocation);
        }

        // Load and start the container
        require_once $containerLocation;
        return new \Fregata\FregataCachedContainer();
    }

    /**
     * Builds the path to the cached service container and checks the cache directory path
     * @throws ConfigurationException
     */
    private function getCachedContainerLocation(?string $cacheDirectory): string
    {
        // Expects to store cached container in a /cache directory by default
        $cacheDirectory ??= __DIR__ . '/../../../../cache';

        if (false === is_dir($cacheDirectory)) {
            $cacheDirectory = realpath($cacheDirectory) ?: $cacheDirectory;
            throw ConfigurationException::invalidCacheDirectory($cacheDirectory);
        }

        return $cacheDirectory . DIRECTORY_SEPARATOR . 'FregataCachedContainer.php';
    }

    /**
     * Creates a service container
     * @throws ConfigurationException
     * @throws \Exception
     */
    private function createContainer(?string $configurationDirectory = null): ContainerBuilder
    {
        $containerBuilder = new ContainerBuilder();

        // Set configuration directory of the application
        $containerBuilder->setParameter(
            'fregata.config_dir',
            $this->getConfigurationDirectory($configurationDirectory)
        );

        // Register main services
        $this->loadMainServices($containerBuilder);

        // register migration services
        $containerBuilder->registerExtension(new FregataExtension());
        $containerBuilder->addCompilerPass(new MigrationsCompilerPass());

        return $containerBuilder;
    }

    /**
     * Checks a given configuration directory path
     * @throws ConfigurationException
     */
    private function getConfigurationDirectory(?string $configurationDirectory): string
    {
        // Expects to find configuration in a /config directory by default
        $configurationDirectory ??= __DIR__ . '/../../../../config';

        if (false === is_dir($configurationDirectory)) {
            $configurationDirectory = realpath($configurationDirectory) ?: $configurationDirectory;
            throw ConfigurationException::invalidConfigurationDirectory($configurationDirectory);
        }

        return $configurationDirectory;
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
        $loader->import('/*.yaml');
    }

    /**
     * Dumps the container
     */
    private function dumpCachedContainer(ContainerBuilder $container, string $location): void
    {
        $container->compile();

        // Dump the cache version
        $dumper = new PhpDumper($container);
        $containerContent = $dumper->dump([
            'namespace' => 'Fregata',
            'class'     => 'FregataCachedContainer',
        ]);

        file_put_contents($location, $containerContent);
    }
}

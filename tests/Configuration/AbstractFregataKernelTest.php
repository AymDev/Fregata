<?php

namespace Fregata\Tests\Configuration;

use Fregata\Configuration\AbstractFregataKernel;
use Fregata\Configuration\ConfigurationException;
use Fregata\Migration\MigrationRegistry;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

class AbstractFregataKernelTest extends TestCase
{
    public function testContainerCreation()
    {
        $fileSystem = vfsStream::setup('abstract-fregata-kernel-test', null, [
            'config' => [
                'fregata.yaml' => 'fregata:',
            ],
            'cache' => []
        ]);

        // Create kernel
        $kernel = new class($fileSystem) extends AbstractFregataKernel {
            private vfsStreamDirectory $vfs;

            public function __construct(vfsStreamDirectory $vfs)
            {
                $this->vfs = $vfs;
            }

            protected function getConfigurationDirectory(): string
            {
                return $this->vfs->url() . '/config';
            }

            protected function getCacheDirectory(): string
            {
                return $this->vfs->url() . '/cache';
            }
        };

        // Container is compiled
        $container = $kernel->getContainer();
        self::assertInstanceOf(Container::class, $container);
        self::assertTrue($container->isCompiled());

        // Has minimal services from extension
        self::assertTrue($container->has(MigrationRegistry::class));
    }

    /**
     * An exception is thrown when an invalid configuration directory is given
     * @dataProvider provideInvalidConfigurationPaths
     */
    public function testContainerCreationWithInvalidPaths(int $exceptionCode, string $cachePath, string $configPath)
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionCode($exceptionCode);

        // Create kernel
        $kernel = new class($cachePath, $configPath) extends AbstractFregataKernel {
            private string $cacheDir;
            private string $configDir;

            public function __construct(string $cacheDir, string $configDir)
            {
                $this->cacheDir = $cacheDir;
                $this->configDir = $configDir;
            }

            protected function getConfigurationDirectory(): string
            {
                return $this->configDir;
            }

            protected function getCacheDirectory(): string
            {
                return $this->cacheDir;
            }
        };

        $kernel->getContainer();
    }

    public function provideInvalidConfigurationPaths(): array
    {
        return [
            // Cache directory does not exist
            [
                1619874486570,
                __DIR__ . '/does-not-exists',
                ''
            ],
            // Cache directory is a file
            [
                1619874486570,
                __FILE__,
                ''
            ],
            // Config directory does not exist
            [
                1619865822238,
                __DIR__,
                __DIR__ . '/does-not-exists'
            ],
            // Config directory is a file
            [
                1619865822238,
                __DIR__,
                __FILE__
            ],
        ];
    }
}

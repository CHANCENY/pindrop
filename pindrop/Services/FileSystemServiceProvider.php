<?php

declare(strict_types=1);

namespace Simp\Pindrop\Services;

use DI\ContainerBuilder;
use Simp\Pindrop\FileSystem\ConfigurableStreamWrapper;
use Simp\Pindrop\FileSystem\FileSystem;
use Simp\Pindrop\FileSystem\FileSystemInterface;
use Simp\Pindrop\FileSystem\FileSystemService;
use function DI\get;

/**
 * FileSystem Service Provider
 * 
 * Registers FileSystem services in the DI container.
 * Provides configuration for stream wrappers and file operations.
 */
class FileSystemServiceProvider
{
    private EnvServiceProvider $envProvider;

    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }

    /**
     * Configure the DI container with FileSystem services
     */
    public function configureContainer(ContainerBuilder $builder): void
    {
        // FileSystem configuration
        $builder->addDefinitions([
            'filesystem.config' => \DI\factory([self::class, 'buildFilesystemConfig']),

            // FileSystem service
            FileSystemInterface::class => function (\DI\Container $container) {
                $config = $container->get('filesystem.config');
                $logger = $container->has('logger') ? $container->get('logger') : null;
                
                return new FileSystem($config, $logger);
            },

            // FileSystem service wrapper
            FileSystemService::class => function (\DI\Container $container) {
                $config = $container->get('filesystem.config');
                $logger = $container->has('logger') ? $container->get('logger') : null;
                
                return new FileSystemService($config, $logger);
            },

            // Alias for filesystem service
            'filesystem' => \DI\get(FileSystemService::class),

            // Alias for filesystem interface
            'filesystem.interface' => \DI\get(FileSystemInterface::class),

            'filesystem.public_stream' => \DI\get(ConfigurableStreamWrapper::class)
        ]);
    }

    public static function buildFilesystemConfig(): array
    {
        return [
            'public_stream' => getenv('PUBLIC_STREAM') ?: 'public://',
        ];
    }

    /**
     * Get environment provider
     */
    public function getEnvProvider(): EnvServiceProvider
    {
        return $this->envProvider;
    }

    /**
     * Get FileSystem service name
     */
    public function getServiceName(): string
    {
        return 'filesystem';
    }

    /**
     * Get FileSystem interface service name
     */
    public function getInterfaceServiceName(): string
    {
        return 'filesystem.interface';
    }
}

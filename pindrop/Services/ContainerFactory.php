<?php

declare(strict_types=1);

namespace Simp\Pindrop\Services;

use DI\Container;
use DI\ContainerBuilder;

class ContainerFactory
{
    /**
     * @throws \Exception
     */
    public static function create(): Container
    {
        $builder = new ContainerBuilder();
        
        // Initialize environment service provider first
        $envProvider = new EnvServiceProvider();
        $envProvider->configureContainer($builder);
        
        // Initialize config service provider
        $configProvider = new ConfigServiceProvider($envProvider);
        $configProvider->configureContainer($builder);
        
        // Enable compilation for production
        if (getenv('APP_ENV') === 'production') {
            $builder->enableCompilation(__DIR__ . '/../../var/cache');
        }
        
        return $builder->build();
    }
    
    public static function createWithEnv(?string $envFile = null): Container
    {
        $builder = new ContainerBuilder();
        
        // Custom env file support
        if ($envFile && file_exists($envFile)) {
            // Temporarily replace .env file location
            $originalEnv = dirname(__DIR__, 2) . '/.env';
            if ($envFile !== $originalEnv) {
                copy($envFile, $originalEnv);
            }
        }
        
        $envProvider = new EnvServiceProvider();
        $envProvider->configureContainer($builder);
        
        // Initialize config service provider
        $configProvider = new ConfigServiceProvider($envProvider);
        $configProvider->configureContainer($builder);
        
        return $builder->build();
    }
}

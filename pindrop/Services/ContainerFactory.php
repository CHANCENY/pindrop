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
        $envProvider = EnvServiceProvider::getInstance();
        $envProvider->configureContainer($builder);
        
        // Initialize config service provider
        $configProvider = new ConfigServiceProvider($envProvider);
        $configProvider->configureContainer($builder);
        
        // Enable compilation cache for production
        $env      = getenv('APP_ENV') ?: 'development';
        $cacheDir = rtrim(getenv('CACHE_DIR') ?: (__DIR__ . '/../../var/cache/di'), '/');
        if ($env === 'production') {
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            $builder->enableCompilation($cacheDir);
            $builder->writeProxiesToFile(true, $cacheDir . '/proxies');
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
        
        $envProvider = EnvServiceProvider::getInstance();
        $envProvider->configureContainer($builder);
        
        // Initialize config service provider
        $configProvider = new ConfigServiceProvider($envProvider);
        $configProvider->configureContainer($builder);
        
        return $builder->build();
    }
}

<?php

declare(strict_types=1);

namespace Simp\Pindrop\Services;

use DI\ContainerBuilder;

class ConfigServiceProvider
{
    private EnvServiceProvider $envProvider;
    
    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }
    
    /**
     * Configure DI container with config services
     */
    public function configureContainer(ContainerBuilder $builder): void
    {
        $definitions = [
            // Config service that provides access to resolved env vars
            'config' => fn() => new ConfigService($this->envProvider),
            
            // Individual config entries
            'config.root' => fn() => $this->envProvider->get('ROOT'),
            'config.plugin_root' => fn() => $this->envProvider->get('PLUGIN_ROOT'),
            
            // Path helpers
            'paths.root' => fn() => $this->envProvider->get('ROOT'),
            'paths.plugins' => fn() => $this->envProvider->get('PLUGIN_ROOT'),
            'paths.storage' => fn() => $this->envProvider->get('ROOT') . '/storage',
            'paths.cache' => fn() => $this->envProvider->get('ROOT') . '/cache',
        ];
        
        $builder->addDefinitions($definitions);
    }
}

class ConfigService
{
    private EnvServiceProvider $envProvider;
    
    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }
    
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->envProvider->get($key, $default);
    }
    
    public function all(): array
    {
        return $this->envProvider->all();
    }
    
    public function getPath(string $name): ?string
    {
        $paths = [
            'root' => $this->get('ROOT'),
            'plugins' => $this->get('PLUGIN_ROOT'),
            'storage' => $this->get('ROOT') . '/storage',
            'cache' => $this->get('ROOT') . '/cache',
        ];
        
        return $paths[$name] ?? null;
    }
}

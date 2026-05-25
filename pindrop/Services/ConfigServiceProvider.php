<?php

declare(strict_types=1);

namespace Simp\Pindrop\Services;

use DI\ContainerBuilder;
use Simp\Pindrop\Settings\Settings;

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
            'config' => \DI\create(ConfigService::class)->constructor(\DI\get(EnvServiceProvider::class)),
            
            // Individual config entries
            'config.root' => fn() => getenv('ROOT') ?: '',
            'config.plugin_root' => fn() => getenv('PLUGIN_ROOT') ?: '',
            
            // Path helpers
            'paths.root' => fn() => getenv('ROOT') ?: '',
            'paths.plugins' => fn() => getenv('PLUGIN_ROOT') ?: '',
            'paths.storage' => fn() => (getenv('ROOT') ?: '') . '/storage',
            'paths.cache' => fn() => (getenv('ROOT') ?: '') . '/cache',
            'site.settings' => fn() => new Settings(\getAppContainer()->get('database')),
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

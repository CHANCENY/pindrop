<?php

declare(strict_types=1);

namespace Simp\Pindrop\Plugin;

use DI\ContainerBuilder;
use Simp\Pindrop\Services\EnvServiceProvider;

/**
 * Plugin Service Provider
 * 
 * Provides plugin services for dependency injection container.
 * Supports plugin management and configuration.
 */
class PluginServiceProvider
{
    private EnvServiceProvider $envProvider;
    private ?\DI\Container $container = null;
    
    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }
    
    /**
     * Configure DI container with plugin services
     */
    public function configureContainer(ContainerBuilder $builder): void
    {
        $definitions = [
            // Plugin configuration
            'plugin.config' => fn() => $this->getPluginConfig(),
            
            // Plugin manager
            'plugin.manager' => fn(\DI\Container $c) => $this->createPluginManager($c),
            
            // Aliases for convenience
            PluginManager::class => fn(\DI\Container $c) => $c->get('plugin.manager'),
        ];

        $builder->addDefinitions($definitions);

        // Register plugin services after container is built
        $builder->addDefinitions([
            'plugin.services.register' => function(\DI\Container $c) {
                $pluginManager = $c->get('plugin.manager');
                $this->registerPluginServices($c, $pluginManager);
            }
        ]);
    }
    
    /**
     * Register plugin services
     */
    private function registerPluginServices(\DI\Container $container, PluginManager $pluginManager): void
    {
        // Register all plugin services
        foreach ($pluginManager->getPluginServices() as $pluginId => $services) {
            $plugin = $pluginManager->getPlugin($pluginId);

            // Only register services from enabled and installed plugins
            if (!$plugin || !$plugin['enabled'] || !$plugin['installed']) {
                continue;
            }
            
            foreach ($services as $serviceName => $serviceConfig) {
                // Validate service class exists
                if (!$this->validateServiceClass($serviceConfig['class'])) {
                    throw new \RuntimeException(
                        "Service class '{$serviceConfig['class']}' not found for plugin '{$pluginId}' service '{$serviceName}'"
                    );
                }
                
                // Create service factory
                $definition = function ($container) use ($serviceConfig) {
                    $className = $serviceConfig['class'];
                    $arguments = $serviceConfig['arguments'] ?? [];
                    
                    // Resolve arguments
                    $resolvedArguments = [];
                    foreach ($arguments as $argument) {
                        if (is_string($argument) && str_starts_with($argument, '@')) {
                            // DI container reference
                            $dependencyName = substr($argument, 1);
                            $resolvedArguments[] = $container->get($dependencyName);
                        } else {
                            $resolvedArguments[] = $argument;
                        }
                    }
                    
                    // Create service instance
                    return new $className(...$resolvedArguments);
                };
                
                // Register service in container
                $container->set($serviceName, $definition);
            }
        }
    }
    
    /**
     * Validate service class
     */
    private function validateServiceClass(string $className): bool
    {
        return class_exists($className);
    }
    
    /**
     * Create plugin manager instance
     */
    private function createPluginManager(\DI\Container $container): PluginManager
    {
        $pluginManager = new PluginManager(
            $this->envProvider,
            $this->getPluginConfig()['plugin_root'],
            $this->getPluginConfig()['config_root']
        );
        
        // Set container for service registration
        $pluginManager->setContainer($container);
        
        // Register plugin services immediately
        $pluginManager->registerPluginServices();
        
        return $pluginManager;
    }
    
    /**
     * Get plugin configuration from environment variables
     */
    private function getPluginConfig(): array
    {
        return [
            'plugin_root' => $this->envProvider->get('PLUGIN_ROOT', __DIR__ . '/../../plugins'),
            'config_root' => $this->envProvider->get('CONFIG', __DIR__ . '/../../config'),
        ];
    }
    
    /**
     * Register plugin service with container
     */
    public function register(ContainerBuilder $builder): void
    {
        $this->configureContainer($builder);
    }
    
    /**
     * Get available plugin services
     */
    public function getAvailableServices(): array
    {
        return [
            'plugin' => PluginManager::class,
        ];
    }
    
    /**
     * Get plugin configuration template
     */
    public function getConfigTemplate(): array
    {
        return [
            'PLUGIN_ROOT' => '/path/to/plugins/directory',
            'CONFIG_ROOT' => '/path/to/config/directory',
        ];
    }
}

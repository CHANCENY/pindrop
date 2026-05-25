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
            'plugin.config' => \DI\factory([self::class, 'buildPluginConfig']),
            
            // Plugin manager
            'plugin.manager' => \DI\factory([self::class, 'buildPluginManager']),
            
            // Aliases for convenience
            PluginManager::class => function(\DI\Container $c) { return $c->get('plugin.manager'); },
        ];

        $builder->addDefinitions($definitions);

        // Register plugin services after container is built
        $builder->addDefinitions([
            'plugin.services.register' => \DI\factory([self::class, 'buildPluginServiceRegistration']),
        ]);
    }
    
    public static function buildPluginServiceRegistration(\DI\Container $c): void
    {
        $pluginManager = $c->get('plugin.manager');
        foreach ($pluginManager->getPluginServices() as $pluginId => $services) {
            $plugin = $pluginManager->getPlugin($pluginId);
            if (!$plugin || !$plugin['enabled'] || !$plugin['installed']) {
                continue;
            }
            foreach ($services as $serviceName => $serviceConfig) {
                if (!class_exists($serviceConfig['class'])) {
                    throw new \RuntimeException(
                        "Service class '{$serviceConfig['class']}' not found for plugin '{$pluginId}' service '{$serviceName}'"
                    );
                }
                // Container is fully built at this point (called from plugin.services.register
                // which runs in bootstrap.inc after buildContainer() returns).
                // Safe to resolve @arguments directly — no circular dependency risk.
                $className = $serviceConfig['class'];
                $resolvedArgs = [];
                foreach ($serviceConfig['arguments'] ?? [] as $arg) {
                    $resolvedArgs[] = (is_string($arg) && str_starts_with($arg, '@'))
                        ? $c->get(substr($arg, 1))
                        : $arg;
                }
                $c->set($serviceName, new $className(...$resolvedArgs));
            }
        }
    }

    /**
     * Register plugin services (instance version kept for BC)
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
                $className = $serviceConfig['class'];
                $resolvedArguments = [];
                foreach ($serviceConfig['arguments'] ?? [] as $argument) {
                    if (is_string($argument) && str_starts_with($argument, '@')) {
                        $resolvedArguments[] = $container->get(substr($argument, 1));
                    } else {
                        $resolvedArguments[] = $argument;
                    }
                }
                $container->set($serviceName, new $className(...$resolvedArguments));
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
    
    public static function buildPluginConfig(): array
    {
        return [
            'plugin_root' => getenv('PLUGIN_ROOT') ?: (__DIR__ . '/../../plugins'),
            'config_root' => getenv('CONFIG')      ?: (__DIR__ . '/../../config'),
        ];
    }

    public static function buildPluginManager(\DI\Container $c): PluginManager
    {
        $config = $c->get('plugin.config');
        $envProvider = \Simp\Pindrop\Services\EnvServiceProvider::getInstance();
        $pluginManager = new PluginManager($envProvider, $config['plugin_root'], $config['config_root']);
        $pluginManager->setContainer($c);
        // NOTE: registerPluginServices() is intentionally NOT called here.
        // Calling it inside a factory causes a circular dependency because
        // @arguments like @plugin.manager resolve back to this factory while
        // it is still running.  The existing bootstrap.inc line:
        //   $container->get('plugin.services.register');
        // runs this after the container is fully built — that is the correct hook.
        return $pluginManager;
    }

    /**
     * Instance version kept for BC
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

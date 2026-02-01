<?php

declare(strict_types=1);

namespace Simp\Pindrop\Routing;

use DI\Container;
use Simp\Pindrop\Plugin\PluginManager;

/**
 * Plugin Middleware
 * 
 * Handles loading and registering middleware from plugins.
 * Works similarly to PluginRoutes but for middleware components.
 */
class PluginMiddleware
{
    private PluginManager $pluginManager;
    private ?Container $container;
    private array $middlewareInstances = [];

    public function __construct(PluginManager $pluginManager, ?Container $container = null)
    {
        $this->pluginManager = $pluginManager;
        $this->container = $container;
    }

    /**
     * Register all plugin middleware
     */
    public function register(): array
    {
        $middlewareClasses = [];
        
        // Get all middleware from enabled plugins
        $pluginMiddleware = $this->pluginManager->getPluginMiddleware();
        
        foreach ($pluginMiddleware as $pluginId => $middleware) {
            if ($this->pluginManager->isPluginEnabled($pluginId)) {
                $middlewareClasses = array_merge($middlewareClasses, $this->registerPluginMiddleware($pluginId, $middleware));
            }
        }

        return $middlewareClasses;
    }

    /**
     * Register middleware for a specific plugin
     */
    private function registerPluginMiddleware(string $pluginId, array $middleware): array
    {
        $middlewareClasses = [];
        
        foreach ($middleware as $middlewareName => $config) {
            $middlewareClass = $this->createMiddlewareInstance($config, $pluginId, $middlewareName);
            
            if ($middlewareClass) {
                $middlewareClasses[] = $middlewareClass;
                $this->middlewareInstances[$middlewareName] = $middlewareClass;
            }
        }

        return $middlewareClasses;
    }

    /**
     * Create middleware instance from configuration
     */
    private function createMiddlewareInstance(array $config, string $pluginId, string $middlewareName): ?object
    {
        $className = $config['class'] ?? null;
        
        if (!$className) {
            return null;
        }

        if (!class_exists($className)) {
            // Log warning but continue
            error_log("Middleware class '{$className}' not found for plugin '{$pluginId}'");
            return null;
        }

        try {
            // Try to create instance with container if available
            if ($this->container && method_exists($className, 'create')) {
                return $className::create($this->container);
            }

            // Try container-based instantiation
            if ($this->container && $this->container instanceof Container) {
                if ($this->container->has($className)) {
                    return $this->container->get($className);
                }
            }

            // Fallback to simple instantiation
            return new $className();
            
        } catch (\Exception $e) {
            error_log("Failed to create middleware '{$className}' for plugin '{$pluginId}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all registered middleware instances
     */
    public function getMiddlewareInstances(): array
    {
        return $this->middlewareInstances;
    }

    /**
     * Get specific middleware instance by name
     */
    public function getMiddlewareInstance(string $middlewareName): ?object
    {
        return $this->middlewareInstances[$middlewareName] ?? null;
    }

    /**
     * Get all middleware classes from enabled plugins
     * Returns a flat array suitable for router registration
     */
    public function getMiddlewareClasses(): array
    {
        return $this->pluginManager->getEnabledPluginMiddlewareClasses();
    }

    /**
     * Get middleware configuration by name
     */
    public function getMiddlewareConfig(string $middlewareName): ?array
    {
        return $this->pluginManager->getMiddlewareConfig($middlewareName);
    }

    /**
     * Check if middleware is registered
     */
    public function hasMiddleware(string $middlewareName): bool
    {
        return isset($this->middlewareInstances[$middlewareName]);
    }

    /**
     * Clear all middleware instances
     */
    public function clear(): void
    {
        $this->middlewareInstances = [];
    }

    /**
     * Get middleware by plugin
     */
    public function getMiddlewareByPlugin(string $pluginId): array
    {
        $pluginMiddleware = $this->pluginManager->getPluginMiddleware();
        return $pluginMiddleware[$pluginId] ?? [];
    }

    /**
     * Validate middleware configuration
     */
    public function validateMiddlewareConfig(array $config): bool
    {
        return isset($config['class']) && is_string($config['class']) && !empty($config['class']);
    }

    /**
     * Get statistics about loaded middleware
     */
    public function getStatistics(): array
    {
        $pluginMiddleware = $this->pluginManager->getPluginMiddleware();
        $stats = [
            'total_plugins_with_middleware' => 0,
            'total_middleware_classes' => 0,
            'enabled_plugins_middleware' => 0,
            'middleware_by_plugin' => []
        ];

        foreach ($pluginMiddleware as $pluginId => $middleware) {
            $stats['total_plugins_with_middleware']++;
            $stats['total_middleware_classes'] += count($middleware);
            
            if ($this->pluginManager->isPluginEnabled($pluginId)) {
                $stats['enabled_plugins_middleware']++;
            }
            
            $stats['middleware_by_plugin'][$pluginId] = count($middleware);
        }

        return $stats;
    }
}

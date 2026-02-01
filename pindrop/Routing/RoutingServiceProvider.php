<?php

declare(strict_types=1);

namespace Simp\Pindrop\Routing;

use DI\ContainerBuilder;
use Simp\Pindrop\Services\EnvServiceProvider;

/**
 * Routing Service Provider
 * 
 * Provides routing services for dependency injection container.
 * Integrates with the RouteProvider pattern from RouteProvider.zip.
 */
class RoutingServiceProvider
{
    private EnvServiceProvider $envProvider;
    
    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }
    
    /**
     * Configure DI container with routing services
     */
    public function configureContainer(ContainerBuilder $builder): void
    {
        $definitions = [
            // Route provider configuration
            'routing.config' => fn() => $this->getRoutingConfig(),
            
            // Route provider instance
            'route.provider' => fn(\DI\Container $c) => $this->createRouteProvider($c),
            
            // Route manager (static facade)
            'route.manager' => fn(\DI\Container $c) => RouteManager::class,
            
            // Plugin routes handler
            'plugin.routes' => fn(\DI\Container $c) => $this->createPluginRoutes($c),
            
            // Plugin middleware handler
            'plugin.middleware' => fn(\DI\Container $c) => $this->createPluginMiddleware($c),
            
            // Aliases for convenience
            RouteProvider::class => fn(\DI\Container $c) => $c->get('route.provider'),
            RouteManager::class => fn(\DI\Container $c) => $c->get('route.manager'),
        ];
        
        $builder->addDefinitions($definitions);
    }
    
    /**
     * Create a route provider instance
     */
    private function createRouteProvider(\DI\Container $container): RouteProvider
    {
        $config = $this->getRoutingConfig();
        $middlewareFile =  getAppContainer()->get('plugin.middleware')->getMiddlewareClasses();
        
        // Initialize the route provider with middleware
        $routeProvider = RouteManager::initialize($middlewareFile);
        
        // Load plugin routes and register them
        $pluginRoutes = $container->get('plugin.routes');
        $pluginRoutes->register($container->get('plugin.manager')->getPluginRoutes());
        
        return $routeProvider;
    }
    
    /**
     * Create PluginRoutes instance
     */
    private function createPluginRoutes(\DI\Container $container): PluginRoutes
    {
        return new PluginRoutes($container->get('route.provider'), $container);
    }

    /**
     * Create PluginMiddleware instance
     */
    private function createPluginMiddleware(\DI\Container $container): PluginMiddleware
    {
        return new PluginMiddleware($container->get('plugin.manager'), $container);
    }
    
    /**
     * Get routing configuration from environment variables
     */
    private function getRoutingConfig(): array
    {
        return [
            'base_path' => $this->envProvider->get('BASE_PATH', ''),
            'debug_mode' => $this->envProvider->get('DEBUG_MODE', false),
            'cache_routes' => $this->envProvider->get('CACHE_ROUTES', true),
            'middleware_file' => $this->envProvider->get('MIDDLEWARE_FILE', null),
        ];
    }
    
    /**
     * Get available services
     */
    public function getAvailableServices(): array
    {
        return [
            'route.provider' => RouteProvider::class,
            'route.manager' => RouteManager::class,
            'plugin.routes' => PluginRoutes::class,
            'plugin.middleware' => PluginMiddleware::class,
        ];
    }
    
    /**
     * Get configuration template
     */
    public function getConfigTemplate(): array
    {
        return [
            'BASE_PATH' => 'The base path for the application (e.g., /app)',
            'DEBUG_MODE' => 'Enable debug mode for routing (true/false)',
            'CACHE_ROUTES' => 'Enable route caching for performance (true/false)',
            'MIDDLEWARE_FILE' => 'Path to middleware configuration file',
        ];
    }
}

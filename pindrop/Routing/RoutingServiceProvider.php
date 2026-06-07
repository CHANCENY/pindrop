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
            'routing.config' => \DI\factory([self::class, 'buildRoutingConfig']),
            
            // Route provider instance
            'route.provider' => \DI\factory([self::class, 'buildRouteProvider']),
            
            // Route manager (static facade)
            'route.manager' => fn(\DI\Container $c) => new RouteManager(),
            
            // Plugin routes handler
            'plugin.routes' => function(\DI\Container $c) { return new PluginRoutes($c->get('route.provider'), $c); },
            
            // Plugin middleware handler
            'plugin.middleware' => function(\DI\Container $c) { return new PluginMiddleware($c->get('plugin.manager'), $c); },
            
            // Aliases for convenience
            RouteProvider::class => fn(\DI\Container $c) => $c->get('route.provider'),
            RouteManager::class => fn(\DI\Container $c) => $c->get('route.manager'),
        ];
        
        $builder->addDefinitions($definitions);
    }
    
    public static function buildRouteProvider(\DI\Container $c): RouteProvider
    {
        $middleware = $c->get('plugin.middleware')->getMiddlewareClasses();
        $routeProvider = RouteManager::initialize($middleware);
        $pluginRoutes = new PluginRoutes($routeProvider, $c);
        $pluginRoutes->register($c->get('plugin.manager')->getPluginRoutes());
        return $routeProvider;
    }

    /**
     * Create a route provider instance (instance version kept for BC)
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
    
    public static function buildRoutingConfig(): array
    {
        return [
            'base_path'       => getenv('BASE_PATH')       ?: '',
            'debug_mode'      => getenv('DEBUG_MODE')      ?: false,
            'cache_routes'    => getenv('CACHE_ROUTES')    ?: true,
            'middleware_file' => getenv('MIDDLEWARE_FILE') ?: null,
        ];
    }

    /**
     * Instance version kept for BC
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

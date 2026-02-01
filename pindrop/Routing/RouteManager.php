<?php

declare(strict_types=1);

namespace Simp\Pindrop\Routing;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * RouteManager
 * 
 * Static wrapper for easy access to routing functionality.
 * Provides a singleton pattern for global route management.
 */
class RouteManager
{
    private static ?RouteProvider $routeProvider = null;

    /**
     * Initialize and return the route provider
     */
    public static function initialize(string|array|null $middleware_register = null): RouteProvider
    {
        if (self::$routeProvider === null) {
            self::$routeProvider = new RouteProvider($middleware_register);
        }
        
        return self::$routeProvider;
    }

    /**
     * Get the route provider instance
     */
    public static function getRouteProvider(): RouteProvider
    {
        if (self::$routeProvider === null) {
            self::initialize();
        }
        
        return self::$routeProvider;
    }

    /**
     * Dispatch all routes and return response
     */
    public static function dispatch(): Response|JsonResponse|RedirectResponse|null
    {
        $routeProvider = self::getRouteProvider();
        return $routeProvider->dispatch();
    }

    /**
     * Add a custom route
     */
    public static function add(string $method, string $path, string $route_name, object $controller, array $options = []): self
    {
        $routeProvider = self::getRouteProvider();
        $routeProvider->$method($path, $route_name, $controller, $options);
        return new self();
    }

    /**
     * Get all registered routes
     */
    public static function getAllRoutes(): array
    {
        return self::getRouteProvider()->getRoutes();
    }

    /**
     * Get routes by HTTP method
     */
    public static function getRoutesByMethod(string $method): array
    {
        return self::getRouteProvider()->getRoutesByMethod($method);
    }

    /**
     * Check if a route exists
     */
    public static function hasRoute(string $route_name): bool
    {
        return self::getRouteProvider()->hasRoute($route_name);
    }

    /**
     * Get a specific route by name
     */
    public static function getRoute(string $route_name): ?array
    {
        return self::getRouteProvider()->getRoute($route_name);
    }

    /**
     * Clear all routes
     */
    public static function clear(): void
    {
        self::getRouteProvider()->clear();
    }

    /**
     * Generate URL for a named route (basic implementation)
     */
    public static function url(string $route_name, array $params = []): string
    {
        $route = self::getRoute($route_name);
        
        if (!$route) {
            throw new \InvalidArgumentException("Route '{$route_name}' not found");
        }
        
        $path = $route['path'];
        
        // Replace parameters in the path
        foreach ($params as $key => $value) {
            $path = preg_replace("/\[" . preg_quote($key) . ":[^\]]+\]/", $value, $path);
        }
        
        return $path;
    }

    /**
     * Get route statistics
     */
    public static function getStatistics(): array
    {
        $routes = self::getAllRoutes();
        $stats = [
            'total_routes' => count($routes),
            'by_method' => [],
            'with_auth' => 0,
            'with_admin' => 0
        ];
        
        foreach ($routes as $route) {
            $method = strtolower($route['method']);
            $stats['by_method'][$method] = ($stats['by_method'][$method] ?? 0) + 1;
            
            if (!empty($route['options']['auth_required'])) {
                $stats['with_auth']++;
            }
            
            if (!empty($route['options']['admin_required'])) {
                $stats['with_admin']++;
            }
        }
        
        return $stats;
    }
}

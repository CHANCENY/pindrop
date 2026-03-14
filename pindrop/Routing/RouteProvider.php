<?php

declare(strict_types=1);

namespace Simp\Pindrop\Routing;

use Simp\Pindrop\Plugin\PluginManager;
use Simp\Pindrop\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * RouteProvider
 * 
 * Main class for registering routes with support for all HTTP methods
 * using the upgraded simp/router package with our custom Route override.
 */
class RouteProvider
{
    private Route $router;
    private array $routes = [];

    public function __construct(string|array|null $middleware_register = null)
    {
        $this->router = new Route($middleware_register);
    }

    /**
     * Register a GET route
     */
    public function get(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'GET',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register a POST route
     */
    public function post(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'POST',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register a PUT route
     */
    public function put(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'PUT',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'DELETE',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register a PATCH route
     */
    public function patch(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'PATCH',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register an OPTIONS route
     */
    public function options(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'OPTIONS',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register a route for any HTTP method
     */
    public function any(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'ANY',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register multiple routes at once
     */
    public function group(array $routes): self
    {
        foreach ($routes as $route) {
            $method = strtolower($route['method'] ?? 'get');
            $path = $route['path'] ?? '/';
            $route_name = $route['route_name'] ?? 'default';
            $controller = $route['controller'];
            $options = $route['options'] ?? [];

            $this->$method($path, $route_name, $controller, $options);
        }
        return $this;
    }

    /**
     * Dispatch all registered routes
     */
    public function dispatch(): Response|JsonResponse|RedirectResponse|null
    {
        /**@var PluginManager $pluginManager **/
        $pluginManager = \getAppContainer()->get('plugin.manager');
        $pluginManager->requireModulesFile();

        if (\getAppContainer()->has('language.support.service')) {
            $sLanguages = \getAppContainer()->get('language.support.service')->languages;
            foreach ($this->routes as $k=>$route) {

                foreach ($sLanguages as $lang=>$sLanguage) {
                    $cloneRoute =  $route;
                    $cloneRoute['path'] = "/{$lang}{$route['path']}";
                    $this->routes["{$k}{$lang}"] = $cloneRoute;
                }

            }
        }

        // Register all routes with the router immediately
        foreach ($this->routes as $route) {
            $method = strtolower($route['method']);
            $path = $route['path'];
            $route_name = $route['route_name'];
            $controller = $route['controller'];
            $options = $route['options'];
            if ($method === 'any') {
                $this->router->any($path, $route_name, $controller, $options);
            } else {
                $this->router->$method($path, $route_name, $controller, $options);
            }
        }

        // Send the response (router handles dispatching automatically)
        $this->router->send();
        
        // Return null since the response is already sent by the router
        return null;
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Clear all registered routes
     */
    public function clear(): self
    {
        $this->routes = [];
        return $this;
    }

    /**
     * Get routes by method
     */
    public function getRoutesByMethod(string $method): array
    {
        return array_filter($this->routes, fn($route) => 
            strtolower($route['method']) === strtolower($method) || $route['method'] === 'ANY'
        );
    }

    /**
     * Check if route exists
     */
    public function hasRoute(string $route_name): bool
    {
        return !empty(array_filter($this->routes, fn($route) => 
            $route['route_name'] === $route_name
        ));
    }

    /**
     * Get route by name
     */
    public function getRoute(string $route_name): ?array
    {
        $found = array_filter($this->routes, fn($route) => 
            $route['route_name'] === $route_name
        );
        
        return !empty($found) ? reset($found) : null;
    }
}

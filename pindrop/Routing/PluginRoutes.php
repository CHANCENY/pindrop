<?php

declare(strict_types=1);

namespace Simp\Pindrop\Routing;

use DI\Container;
use Simp\Pindrop\Form\FormBase;
use Simp\Pindrop\Form\FormBuilder;
use Simp\Pindrop\Form\FormState;
use Symfony\Component\HttpFoundation\Request;
use Simp\Pindrop\Routing\ControllerFactory;

/**
 * PluginRoutes
 * 
 * Handles loading and registering routes from plugins.
 * This replaces the WebRoutes class but works with plugin system.
 * Uses ControllerFactory for proper dependency injection support.
 */
class PluginRoutes
{
    private RouteProvider $routeProvider;
    private array $pluginRoutes = [];
    private ?Container $container;
    private ControllerFactory $controllerFactory;

    public function __construct(RouteProvider $routeProvider, ?Container $container = null)
    {
        $this->routeProvider = $routeProvider;
        $this->container = $container;
        $this->controllerFactory = new ControllerFactory($container);
    }

    /**
     * Register all plugin routes
     */
    public function register(array $pluginRoutes): RouteProvider
    {
        $this->pluginRoutes = $pluginRoutes;

        foreach ($pluginRoutes as $pluginId => $routes) {
            $this->registerPluginRoutes($pluginId, $routes);
        }

        return $this->routeProvider;
    }

    /**
     * Register routes for a specific plugin
     */
    private function registerPluginRoutes(string $pluginId, array $routes): void
    {
        foreach ($routes as $routeName => $routeConfig) {
            $this->registerRoute($routeName, $routeConfig);
        }
    }

    /**
     * Register a single route from plugin configuration
     */
    private function registerRoute(string $name, array $config): void
    {
        $path = $config['path'] ?? '/';
        $methods = $config['methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $defaults = $config['defaults'] ?? [];
        $requirements = $config['requirements'] ?? [];
        
        // Extract controller or form handler
        $handler = $defaults['_controller'] ?? $defaults['_form'] ?? null;
        
        if (!$handler) {
            throw new \InvalidArgumentException("Route '{$name}' must have either '_controller' or '_form' in defaults");
        }
        
        // Create controller instance using ControllerFactory
        $controller = $this->createControllerInstance($handler, $name);
        
        // Create options array with route name and controller method
        $options = $this->createRouteOptions($requirements);
        $options['route_name'] = $name;  // Add route name to options
        
        // Set controller method based on handler type
        if (isset($defaults['_controller'])) {
            // Extract method from controller string using ControllerFactory
            $method = $this->controllerFactory->extractMethod($handler, $name);
            $options['controller_method'] = $method;
        } elseif (isset($defaults['_form'])) {
            // Form handlers use formHandlerBuilder method
            $options['controller_method'] = 'formHandlerBuilder';
        }

        // Register route with each method
        foreach ((array) $methods as $method) {
            $this->routeProvider->$method($path, $name, $controller, $options);
        }
    }

    /**
     * Create controller instance from handler string using ControllerFactory
     */
    private function createControllerInstance(string $handler, string $routeName): object
    {
        // Controller handler: Class::method or Class@method
        if ($this->controllerFactory->isValidHandler($handler)) {
            $controllerClass = $this->controllerFactory->extractClass($handler);
            
            // Use ControllerFactory for proper instantiation
            return $this->controllerFactory->createController($handler, $routeName, 'formHandlerBuilder');
        }

        throw new \RuntimeException("Invalid handler format for route '{$routeName}': {$handler}");
    }

    /**
     * Create route options from requirements
     */
    private function createRouteOptions(array $requirements): array
    {
        $options = [];
        
        // Add permissions in the new format: _permissions['{role_name}___required'] = true
        if (isset($requirements['_permission'])) {
            $permissions = (array) $requirements['_permission'];
            $options['_permissions'] = [];
            
            foreach ($permissions as $role) {
                $options['_permissions'][$role . '___required'] = true;
            }
        }
        
        // Add middleware
        if (isset($requirements['_middleware'])) {
            $options['middleware'] = (array) $requirements['_middleware'];
        }
        
        return $options;
    }

    /**
     * Get all registered plugin routes
     */
    public function getPluginRoutes(): array
    {
        return $this->pluginRoutes;
    }

    /**
     * Get the controller factory instance
     */
    public function getControllerFactory(): ControllerFactory
    {
        return $this->controllerFactory;
    }

    /**
     * Set controller factory (for testing or custom implementations)
     */
    public function setControllerFactory(ControllerFactory $controllerFactory): void
    {
        $this->controllerFactory = $controllerFactory;
    }
}

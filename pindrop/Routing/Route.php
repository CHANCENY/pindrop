<?php

declare(strict_types=1);

namespace Simp\Pindrop\Routing;

use ReflectionException;
use Simp\Router\Router\NotFoundException;
use Simp\Router\Router\RouterRegister;

/**
 * Route
 * 
 * Override of vendor/simp/router/src/Route.php to fix controller method handling.
 * This implementation avoids the problematic $options['controller_method'] = end($list); 
 * approach and instead relies on the controller_method being properly set in options
 * by the PluginRoutes class using ControllerFactory.
 */
class Route
{
    protected RouterRegister $router_register;

    public function __construct(string|array|null $middleware_register_file = null)
    {
        $this->router_register = new RouterRegister($middleware_register_file);
    }

    /**
     * Create controller instance - simplified without method extraction
     * 
     * @param string|object $controller_class Controller class or instance
     * @return object Controller instance
     * @throws ReflectionException
     */
    private function createControllerInstance($controller_class): object
    {
        // If it's already an object, return it as-is
        if (is_object($controller_class)) {
            return $controller_class;
        }

        // If it's a string, create instance
        if (is_string($controller_class)) {
            // Check for @ syntax and extract class only
            if (strpos($controller_class, '@') !== false) {
                $list = explode('@', $controller_class);
                $controller_class = $list[0];
            }
            
            // Check for :: syntax and extract class only
            if (strpos($controller_class, '::') !== false) {
                $list = explode('::', $controller_class);
                $controller_class = $list[0];
            }

            $reflection = new \ReflectionClass($controller_class);
            return $reflection->newInstance();
        }

        throw new \InvalidArgumentException("Invalid controller type: " . gettype($controller_class));
    }

    /**
     * Listener for HTTP method GET
     */
    public function get(string $path, string $route_name, $controller, array $options = []) {
        $controller_instance = $this->createControllerInstance($controller);
        $this->router_register->get($path, $route_name, $controller_instance, $options);
    }

    /**
     * Listener for HTTP method POST
     */
    public function post(string $path, string $route_name, $controller, array $options = []) {
        $controller_instance = $this->createControllerInstance($controller);
        $this->router_register->post($path, $route_name, $controller_instance, $options);
    }

    /**
     * Listener for HTTP method PUT
     */
    public function put(string $path, string $route_name, $controller, array $options = []) {
        $controller_instance = $this->createControllerInstance($controller);
        $this->router_register->put($path, $route_name, $controller_instance, $options);
    }

    /**
     * Listener for HTTP method DELETE
     */
    public function delete(string $path, string $route_name, $controller, array $options = []) {
        $controller_instance = $this->createControllerInstance($controller);
        $this->router_register->delete($path, $route_name, $controller_instance, $options);
    }

    /**
     * Listener for HTTP method OPTIONS
     */
    public function options(string $path, string $route_name, $controller, array $options = []) {
        $controller_instance = $this->createControllerInstance($controller);
        $this->router_register->options($path, $route_name, $controller_instance, $options);
    }

    /**
     * Listener for HTTP method PATCH
     */
    public function patch(string $path, string $route_name, $controller, array $options = []) {
        $controller_instance = $this->createControllerInstance($controller);
        $this->router_register->patch($path, $route_name, $controller_instance, $options);
    }

    /**
     * Listener for any HTTP method
     */
    public function any(string $path, string $route_name, $controller, array $options = []) {
        $controller_instance = $this->createControllerInstance($controller);
        $this->router_register->any($path, $route_name, $controller_instance, $options);
    }

    /**
     * Send the response
     */
    public function send() {
        $this->router_register->send();
    }

    /**
     * Get the response
     */
    public function getResponse()
    {
        return $this->router_register->getResponse();
    }
}

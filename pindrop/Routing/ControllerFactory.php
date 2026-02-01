<?php

declare(strict_types=1);

namespace Simp\Pindrop\Routing;

use DI\Container;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Controller Factory
 * 
 * Handles controller instantiation with proper dependency injection support.
 * Overrides the default simp/router controller creation to support:
 * - Both :: and @ syntax for method specification
 * - Dependency injection via container
 * - Static factory methods
 * - Proper error handling
 */
class ControllerFactory
{
    private ?ContainerInterface $container;

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Create controller instance from handler string
     * 
     * Supports formats:
     * - "Class::method" - Traditional PHP method syntax
     * - "Class@method" - Laravel-style method syntax
     * - "Class" - Class only (method determined by route name)
     * 
     * @param string $handler Controller handler string
     * @param string $routeName Route name for error reporting
     * @param string $defaultMethod Default method if not specified in handler
     * @return object Controller instance
     * @throws RuntimeException If controller class not found or instantiation fails
     */
    public function createController(string $handler, string $routeName, string $defaultMethod = ''): object
    {
        // Parse handler to extract class and method
        [$controllerClass, $method] = $this->parseHandler($handler, $defaultMethod);
        
        // Validate controller class exists
        if (!class_exists($controllerClass)) {
            throw new \RuntimeException("Controller class '{$controllerClass}' not found for route '{$routeName}'");
        }

        // Create controller instance with dependency injection
        $controller = $this->instantiateController($controllerClass, $routeName);
        
        // Validate method exists on controller
        if (!method_exists($controller, $method)) {
            throw new \RuntimeException("Controller method '{$method}' not found in '{$controllerClass}' for route '{$routeName}'");
        }

        return $controller;
    }

    /**
     * Parse handler string to extract controller class and method
     * 
     * @param string $handler Handler string
     * @param string $defaultMethod Default method if not specified
     * @return array [controllerClass, method]
     */
    private function parseHandler(string $handler, string $defaultMethod): array
    {
        // Check for :: syntax (traditional PHP)
        if (strpos($handler, '::') !== false) {
            return explode('::', $handler, 2);
        }

        // Check for @ syntax (Laravel-style)
        if (strpos($handler, '@') !== false) {
            return explode('@', $handler, 2);
        }

        // No method specified, use default
        return [$handler, $defaultMethod];
    }

    /**
     * Instantiate controller with dependency injection support
     * 
     * @param string $controllerClass Controller class name
     * @param string $routeName Route name for error reporting
     * @return object Controller instance
     * @throws RuntimeException If instantiation fails
     */
    private function instantiateController(string $controllerClass, string $routeName): object
    {
        try {
            // Try static factory method first (preferred for DI)
            if ($this->container && method_exists($controllerClass, 'create')) {
                return $controllerClass::create($this->container);
            }

            // Try container-based instantiation
            if ($this->container && $this->container instanceof Container) {
                // Check if container can create the controller
                if ($this->container->has($controllerClass)) {
                    return $this->container->get($controllerClass);
                }
                
                // Try autowiring with container
                try {
                    $reflection = new ReflectionClass($controllerClass);
                    $constructor = $reflection->getConstructor();
                    
                    if ($constructor && $constructor->getNumberOfParameters() > 0) {
                        // Use container to resolve constructor parameters
                        return $this->container->make($controllerClass);
                    }
                } catch (\Exception $e) {
                    // Fall back to simple instantiation if autowiring fails
                }
            }

            // Fallback to simple instantiation
            return new $controllerClass();
            
        } catch (ReflectionException $e) {
            throw new \RuntimeException("Failed to instantiate controller '{$controllerClass}' for route '{$routeName}': " . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            throw new \RuntimeException("Error creating controller '{$controllerClass}' for route '{$routeName}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Set container for dependency injection
     * 
     * @param ContainerInterface|null $container DI container
     */
    public function setContainer(?ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * Get current container
     * 
     * @return ContainerInterface|null
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * Check if handler string is valid
     * 
     * @param string $handler Handler string to validate
     * @return bool True if valid format
     */
    public function isValidHandler(string $handler): bool
    {
        // Must contain at least a class name
        if (empty(trim($handler))) {
            return false;
        }

        // Check for valid syntax patterns
        if (strpos($handler, '::') !== false) {
            $parts = explode('::', $handler, 2);
            return count($parts) === 2 && !empty($parts[0]) && !empty($parts[1]);
        }

        if (strpos($handler, '@') !== false) {
            $parts = explode('@', $handler, 2);
            return count($parts) === 2 && !empty($parts[0]) && !empty($parts[1]);
        }

        // Just a class name is valid
        return true;
    }

    /**
     * Extract method name from handler
     * 
     * @param string $handler Handler string
     * @param string $defaultMethod Default method if not specified
     * @return string Method name
     */
    public function extractMethod(string $handler, string $defaultMethod = ''): string
    {
        if (strpos($handler, '::') !== false) {
            return explode('::', $handler, 2)[1];
        }

        if (strpos($handler, '@') !== false) {
            return explode('@', $handler, 2)[1];
        }

        return $defaultMethod;
    }

    /**
     * Extract class name from handler
     * 
     * @param string $handler Handler string
     * @return string Class name
     */
    public function extractClass(string $handler): string
    {
        if (strpos($handler, '::') !== false) {
            return explode('::', $handler, 2)[0];
        }

        if (strpos($handler, '@') !== false) {
            return explode('@', $handler, 2)[0];
        }

        return $handler;
    }
}

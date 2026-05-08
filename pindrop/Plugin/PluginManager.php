<?php

declare(strict_types=1);

namespace Simp\Pindrop\Plugin;

use Simp\Pindrop\Message\Message;
use Simp\Pindrop\Services\EnvServiceProvider;
use Simp\Pindrop\Templating\LibraryAssets;
use Symfony\Component\Yaml\Yaml;
use DI\Container;
use DI\ContainerBuilder;
use Exception;
use Simp\Pindrop\Events\SystemEvents\Events;

/**
 * Plugin Manager
 * 
 * Handles plugin discovery, installation, and configuration.
 * Supports YAML configuration files and service registration.
 */
class PluginManager
{
    private EnvServiceProvider $envProvider;
    private string $pluginRoot;
    private string $configRoot;
    private array $plugins = [];
    private array $enabledPlugins = [];
    private array $pluginConfigs = [];
    private array $pluginServices = [];
    private array $pluginRoutes = [];
    private array $pluginMiddleware = [];
    private array $pluginMysqlSchemas = [];
    private array $pluginMenus = [];

    private array $pluginTemplatesSources = [];
    private ?Container $container = null;
    
    public function __construct(
        EnvServiceProvider $envProvider,
        ?string $pluginRoot = null,
        ?string $configRoot = null
    ) {
        $this->envProvider = $envProvider;
        $this->pluginRoot = $pluginRoot ?? $envProvider->get('PLUGIN_ROOT', __DIR__ . '/../../modules');
        $this->configRoot = $configRoot ?? $envProvider->get('CONFIG', __DIR__ . '/../../config/sync');
        
        $this->initialize();
    }
    
    /**
     * Set DI container for service registration
     */
    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }
    
    /**
     * Register plugin services in DI container
     */
    public function registerPluginServices(): void
    {
        if (!$this->container) {
            return;
        }
        
        foreach ($this->pluginServices as $pluginId => $services) {
            if ($this->plugins[$pluginId]['enabled'] && $this->plugins[$pluginId]['installed']) {
                $this->registerPluginServiceDefinitions($pluginId, $services);
            }
        }
    }
    
    /**
     * Register service definitions for a specific plugin
     */
    private function registerPluginServiceDefinitions(string $pluginId, array $services): void
    {
        foreach ($services as $serviceName => $serviceConfig) {
            // Validate service class exists
            if (!$this->validateServiceClass($serviceConfig['class'])) {
                throw new \RuntimeException(
                    "Service class '{$serviceConfig['class']}' not found for plugin '{$pluginId}' service '{$serviceName}'"
                );
            }
            
            // Validate service dependencies exist
            $this->validateServiceDependencies($serviceConfig, $pluginId, $serviceName);
            
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
            $this->container->set($serviceName, $definition);
        }
    }
    
    /**
     * Validate service class exists
     */
    private function validateServiceClass(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }
        
        try {
            $reflection = new \ReflectionClass($className);
            return $reflection->isInstantiable();
        } catch (\ReflectionException $e) {
            return false;
        }
    }
    
    /**
     * Validate service dependencies
     */
    private function validateServiceDependencies(array $serviceConfig, string $pluginId, string $serviceName): void
    {
        $arguments = $serviceConfig['arguments'] ?? [];
        
        foreach ($arguments as $argument) {
            if (is_string($argument) && str_starts_with($argument, '@')) {
                $dependencyName = substr($argument, 1);

                // Check if dependency is a core service or plugin service
                if (!$this->isValidDependency($dependencyName)) {
                    throw new \RuntimeException(
                        "Invalid dependency '{$dependencyName}' for plugin '{$pluginId}' service '{$serviceName}'"
                    );
                }
            }
        }
    }
    
    /**
     * Check if dependency is valid
     */
    private function isValidDependency(string $dependencyName): bool
    {
        // Core services that should always be available
        $coreServices = [
            'logger',
            'database.service',
            'mail.manager',
            'plugin.manager',
            'env.services',
            'config.services',
            'filesystem.config',
            'filesystem',
            'filesystem.interface',
            'database'
        ];
        
        // Check if it's a core service
        if (in_array($dependencyName, $coreServices)) {
            return true;
        }

        $flag = false;
        // Check if it's a registered plugin service
        foreach ($this->pluginServices as $pluginService) {
            foreach ($pluginService as $name=>$service) {
                if ($dependencyName === $name) {
                    $flag = true;
                    break;
                }
            }
        }
        return $flag;
    }
    
    /**
     * Initialize plugin system
     */
    private function initialize(): void
    {
        $this->ensureDirectories();
        $this->discoverPlugins();
        $this->loadInstalledPlugins();
    }
    
    /**
     * Ensure required directories exist
     */
    private function ensureDirectories(): void
    {
        $directories = [
            $this->pluginRoot,
            $this->configRoot,
        ];
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, recursive:  true);
            }
        }
    }
    
    /**
     * Discover available plugins
     */
    private function discoverPlugins(): void
    {
        if (!is_dir($this->pluginRoot)) {
            return;
        }
        
        $iterator = new \DirectoryIterator($this->pluginRoot);
        
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isDot()) {
                $pluginId = $item->getBasename();
                $pluginPath = $item->getPathname();

                $data = Yaml::parseFile($pluginPath . '/info.yml');

                $this->plugins[$pluginId] = [
                    'id' => $pluginId,
                    'path' => $pluginPath,
                    'info_file' => $pluginPath . '/info.yml',
                    'enabled' => false,
                    'installed' => false,
                    'config' => null,
                    ...$data
                ];
            }
        }
    }
    
    /**
     * Load installed plugins configuration
     */
    private function loadInstalledPlugins(): void
    {
        $installedFile = $this->configRoot . '/core.plugin.yml';
        
        if (!file_exists($installedFile)) {
            $this->createDefaultInstalledConfig($installedFile);
            return;
        }
        
        try {
            $installed = Yaml::parseFile($installedFile);
            
            if (is_array($installed)) {
                foreach ($installed as $pluginId => $enabled) {
                    if (isset($this->plugins[$pluginId])) {
                        $this->plugins[$pluginId]['installed'] = true;
                        $this->plugins[$pluginId]['enabled'] = (bool) $enabled;
                        $this->plugins[$pluginId]['version'] = 'unknown';
                        $this->plugins[$pluginId]['installed_at'] = null;

                        if (!empty($this->plugins[$pluginId]['enabled'])) {
                            $this->enabledPlugins[] = $pluginId;
                        }
                        
                        // Load plugin configuration for installed plugins (regardless of enabled status)
                        $this->loadPluginConfig($pluginId);
                    }
                }
            }
            
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to load installed plugins: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Load plugin configuration
     */
    private function loadPluginConfig(string $pluginId): void
    {
        $plugin = $this->plugins[$pluginId] ?? null;
        
        if (!$plugin) {
            return;
        }
        
        $configFile = $plugin['info_file'];

        if (!file_exists($configFile)) {
            return;
        }
        
        try {
            $config = Yaml::parseFile($configFile);
            
            $this->pluginConfigs[$pluginId] = $config;

            if (empty($plugin['installed']) || empty($plugin['enabled'])) {
                return;
            }
            
            // Load plugin services if services.yml exists (regardless of enabled status)
            $servicesFile = $plugin['path'] . '/services.yml';
            if (file_exists($servicesFile)) {
                $services = Yaml::parseFile($servicesFile);
                if (is_array($services)) {
                    $this->pluginServices[$pluginId] = $services;
                }
            }
            
            // Load plugin routes if routing.yml exists
            $routingFile = $plugin['path'] . '/routing.yml';
            if (file_exists($routingFile)) {
                $routes = Yaml::parseFile($routingFile);
                
                // Handle both formats: routes under 'routes' key or at root level
                if (isset($routes['routes']) && is_array($routes['routes'])) {
                    $this->pluginRoutes[$pluginId] = $routes['routes'];
                } elseif (is_array($routes)) {
                    // Routes are at root level (like in alert plugin)
                    $this->pluginRoutes[$pluginId] = $routes;
                }
            }
            
            // Load plugin menus if menu.yml exists
            $menuFile = $plugin['path'] . '/menu.yml';
            if (file_exists($menuFile)) {
                $menus = Yaml::parseFile($menuFile);
                if (is_array($menus)) {
                    $this->pluginMenus[$pluginId] = $menus;
                }
            }
            
            // Load plugin middleware if middleware.yml exists
            $middlewareFile = $plugin['path'] . '/middleware.yml';
            if (file_exists($middlewareFile)) {
                $middleware = Yaml::parseFile($middlewareFile);
                if (is_array($middleware)) {
                    $this->pluginMiddleware[$pluginId] = $middleware;
                }
            }

            // Load plugin mysql schemas if mysql directory exists
            $mysqlDirectory = $plugin['path'] . '/mysql';
            if (is_dir($mysqlDirectory)) {
                $list = array_diff(scandir($mysqlDirectory), ['.', '..']);
                foreach ($list as $file) {
                    $fullPath = $mysqlDirectory . '/' . $file;
                    if (is_file($fullPath) && file_exists($fullPath) && str_ends_with($file, '.sql')) {
                        $this->pluginMysqlSchemas[] = $fullPath;
                    }
                }
            }

            $templateDirectory = $plugin['path'] . '/templates';
            if (is_dir($templateDirectory)) {
                $this->pluginTemplatesSources[] = $templateDirectory;
            }
            
        }
        catch (Exception $e) {
            throw new \RuntimeException("Failed to load plugin config for {$pluginId}: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Enable a plugin
     */
    public function enablePlugin(string $pluginId): bool
    {
    
        if (!isset($this->plugins[$pluginId])) {
            throw new \InvalidArgumentException("Plugin '{$pluginId}' not found");
        }
        
        $plugin = $this->plugins[$pluginId];
        
        if ($plugin['enabled']) {
            return true; // Already enabled
        }
        
        try {
            new LibraryAssets(\getAppContainer()->get('database'))->clearCache();
            $dependencies = $plugin['dependencies'] ?? [];
            $flag = true;
            foreach ($dependencies as $dependency) {
                if (empty($this->plugins[$dependency])) {

                    if (php_sapi_name() === 'cli') {
                        echo "[{$pluginId}] cannot be installed without plugin '{$dependency}'\n";
                    }
                    else {
                        Message::error("[{$pluginId}] cannot be installed without plugin '{$dependency}'\n");
                    }
                    $flag = false;
                }
            }

            if (!$flag) {
                return false;
            }

            // Load plugin config if not already loaded
            if (!isset($this->pluginConfigs[$pluginId])) {
                $this->loadPluginConfig($pluginId);
            }
            
            // Mark as enabled
            $this->plugins[$pluginId]['enabled'] = true;
            $this->enabledPlugins[] = $pluginId;
            
            // Register plugin services in DI container
            if ($this->container && isset($this->pluginServices[$pluginId])) {
                $this->registerPluginServiceDefinitions($pluginId, $this->pluginServices[$pluginId]);
            }
            
            // Save plugin state
            $this->savePluginState();
            
            return true;
            
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to enable plugin '{$pluginId}': " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Disable a plugin
     */
    public function disablePlugin(string $pluginId): bool
    {
        if (!isset($this->plugins[$pluginId])) {
            throw new \InvalidArgumentException("Plugin '{$pluginId}' not found");
        }
        
        $plugin = $this->plugins[$pluginId];
        
        if (!$plugin['enabled']) {
            return true; // Already disabled
        }
        
        try {
             new LibraryAssets(\getAppContainer()->get('database'))->clearCache();
            // Mark as disabled
            $this->plugins[$pluginId]['enabled'] = false;
            
            // Remove from enabled list
            $this->enabledPlugins = array_filter($this->enabledPlugins, fn($id) => $id !== $pluginId);
            
            // Unregister plugin services from DI container
            if ($this->container && isset($this->pluginServices[$pluginId])) {
                $this->unregisterPluginServiceDefinitions($pluginId, $this->pluginServices[$pluginId]);
            }
            
            // Save plugin state
            $this->savePluginState();
            
            return true;
            
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to disable plugin '{$pluginId}': " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Unregister service definitions for a specific plugin
     */
    private function unregisterPluginServiceDefinitions(string $pluginId, array $services): void
    {
        if (!$this->container) {
            return;
        }
        
        foreach (array_keys($services) as $serviceName) {
            // Remove service from container
            $this->container->set($serviceName, null);
        }
    }
    
    /**
     * Install a plugin
     */
    public function installPlugin(string $pluginId): bool
    {
        if (!isset($this->plugins[$pluginId])) {
            throw new \InvalidArgumentException("Plugin '{$pluginId}' not found");
        }
        
        $plugin = $this->plugins[$pluginId];
        
        if ($plugin['installed']) {
            return true; // Already installed
        }
        
        try {

            new LibraryAssets(\getAppContainer()->get('database'))->clearCache();
            $dependencies = $plugin['dependencies'] ?? [];
            $flag = true;
            foreach ($dependencies as $dependency) {
                if (empty($this->plugins[$dependency])) {

                    if (php_sapi_name() === 'cli') {
                        echo "[{$pluginId}] cannot be installed without plugin '{$dependency}'\n";
                    }
                    else {
                        Message::error("[{$pluginId}] cannot be installed without plugin '{$dependency}'\n");
                    }
                    $flag = false;
                }
            }

            if (!$flag) {
                return false;
            }

            // Load plugin config
            $this->loadPluginConfig($pluginId);
            
            // Mark as installed
            $this->plugins[$pluginId]['installed'] = true;
            $this->plugins[$pluginId]['installed_at'] = date('Y-m-d H:i:s');
            
            // Save plugin state
            $this->savePluginState();

            \appEvents()->invokeEvents(Events::PLUGIN_INSTALLED, ['plugin_id' => $pluginId, 'container'=> $this->container]);
            
            return true;
            
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to install plugin '{$pluginId}': " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Uninstall a plugin
     */
    public function uninstallPlugin(string $pluginId): bool
    {
        if (!isset($this->plugins[$pluginId])) {
            throw new \InvalidArgumentException("Plugin '{$pluginId}' not found");
        }
        
        $plugin = $this->plugins[$pluginId];
        
        if (!$plugin['installed']) {
            return true; // Already uninstalled
        }
        
        try {
             new LibraryAssets(\getAppContainer()->get('database'))->clearCache();
            // Disable first
            if ($plugin['enabled']) {
                $this->disablePlugin($pluginId);
            }
            
            // Mark as uninstalled
            $this->plugins[$pluginId]['installed'] = false;
            unset($this->plugins[$pluginId]['installed_at']);
            
            // Remove plugin config
            unset($this->pluginConfigs[$pluginId]);
            unset($this->pluginServices[$pluginId]);
            unset($this->pluginRoutes[$pluginId]);
            unset($this->pluginMiddleware[$pluginId]);
            unset($this->pluginMenus[$pluginId]);
            
            // Save plugin state
            $this->savePluginState();

            \appEvents()->invokeEvents(Events::PLUGIN_UNINSTALLED, ['plugin_id' => $pluginId, 'container'=> $this->container]);
            
            return true;
            
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to uninstall plugin '{$pluginId}': " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Save plugin state configuration
     */
    private function savePluginState(): void
    {
        $installedFile = $this->configRoot . '/core.plugin.yml';
        $data = [];
        foreach ($this->plugins as $pluginId => $plugin) {
            $data[$pluginId] = $plugin['enabled'] ? 1 : 0;
        }
        
        $yaml = Yaml::dump($data, 4, 2);
        
        if (file_put_contents($installedFile, $yaml) === false) {
            throw new \RuntimeException("Failed to save plugin state to: {$installedFile}");
        }
    }
    
    /**
     * Create default installed plugins configuration
     */
    private function createDefaultInstalledConfig(string $filePath): void
    {
        $data = [];
        
        $yaml = Yaml::dump($data, 4, 2);
        
        if (file_put_contents($filePath, $yaml) === false) {
            throw new \RuntimeException("Failed to create default plugin config: {$filePath}");
        }
    }
    
    /**
     * Get all plugins
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }
    
    /**
     * Get enabled plugins
     */
    public function getEnabledPlugins(): array
    {
        return array_filter($this->plugins, fn($plugin) => $plugin['enabled']);
    }
    
    /**
     * Get installed plugins
     */
    public function getInstalledPlugins(): array
    {
        return array_filter($this->plugins, fn($plugin) => $plugin['installed']);
    }
    
    /**
     * Get plugin configuration
     */
    public function getPluginConfig(string $pluginId): ?array
    {
        return $this->pluginConfigs[$pluginId] ?? null;
    }
    
    /**
     * Get all plugin services
     */
    public function getPluginServices(): array
    {
        return $this->pluginServices;
    }
    
    /**
     * Get all plugin routes
     */
    public function getPluginRoutes(): array
    {
        return $this->pluginRoutes;
    }

    /**
     * Get all plugin middleware
     */
    public function getPluginMiddleware(): array
    {
        return $this->pluginMiddleware;
    }

    /**
     * Get all plugin menus
     */
    public function getPluginMenus(): array
    {
        return $this->pluginMenus;
    }

    /**
     * Get all middleware classes from enabled plugins
     * Returns a flat array of middleware class names for router registration
     */
    public function getEnabledPluginMiddlewareClasses(): array
    {
        $middlewareClasses = [];
        foreach ($this->pluginMiddleware as $pluginId => $middleware) {
            // Only include middleware from enabled plugins
            if ($this->isPluginEnabled($pluginId)) {
                foreach ($middleware as $middlewareName => $config) {
                    if (isset($config['class']) && class_exists($config['class'])) {
                        $middlewareClasses[] = $config['class'];
                    }
                }
            }
        }
        
        return $middlewareClasses;
    }

    /**
     * Get middleware configuration by name
     */
    public function getMiddlewareConfig(string $middlewareName): ?array
    {
        foreach ($this->pluginMiddleware as $pluginId => $middleware) {
            if (isset($middleware[$middlewareName])) {
                return $middleware[$middlewareName];
            }
        }
        
        return null;
    }
    
    /**
     * Check if plugin is enabled
     */
    public function isPluginEnabled(string $pluginId): bool
    {
        return in_array($pluginId, $this->enabledPlugins);
    }
    
    /**
     * Check if plugin is installed
     */
    public function isPluginInstalled(string $pluginId): bool
    {
        return ($this->plugins[$pluginId]['installed'] ?? false);
    }
    
    /**
     * Get plugin by ID
     */
    public function getPlugin(string $pluginId): ?array
    {
        return $this->plugins[$pluginId] ?? null;
    }
    
    /**
     * Get plugin manager statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_plugins' => count($this->plugins),
            'enabled_plugins' => count($this->enabledPlugins),
            'installed_plugins' => count($this->getInstalledPlugins())
        ];
    }
    
    /**
     * Re-discover plugins (public method for scanning)
     */
    public function rediscoverPlugins(): array
    {
        // Store current plugins to compare
        $previousPlugins = $this->plugins;
        
        // Re-discover plugins
        $this->discoverPlugins();
        
        // Find newly discovered plugins
        $newPlugins = [];
        foreach ($this->plugins as $pluginId => $plugin) {
            if (!isset($previousPlugins[$pluginId])) {
                $newPlugins[] = $pluginId;
            }
        }
        
        return $newPlugins;
    }

    /**
     * Get entity classes from entity.repository.yml files
     */
    public function getEntityClasses(): array
    {
        $entityClasses = [];
        
        foreach ($this->plugins as $pluginId => $plugin) {
            // Only load from enabled plugins
            if (!$plugin['enabled']) {
                continue;
            }
            
            $entityFile = $plugin['path'] . '/entity.repository.yml';
            
            if (file_exists($entityFile)) {
                try {
                    $entities = Yaml::parseFile($entityFile);
                    
                    if (is_array($entities)) {
                        foreach ($entities as $entityName => $entityConfig) {
                            // Validate entity configuration
                            if (isset($entityConfig['class']) && class_exists($entityConfig['class'])) {
                                $entityClasses[$entityName] = [
                                    'class' => $entityConfig['class'],
                                    'plugin' => $pluginId,
                                    'config' => $entityConfig
                                ];
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->logger?->error("Failed to load entity config for {$pluginId}: " . $e->getMessage());
                }
            }
        }
        
        return $entityClasses;
    }
    
    /**
     * Get specific entity class by name
     */
    public function getEntityClass(string $entityName): ?array
    {
        $entityClasses = $this->getEntityClasses();
        return $entityClasses[$entityName] ?? null;
    }
    
    /**
     * Check if entity class exists
     */
    public function hasEntityClass(string $entityName): bool
    {
        $entityClasses = $this->getEntityClasses();
        return isset($entityClasses[$entityName]);
    }

    public function getPluginRoot()
    {
        return $this->pluginRoot;
    }

    public function getPluginMysqlSchemas(): array
    {
        return $this->pluginMysqlSchemas;
    }

    public function getPluginTemplateSources(): array
    {
        return $this->pluginTemplatesSources;
    }

    public function getPluginYamlContent(string $pluginId, string $fileName): array
    {
        $plugin = array_find($this->plugins, fn($plugin) => $plugin['id'] === $pluginId);

        if (!$plugin) {
            return [];
        }

        $configFile = $plugin['path'] . '/' . $fileName . ".yml";

        if (!file_exists($configFile)) {
            return [];
        }

        return Yaml::parseFile($configFile) ?? [];
    }

    public function getPluginsYamlContent(string $filename): array
    {
        $plugins = $this->getEnabledPlugins();
        $content = [];

        foreach ($plugins as $plugin) {
            $content[$plugin['id']] = $this->getPluginYamlContent($plugin['id'], $filename);
        }

        return $content;
    }

    public function requireModulesFile(): void
    {
        $list = [];
        foreach ($this->plugins as $pluginId => $plugin) {
            if (!empty($plugin['path'])) {
                $fullPath = $plugin['path'] . '/' . $plugin['id'] . '.module';
                if (file_exists($fullPath)) {
                    $list[] = $fullPath;
                }
            }
        }

        foreach ($list as $path) {
            require_once $path;
        }
    }
}

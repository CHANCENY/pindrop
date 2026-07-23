<?php

declare(strict_types=1);

namespace Simp\Pindrop\Plugin;

use DI\Container;
use Exception;
use InvalidArgumentException;
use Reflection;
use Simp\Pindrop\Events\SystemEvents\Events;
use Simp\Pindrop\Message\Message;
use Simp\Pindrop\Routing\AttributeRoute;
use Simp\Pindrop\Services\EnvServiceProvider;
use Simp\Pindrop\Templating\LibraryAssets;
use Symfony\Component\Yaml\Yaml;

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
    private array $pluginPermissions = [];
    private ?\Simp\Pindrop\Database\PluginTableRegistry $tableRegistry = null;
    private array $pluginRoles = [];
    private ?Container $container = null;

    // ── YAML manifest cache ──────────────────────────────────────────────────
    // On first production request every plugin YAML file is parsed and the
    // resulting array is written to var/cache/plugins/manifest.php as a plain
    // PHP return statement.  Subsequent requests just require() that one file
    // (OPcache holds it in memory) — no YAML parsing, no disk reads per plugin.
    //
    // The cache is invalidated automatically whenever savePluginState() runs
    // (enable / disable / install / uninstall actions) and when
    // rediscoverPlugins() runs.  In development it is never written.
    private ?string $manifestCacheFile = null;
    private bool $cacheEnabled = false;

    public function __construct(
        EnvServiceProvider $envProvider,
        ?string $pluginRoot = null,
        ?string $configRoot = null
    ) {
        $this->envProvider = $envProvider;
        $this->pluginRoot = $pluginRoot ?? $envProvider->get('PLUGIN_ROOT', __DIR__ . '/../../modules');
        $this->configRoot = $configRoot ?? $envProvider->get('CONFIG', __DIR__ . '/../../config/sync');
        $this->tableRegistry = new \Simp\Pindrop\Database\PluginTableRegistry();

        // Enable manifest cache in production only.
        // In development YAML files are parsed fresh every request so changes
        // are visible immediately without needing to clear any cache.
        $env = getenv('APP_ENV') ?: 'development';
        if ($env === 'production') {
            $cacheDir = rtrim(getenv('CACHE_DIR') ?: (dirname(__DIR__, 2) . '/var/cache'), '/');
            $pluginCacheDir = $cacheDir . '/plugins';
            if (!is_dir($pluginCacheDir)) {
                mkdir($pluginCacheDir, 0755, true);
            }
            $this->manifestCacheFile = $pluginCacheDir . '/manifest.php';
            $this->cacheEnabled = true;
        }

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

            // registerPluginServices() is only called from plugin.services.register
            // which runs in bootstrap.inc AFTER buildContainer() returns, so the
            // container is fully built here — safe to resolve @arguments directly.
            $className = $serviceConfig['class'];
            $resolvedArguments = [];
            foreach ($serviceConfig['arguments'] ?? [] as $argument) {
                if (is_string($argument) && str_starts_with($argument, '@')) {
                    $resolvedArguments[] = $this->container->get(substr($argument, 1));
                } else {
                    $resolvedArguments[] = $argument;
                }
            }
            $this->container->set($serviceName, new $className(...$resolvedArguments));
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
            'database',
            'session'
        ];

        // Check if it's a core service
        if (in_array($dependencyName, $coreServices)) {
            return true;
        }

        $flag = false;
        // Check if it's a registered plugin service
        foreach ($this->pluginServices as $pluginService) {
            foreach ($pluginService as $name => $service) {
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

        if ($this->cacheEnabled && $this->loadFromManifestCache()) {
            // Served entirely from the PHP manifest cache — no YAML parsing.
            return;
        }

        // Cache miss or development mode: parse all YAMLs the normal way.
        $this->discoverPlugins();
        $this->loadInstalledPlugins();

        // Write the manifest cache for next request.
        if ($this->cacheEnabled) {
            $this->writeManifestCache();
        }
    }

    // ── Manifest cache helpers ───────────────────────────────────────────────

    /**
     * Attempt to load the full plugin manifest from the compiled PHP cache.
     * Returns true on success (no YAML parsing needed), false on cache miss.
     */
    private function loadFromManifestCache(): bool
    {
        if (!file_exists($this->manifestCacheFile)) {
            return false;
        }

        try {
            $manifest = require $this->manifestCacheFile;
        } catch (\Throwable $e) {
            // Corrupted cache file — delete and rebuild
            @unlink($this->manifestCacheFile);
            return false;
        }

        if (!is_array($manifest) || empty($manifest['_version'] ?? null)) {
            @unlink($this->manifestCacheFile);
            return false;
        }

        $this->plugins = $manifest['plugins'] ?? [];
        $this->enabledPlugins = $manifest['enabledPlugins'] ?? [];
        $this->pluginConfigs = $manifest['pluginConfigs'] ?? [];
        $this->pluginServices = $manifest['pluginServices'] ?? [];
        $this->pluginRoutes = $manifest['pluginRoutes'] ?? [];
        $this->pluginMiddleware = $manifest['pluginMiddleware'] ?? [];
        $this->pluginMysqlSchemas = $manifest['pluginMysqlSchemas'] ?? [];
        $this->pluginMenus = $manifest['pluginMenus'] ?? [];
        $this->pluginTemplatesSources = $manifest['pluginTemplatesSources'] ?? [];
        $this->pluginPermissions = $manifest['pluginPermissions'] ?? [];
        $this->pluginRoles = $manifest['pluginRoles'] ?? [];

        return true;
    }

    /**
     * Write all current plugin data to the PHP manifest cache.
     * Uses var_export() + return so OPcache can cache the result in memory.
     */
    private function writeManifestCache(): void
    {
        $manifest = [
            '_version' => '1',
            '_written_at' => time(),
            'plugins' => $this->plugins,
            'enabledPlugins' => $this->enabledPlugins,
            'pluginConfigs' => $this->pluginConfigs,
            'pluginServices' => $this->pluginServices,
            'pluginRoutes' => $this->pluginRoutes,
            'pluginMiddleware' => $this->pluginMiddleware,
            'pluginMysqlSchemas' => $this->pluginMysqlSchemas,
            'pluginMenus' => $this->pluginMenus,
            'pluginTemplatesSources' => $this->pluginTemplatesSources,
            'pluginPermissions' => $this->pluginPermissions,
        ];

        $php = '<?php return ' . var_export($manifest, true) . ';' . PHP_EOL;

        // Write atomically: write to a temp file then rename so a concurrent
        // request never reads a half-written cache file.
        $tmp = $this->manifestCacheFile . '.tmp.' . getmypid();
        file_put_contents($tmp, $php, LOCK_EX);
        rename($tmp, $this->manifestCacheFile);
    }

    /**
     * Invalidate the manifest cache.
     * Called automatically after any state change (enable/disable/install).
     */
    public function clearManifestCache(): void
    {
        if ($this->manifestCacheFile && file_exists($this->manifestCacheFile)) {
            @unlink($this->manifestCacheFile);
        }
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
                mkdir($directory, recursive: true);
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



    function topologicalSortServices(array $modules): array
    {
        // Step 1: Build a flat map of ALL service IDs => config (read-only, for lookup)
        $allServices = [];
        foreach ($modules as $moduleServices) {
            foreach ($moduleServices as $serviceId => $serviceConfig) {
                $allServices[$serviceId] = $serviceConfig;
            }
        }

        // Step 2: Build dependency map for every service
        $dependencies = [];
        foreach ($allServices as $serviceId => $config) {
            $dependencies[$serviceId] = [];
            foreach ($config['arguments'] ?? [] as $arg) {
                if (is_string($arg) && str_starts_with($arg, '@')) {
                    $dep = ltrim($arg, '@');
                    if (isset($allServices[$dep])) {
                        $dependencies[$serviceId][] = $dep;
                    }
                }
            }
        }

        // Step 3: Topological sort (Kahn's algorithm) — produces ordered service IDs
        $inDegree = array_fill_keys(array_keys($allServices), 0);
        foreach ($dependencies as $serviceId => $deps) {
            foreach ($deps as $dep) {
                $inDegree[$serviceId]++;
            }
        }

        $queue = array_keys(array_filter($inDegree, fn($d) => $d === 0));
        $sortedServiceIds = [];

        while (!empty($queue)) {
            $current = array_shift($queue);
            $sortedServiceIds[] = $current;

            foreach ($dependencies as $serviceId => $deps) {
                if (in_array($current, $deps)) {
                    $inDegree[$serviceId]--;
                    if ($inDegree[$serviceId] === 0) {
                        $queue[] = $serviceId;
                    }
                }
            }
        }

        // Detect circular dependencies
        if (count($sortedServiceIds) !== count($allServices)) {
            $unresolved = array_diff(array_keys($allServices), $sortedServiceIds);
            throw new \RuntimeException(
                'Circular dependency detected: ' . implode(', ', $unresolved)
            );
        }

        // Step 4: Rebuild modules preserving structure, but reorder services within
        //         each module AND reorder the modules themselves by first-service appearance

        // Assign each module a "priority" = position of its earliest service in sortedServiceIds
        $moduleOrder = [];
        foreach ($modules as $moduleName => $moduleServices) {
            $earliest = PHP_INT_MAX;
            foreach (array_keys($moduleServices) as $serviceId) {
                $pos = array_search($serviceId, $sortedServiceIds);
                if ($pos !== false && $pos < $earliest) {
                    $earliest = $pos;
                }
            }
            $moduleOrder[$moduleName] = $earliest;
        }
        asort($moduleOrder);

        // Step 5: Rebuild the result — modules in resolved order, services within
        //         each module also in resolved order
        $result = [];
        foreach (array_keys($moduleOrder) as $moduleName) {
            $moduleServices = $modules[$moduleName];

            // Sort services within this module by their position in sortedServiceIds
            $serviceOrder = [];
            foreach (array_keys($moduleServices) as $serviceId) {
                $serviceOrder[$serviceId] = array_search($serviceId, $sortedServiceIds);
            }
            asort($serviceOrder);

            $result[$moduleName] = [];
            foreach (array_keys($serviceOrder) as $serviceId) {
                $result[$moduleName][$serviceId] = $moduleServices[$serviceId];
            }
        }

        return $result;
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

            $this->pluginServices = $this->topologicalSortServices($this->pluginServices);

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

            //TODO add route from attributes
            $attribute_routes_directory = $plugin['path'] . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Routes';
            $attrbuteRoutes = [];
            if (is_dir($attribute_routes_directory)) {
                $files = array_diff(scandir($attribute_routes_directory) ?? [], ['.', '..']);
                foreach ($files as $file) {
                    $full_path = $attribute_routes_directory . DIRECTORY_SEPARATOR . $file;
                    $class_details = $this->getClassInfo($full_path);
                    $qualified_class = $class_details['fqcn'] ?? null;
                    if (!is_null($qualified_class) && !class_exists($qualified_class)) {
                        throw new Exception(json_encode($class_details) . " attribute route class not found");
                    }

                    $reflection = new \ReflectionClass($qualified_class);
                    foreach ($reflection->getMethods() as $method) {
                        $attributes = $method->getAttributes(AttributeRoute::class);
                        foreach ($attributes as $attribute) {
                            $arguments = $attribute->getArguments();
                            $route = null;
                            if (isset($arguments['name']) && isset($arguments['controller'])) {
                                $route = $attribute->newInstance();
                            } else {
                                $arguments['name'] = $class_details['class'] . "." . $method->getName();
                                $arguments['controller'] = $class_details['fqcn'] . "::" . $method->getName();
                                $route = new AttributeRoute(...$arguments);
                            }

                            if (!is_null($route) && $route->isValidRoute()) {
                                $attrbuteRoutes[$route->getName()] = $route->routeDefinition();
                            }
                        }


                    }
                }
            }

            $this->pluginRoutes[$pluginId] = array_merge($this->pluginRoutes[$pluginId] ?? [], $attrbuteRoutes);

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

            // Load plugin role definitions if user.roles.yml exists
            $rolesFile = $plugin['path'] . '/user.roles.yml';
            if (file_exists($rolesFile)) {
                $roles = Yaml::parseFile($rolesFile);
                if (is_array($roles)) {
                    foreach ($roles as $roleKey => $roleDef) {
                        // Core roles cannot be redefined by plugins
                        $coreRoles = ['super_admin', 'admin', 'moderator', 'user', 'anonymous'];
                        if (in_array($roleKey, $coreRoles, true)) {
                            error_log("[Pindrop] Plugin '$pluginId' attempted to redefine core role '$roleKey' — skipped.");
                            continue;
                        }
                        if (!isset($this->pluginRoles[$roleKey])) {
                            $this->pluginRoles[$roleKey] = array_merge(
                                $roleDef,
                                ['defined_by' => $pluginId]
                            );
                        }
                    }
                }
            }

            // Load plugin permissions if user.permissions.yml exists
            $permissionsFile = $plugin['path'] . '/user.permissions.yml';
            if (file_exists($permissionsFile)) {
                $permissions = Yaml::parseFile($permissionsFile);
                if (is_array($permissions)) {
                    // Merge into global permissions — plugin permissions are additive.
                    // A plugin cannot redefine or remove a core permission key;
                    // it can only add new keys to existing roles or define new roles.
                    foreach ($permissions as $role => $rolePerms) {
                        if (!isset($this->pluginPermissions[$role])) {
                            $this->pluginPermissions[$role] = [];
                        }
                        foreach ($rolePerms as $permKey => $permDef) {
                            // Core permissions take precedence — skip if already defined
                            if (!isset($this->pluginPermissions[$role][$permKey])) {
                                $this->pluginPermissions[$role][$permKey] = $permDef;
                            }
                        }
                    }
                }
            }

            // Register plugin tables with PluginTableRegistry
            if ($this->tableRegistry !== null) {
                try {
                    $this->tableRegistry->registerPluginTables($pluginId, $plugin['path']);
                    $this->tableRegistry->registerDbPermissions($pluginId, $plugin['path']);
                } catch (\RuntimeException $e) {
                    // Table conflict — log and continue so other plugins still load
                    error_log("[Pindrop] Table registry error for plugin '$pluginId': " . $e->getMessage());
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

        } catch (Exception $e) {
            throw new \RuntimeException("Failed to load plugin config for {$pluginId}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the namespace, class name and fully-qualified class name (FQCN)
     * from a PHP file.
     *
     * @param string $file
     * @return array{
     *     namespace: ?string,
     *     class: ?string,
     *     fqcn: ?string
     * }
     */
    function getClassInfo(string $file): array
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException("File not found: {$file}");
        }

        $tokens = token_get_all(file_get_contents($file));

        $namespace = '';
        $class = '';

        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            if (!is_array($tokens[$i])) {
                continue;
            }

            // Namespace
            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = '';

                for ($i++; $i < $count; $i++) {
                    if (!is_array($tokens[$i])) {
                        if ($tokens[$i] === ';' || $tokens[$i] === '{') {
                            break;
                        }
                        continue;
                    }

                    if (in_array($tokens[$i][0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $namespace .= $tokens[$i][1];
                    }
                }
            }

            // Class
            if ($tokens[$i][0] === T_CLASS) {
                for ($i++; $i < $count; $i++) {
                    if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                        $class = $tokens[$i][1];
                        break 2;
                    }
                }
            }
        }

        return [
            'namespace' => $namespace ?: null,
            'class' => $class ?: null,
            'fqcn' => $class ? ($namespace ? "{$namespace}\\{$class}" : $class) : null,
        ];
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
                    } else {
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

            \appEvents()->invokeEvents(Events::PLUGIN_INSTALLED, ['plugin_id' => $pluginId, 'container' => $this->container]);

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

            \appEvents()->invokeEvents(Events::PLUGIN_UNINSTALLED, ['plugin_id' => $pluginId, 'container' => $this->container]);

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
                    } else {
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

        // State changed — invalidate the manifest cache so the next request
        // rebuilds it with the updated plugin list.
        $this->clearManifestCache();
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

        // New plugins found — invalidate manifest cache so next request rebuilds.
        if (!empty($newPlugins)) {
            $this->clearManifestCache();
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

    /**
     * Get raw permissions array as loaded from all user.permissions.yml files.
     * Structure: ['role' => ['permission_key' => ['title' => ..., 'description' => ...]]]
     */
    public function setTableRegistry(\Simp\Pindrop\Database\PluginTableRegistry $registry): void
    {
        $this->tableRegistry = $registry;
    }

    public function getPluginRoles(): array
    {
        return $this->pluginRoles;
    }

    /**
     * Get all roles — core + plugin-defined.
     */
    public function getAllRoles(): array
    {
        $coreRoles = [
            'super_admin' => ['label' => 'Super Admin', 'description' => 'Full system access.', 'parent' => null],
            'admin' => ['label' => 'Admin', 'description' => 'Administrative access.', 'parent' => null],
            'moderator' => ['label' => 'Moderator', 'description' => 'Content moderation access.', 'parent' => 'user'],
            'user' => ['label' => 'User', 'description' => 'Authenticated user.', 'parent' => null],
            'anonymous' => ['label' => 'Anonymous', 'description' => 'Unauthenticated visitor.', 'parent' => null],
        ];
        return array_merge($coreRoles, $this->pluginRoles);
    }

    public function getTableRegistry(): ?\Simp\Pindrop\Database\PluginTableRegistry
    {
        return $this->tableRegistry;
    }

    public function getPluginPermissions(): array
    {
        return $this->pluginPermissions;
    }

    /**
     * Get merged permissions for a specific role, combining core permissions
     * (from pindrop/user.permissions.yml) with all plugin-defined permissions.
     *
     * Returns an array of permission keys with their title/description metadata.
     * Example: ['can_access_admin_panel' => ['title' => ..., 'description' => ...]]
     */
    public function getRolePermissions(string $role): array
    {
        // Load core permissions once and cache them in a static variable.
        static $corePermissions = null;
        if ($corePermissions === null) {
            $coreFile = dirname(__DIR__) . '/user.permissions.yml';
            $corePermissions = file_exists($coreFile)
                ? (Yaml::parseFile($coreFile) ?? [])
                : [];
        }

        $merged = $corePermissions[$role] ?? [];

        // Merge plugin permissions — core takes precedence (already enforced
        // during loading, but we apply it again here for safety).
        foreach ($this->pluginPermissions[$role] ?? [] as $key => $def) {
            if (!isset($merged[$key])) {
                $merged[$key] = $def;
            }
        }

        return $merged;
    }

    /**
     * Get ALL permissions across ALL roles, merged from core + all plugins.
     * Useful for the admin permissions management UI.
     *
     * Returns: ['role' => ['permission_key' => ['title' => ..., 'description' => ...]]]
     */
    public function getAllPermissions(): array
    {
        static $corePermissions = null;
        if ($corePermissions === null) {
            $coreFile = dirname(__DIR__) . '/user.permissions.yml';
            $corePermissions = file_exists($coreFile)
                ? (Yaml::parseFile($coreFile) ?? [])
                : [];
        }

        // Start with core, then layer in plugin additions
        $all = $corePermissions;
        foreach ($this->pluginPermissions as $role => $perms) {
            if (!isset($all[$role])) {
                $all[$role] = [];
            }
            foreach ($perms as $key => $def) {
                if (!isset($all[$role][$key])) {
                    $all[$role][$key] = $def;
                }
            }
        }

        return $all;
    }

    /**
     * Check whether a given permission key exists for a given role,
     * across core + all loaded plugins.
     */
    public function roleHasPermission(string $role, string $permissionKey): bool
    {
        return isset($this->getRolePermissions($role)[$permissionKey]);
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

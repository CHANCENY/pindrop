<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Session scaling — database-backed sessions
// ---------------------------------------------------------------------------
// We must start the session BEFORE the DI container is built, so we do a
// minimal .env parse here (just the DB_* keys) and wire up our custom
// SessionHandlerInterface.  Everything downstream ($_SESSION, session_id(),
// SessionStorage) keeps working exactly as before — only the storage backend
// changes from the local filesystem to MySQL.
// ---------------------------------------------------------------------------

// Minimal .env parse — only what we need before the autoloader is available.
// The full parse (with variable interpolation) runs again inside
// EnvServiceProvider after the autoloader loads; there is no conflict.
if (file_exists(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (str_starts_with(trim($_line), '#') || !str_contains($_line, '=')) {
            continue;
        }
        [$_k, $_v] = explode('=', $_line, 2);
        $_k = trim($_k);
        $_v = trim($_v, " \t\n\r\0\x0B\"'");
        if (getenv($_k) === false) {   // don't overwrite real env vars
            putenv("{$_k}={$_v}");
            $_ENV[$_k] = $_v;
        }
    }
    unset($_line, $_k, $_v);
}

require_once 'vendor/autoload.php';

// Now the autoloader is ready — register the DB session handler.
//
// IMPORTANT: $_sessionHandler must NOT be unset.
// PHP holds a reference internally, but prematurely destroying the variable
// can trigger GC in some PHP builds, leaving a dead object by shutdown.
//
// session_set_save_handler($handler, true) auto-registers session_write_close
// but we pass FALSE and use a closure instead — this keeps the handler object
// alive in the closure scope so write() still has a valid $this at shutdown.
try {
    $_sessionHandler = \Simp\Pindrop\Session\DatabaseSessionHandler::createFromEnv();
    session_set_save_handler($_sessionHandler, false);
    register_shutdown_function(static function () use ($_sessionHandler): void {
        // @ suppresses the E_WARNING that Whoops converts to ErrorException.
        // The real error is already logged inside DatabaseSessionHandler::write().
        @session_write_close();
    });
    
} catch (\Throwable $_sessionException) {
    error_log('[Pindrop] DatabaseSessionHandler unavailable, falling back to file sessions: '
        . $_sessionException->getMessage());
    unset($_sessionException);
    
}

session_start();

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Simp\Pindrop\Entity\User\CurrentUser;
use Simp\Pindrop\Events\EventsManager;
use Simp\Pindrop\Routing\RouteProvider;
use Simp\Pindrop\Services\ServiceProvider;
use Simp\Pindrop\Routing\RouteManager;
use Simp\Pindrop\Routing\PluginRoutes;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Run;
use Simp\Pindrop\FileSystem\FileSystemService;

/**
 * Bootstrap Application
 * 
 * Initializes the application, starts the DI container, loads services,
 * and registers routes for request handling.
 */

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
            $_ENV[trim($key)] = trim($value);
            $_SERVER[trim($key)] = trim($value);
        }
    }
}

// Set error reporting based on environment
$environment = getenv('APP_ENV') ?: 'development';
$debug = getenv('DEBUG') ?: 'true';
$debug = (bool)$debug;

if ($environment === 'production') {
    ini_set('display_errors', '0');
}
else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Set timezone and charset
date_default_timezone_set(getenv('TIMEZONE') ?: 'UTC');
ini_set('default_charset', 'UTF-8');

// Initialize service provider
$serviceProvider = new ServiceProvider();

// Start DI container and load all services
$container = $serviceProvider->buildContainer();

// Load plugin services into container
$pluginManager = $container->get('plugin.manager');
$container->get('plugin.services.register');

// ── Sync PluginTableRegistry from PluginManager into DatabaseService ─────────
// PluginManager creates and populates its own PluginTableRegistry during
// loadInstalledPlugins() (which already ran above inside buildContainer).
// We retrieve that SAME instance and inject it into DatabaseService so
// DatabasePermissionGuard uses the fully-populated registry.
try {
    $pluginManager = $container->get('plugin.manager');
    $dbService     = $container->get('database');
    $registry      = $pluginManager->getTableRegistry();

    if ($registry !== null) {
        // Inject the populated registry into DatabaseService
        $dbService->setTableRegistry($registry);

        // Also update the guard inside DatabaseService with the same registry
        $guard = new \Simp\Pindrop\Database\DatabasePermissionGuard(
            new \Simp\Pindrop\Database\CurrentUserResolver(),
            $registry
        );
        $dbService->setPermissionGuard($guard);
    }
} catch (\Throwable $e) {
    error_log("[Pindrop] Failed to sync PluginTableRegistry: " . $e->getMessage());
}

// ── Database permission guard — register current user resolver ──────────────
// Late-binding closure so the DB layer never directly imports User/Session.
\Simp\Pindrop\Database\CurrentUserResolver::register(function () use ($container) {
    try {
       
        /**
         * @var ?CurrentUser $currentUser
         */
        $currentUser = $container->get('current_user');
        return $currentUser && $currentUser->isLoggedIn() ? $currentUser->getUser() : null;
    } catch (\Throwable) {
        return null;
    }
});

$eventsManager = $container->get(\Simp\Pindrop\Events\EventsManager::class);

$middleware_list =  $container->get('plugin.middleware')->getMiddlewareClasses();
// Load plugin routes and register with RouteManager
$routeProvider = RouteManager::initialize($middleware_list);
$pluginRoutes = new PluginRoutes($routeProvider, $container);
$pluginRoutes->register($pluginManager->getPluginRoutes());

// Initialize Whoops error handler for development
if ($environment !== 'production' && $debug === true) {
    $whoops = new Run();

    // Add pretty page handler for browser requests
    $prettyPageHandler = new PrettyPageHandler();
    $prettyPageHandler->setPageTitle('Pindrop - Something went wrong!');
    $prettyPageHandler->addDataTable('Pindrop Environment', [
        'Environment' => $environment,
        'Debug Mode' => $debug,
        'Timezone' => date_default_timezone_get(),
        'PHP Version' => PHP_VERSION,
        'App Directory' => __DIR__,
    ]);
    
    // Add JSON handler for AJAX requests
    $jsonHandler = new JsonResponseHandler();
    $jsonHandler->setJsonApi(true);
    
    $whoops->pushHandler($prettyPageHandler);

    if (!empty($_ENV['APP_REST_API'])) {
        $whoops->pushHandler($jsonHandler);
    }

    $whoops->register();
    
    // Store whoops instance in container for later use
    if (!function_exists('getWhoops')) {
        function getWhoops(): Run {
            global $whoops;
            return $whoops;
        }
    }
}

// Create from environment
$fileSystemService = FileSystemService::createFromEnv(getAppContainer()->get('logger'));

// Or create with custom config
$fileSystemService = new FileSystemService([
    'public_stream' => $_ENV['PUBLIC_STREAM'],
    'public_stream_dir' => $_ENV['PUBLIC_STREAM_DIR']
], getAppContainer()->get('logger'));

/**
 * Get the application container
 * 
 * @return Container
 */
function getAppContainer(): Container
{
    global $container;
    return $container;
}

/**
 * Load all events registered.
 */

function appEvents(): EventsManager
{
    global $eventsManager;
    return $eventsManager;
}

/**
 * Get the route provider
 * 
 * @return RouteProvider
 */
function getRouteProvider(): RouteProvider
{
    global $routeProvider;
    return $routeProvider;
}

/**
 * Get a service from the container
 *
 * @param string $service
 * @return mixed
 * @throws DependencyException
 * @throws NotFoundException
 */
function app(string $service)
{
    return getAppContainer()->get($service);
}

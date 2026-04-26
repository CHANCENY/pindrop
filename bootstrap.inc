<?php
declare(strict_types=1);

@session_start();

require_once 'vendor/autoload.php';

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Simp\Pindrop\Events\EventsManager;
use Simp\Pindrop\Routing\RouteProvider;
use Simp\Pindrop\Services\ServiceProvider;
use Simp\Pindrop\Routing\RouteManager;
use Simp\Pindrop\Routing\PluginRoutes;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Run;

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

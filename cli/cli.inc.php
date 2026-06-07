<?php

use Simp\Pindrop\Events\EventsManager;
use Simp\Pindrop\Services\ServiceProvider;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . "/colors.php";
require_once __DIR__ . "/cli_printer.php";

$serviceProvider = new ServiceProvider();
$container = $serviceProvider->buildContainer();

$container->get('plugin.services.register');

$eventsManager = $container->get(\Simp\Pindrop\Events\EventsManager::class);

function getAppContainer(): \DI\Container
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

// Allow CLI only
if (php_sapi_name() !== 'cli') {
    exit('This script must be run from the command line.');
}


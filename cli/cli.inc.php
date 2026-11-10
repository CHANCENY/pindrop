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


function param(string $name)
{
    global $params;

    if (isset($params[2])) {
        $array = array_slice($params, 2);
        $paramsAssoc = [];
        foreach ($array as $param) {
            $parts = explode('=', $param, 2);
            if (count($parts) === 2) {
                $paramsAssoc[$parts[0]] = $parts[1];
            }
        }
        
        if (isset($paramsAssoc[$name])) {
            return $paramsAssoc[$name];
        }
    }

    // if params is not set like eg url=https://example.com/file.txt, then try to look by
    // finding value that comes after eg -$name but not after another -name
    foreach ($params as $i => $param) {
        if ($param === "-$name" && isset($params[$i + 1]) && strpos($params[$i + 1], '-') !== 0) {
            return $params[$i + 1];
        }
    }
    
    // if params is not set like eg url=https://example.com/file.txt, then try to look by
    // finding value that comes after eg --$name but not after another --name
    foreach ($params as $i => $param) {
        if ($param === "--$name" && isset($params[$i + 1]) && strpos($params[$i + 1], '--') !== 0) {
            return $params[$i + 1];
        }
    }
    return null;
}

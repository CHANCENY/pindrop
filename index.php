<?php

declare(strict_types=1);

/**
 * Application Entry Point
 * 
 * Handles all incoming HTTP requests and routes them through RouteManager.
 * Since all requests are directed to index.php by .htaccess.
 */

// Bootstrap the application
require_once __DIR__ . '/bootstrap.inc';

// Handle the current request - RouteProvider will automatically dispatch and send response
getRouteProvider()->dispatch();
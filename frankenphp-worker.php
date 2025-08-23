<?php

/**
 * FrankenPHP Worker Script for Laravel
 * 
 * This script is used by FrankenPHP worker mode to keep the application
 * in memory for better performance in production.
 */

// Autoload
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';

// Handle requests in a loop (FrankenPHP worker mode)
$handler = function () use ($app) {
    // Create kernel
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    
    // Handle the request
    $request = Illuminate\Http\Request::capture();
    $response = $kernel->handle($request);
    
    // Send response
    $response->send();
    
    // Terminate
    $kernel->terminate($request, $response);
    
    // Clear resolved instances and reset for next request
    $app->forgetScopedInstances();
    
    // Clear any session data that might have been set
    if ($app->bound('session')) {
        $app['session']->flush();
    }
    
    // Clear any view data
    if ($app->bound('view')) {
        $app['view']->flushState();
    }
};

// Register the handler with FrankenPHP
\frankenphp_handle_request($handler);

// Keep the worker running
while (true) {
    \frankenphp_finish_request();
}
<?php

declare(strict_types=1);

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Enhanced autoloader with debugging
spl_autoload_register(function ($class) {
    // Only handle Filament namespace
    if (strpos($class, 'Filament\\') !== 0) {
        return false;
    }
    
    // Remove 'Filament\' prefix and convert to path
    $path = substr($class, strlen('Filament\\'));
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', $path) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    // Log for debugging
    error_log("Autoloader: Could not find '{$class}' at '{$file}'");
    return false;
});

try {
    // Test if basic classes can be loaded
    if (!class_exists('Filament\Core\Application')) {
        throw new Exception('Application class could not be loaded');
    }
    
    if (!class_exists('Filament\Core\Router')) {
        throw new Exception('Router class could not be loaded');
    }
    
    // Load configuration with error handling
    $configFile = __DIR__ . '/../config/config.php';
    if (!file_exists($configFile)) {
        throw new Exception('Configuration file not found');
    }
    
    $config = require_once $configFile;
    if (!is_array($config)) {
        throw new Exception('Invalid configuration format');
    }
    
    // Initialize application
    $app = new Filament\Core\Application($config);
    
    // Initialize router with security middleware
    $router = new Filament\Core\Router($app);
    
    // Add all application routes
    $router->addRoutes();
    
    // Dispatch the request
    $router->dispatch();
    
} catch (Throwable $e) {
    // Error page
    http_response_code(500);
    echo "<!DOCTYPE html>\n";
    echo "<html>\n<head>\n<title>Application Error</title>\n</head>\n<body>\n";
    echo "<h1>⚠️ Application Error</h1>\n";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p>File: " . htmlspecialchars($e->getFile()) . "</p>\n";
    echo "<p>Line: " . $e->getLine() . "</p>\n";
    echo "</body>\n</html>\n";
    
    // Log the error
    error_log("Application Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}
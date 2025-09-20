<?php

declare(strict_types=1);

namespace Filament\Middleware;

/**
 * API Middleware
 * 
 * Handles API-specific functionality like JSON responses.
 */
class ApiMiddleware
{
    /**
     * Handle API request setup
     */
    public function handle(array $request = []): void
    {
        // Set JSON content type for API responses
        header('Content-Type: application/json');
        
        // Add CORS headers if needed
        $this->addCorsHeaders();
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Add CORS headers for API access
     */
    private function addCorsHeaders(): void
    {
        // Allow specific origins in production, or all for development
        $allowedOrigins = [
            'https://filament.neuhauser.cloud',
            'http://localhost:8000'
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: {$origin}");
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
        header('Access-Control-Allow-Credentials: true');
    }
}
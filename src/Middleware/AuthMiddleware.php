<?php

declare(strict_types=1);

namespace Filament\Middleware;

/**
 * Authentication Middleware
 * 
 * Handles user authentication and authorization.
 */
class AuthMiddleware
{
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'role' => null,
            'redirect' => '/login'
        ], $config);
    }
    
    /**
     * Handle authentication check
     */
    public function handle(array $request = []): void
    {
        // Check if user is authenticated
        if (!isset($_SESSION['user_id'])) {
            $this->handleUnauthenticated();
            return;
        }
        
        // Check role if specified
        if ($this->config['role'] !== null) {
            $userRole = $_SESSION['user_role'] ?? null;
            if ($userRole !== $this->config['role']) {
                $this->handleUnauthorized();
                return;
            }
        }
    }
    
    /**
     * Handle unauthenticated request
     */
    private function handleUnauthenticated(): void
    {
        if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
        } else {
            header('Location: ' . $this->config['redirect']);
        }
        exit;
    }
    
    /**
     * Handle unauthorized request
     */
    private function handleUnauthorized(): void
    {
        if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Insufficient permissions']);
        } else {
            http_response_code(403);
            echo '<h1>403 - Access Denied</h1><p>You do not have permission to access this resource.</p>';
        }
        exit;
    }
}
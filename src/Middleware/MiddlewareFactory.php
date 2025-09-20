<?php

declare(strict_types=1);

namespace Filament\Middleware;

use Filament\Security\SecurityManager;

/**
 * Middleware Factory
 * 
 * Creates and manages middleware instances for the application.
 */
class MiddlewareFactory
{
    private SecurityManager $security;
    
    public function __construct(SecurityManager $security)
    {
        $this->security = $security;
    }
    
    /**
     * Create middleware stack for different route types
     */
    public function createStack(string $type = 'default'): MiddlewareStack
    {
        $stack = new MiddlewareStack();
        
        switch ($type) {
            case 'admin':
                $stack->add(new SecurityMiddleware($this->security));
                $stack->add(new AuthMiddleware(['role' => 'admin']));
                break;
                
            case 'api':
                $stack->add(new SecurityMiddleware($this->security));
                $stack->add(new AuthMiddleware());
                $stack->add(new ApiMiddleware());
                break;
                
            case 'auth':
                $stack->add(new SecurityMiddleware($this->security, ['csrf_enabled' => false]));
                $stack->add(new ApiMiddleware());
                break;
                
            case 'default':
            default:
                $stack->add(new SecurityMiddleware($this->security));
                break;
        }
        
        return $stack;
    }
    
    /**
     * Create security middleware
     */
    public function createSecurityMiddleware(): SecurityMiddleware
    {
        return new SecurityMiddleware($this->security);
    }
    
    /**
     * Apply middleware stack for a given route
     */
    public function applyMiddleware(string $route): void
    {
        // Apply security middleware for all routes
        $securityMiddleware = $this->createSecurityMiddleware();
        $securityMiddleware->handle();
        
        // Apply specific middleware based on route patterns
        if (strpos($route, '/admin') === 0) {
            $this->applyAdminMiddleware();
        }
        
        if (strpos($route, '/api') === 0) {
            $this->applyApiMiddleware();
        }
    }
    
    /**
     * Apply admin-specific middleware
     */
    private function applyAdminMiddleware(): void
    {
        // Admin routes require authentication and admin role
        if (!isset($_SESSION['user_id'])) {
            $this->redirectToLogin();
        }
        
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            $this->accessDenied();
        }
    }
    
    /**
     * Apply API-specific middleware
     */
    private function applyApiMiddleware(): void
    {
        // API routes get JSON error responses
        header('Content-Type: application/json');
    }
    
    /**
     * Redirect to login page
     */
    private function redirectToLogin(): void
    {
        header('Location: /login');
        exit;
    }
    
    /**
     * Return access denied response
     */
    private function accessDenied(): void
    {
        http_response_code(403);
        if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
            echo json_encode(['error' => 'Access denied']);
        } else {
            echo '<h1>403 - Access Denied</h1><p>You do not have permission to access this resource.</p>';
        }
        exit;
    }
}
<?php

declare(strict_types=1);

namespace Filament\Middleware;

use Filament\Security\SecurityManager;
use Exception;

/**
 * Security Middleware
 * 
 * Applies security checks to all incoming requests including
 * CSRF validation, rate limiting, input validation, and threat detection.
 */
class SecurityMiddleware
{
    private SecurityManager $security;
    private array $config;
    
    public function __construct(SecurityManager $security, array $config = [])
    {
        $this->security = $security;
        $this->config = array_merge([
            'enable_threat_detection' => true,
            'log_violations' => true,
            'block_on_violation' => true
        ], $config);
    }
    
    /**
     * Handle incoming request
     */
    public function handle($request = null, $next = null): void
    {
        try {
            // Initialize security components
            $this->security->initialize();
            
            // Detect suspicious activity
            if ($this->config['enable_threat_detection']) {
                $threats = $this->security->detectSuspiciousActivity();
                if (!empty($threats) && $this->config['log_violations']) {
                    $this->security->logSecurityEvent('THREAT_DETECTED', ['threats' => $threats]);
                    
                    if ($this->config['block_on_violation']) {
                        $this->security->handleViolation('threat_detection', ['threats' => $threats]);
                        return;
                    }
                }
            }
            
            // Validate request security
            $violations = $this->security->validateRequest();
            if (!empty($violations)) {
                if ($this->config['log_violations']) {
                    $this->security->logSecurityEvent('REQUEST_VIOLATION', ['violations' => $violations]);
                }
                
                if ($this->config['block_on_violation']) {
                    $violationType = $this->determineViolationType($violations);
                    $this->security->handleViolation($violationType, ['violations' => $violations]);
                    return;
                }
            }
            
            // If next callback is provided, call it
            if ($next && is_callable($next)) {
                $next($request);
            }
            
        } catch (Exception $e) {
            $this->security->logSecurityEvent('MIDDLEWARE_ERROR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Send generic error response
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Security middleware error'
            ]);
            exit;
        }
    }
    
    /**
     * Determine violation type from violations array
     */
    private function determineViolationType(array $violations): string
    {
        foreach ($violations as $violation) {
            if (strpos($violation, 'CSRF') !== false) {
                return 'csrf';
            }
            if (strpos($violation, 'Rate limit') !== false) {
                return 'rate_limit';
            }
            if (strpos($violation, 'validation') !== false) {
                return 'validation';
            }
        }
        
        return 'unknown';
    }
}

/**
 * CSRF Middleware
 * 
 * Dedicated middleware for CSRF token validation
 */
class CsrfMiddleware
{
    private SecurityManager $security;
    
    public function __construct(SecurityManager $security)
    {
        $this->security = $security;
    }
    
    public function handle($request = null, $next = null): void
    {
        $csrf = $this->security->getCsrf();
        
        // Skip CSRF for GET requests
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($next && is_callable($next)) {
                $next($request);
            }
            return;
        }
        
        if (!$csrf->validateRequest()) {
            $this->security->handleViolation('csrf');
            return;
        }
        
        if ($next && is_callable($next)) {
            $next($request);
        }
    }
}

/**
 * Rate Limiting Middleware
 */
class RateLimitMiddleware
{
    private SecurityManager $security;
    private string $limitType;
    
    public function __construct(SecurityManager $security, string $limitType = 'default')
    {
        $this->security = $security;
        $this->limitType = $limitType;
    }
    
    public function handle($request = null, $next = null): void
    {
        $rateLimiter = $this->security->getRateLimiter();
        
        if (!$rateLimiter->middleware($this->limitType)) {
            $this->security->handleViolation('rate_limit');
            return;
        }
        
        if ($next && is_callable($next)) {
            $next($request);
        }
    }
}

/**
 * Input Validation Middleware
 */
class ValidationMiddleware
{
    private SecurityManager $security;
    private array $rules;
    
    public function __construct(SecurityManager $security, array $rules = [])
    {
        $this->security = $security;
        $this->rules = $rules;
    }
    
    public function handle($request = null, $next = null): void
    {
        if (empty($this->rules)) {
            if ($next && is_callable($next)) {
                $next($request);
            }
            return;
        }
        
        try {
            $inputData = array_merge($_GET, $_POST);
            $this->security->validateInput($inputData, $this->rules);
            
            if ($next && is_callable($next)) {
                $next($request);
            }
        } catch (Exception $e) {
            $this->security->handleViolation('validation', [
                'errors' => $e->getMessage()
            ]);
        }
    }
}

/**
 * Authentication Middleware
 */
class AuthMiddleware
{
    private SecurityManager $security;
    private bool $requireAdmin;
    
    public function __construct(SecurityManager $security, bool $requireAdmin = false)
    {
        $this->security = $security;
        $this->requireAdmin = $requireAdmin;
    }
    
    public function handle($request = null, $next = null): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Check if user is authenticated
        if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Authentication required',
                'redirect' => '/auth/login'
            ]);
            exit;
        }
        
        // Check admin requirement
        if ($this->requireAdmin && ($_SESSION['user']['role'] ?? '') !== 'admin') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Admin access required'
            ]);
            exit;
        }
        
        if ($next && is_callable($next)) {
            $next($request);
        }
    }
}

/**
 * Middleware Stack
 * 
 * Manages multiple middleware components in a stack
 */
class MiddlewareStack
{
    private array $middlewares = [];
    
    /**
     * Add middleware to stack
     */
    public function add(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }
    
    /**
     * Execute middleware stack
     */
    public function execute($request = null): void
    {
        $this->executeStack($request, 0);
    }
    
    /**
     * Execute middleware stack recursively
     */
    private function executeStack($request, int $index): void
    {
        if ($index >= count($this->middlewares)) {
            return;
        }
        
        $middleware = $this->middlewares[$index];
        $next = function($request) use ($index) {
            $this->executeStack($request, $index + 1);
        };
        
        if (is_object($middleware) && method_exists($middleware, 'handle')) {
            $middleware->handle($request, $next);
        } else {
            $middleware($request, $next);
        }
    }
}

/**
 * Middleware Factory
 * 
 * Creates and configures middleware instances
 */
class MiddlewareFactory
{
    private SecurityManager $security;
    
    public function __construct(SecurityManager $security)
    {
        $this->security = $security;
    }
    
    /**
     * Create security middleware stack for different contexts
     */
    public function createStack(string $context = 'default'): MiddlewareStack
    {
        $stack = new MiddlewareStack();
        
        switch ($context) {
            case 'api':
                $stack->add(new SecurityMiddleware($this->security));
                $stack->add(new RateLimitMiddleware($this->security, 'api'));
                $stack->add(new CsrfMiddleware($this->security));
                break;
                
            case 'auth':
                $stack->add(new SecurityMiddleware($this->security));
                $stack->add(new RateLimitMiddleware($this->security, 'login'));
                break;
                
            case 'admin':
                $stack->add(new SecurityMiddleware($this->security));
                $stack->add(new AuthMiddleware($this->security, true));
                $stack->add(new CsrfMiddleware($this->security));
                break;
                
            case 'user':
                $stack->add(new SecurityMiddleware($this->security));
                $stack->add(new AuthMiddleware($this->security, false));
                $stack->add(new CsrfMiddleware($this->security));
                break;
                
            default:
                $stack->add(new SecurityMiddleware($this->security));
        }
        
        return $stack;
    }
}
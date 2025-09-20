<?php

declare(strict_types=1);

namespace Filament\Security;

/**
 * Security Manager
 * 
 * Coordinates all security components and provides a unified interface
 * for implementing security measures across the application.
 */
class SecurityManager
{
    private CsrfProtection $csrf;
    private RateLimiter $rateLimiter;
    private ContentSecurityPolicy $csp;
    private InputValidator $validator;
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'csrf_enabled' => true,
            'rate_limiting_enabled' => true,
            'csp_enabled' => true,
            'environment' => 'production',
            'security_headers' => true
        ], $config);
        
        $this->csrf = new CsrfProtection();
        $this->rateLimiter = new RateLimiter();
        $this->csp = new ContentSecurityPolicy();
        $this->validator = new InputValidator();
        
        // Set CSP based on environment
        $this->csp->setEnvironmentPolicy($this->config['environment']);
    }
    
    /**
     * Initialize security middleware
     */
    public function initialize(): void
    {
        // Start session if not already started
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Set secure session settings
        $this->configureSecureSession();
        
        // Send security headers
        if ($this->config['security_headers']) {
            $this->sendSecurityHeaders();
        }
        
        // Send CSP headers
        if ($this->config['csp_enabled']) {
            $this->csp->sendHeader();
        }
    }
    
    /**
     * Validate request security
     */
    public function validateRequest(): array
    {
        $errors = [];
        
        // Check CSRF token
        if ($this->config['csrf_enabled'] && !$this->csrf->middleware()) {
            $errors[] = 'Invalid CSRF token';
        }
        
        // Check rate limiting
        if ($this->config['rate_limiting_enabled']) {
            $endpoint = $this->detectEndpoint();
            if (!$this->rateLimiter->middleware($endpoint)) {
                $errors[] = 'Rate limit exceeded';
            }
        }
        
        return $errors;
    }
    
    public function getCsrf(): CsrfProtection { return $this->csrf; }
    public function getRateLimiter(): RateLimiter { return $this->rateLimiter; }
    public function getCsp(): ContentSecurityPolicy { return $this->csp; }
    public function getValidator(): InputValidator { return $this->validator; }
    
    private function configureSecureSession(): void
    {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $this->isHttps() ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        
        if (isset($_SESSION['last_regeneration'])) {
            if (time() - $_SESSION['last_regeneration'] > 300) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        } else {
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    private function sendSecurityHeaders(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        if ($this->isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
            header('Expect-CT: max-age=86400, enforce');
        }
        
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header_remove('X-Powered-By');
        header_remove('Server');
    }
    
    private function detectEndpoint(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        
        $endpointMap = [
            '/auth/login' => 'login',
            '/auth/register' => 'register',
            '/api/' => 'api'
        ];
        
        foreach ($endpointMap as $pattern => $type) {
            if (strpos($path, $pattern) !== false) {
                return $type;
            }
        }
        
        return 'default';
    }
    
    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               $_SERVER['SERVER_PORT'] == 443 ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
    
    public function sanitizeOutput(string $data): string
    {
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Handle security violation
     */
    public function handleViolation(string $type, array $details = []): void
    {
        // Log the violation
        error_log("Security violation: {$type} - " . json_encode($details));
        
        // Send appropriate HTTP status
        switch ($type) {
            case 'csrf':
                http_response_code(403);
                break;
            case 'rate_limit':
                http_response_code(429);
                break;
            case 'validation':
                http_response_code(400);
                break;
            default:
                http_response_code(403);
        }
        
        // Return appropriate response
        if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Security violation detected',
                'type' => $type
            ]);
        } else {
            echo '<h1>Security Error</h1><p>Your request was blocked for security reasons.</p>';
        }
        exit;
    }
}
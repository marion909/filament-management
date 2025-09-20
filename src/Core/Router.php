<?php

declare(strict_types=1);

namespace Filament\Core;

use Filament\Security\SecurityManager;

/**
 * Simple Router Class with Security Integration
 */
class Router
{
    private Application $app;
    private array $routes = [];
    private SecurityManager $security;

    public function __construct(Application $app)
    {
        $this->app = $app;
        
        // Initialize security components
        $this->security = new SecurityManager([
            'environment' => $app->getEnvironment(),
            'csrf_enabled' => true,
            'rate_limiting_enabled' => true,
            'csp_enabled' => true
        ]);
        
        // Initialize security middleware
        $this->security->initialize();
    }
    
    /**
     * Get security manager instance
     */
    public function getSecurity(): SecurityManager
    {
        return $this->security;
    }

    public function addRoutes(): void
    {
        // Frontend Routes
        $this->routes = [
            'GET /' => [$this, 'showDashboard'],
            'GET /login' => [$this, 'showLogin'],
            'GET /register' => [$this, 'showRegister'],
            'GET /spools' => [$this, 'showSpools'],
            'GET /admin' => [$this, 'showAdmin'],
            
            // Auth API Routes
            'POST /api/auth/login' => [$this, 'apiLogin'],
            'POST /api/auth/register' => [$this, 'apiRegister'],
            'POST /api/auth/logout' => [$this, 'apiLogout'],
            'GET /api/auth/verify' => [$this, 'apiVerify'],
            'POST /api/auth/request-reset' => [$this, 'apiRequestReset'],
            'POST /api/auth/reset-password' => [$this, 'apiResetPassword'],
            'GET /api/user/me' => [$this, 'apiMe'],
            
            // Spool API Routes
            'GET /api/spools' => [$this, 'apiSpoolsIndex'],
            'POST /api/spools' => [$this, 'apiSpoolsCreate'],
            'GET /api/spools/stats' => [$this, 'apiSpoolsStats'],
            'GET /api/presets' => [$this, 'apiPresets'],
            'POST /api/colors' => [$this, 'apiColorsCreate'],
            'POST /api/types' => [$this, 'apiTypesCreate'],
            
            // Admin API Routes
            'GET /api/admin/stats' => [$this, 'apiAdminStats'],
            'GET /api/admin/users' => [$this, 'apiAdminUsers'],
            'PUT /api/admin/users' => [$this, 'apiAdminUpdateUser'],
            'DELETE /api/admin/users' => [$this, 'apiAdminDeleteUser'],
            'GET /api/admin/types' => [$this, 'apiAdminTypes'],
            'POST /api/admin/types' => [$this, 'apiAdminCreateType'],
            'PUT /api/admin/types' => [$this, 'apiAdminUpdateType'],
            'DELETE /api/admin/types' => [$this, 'apiAdminDeleteType'],
            'GET /api/admin/colors' => [$this, 'apiAdminColors'],
            'POST /api/admin/colors' => [$this, 'apiAdminCreateColor'],
            'PUT /api/admin/colors' => [$this, 'apiAdminUpdateColor'],
            'DELETE /api/admin/colors' => [$this, 'apiAdminDeleteColor'],
            'GET /api/admin/presets' => [$this, 'apiAdminPresets'],
            'POST /api/admin/presets' => [$this, 'apiAdminCreatePreset'],
            'PUT /api/admin/presets' => [$this, 'apiAdminUpdatePreset'],
            'DELETE /api/admin/presets' => [$this, 'apiAdminDeletePreset'],
            'POST /api/admin/backup' => [$this, 'apiAdminCreateBackup'],
            'GET /api/admin/backups' => [$this, 'apiAdminListBackups'],
            'GET /api/admin/backups/download' => [$this, 'apiAdminDownloadBackup'],
            'DELETE /api/admin/backups' => [$this, 'apiAdminDeleteBackup'],
            
            // NFC API Routes
            'POST /api/nfc/scan' => [$this, 'apiNfcScan'],
            'POST /api/nfc/register' => [$this, 'apiNfcRegister'],
            'GET /api/nfc/history' => [$this, 'apiNfcHistory'],
            'GET /sse/nfc' => [$this, 'apiNfcStream'],
            
            // Export Routes
            'GET /api/export/spools.csv' => [$this, 'apiExportSpoolsCsv'],
            
            // System Routes
            'GET /api/status' => [$this, 'apiStatus'],
            'GET /api/nfc/test' => [$this, 'apiNfcTest'],
        ];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        
        // Apply security middleware based on route
        $this->applySecurityMiddleware($path);
        
        // Handle dynamic routes like /api/spools/123
        if (preg_match('#^/api/spools/(\d+)$#', $path, $matches)) {
            $spoolId = (int)$matches[1];
            
            if ($method === 'GET') {
                $this->apiSpoolsShow($spoolId);
                return;
            } elseif ($method === 'PUT') {
                $this->apiSpoolsUpdate($spoolId);
                return;
            } elseif ($method === 'DELETE') {
                $this->apiSpoolsDelete($spoolId);
                return;
            }
        }
        
        // Handle adjust weight endpoint
        if (preg_match('#^/api/spools/(\d+)/adjust$#', $path, $matches)) {
            if ($method === 'POST') {
                $this->apiSpoolsAdjust((int)$matches[1]);
                return;
            }
        }
        
        // Handle NFC binding endpoints
        if (preg_match('#^/api/spools/(\d+)/bind-nfc$#', $path, $matches)) {
            if ($method === 'POST') {
                $this->apiSpoolsBindNfc((int)$matches[1]);
                return;
            }
        }
        
        if (preg_match('#^/api/spools/(\d+)/unbind-nfc$#', $path, $matches)) {
            if ($method === 'POST') {
                $this->apiSpoolsUnbindNfc((int)$matches[1]);
                return;
            }
        }
        
        // Handle admin dynamic routes
        if (preg_match('#^/api/admin/users/(\d+)$#', $path, $matches)) {
            $userId = (int)$matches[1];
            if ($method === 'GET') {
                $this->apiAdminGetUser($userId);
                return;
            } elseif ($method === 'PUT') {
                $this->apiAdminUpdateUser($userId);
                return;
            } elseif ($method === 'DELETE') {
                $this->apiAdminDeleteUser($userId);
                return;
            }
        }
        
        if (preg_match('#^/api/admin/types/(\d+)$#', $path, $matches)) {
            $typeId = (int)$matches[1];
            if ($method === 'PUT') {
                $this->apiAdminUpdateType($typeId);
                return;
            } elseif ($method === 'DELETE') {
                $this->apiAdminDeleteType($typeId);
                return;
            }
        }
        
        if (preg_match('#^/api/admin/colors/(\d+)$#', $path, $matches)) {
            $colorId = (int)$matches[1];
            if ($method === 'PUT') {
                $this->apiAdminUpdateColor($colorId);
                return;
            } elseif ($method === 'DELETE') {
                $this->apiAdminDeleteColor($colorId);
                return;
            }
        }
        
        if (preg_match('#^/api/admin/presets/(\d+)$#', $path, $matches)) {
            $presetId = (int)$matches[1];
            if ($method === 'PUT') {
                $this->apiAdminUpdatePreset($presetId);
                return;
            } elseif ($method === 'DELETE') {
                $this->apiAdminDeletePreset($presetId);
                return;
            }
        }
        
        if (preg_match('#^/api/admin/backups/([^/]+)$#', $path, $matches)) {
            $filename = $matches[1];
            if ($method === 'GET') {
                $this->apiAdminDownloadBackup($filename);
                return;
            } elseif ($method === 'DELETE') {
                $this->apiAdminDeleteBackup($filename);
                return;
            }
        }
        
        $route = "$method $path";

        if (isset($this->routes[$route])) {
            call_user_func($this->routes[$route]);
        } else {
            $this->notFound();
        }
    }

    private function showDashboard(): void
    {
        if (!$this->isAuthenticated()) {
            $this->redirect('/login');
            return;
        }
        
        // Make CSP nonce available to the view
        $cspNonce = $this->security->getCsp()->getNonce();
        
        include __DIR__ . '/../Views/dashboard.php';
    }

    private function showLogin(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirect('/');
            return;
        }
        
        // Make CSP nonce available to the view
        $cspNonce = $this->security->getCsp()->getNonce();
        
        include __DIR__ . '/../Views/login.php';
    }

    private function showRegister(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirect('/');
            return;
        }
        
        // Make CSP nonce available to the view
        $cspNonce = $this->security->getCsp()->getNonce();
        
        include __DIR__ . '/../Views/register.php';
    }

    private function showSpools(): void
    {
        if (!$this->isAuthenticated()) {
            $this->redirect('/login');
            return;
        }
        
        // Make CSP nonce available to the view
        $cspNonce = $this->security->getCsp()->getNonce();
        
        include __DIR__ . '/../Views/spools.php';
    }
    
    private function showAdmin(): void
    {
        if (!$this->isAuthenticated()) {
            $this->redirect('/login');
            return;
        }
        
        $user = $this->getCurrentUser();
        if (!$user || $user['role'] !== 'admin') {
            $this->redirect('/');
            return;
        }
        
        // Make CSP nonce available to the view
        $cspNonce = $this->security->getCsp()->getNonce();
        
        include __DIR__ . '/../Views/admin.php';
    }
    
    // === API Route Handlers ===
    
    private function apiSpoolsIndex(): void
    {
        $controller = $this->getSpoolController();
        $controller->index();
    }
    
    private function apiSpoolsShow(int $id): void
    {
        $controller = $this->getSpoolController();
        $controller->show($id);
    }
    
    private function apiSpoolsCreate(): void
    {
        $controller = $this->getSpoolController();
        $controller->create();
    }
    
    private function apiSpoolsUpdate(int $id): void
    {
        $controller = $this->getSpoolController();
        $controller->update($id);
    }
    
    private function apiSpoolsDelete(int $id): void
    {
        $controller = $this->getSpoolController();
        $controller->delete($id);
    }
    
    private function apiSpoolsAdjust(int $id): void
    {
        $controller = $this->getSpoolController();
        $controller->adjustWeight($id);
    }
    
    private function apiSpoolsBindNfc(int $id): void
    {
        $controller = $this->getSpoolController();
        $controller->bindNfc($id);
    }
    
    private function apiSpoolsUnbindNfc(int $id): void
    {
        $controller = $this->getSpoolController();
        $controller->unbindNfc($id);
    }
    
    private function apiSpoolsStats(): void
    {
        $controller = $this->getSpoolController();
        $controller->getDashboardStats();
    }
    
    private function apiPresets(): void
    {
        $controller = $this->getPresetController();
        $controller->getAllPresets();
    }
    
    private function apiColorsCreate(): void
    {
        $controller = $this->getPresetController();
        $controller->createColor();
    }

    private function apiTypesCreate(): void
    {
        $controller = $this->getPresetController();
        $controller->createType();
    }
    
    private function apiExportSpoolsCsv(): void
    {
        $controller = $this->getExportController();
        $controller->exportSpoolsCsv();
    }

    private function apiLogin(): void
    {
        $authController = $this->getAuthController();
        $authController->login();
    }

    private function apiRegister(): void
    {
        $authController = $this->getAuthController();
        $authController->register();
    }

    private function apiLogout(): void
    {
        $authController = $this->getAuthController();
        $authController->logout();
    }
    
    private function apiVerify(): void
    {
        $authController = $this->getAuthController();
        $authController->verify();
    }
    
    private function apiRequestReset(): void
    {
        $authController = $this->getAuthController();
        $authController->requestReset();
    }
    
    private function apiResetPassword(): void
    {
        $authController = $this->getAuthController();
        $authController->resetPassword();
    }
    
    private function apiMe(): void
    {
        $authController = $this->getAuthController();
        $authController->me();
    }

    private function apiStatus(): void
    {
        $this->jsonResponse([
            'status' => 'ok',
            'timestamp' => date('c'),
            'version' => '1.0.0',
            'nfc_enabled' => class_exists('\Filament\Controllers\NFCController')
        ]);
    }
    
    private function apiNfcTest(): void
    {
        $this->jsonResponse([
            'nfc_controller_exists' => class_exists('\Filament\Controllers\NFCController'),
            'nfc_routes' => [
                'scan' => 'POST /api/nfc/scan',
                'register' => 'POST /api/nfc/register', 
                'history' => 'GET /api/nfc/history',
                'stream' => 'GET /sse/nfc'
            ],
            'test_scan_url' => 'POST /api/nfc/scan',
            'status' => 'NFC API available'
        ]);
    }
    
    // === NFC API Route Handlers ===
    
    private function apiNfcScan(): void
    {
        $controller = $this->getNfcController();
        $controller->scan();
    }
    
    private function apiNfcRegister(): void
    {
        $controller = $this->getNfcController();
        $controller->register();
    }
    
    private function apiNfcHistory(): void
    {
        $controller = $this->getNfcController();
        $controller->getScanHistory();
    }
    
    private function apiNfcStream(): void
    {
        $controller = $this->getNfcController();
        $controller->scanStream();
    }
    
    // === Admin API Route Handlers ===
    
    private function apiAdminStats(): void
    {
        $controller = $this->getAdminController();
        $controller->getSystemStats();
    }
    
    private function apiAdminUsers(): void
    {
        $controller = $this->getAdminController();
        $controller->getUsers();
    }
    
    private function apiAdminGetUser(int $userId): void
    {
        $controller = $this->getAdminController();
        $controller->getUser($userId);
    }
    
    private function apiAdminUpdateUser(int $userId): void
    {
        $controller = $this->getAdminController();
        $controller->updateUser($userId);
    }
    
    private function apiAdminDeleteUser(int $userId): void
    {
        $controller = $this->getAdminController();
        $controller->deleteUser($userId);
    }
    
    private function apiAdminTypes(): void
    {
        $controller = $this->getAdminController();
        $controller->getFilamentTypes();
    }
    
    private function apiAdminCreateType(): void
    {
        $controller = $this->getAdminController();
        $controller->createFilamentType();
    }
    
    private function apiAdminUpdateType(int $typeId): void
    {
        $controller = $this->getAdminController();
        $controller->updateFilamentType($typeId);
    }
    
    private function apiAdminDeleteType(int $typeId): void
    {
        $controller = $this->getAdminController();
        $controller->deleteFilamentType($typeId);
    }
    
    private function apiAdminColors(): void
    {
        $controller = $this->getAdminController();
        $controller->getColors();
    }
    
    private function apiAdminCreateColor(): void
    {
        $controller = $this->getAdminController();
        $controller->createColor();
    }
    
    private function apiAdminUpdateColor(int $colorId): void
    {
        $controller = $this->getAdminController();
        $controller->updateColor($colorId);
    }
    
    private function apiAdminDeleteColor(int $colorId): void
    {
        $controller = $this->getAdminController();
        $controller->deleteColor($colorId);
    }
    
    private function apiAdminPresets(): void
    {
        $controller = $this->getAdminController();
        $controller->getSpoolPresets();
    }
    
    private function apiAdminCreatePreset(): void
    {
        $controller = $this->getAdminController();
        $controller->createSpoolPreset();
    }
    
    private function apiAdminUpdatePreset(int $presetId): void
    {
        $controller = $this->getAdminController();
        $controller->updateSpoolPreset($presetId);
    }
    
    private function apiAdminDeletePreset(int $presetId): void
    {
        $controller = $this->getAdminController();
        $controller->deleteSpoolPreset($presetId);
    }
    
    private function apiAdminCreateBackup(): void
    {
        $controller = $this->getAdminController();
        $controller->createBackup();
    }
    
    private function apiAdminListBackups(): void
    {
        $controller = $this->getAdminController();
        $controller->getBackups();
    }
    
    private function apiAdminDownloadBackup(string $filename): void
    {
        $controller = $this->getAdminController();
        $controller->downloadBackup($filename);
    }
    
    private function apiAdminDeleteBackup(string $filename): void
    {
        $controller = $this->getAdminController();
        $controller->deleteBackup($filename);
    }

    private function notFound(): void
    {
        http_response_code(404);
        if (str_starts_with($_SERVER['REQUEST_URI'], '/api/')) {
            $this->jsonResponse(['error' => 'Not Found'], 404);
        } else {
            echo '<h1>404 - Page Not Found</h1>';
        }
    }

    private function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Get Auth Controller instance
     */
    private function getAuthController(): \Filament\Controllers\AuthController
    {
        $userModel = new \Filament\Models\User($this->app->getDb());
        $mailService = new \Filament\Services\MailService($this->app->getConfig());
        $authService = new \Filament\Services\AuthService($userModel, $mailService);
        
        return new \Filament\Controllers\AuthController($authService);
    }
    
    /**
     * Get Spool Controller instance
     */
    private function getSpoolController(): \Filament\Controllers\SpoolController
    {
        $spoolModel = new \Filament\Models\FilamentSpool($this->app->getDb());
        $usageModel = new \Filament\Models\UsageLog($this->app->getDb());
        $userModel = new \Filament\Models\User($this->app->getDb());
        $mailService = new \Filament\Services\MailService($this->app->getConfig());
        $authService = new \Filament\Services\AuthService($userModel, $mailService);
        
        return new \Filament\Controllers\SpoolController($spoolModel, $usageModel, $authService);
    }
    
    /**
     * Get Preset Controller instance
     */
    private function getPresetController(): \Filament\Controllers\PresetController
    {
        $typeModel = new \Filament\Models\FilamentType($this->app->getDb());
        $colorModel = new \Filament\Models\Color($this->app->getDb());
        $presetModel = new \Filament\Models\SpoolPreset($this->app->getDb());
        $userModel = new \Filament\Models\User($this->app->getDb());
        $mailService = new \Filament\Services\MailService($this->app->getConfig());
        $authService = new \Filament\Services\AuthService($userModel, $mailService);
        
        return new \Filament\Controllers\PresetController($typeModel, $colorModel, $presetModel, $authService);
    }

    /**
     * Get Export Controller instance
     */
    private function getExportController(): \Filament\Controllers\ExportController
    {
        $spoolModel = new \Filament\Models\FilamentSpool($this->app->getDb());
        $userModel = new \Filament\Models\User($this->app->getDb());
        $mailService = new \Filament\Services\MailService($this->app->getConfig());
        $authService = new \Filament\Services\AuthService($userModel, $mailService);
        
        return new \Filament\Controllers\ExportController($spoolModel, $authService);
    }
    
    /**
     * Get NFC Controller instance
     */
    private function getNfcController(): \Filament\Controllers\NFCController
    {
        $spoolModel = new \Filament\Models\FilamentSpool($this->app->getDb());
        $userModel = new \Filament\Models\User($this->app->getDb());
        $mailService = new \Filament\Services\MailService($this->app->getConfig());
        $authService = new \Filament\Services\AuthService($userModel, $mailService);
        
        return new \Filament\Controllers\NFCController($spoolModel, $authService);
    }
    
    /**
     * Get Admin Controller instance
     */
    private function getAdminController(): \Filament\Controllers\AdminController
    {
        $userModel = new \Filament\Models\User($this->app->getDb());
        $typeModel = new \Filament\Models\FilamentType($this->app->getDb());
        $colorModel = new \Filament\Models\Color($this->app->getDb());
        $presetModel = new \Filament\Models\SpoolPreset($this->app->getDb());
        $mailService = new \Filament\Services\MailService($this->app->getConfig());
        $authService = new \Filament\Services\AuthService($userModel, $mailService);
        $backupService = new \Filament\Services\BackupService($this->app->getDb(), $this->app->getConfig());
        
        return new \Filament\Controllers\AdminController(
            $userModel,
            $typeModel,
            $colorModel,
            $presetModel,
            $authService,
            $backupService,
            $this->app->getDb()
        );
    }
    
    /**
     * Apply security middleware based on route
     */
    private function applySecurityMiddleware(string $path): void
    {
        // Initialize security for all requests
        $this->security->initialize();
        
        // Skip CSRF for all API endpoints (APIs should use other auth methods)
        if (strpos($path, '/api/') === 0) {
            // Only apply rate limiting for API endpoints
            $errors = [];
            if ($this->security->getRateLimiter()) {
                $endpoint = $this->detectEndpoint($path);
                if (!$this->security->getRateLimiter()->middleware($endpoint)) {
                    $errors[] = 'Rate limit exceeded';
                }
            }
            
            if (!empty($errors)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Security validation failed', 'details' => $errors]);
                exit;
            }
            return;
        }
        
        // Apply security checks for web routes
        // Only validate CSRF for state-changing methods (POST, PUT, DELETE, PATCH)
        $method = $_SERVER['REQUEST_METHOD'];
        $requiresCsrf = in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH']);
        
        $errors = [];
        
        // Check CSRF token only for state-changing requests
        if ($requiresCsrf && !$this->security->getCsrf()->middleware()) {
            $errors[] = 'Invalid CSRF token';
        }
        
        // Check rate limiting for all requests
        if ($this->security->getRateLimiter()) {
            $endpoint = $this->detectEndpoint($path);
            if (!$this->security->getRateLimiter()->middleware($endpoint)) {
                $errors[] = 'Rate limit exceeded';
            }
        }
        
        if (!empty($errors)) {
            http_response_code(403);
            echo '<h1>Security Error</h1><p>Request blocked for security reasons.</p>';
            exit;
        }
        
        // Check authentication for protected routes
        if (strpos($path, '/admin') === 0) {
            if (!isset($_SESSION['user_id'])) {
                header('Location: /login');
                exit;
            }
            
            if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
                http_response_code(403);
                echo '<h1>403 - Access Denied</h1><p>Admin access required.</p>';
                exit;
            }
        }
        
        // Set JSON content type for API routes
        if (strpos($path, '/api/') === 0) {
            header('Content-Type: application/json');
        }
    }
    
    /**
     * Get CSRF token for forms
     */
    public function getCsrfToken(): string
    {
        return $this->security->getCsrf()->getCurrentToken();
    }
    
    /**
     * Get CSRF token field HTML
     */
    public function getCsrfField(): string
    {
        return $this->security->getCsrf()->getTokenField();
    }
    
    /**
     * Get CSP nonce for inline scripts/styles
     */
    public function getCspNonce(): string
    {
        return $this->security->getCsp()->getNonce();
    }
    
    /**
     * Detect endpoint for rate limiting
     */
    private function detectEndpoint(string $path): string
    {
        // Normalize dynamic routes for rate limiting
        $path = preg_replace('#/\d+#', '/{id}', $path);
        return $_SERVER['REQUEST_METHOD'] . ' ' . $path;
    }
    
    /**
     * Get current authenticated user
     */
    private function getCurrentUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        $userModel = new \Filament\Models\User($this->app->getDb());
        return $userModel->findById($_SESSION['user_id']);
    }
}
<?php

declare(strict_types=1);

namespace Filament\Controllers;

use Filament\Services\AuthService;
use Exception;

/**
 * Authentication Controller
 */
class AuthController
{
    private AuthService $authService;
    
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }
    
    /**
     * Handle user registration
     */
    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON data'], 400);
            return;
        }
        
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $name = trim($input['name'] ?? '');
        
        if (empty($email) || empty($password) || empty($name)) {
            $this->jsonResponse(['error' => 'Alle Felder sind erforderlich'], 400);
            return;
        }
        
        try {
            $result = $this->authService->register($email, $password, $name);
            $this->jsonResponse($result, 201);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Handle user login
     */
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON data'], 400);
            return;
        }
        
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $this->jsonResponse(['error' => 'E-Mail und Passwort sind erforderlich'], 400);
            return;
        }
        
        try {
            $result = $this->authService->login($email, $password);
            $this->jsonResponse($result);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 401);
        }
    }
    
    /**
     * Handle user logout
     */
    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        $this->authService->logout();
        $this->jsonResponse(['message' => 'Erfolgreich abgemeldet']);
    }
    
    /**
     * Handle email verification
     */
    public function verify(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            $this->jsonResponse(['error' => 'Verification token required'], 400);
            return;
        }
        
        try {
            $success = $this->authService->verifyEmail($token);
            
            if ($success) {
                // Redirect to login page with success message
                header('Location: /login?verified=1');
            } else {
                $this->jsonResponse(['error' => 'Invalid or expired token'], 400);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Handle password reset request
     */
    public function requestReset(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON data'], 400);
            return;
        }
        
        $email = trim($input['email'] ?? '');
        
        if (empty($email)) {
            $this->jsonResponse(['error' => 'E-Mail ist erforderlich'], 400);
            return;
        }
        
        try {
            $sent = $this->authService->requestPasswordReset($email);
            
            // Always return success to prevent email enumeration
            $this->jsonResponse([
                'message' => 'Falls ein Konto mit dieser E-Mail existiert, wurde eine Nachricht gesendet.'
            ]);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Handle password reset
     */
    public function resetPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->jsonResponse(['error' => 'Invalid JSON data'], 400);
            return;
        }
        
        $token = $input['token'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($token) || empty($password)) {
            $this->jsonResponse(['error' => 'Token und neues Passwort sind erforderlich'], 400);
            return;
        }
        
        try {
            $success = $this->authService->resetPassword($token, $password);
            
            if ($success) {
                $this->jsonResponse(['message' => 'Passwort erfolgreich zurückgesetzt']);
            } else {
                $this->jsonResponse(['error' => 'Ungültiger oder abgelaufener Token'], 400);
            }
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Get current user info
     */
    public function me(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        if (!$this->authService->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Not authenticated'], 401);
            return;
        }
        
        $user = $this->authService->getCurrentUser();
        $this->jsonResponse(['user' => $user]);
    }
    
    /**
     * Send JSON response
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
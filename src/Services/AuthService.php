<?php

declare(strict_types=1);

namespace Filament\Services;

use Exception;

/**
 * Authentication Service
 */
class AuthService
{
    private \Filament\Models\User $userModel;
    private \Filament\Services\MailService $mailService;
    
    public function __construct(\Filament\Models\User $userModel, \Filament\Services\MailService $mailService)
    {
        $this->userModel = $userModel;
        $this->mailService = $mailService;
    }
    
    /**
     * Register new user
     */
    public function register(string $email, string $password, string $name): array
    {
        // Check if user already exists
        $existingUser = $this->userModel->findByEmail($email);
        if ($existingUser) {
            throw new Exception('E-Mail bereits registriert');
        }
        
        // Validate input
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Ungültige E-Mail-Adresse');
        }
        
        if (strlen($password) < 8) {
            throw new Exception('Passwort muss mindestens 8 Zeichen lang sein');
        }
        
        if (empty(trim($name))) {
            throw new Exception('Name ist erforderlich');
        }
        
        // Create user
        $userId = $this->userModel->createUser($email, $password, trim($name));
        $user = $this->userModel->find($userId);
        
        // Send verification email
        if ($user && $user['verification_token']) {
            $this->mailService->sendVerificationEmail($email, $name, $user['verification_token']);
        }
        
        return [
            'success' => true,
            'message' => 'Registrierung erfolgreich. Bitte bestätigen Sie Ihre E-Mail-Adresse.',
            'user_id' => $userId
        ];
    }
    
    /**
     * Login user
     */
    public function login(string $email, string $password): array
    {
        $user = $this->userModel->findByEmail($email);
        
        if (!$user) {
            throw new Exception('Ungültige Anmeldedaten');
        }
        
        if (!$user['is_active']) {
            throw new Exception('Account deaktiviert');
        }
        
        // Note: Email verification temporarily disabled for testing
        // Uncomment the following lines to enable email verification:
        // if (!$user['verified_at']) {
        //     throw new Exception('E-Mail-Adresse nicht verifiziert');
        // }
        
        if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
            throw new Exception('Ungültige Anmeldedaten');
        }
        
        // Update last login
        $this->userModel->updateLastLogin($user['id']);
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        return [
            'success' => true,
            'user' => $this->userModel->getSafeUser($user)
        ];
    }
    
    /**
     * Logout user
     */
    public function logout(): void
    {
        session_destroy();
        session_start(); // Restart clean session
    }
    
    /**
     * Verify user account
     */
    public function verifyEmail(string $token): bool
    {
        if (empty($token)) {
            return false;
        }
        
        return $this->userModel->verifyUser($token);
    }
    
    /**
     * Request password reset
     */
    public function requestPasswordReset(string $email): bool
    {
        $resetToken = $this->userModel->setResetToken($email);
        
        if ($resetToken) {
            $user = $this->userModel->findByEmail($email);
            $this->mailService->sendPasswordResetEmail($email, $user['name'], $resetToken);
            return true;
        }
        
        return false;
    }
    
    /**
     * Reset password
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        if (strlen($newPassword) < 8) {
            throw new Exception('Passwort muss mindestens 8 Zeichen lang sein');
        }
        
        return $this->userModel->resetPassword($token, $newPassword);
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->isAuthenticated() && ($_SESSION['user_role'] ?? '') === 'admin';
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $user = $this->userModel->find($_SESSION['user_id']);
        return $user ? $this->userModel->getSafeUser($user) : null;
    }
}
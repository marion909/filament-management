<?php

declare(strict_types=1);

namespace Filament\Models;

use PDO;
use Exception;

/**
 * User Model for Authentication
 */
class User extends BaseModel
{
    protected string $table = 'users';
    
    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Find user by verification token
     */
    public function findByVerificationToken(string $token): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE verification_token = ? LIMIT 1");
        $stmt->execute([$token]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Find user by reset token
     */
    public function findByResetToken(string $token): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
        $stmt->execute([$token]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Create new user with hashed password
     */
    public function createUser(string $email, string $password, string $name, string $role = 'user'): int
    {
        $verificationToken = bin2hex(random_bytes(32));
        
        $data = [
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'name' => $name,
            'role' => $role,
            'verification_token' => $verificationToken,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->create($data);
    }
    
    /**
     * Verify user password
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Verify user account
     */
    public function verifyUser(string $token): bool
    {
        $user = $this->findByVerificationToken($token);
        if (!$user) {
            return false;
        }
        
        return $this->update($user['id'], [
            'verified_at' => date('Y-m-d H:i:s'),
            'verification_token' => null
        ]);
    }
    
    /**
     * Set password reset token
     */
    public function setResetToken(string $email): ?string
    {
        $user = $this->findByEmail($email);
        if (!$user || !$user['is_active'] || !$user['verified_at']) {
            return null;
        }
        
        $resetToken = bin2hex(random_bytes(32));
        $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $this->update($user['id'], [
            'reset_token' => $resetToken,
            'reset_expires' => $resetExpires
        ]);
        
        return $resetToken;
    }
    
    /**
     * Reset password using token
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $user = $this->findByResetToken($token);
        if (!$user) {
            return false;
        }
        
        return $this->update($user['id'], [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'reset_token' => null,
            'reset_expires' => null
        ]);
    }
    
    /**
     * Update last login timestamp
     */
    public function updateLastLogin(int $userId): bool
    {
        return $this->update($userId, [
            'last_login' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get user without sensitive data
     */
    public function getSafeUser(array $user): array
    {
        unset($user['password_hash']);
        unset($user['verification_token']);
        unset($user['reset_token']);
        unset($user['reset_expires']);
        
        return $user;
    }
}
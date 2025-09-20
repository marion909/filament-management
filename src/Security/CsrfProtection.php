<?php

declare(strict_types=1);

namespace Filament\Security;

/**
 * CSRF Protection Service
 */
class CsrfProtection
{
    private string $tokenName;
    private int $tokenLength;
    
    public function __construct(string $tokenName = '_token', int $tokenLength = 32)
    {
        $this->tokenName = $tokenName;
        $this->tokenLength = $tokenLength;
    }
    
    public function generateToken(): string
    {
        $token = bin2hex(random_bytes($this->tokenLength));
        $_SESSION[$this->tokenName] = $token;
        return $token;
    }
    
    public function getToken(): string
    {
        if (!isset($_SESSION[$this->tokenName])) {
            return $this->generateToken();
        }
        return $_SESSION[$this->tokenName];
    }
    
    public function validateToken(string $token): bool
    {
        if (!isset($_SESSION[$this->tokenName])) {
            return false;
        }
        
        return hash_equals($_SESSION[$this->tokenName], $token);
    }
    
    public function middleware(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST[$this->tokenName] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            return $this->validateToken($token);
        }
        return true;
    }
    
    public function getCurrentToken(): string
    {
        return $this->getToken();
    }
    
    public function getTokenField(): string
    {
        return '<input type="hidden" name="' . $this->tokenName . '" value="' . $this->getToken() . '">';
    }
    
    public function getTokenName(): string
    {
        return $this->tokenName;
    }
}
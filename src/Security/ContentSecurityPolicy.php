<?php

declare(strict_types=1);

namespace Filament\Security;

/**
 * Content Security Policy Service
 */
class ContentSecurityPolicy
{
    private array $policy;
    private string $nonce;
    
    public function __construct()
    {
        $this->nonce = base64_encode(random_bytes(16));
        $this->policy = [
            'default-src' => "'self'",
            'script-src' => "'self' 'nonce-{$this->nonce}'",
            'style-src' => "'self' 'unsafe-inline'",
            'img-src' => "'self' data:",
            'font-src' => "'self'",
            'connect-src' => "'self'",
            'frame-ancestors' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'"
        ];
    }
    
    public function setEnvironmentPolicy(string $environment): void
    {
        if ($environment === 'development') {
            $this->policy['script-src'] = "'self' 'unsafe-eval' 'nonce-{$this->nonce}'";
        }
    }
    
    public function sendHeader(): void
    {
        $policyString = '';
        foreach ($this->policy as $directive => $value) {
            $policyString .= $directive . ' ' . $value . '; ';
        }
        
        header('Content-Security-Policy: ' . rtrim($policyString, '; '));
    }
    
    public function getNonce(): string
    {
        return $this->nonce;
    }
}
<?php

declare(strict_types=1);

namespace Filament\Security;

/**
 * Rate Limiting Service
 */
class RateLimiter
{
    private array $limits;
    private string $storageFile;
    
    public function __construct()
    {
        $this->limits = [
            'default' => ['requests' => 10000, 'window' => 3600],
            'login' => ['requests' => 5000, 'window' => 300],
            'register' => ['requests' => 3000, 'window' => 3600],
            'api' => ['requests' => 60000, 'window' => 60]
        ];
        
        $this->storageFile = sys_get_temp_dir() . '/rate_limits.json';
    }
    
    public function checkLimit(string $key, string $type = 'default'): bool
    {
        $limit = $this->limits[$type] ?? $this->limits['default'];
        $requests = $this->getRequests($key);
        
        $currentTime = time();
        $windowStart = $currentTime - $limit['window'];
        
        // Filter requests within the current window
        $requests = array_filter($requests, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        return count($requests) < $limit['requests'];
    }
    
    public function recordRequest(string $key, string $type = 'default'): void
    {
        $requests = $this->getRequests($key);
        $requests[] = time();
        
        $this->saveRequests($key, $requests);
    }
    
    public function middleware(string $endpoint = 'default'): bool
    {
        $clientId = $this->getClientId();
        
        if (!$this->checkLimit($clientId, $endpoint)) {
            return false;
        }
        
        $this->recordRequest($clientId, $endpoint);
        return true;
    }
    
    private function getClientId(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $ip . $userAgent);
    }
    
    private function getRequests(string $key): array
    {
        if (!file_exists($this->storageFile)) {
            return [];
        }
        
        $data = json_decode(file_get_contents($this->storageFile), true);
        return $data[$key] ?? [];
    }
    
    private function saveRequests(string $key, array $requests): void
    {
        $data = [];
        if (file_exists($this->storageFile)) {
            $data = json_decode(file_get_contents($this->storageFile), true) ?: [];
        }
        
        $data[$key] = $requests;
        file_put_contents($this->storageFile, json_encode($data));
    }
}
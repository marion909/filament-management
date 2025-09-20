<?php

declare(strict_types=1);

namespace Filament\Core;

/**
 * Simple Application Bootstrap Class
 */
class Application
{
    private array $config;
    private ?\PDO $db = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializeSession();
        $this->setErrorReporting();
    }

    private function initializeSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $sessionConfig = $this->config['session'] ?? [];
            
            session_name($sessionConfig['name'] ?? 'filament_session');
            
            if (isset($sessionConfig['lifetime'])) {
                ini_set('session.gc_maxlifetime', (string)$sessionConfig['lifetime']);
            }
            
            session_start();
        }
    }

    private function setErrorReporting(): void
    {
        if ($this->config['app']['debug'] ?? false) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getEnvironment(): string
    {
        return $this->config['app']['environment'] ?? 'production';
    }

    public function getDb(): \PDO
    {
        if ($this->db === null) {
            $dbConfig = $this->config['database'];
            
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $dbConfig['host'],
                $dbConfig['name'],
                $dbConfig['charset']
            );
            
            $this->db = new \PDO(
                $dsn,
                $dbConfig['user'],
                $dbConfig['password'],
                $dbConfig['options']
            );
        }
        
        return $this->db;
    }
}
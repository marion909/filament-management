<?php

declare(strict_types=1);

namespace Filament\Controllers;

use Filament\Models\FilamentSpool;
use Filament\Services\AuthService;
use Exception;

/**
 * NFC Controller for scanner integration
 */
class NFCController
{
    private FilamentSpool $spoolModel;
    private AuthService $authService;
    
    public function __construct(FilamentSpool $spoolModel, AuthService $authService)
    {
        $this->spoolModel = $spoolModel;
        $this->authService = $authService;
    }
    
    /**
     * Handle NFC scan from hardware scanner
     */
    public function scan(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        // Allow scanner to access without authentication for simplicity
        // In production, consider API key authentication for scanner
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || empty($input['nfc_uid'])) {
                $this->jsonResponse(['error' => 'nfc_uid is required'], 400);
                return;
            }
            
            $nfcUid = trim($input['nfc_uid']);
            $scannerId = $input['scanner_id'] ?? 'unknown';
            
            // Log scan attempt
            $this->logScan($nfcUid, $scannerId);
            
            // Find spool by NFC UID
            $spool = $this->spoolModel->findByNfcUid($nfcUid);
            
            if ($spool) {
                $this->jsonResponse([
                    'found' => true,
                    'spool' => [
                        'id' => $spool['id'],
                        'uuid' => $spool['uuid'],
                        'material' => $spool['material'],
                        'total_weight' => $spool['total_weight'],
                        'remaining_weight' => $spool['remaining_weight'],
                        'location' => $spool['location'],
                        'created_at' => $spool['created_at']
                    ]
                ]);
            } else {
                $this->jsonResponse([
                    'found' => false,
                    'message' => 'no_spool',
                    'nfc_uid' => $nfcUid
                ], 404);
            }
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Register new spool with NFC UID
     */
    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        if (!$this->authService->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Not authenticated'], 401);
            return;
        }
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $this->jsonResponse(['error' => 'Invalid JSON data'], 400);
                return;
            }
            
            // Validate required fields
            $required = ['nfc_uid', 'type_id', 'material', 'total_weight'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    $this->jsonResponse(['error' => "Field '{$field}' is required"], 400);
                    return;
                }
            }
            
            $userId = $_SESSION['user_id'];
            $spoolId = $this->spoolModel->createSpool($input, $userId);
            $spool = $this->spoolModel->find($spoolId);
            
            $this->jsonResponse([
                'message' => 'Spool created and NFC bound successfully',
                'spool' => $spool
            ], 201);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Get NFC scan history/logs
     */
    public function getScanHistory(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        if (!$this->authService->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Not authenticated'], 401);
            return;
        }
        
        try {
            $logFile = __DIR__ . '/../../logs/nfc_scans.log';
            $scans = [];
            
            if (file_exists($logFile)) {
                $lines = array_slice(file($logFile, FILE_IGNORE_NEW_LINES), -50); // Last 50 scans
                
                foreach (array_reverse($lines) as $line) {
                    if (preg_match('/\[(.*?)\] Scanner: (.*?) \| UID: (.*?) \| (.*)/', $line, $matches)) {
                        $scans[] = [
                            'timestamp' => $matches[1],
                            'scanner_id' => $matches[2],
                            'nfc_uid' => $matches[3],
                            'result' => $matches[4]
                        ];
                    }
                }
            }
            
            $this->jsonResponse(['scans' => $scans]);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Server-Sent Events endpoint for live scan updates
     */
    public function scanStream(): void
    {
        if (!$this->authService->isAuthenticated()) {
            http_response_code(401);
            exit;
        }
        
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        // Send initial connection message
        echo "data: " . json_encode(['type' => 'connected', 'message' => 'Scanner stream connected']) . "\n\n";
        ob_flush();
        flush();
        
        // In a real implementation, this would monitor the log file or database
        // For now, just keep the connection alive
        $lastCheck = time();
        
        while (true) {
            // Check for new scans every 2 seconds
            if (time() - $lastCheck >= 2) {
                $logFile = __DIR__ . '/../../logs/nfc_scans.log';
                
                if (file_exists($logFile) && filemtime($logFile) > $lastCheck) {
                    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
                    $lastLine = end($lines);
                    
                    if (preg_match('/\[(.*?)\] Scanner: (.*?) \| UID: (.*?) \| (.*)/', $lastLine, $matches)) {
                        $scanData = [
                            'type' => 'scan',
                            'timestamp' => $matches[1],
                            'scanner_id' => $matches[2],
                            'nfc_uid' => $matches[3],
                            'result' => $matches[4]
                        ];
                        
                        echo "data: " . json_encode($scanData) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }
                
                $lastCheck = time();
            }
            
            // Break connection after 5 minutes
            if (time() - $lastCheck > 300) {
                break;
            }
            
            sleep(1);
        }
    }
    
    /**
     * Log NFC scan attempt
     */
    private function logScan(string $nfcUid, string $scannerId): void
    {
        $logFile = __DIR__ . '/../../logs/nfc_scans.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $spool = $this->spoolModel->findByNfcUid($nfcUid);
        $result = $spool ? "Found spool ID {$spool['id']} - {$spool['material']}" : "No spool found";
        
        $logEntry = "[{$timestamp}] Scanner: {$scannerId} | UID: {$nfcUid} | {$result}\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Send JSON response
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
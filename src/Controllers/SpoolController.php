<?php

declare(strict_types=1);

namespace Filament\Controllers;

use Filament\Models\FilamentSpool;
use Filament\Models\UsageLog;
use Filament\Models\FilamentType;
use Filament\Models\Color;
use Filament\Models\SpoolPreset;
use Filament\Services\AuthService;
use Exception;

/**
 * Spools/Filament Controller
 */
class SpoolController
{
    private FilamentSpool $spoolModel;
    private UsageLog $usageModel;
    private AuthService $authService;
    
    public function __construct(FilamentSpool $spoolModel, UsageLog $usageModel, AuthService $authService)
    {
        $this->spoolModel = $spoolModel;
        $this->usageModel = $usageModel;
        $this->authService = $authService;
    }
    
    /**
     * Get all spools with filtering and pagination
     */
    public function index(): void
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
            // Get query parameters
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
            
            $filters = [];
            if (!empty($_GET['material'])) {
                $filters['material'] = $_GET['material'];
            }
            if (!empty($_GET['type_id'])) {
                $filters['type_id'] = $_GET['type_id'];
            }
            if (!empty($_GET['color_id'])) {
                $filters['color_id'] = $_GET['color_id'];
            }
            if (!empty($_GET['location'])) {
                $filters['location'] = $_GET['location'];
            }
            if (!empty($_GET['low_stock'])) {
                $filters['low_stock'] = true;
            }
            
            $result = $this->spoolModel->getFilaments($filters, $page, $limit);
            $this->jsonResponse($result);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get single spool by ID
     */
    public function show(int $id): void
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
            $spool = $this->spoolModel->find($id);
            
            if (!$spool || !$spool['is_active']) {
                $this->jsonResponse(['error' => 'Spool not found'], 404);
                return;
            }
            
            // Get usage history
            $history = $this->usageModel->getSpoolHistory($id);
            
            $this->jsonResponse([
                'spool' => $spool,
                'usage_history' => $history
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Create new spool
     */
    public function create(): void
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
            $required = ['type_id', 'material', 'total_weight'];
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
                'message' => 'Spool created successfully',
                'spool' => $spool
            ], 201);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Update spool
     */
    public function update(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        if (!$this->authService->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Not authenticated'], 401);
            return;
        }
        
        try {
            $spool = $this->spoolModel->find($id);
            
            if (!$spool || !$spool['is_active']) {
                $this->jsonResponse(['error' => 'Spool not found'], 404);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $this->jsonResponse(['error' => 'Invalid JSON data'], 400);
                return;
            }
            
            // Allow updating these fields
            $allowedFields = [
                'type_id', 'material', 'color_id', 'location', 'notes', 
                'purchase_date', 'batch_number', 'total_weight'
            ];
            
            $updateData = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $input)) {
                    $updateData[$field] = $input[$field];
                }
            }
            
            // Debug log
            error_log("Spool Update - Received data: " . json_encode($input));
            error_log("Spool Update - Update data: " . json_encode($updateData));
            
            if (!empty($updateData)) {
                $updateData['updated_at'] = date('Y-m-d H:i:s');
                $this->spoolModel->update($id, $updateData);
            }
            
            $updatedSpool = $this->spoolModel->find($id);
            
            $this->jsonResponse([
                'message' => 'Spool updated successfully',
                'spool' => $updatedSpool
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Delete (soft delete) spool
     */
    public function delete(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        if (!$this->authService->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Not authenticated'], 401);
            return;
        }
        
        try {
            $spool = $this->spoolModel->find($id);
            
            if (!$spool || !$spool['is_active']) {
                $this->jsonResponse(['error' => 'Spool not found'], 404);
                return;
            }
            
            $this->spoolModel->softDelete($id);
            
            $this->jsonResponse(['message' => 'Spool deleted successfully']);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Adjust weight (usage tracking)
     */
    public function adjustWeight(int $id): void
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
            $spool = $this->spoolModel->find($id);
            
            if (!$spool || !$spool['is_active']) {
                $this->jsonResponse(['error' => 'Spool not found'], 404);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['delta_grams'])) {
                $this->jsonResponse(['error' => 'delta_grams is required'], 400);
                return;
            }
            
            $deltaGrams = (int)$input['delta_grams'];
            $userId = $_SESSION['user_id'];
            
            // Update weight
            $success = $this->spoolModel->adjustWeight($id, $deltaGrams, $userId);
            
            if (!$success) {
                $this->jsonResponse(['error' => 'Failed to update weight'], 500);
                return;
            }
            
            // Log usage if it's consumption (negative delta)
            if ($deltaGrams < 0) {
                $this->usageModel->logUsage(
                    $id, 
                    abs($deltaGrams), 
                    $userId,
                    $input['job_name'] ?? null,
                    $input['job_id'] ?? null,
                    $input['reason'] ?? null
                );
            }
            
            $updatedSpool = $this->spoolModel->find($id);
            
            $this->jsonResponse([
                'message' => 'Weight adjusted successfully',
                'remaining_weight' => $updatedSpool['remaining_weight']
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Bind NFC UID to spool
     */
    public function bindNfc(int $id): void
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
            $spool = $this->spoolModel->find($id);
            
            if (!$spool || !$spool['is_active']) {
                $this->jsonResponse(['error' => 'Spool not found'], 404);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $nfcUid = $input['nfc_uid'] ?? $_GET['nfc_uid'] ?? '';
            
            if (empty($nfcUid)) {
                $this->jsonResponse(['error' => 'nfc_uid is required'], 400);
                return;
            }
            
            $this->spoolModel->bindNfc($id, $nfcUid);
            
            $this->jsonResponse(['message' => 'NFC bound successfully']);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 409);
        }
    }
    
    /**
     * Unbind NFC UID from spool
     */
    public function unbindNfc(int $id): void
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
            $spool = $this->spoolModel->find($id);
            
            if (!$spool || !$spool['is_active']) {
                $this->jsonResponse(['error' => 'Spool not found'], 404);
                return;
            }
            
            $this->spoolModel->unbindNfc($id);
            
            $this->jsonResponse(['message' => 'NFC unbound successfully']);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get dashboard stats
     */
    public function getDashboardStats(): void
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
            $stats = $this->spoolModel->getDashboardStats();
            $usageStats = $this->usageModel->getUsageStats(7); // Last 7 days
            
            $this->jsonResponse([
                'spools' => $stats,
                'usage' => $usageStats
            ]);
            
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
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
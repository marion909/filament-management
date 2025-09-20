<?php

declare(strict_types=1);

namespace Filament\Controllers;

use Filament\Models\FilamentSpool;
use Filament\Services\AuthService;
use Exception;

/**
 * Export Controller for CSV and other formats
 */
class ExportController
{
    private FilamentSpool $spoolModel;
    private AuthService $authService;
    
    public function __construct(FilamentSpool $spoolModel, AuthService $authService)
    {
        $this->spoolModel = $spoolModel;
        $this->authService = $authService;
    }
    
    /**
     * Export spools to CSV
     */
    public function exportSpoolsCsv(): void
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
            // Get all spools (no limit for export)
            $result = $this->spoolModel->getFilaments([], 1, 10000);
            $spools = $result['spools'];
            
            // Set headers for CSV download
            $filename = 'filament-spools-' . date('Y-m-d-H-i-s') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            
            // Create file handle
            $output = fopen('php://output', 'w');
            
            // Add BOM for proper UTF-8 handling in Excel
            fputs($output, "\xEF\xBB\xBF");
            
            // CSV Header
            $headers = [
                'ID',
                'UUID',
                'NFC UID',
                'Material',
                'Typ',
                'Farbe',
                'Durchmesser (mm)',
                'Gesamtgewicht (g)',
                'Restgewicht (g)',
                'Verbrauch (g)',
                'Restgewicht (%)',
                'Standort',
                'Kaufdatum',
                'Chargen-Nr.',
                'Notizen',
                'Erstellt am',
                'Zuletzt geändert'
            ];
            
            fputcsv($output, $headers, ';');
            
            // CSV Data
            foreach ($spools as $spool) {
                $usedWeight = $spool['total_weight'] - $spool['remaining_weight'];
                $remainingPercentage = $spool['total_weight'] > 0 
                    ? round(($spool['remaining_weight'] / $spool['total_weight']) * 100, 1)
                    : 0;
                
                $row = [
                    $spool['id'],
                    $spool['uuid'],
                    $spool['nfc_uid'] ?? '',
                    $spool['material'] ?? '',
                    $spool['type_name'] ?? '',
                    $spool['color_name'] ?? '',
                    $spool['diameter'] ?? '',
                    $spool['total_weight'],
                    $spool['remaining_weight'],
                    $usedWeight,
                    $remainingPercentage . '%',
                    $spool['location'] ?? '',
                    $spool['purchase_date'] ?? '',
                    $spool['batch_number'] ?? '',
                    $spool['notes'] ?? '',
                    $spool['created_at'],
                    $spool['updated_at'] ?? ''
                ];
                
                fputcsv($output, $row, ';');
            }
            
            fclose($output);
            exit;
            
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
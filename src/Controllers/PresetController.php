<?php

declare(strict_types=1);

namespace Filament\Controllers;

use Filament\Models\FilamentType;
use Filament\Models\Color;
use Filament\Models\SpoolPreset;
use Filament\Services\AuthService;
use Exception;

/**
 * Presets Controller for Types, Colors, etc.
 */
class PresetController
{
    private FilamentType $typeModel;
    private Color $colorModel;
    private SpoolPreset $presetModel;
    private AuthService $authService;
    
    public function __construct(FilamentType $typeModel, Color $colorModel, SpoolPreset $presetModel, AuthService $authService)
    {
        $this->typeModel = $typeModel;
        $this->colorModel = $colorModel;
        $this->presetModel = $presetModel;
        $this->authService = $authService;
    }
    
    /**
     * Get all filament types
     */
    public function getTypes(): void
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
            $types = $this->typeModel->getAllTypes();
            $this->jsonResponse(['types' => $types]);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get all colors
     */
    public function getColors(): void
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
            $colors = $this->colorModel->getAllColors();
            $this->jsonResponse(['colors' => $colors]);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get all spool presets
     */
    public function getPresets(): void
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
            $presets = $this->presetModel->getAllPresets();
            $this->jsonResponse(['presets' => $presets]);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get all presets at once
     */
    public function getAllPresets(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }

        try {
            // Load data from database tables
            $types = $this->typeModel->getAllTypes();
            $colors = $this->colorModel->getAllColors();
            
            $this->jsonResponse([
                'types' => $types,
                'colors' => $colors,
                'spool_presets' => []
            ]);
        } catch (Exception $e) {
            error_log("PresetController error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to load presets'], 500);
        }
    }
    
    /**
     * Create a new color
     */
    public function createColor(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['name']) || empty(trim($input['name']))) {
                $this->jsonResponse(['error' => 'Color name is required'], 400);
                return;
            }
            
            $colorName = trim($input['name']);
            
            // Check if color already exists
            $existingColors = $this->colorModel->getAllColors();
            foreach ($existingColors as $color) {
                if (strtolower($color['name']) === strtolower($colorName)) {
                    $this->jsonResponse(['error' => 'Color already exists'], 409);
                    return;
                }
            }
            
            // Create the color
            $colorId = $this->colorModel->createColor($colorName);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Color created successfully',
                'color' => [
                    'id' => $colorId,
                    'name' => $colorName
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("PresetController error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to create color'], 500);
        }
    }
    
    /**
     * Create a new type
     */
    public function createType(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['name']) || empty(trim($input['name']))) {
                $this->jsonResponse(['error' => 'Type name is required'], 400);
                return;
            }
            
            $typeName = trim($input['name']);
            
            // Check if type already exists
            $existingTypes = $this->typeModel->getAllTypes();
            foreach ($existingTypes as $type) {
                if (strtolower($type['name']) === strtolower($typeName)) {
                    $this->jsonResponse(['error' => 'Type already exists'], 409);
                    return;
                }
            }
            
            // Create the type
            $typeId = $this->typeModel->createType($typeName);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Type created successfully',
                'type' => [
                    'id' => $typeId,
                    'name' => $typeName
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("PresetController error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to create type'], 500);
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
<?php
/**
 * Einfacher NFC Lookup Service
 * Kann ohne Authentifizierung verwendet werden (nur für Scanner)
 * WICHTIG: Umgeht alle Security-Middleware für Scanner-Access
 */

// Session-Start um Security-Konflikte zu vermeiden
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Direkter Datenbankzugriff
$host = 'localhost';
$db = 'filament'; 
$user = 'filament';
$password = '7fLy2Ckr2NhyJYrA';

// CORS und Security Headers für Scanner
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('X-Robots-Tag: noindex');

// Handle OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['nfc_uid'])) {
    http_response_code(400);
    echo json_encode(['error' => 'nfc_uid is required']);
    exit;
}

$nfcUid = trim($input['nfc_uid']);
$scannerId = $input['scanner_id'] ?? 'unknown';

try {
    // Database connection
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Search for spool by NFC UID
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.uuid,
            s.material,
            s.total_weight,
            s.remaining_weight,
            s.location,
            s.created_at,
            s.nfc_uid,
            t.name as filament_type,
            c.name as color_name
        FROM filaments s
        LEFT JOIN filament_types t ON s.type_id = t.id
        LEFT JOIN colors c ON s.color_id = c.id
        WHERE s.nfc_uid = ? AND s.is_active = 1
    ");
    
    $stmt->execute([$nfcUid]);
    $spool = $stmt->fetch();
    
    // Log scan attempt
    $timestamp = date('Y-m-d H:i:s');
    $logStmt = $pdo->prepare("
        INSERT INTO nfc_scan_log (nfc_uid, scanner_id, found_spool_id, created_at)
        VALUES (?, ?, ?, ?)
    ");
    $logStmt->execute([$nfcUid, $scannerId, $spool ? $spool['id'] : null, $timestamp]);
    
    if ($spool) {
        echo json_encode([
            'found' => true,
            'spool' => [
                'id' => $spool['id'],
                'uuid' => $spool['uuid'],
                'material' => $spool['material'],
                'filament_type' => $spool['filament_type'],
                'color_name' => $spool['color_name'],
                'total_weight' => (float)$spool['total_weight'],
                'remaining_weight' => (float)$spool['remaining_weight'],
                'location' => $spool['location'],
                'created_at' => $spool['created_at']
            ],
            'scanner_id' => $scannerId,
            'timestamp' => $timestamp
        ]);
    } else {
        echo json_encode([
            'found' => false,
            'message' => 'no_spool_found',
            'nfc_uid' => $nfcUid,
            'scanner_id' => $scannerId,
            'timestamp' => $timestamp
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
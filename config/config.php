<?php

// Simple environment variable loader (without Dotenv)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

return [
    // Database configuration
    'database' => [
        'host' => $_ENV['DB_HOST'],
        'name' => $_ENV['DB_NAME'],
        'user' => $_ENV['DB_USER'],
        'password' => $_ENV['DB_PASSWORD'],
        'charset' => $_ENV['DB_CHARSET'],
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],

    // Application settings
    'app' => [
        'env' => $_ENV['APP_ENV'],
        'debug' => filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN),
        'url' => $_ENV['APP_URL'],
        'key' => $_ENV['APP_KEY'],
        'timezone' => 'Europe/Berlin',
    ],

    // Mail configuration
    'mail' => [
        'host' => $_ENV['MAIL_HOST'],
        'port' => (int)$_ENV['MAIL_PORT'],
        'username' => $_ENV['MAIL_USERNAME'],
        'password' => $_ENV['MAIL_PASSWORD'],
        'encryption' => $_ENV['MAIL_ENCRYPTION'],
        'from' => [
            'address' => $_ENV['MAIL_FROM_ADDRESS'],
            'name' => $_ENV['MAIL_FROM_NAME'],
        ],
    ],

    // Session settings
    'session' => [
        'name' => 'filament_session',
        'lifetime' => (int)$_ENV['SESSION_LIFETIME'],
        'secure' => filter_var($_ENV['SESSION_SECURE'], FILTER_VALIDATE_BOOLEAN),
        'httponly' => filter_var($_ENV['SESSION_HTTPONLY'], FILTER_VALIDATE_BOOLEAN),
        'samesite' => $_ENV['SESSION_SAMESITE'],
    ],

    // Security settings
    'security' => [
        'csrf_token_name' => $_ENV['CSRF_TOKEN_NAME'],
        'rate_limit' => [
            'requests' => (int)$_ENV['RATE_LIMIT_REQUESTS'],
            'window' => (int)$_ENV['RATE_LIMIT_WINDOW'],
        ],
    ],

    // NFC settings
    'nfc' => [
        'enabled' => filter_var($_ENV['NFC_SCANNER_ENABLED'], FILTER_VALIDATE_BOOLEAN),
        'live_updates' => filter_var($_ENV['NFC_LIVE_UPDATES'], FILTER_VALIDATE_BOOLEAN),
    ],

    // File storage paths
    'paths' => [
        'storage' => $_ENV['STORAGE_PATH'],
        'backups' => $_ENV['BACKUP_PATH'],
        'qr_codes' => $_ENV['QR_CODE_PATH'],
        'logs' => $_ENV['LOG_PATH'],
    ],
];
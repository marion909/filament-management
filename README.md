# 🧵 Filament Management System

Ein umfassendes System zur Verwaltung von 3D-Drucker Filamenten mit NFC-Integration und moderner Web-Oberfläche.

## ✨ Features

### 🌐 Web-Application
- **Filament-Spulen Verwaltung**: Vollständige CRUD-Operationen für Filament-Spulen
- **Material-Management**: PLA, PETG, ABS, TPU und benutzerdefinierte Materialien
- **Farb-System**: Vordefinierte Farben und "Andere" Funktion für individuelle Farben
- **Typ-System**: Standard-Typen und "Andere" Funktion für benutzerdefinierte Typen
- **NFC-Integration**: Verknüpfung von Spulen mit NFC-Tags (ACR122U kompatibel)
- **Erweiterte Filter**: Suche und Filterung nach Material, Farbe, Hersteller
- **Responsive Design**: Funktioniert auf Desktop und mobilen Geräten
- **UTF-8 Support**: Vollständige Unterstützung für deutsche Umlaute und Emojis

### 🏷️ NFC Desktop-App
- **Windows GUI**: Moderne tkinter-basierte Benutzeroberfläche
- **ACR122U Support**: Volle Unterstützung für ACR122U NFC-Reader
- **Real-time Scanning**: Live NFC-Tag Erkennung
- **Spool Information**: Detaillierte Anzeige von Spulen-Informationen
- **Clipboard Integration**: Einfaches Kopieren von NFC UIDs
- **API Integration**: Direkte Verbindung zur Web-Anwendung
- **Portable EXE**: Kompiliert als eigenständige Windows-Anwendung

## 🚀 Installation

### Voraussetzungen
- **PHP 8.0+** mit PDO MySQL Extension
- **MySQL/MariaDB** Datenbank
- **Web Server** (Apache/Nginx) oder PHP Built-in Server
- **ACR122U NFC Reader** (optional, für NFC-Funktionalität)
- **Python 3.8+** (für NFC Desktop-App)

### 1. Repository klonen
```bash
git clone https://github.com/marion909/filament-management.git
cd filament-management
```

### 2. Datenbank einrichten
```sql
-- Datenbank erstellen
CREATE DATABASE filament_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Schema mit allen Tabellen und Preset-Daten importieren
mysql -u root -p filament_management < schema.sql
```

### 3. Admin-User erstellen
```bash
# Admin-User erstellen (falls create_admin.php verfügbar)
php create_admin.php

# Oder manuell in der Datenbank (Passwort: admin123)
mysql -u root -p filament_management -e "INSERT INTO users (email, password_hash, name, role, verified_at, is_active) VALUES ('admin@example.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', NOW(), 1);"
```

### 4. Konfiguration
```php
// config/database.php anpassen
return [
    'host' => 'localhost',
    'dbname' => 'filament_management',
    'username' => 'ihr_username',
    'password' => 'ihr_passwort',
    'charset' => 'utf8mb4'
];
```

### 5. Web-Server starten
    'password' => 'ihr_passwort',
    'charset' => 'utf8mb4'
];
```

### 4. Web-Server starten
```bash
# PHP Built-in Server
php -S localhost:8000 -t public

# Oder Apache/Nginx DocumentRoot auf /public setzen
```

## 🖥️ NFC Desktop-App

### Installation
```bash
cd scanner
pip install -r requirements.txt
```

### GUI-Version kompilieren
```bash
# Portable EXE erstellen
python build_exe.py

# Oder einfach
build.bat
```

### Konfiguration
```ini
# scanner/config.ini
[DEFAULT]
API_URL = http://localhost:8000
SCANNER_ID = acr122u_001
ENABLE_SOUND = 1
DEBUG_MODE = 0
```

## 📱 Verwendung

### Web-Interface
1. **Spulen hinzufügen**: Neue Filament-Spulen mit allen Details anlegen
2. **NFC-Tags verknüpfen**: NFC UID bei der Spulen-Erstellung eingeben
3. **Material-Filter**: Spulen nach Material, Farbe oder Hersteller filtern
4. **Spulen bearbeiten**: Vollständige Bearbeitung aller Spulen-Eigenschaften

### NFC Scanner App
1. **Scanner starten**: ACR122U anschließen und "Scanner starten" klicken
2. **NFC-Tag scannen**: Tag an Reader halten
3. **Spulen-Info**: Automatische Anzeige der verknüpften Spule
4. **Unbekannte Tags**: UID kopieren für Registrierung in Web-App

## 🛠️ Technische Details

### Backend (PHP)
- **MVC-Architektur**: Saubere Trennung von Model, View und Controller
- **PDO**: Sichere Datenbankabfragen mit Prepared Statements
- **Router**: Flexibles Routing-System für API und Views
- **UTF-8**: Vollständige Unicode-Unterstützung
- **CSP**: Content Security Policy für erhöhte Sicherheit

### Frontend (JavaScript)
- **Vanilla JS**: Keine externen Dependencies
- **AJAX**: Asynchrone API-Kommunikation
- **Responsive**: Mobile-first Design Ansatz

### NFC Desktop-App (Python)
- **tkinter**: Native Windows GUI-Framework
- **pyscard**: Smart Card/NFC-Reader Integration
- **threading**: Non-blocking UI mit Background-Scanning
- **requests**: HTTP-API Kommunikation
- **PyInstaller**: Portable EXE-Kompilierung
- Git

### Setup

1. **Repository klonen**
   ```bash
   git clone <repository-url> /var/www/filament.neuhauser.cloud
   cd /var/www/filament.neuhauser.cloud
   ```

2. **Dependencies installieren**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Umgebungsvariblen konfigurieren**
   ```bash
   cp .env.example .env
   nano .env  # Datenbankdaten und andere Einstellungen anpassen
   ```

4. **Datenbank erstellen**
   ```bash
   mysql -u root -p
   CREATE DATABASE filament CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   GRANT ALL PRIVILEGES ON filament.* TO 'filament'@'localhost' IDENTIFIED BY 'your-password';
   FLUSH PRIVILEGES;
   EXIT;
   ```

5. **Schema und Seeds ausführen**
   ```bash
   php scripts/setup-database.php
   ```

6. **nginx konfigurieren** (siehe docs/nginx-example.conf)

7. **Permissions setzen**
   ```bash
   chown -R www-data:www-data /var/www/filament.neuhauser.cloud
   chmod -R 755 /var/www/filament.neuhauser.cloud
   chmod -R 777 storage/ logs/
   ```

## Entwicklung

### Lokale Entwicklung

```bash
# Dependencies installieren (mit dev)
composer install

# Tests ausführen
composer test

# Code Style Check
composer cs-check
composer cs-fix

# Lokaler Server (für Entwicklung)
php -S localhost:8000 -t public/
```

### API-Dokumentation

Die API-Endpunkte sind in der Roadmap dokumentiert. Beispiele:

- `POST /api/auth/login` - Benutzer-Login
- `GET /api/spools` - Filament-Spulen auflisten
- `POST /api/spools` - Neue Spule anlegen
- `POST /api/nfc/scan` - NFC-Tag scannen

### Datenbank-Schema

Siehe `scripts/schema.sql` für das vollständige Schema.

Haupttabellen:
- `users` - Benutzerkonten
- `filaments` - Filament-Spulen
- `usage_logs` - Verbrauchshistorie
- `filament_types` - Material-Presets
- `colors` - Farb-Presets

## NFC-Scanner Setup

### Hardware Scanner (empfohlen)

1. **Scanner-Script installieren**
   ```bash
   pip install nfcpy requests
   cp scripts/nfc-scanner.py /usr/local/bin/
   ```

2. **systemd Service erstellen**
   ```bash
   cp scripts/nfc-scanner.service /etc/systemd/system/
   systemctl enable nfc-scanner
   systemctl start nfc-scanner
   ```

### WebNFC (Android Chrome)

WebNFC ist direkt im Browser verfügbar - keine zusätzliche Installation nötig.

## Backup & Monitoring

### Automatische Backups

```bash
# Cron-Job für tägliche Backups
0 2 * * * /usr/local/bin/backup-filament-db.sh
```

### Monitoring

Health-Check Endpoint: `/api/status`

## Lizenz

Dieses Projekt steht unter der MIT-Lizenz.

## Support

Bei Fragen oder Problemen erstelle bitte ein Issue im Repository.
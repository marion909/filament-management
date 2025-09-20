# Production Deployment Guide - Filament Management System

## Übersicht
Diese Anleitung führt Sie durch den kompletten Deployment-Prozess der Filament Management System Anwendung auf einem Produktionsserver.

## Voraussetzungen

### Server Requirements
- Ubuntu 20.04+ oder Debian 11+ 
- Mindestens 2GB RAM
- 10GB freier Speicherplatz
- Root-Zugriff oder sudo-Berechtigung
- Internet-Verbindung für Package-Installation

### DNS Configuration
Stellen Sie sicher, dass Ihre Domain bereits auf die Server-IP zeigt:
```bash
# Überprüfen Sie die DNS-Auflösung
dig filament.neuhauser.cloud
nslookup filament.neuhauser.cloud
```

## Schritt 1: Server-Vorbereitung

### 1.1 Verbindung zum Server
```bash
ssh root@your-server-ip
# oder mit sudo-User:
ssh username@your-server-ip
```

### 1.2 System aktualisieren
```bash
apt update && apt upgrade -y
```

### 1.3 Erforderliche Pakete installieren
```bash
apt install -y git curl wget unzip
```

## Schritt 2: Code-Deployment

### 2.1 Repository klonen
```bash
cd /mnt/HC_Volume_101973258/
git clone https://github.com/your-username/filament.neuhauser.cloud.git
cd filament.neuhauser.cloud
```

### 2.2 Deploy-Script ausführbar machen
```bash
chmod +x deployment/deploy-production.sh
```

### 2.3 Deployment starten
```bash
./deployment/deploy-production.sh
```

Das Script führt automatisch folgende Schritte aus:
- ✅ System-Packages aktualisieren
- ✅ Nginx und PHP 8.2 installieren
- ✅ Verzeichnisstruktur erstellen
- ✅ Berechtigungen setzen
- ✅ PHP-FPM konfigurieren
- ✅ Nginx Virtual Host einrichten
- ✅ SSL-Zertifikat installieren
- ✅ Firewall konfigurieren
- ✅ Log-Rotation einrichten
- ✅ Backup-System aktivieren
- ✅ Monitoring aufsetzen

## Schritt 3: Datenbank-Setup

### 3.1 MySQL/MariaDB installieren (falls nicht vorhanden)
```bash
apt install -y mariadb-server
mysql_secure_installation
```

### 3.2 Datenbank erstellen
```bash
mysql -u root -p
```

```sql
CREATE DATABASE filament_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'filament_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON filament_management.* TO 'filament_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3.3 Datenbank-Konfiguration anpassen
```bash
nano /mnt/HC_Volume_101973258/filament.neuhauser.cloud/config/database.php
```

Aktualisieren Sie die Datenbankverbindung:
```php
return [
    'host' => 'localhost',
    'database' => 'filament_management',
    'username' => 'filament_user',
    'password' => 'secure_password_here',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];
```

### 3.4 Datenbank-Schema importieren
```bash
cd /mnt/HC_Volume_101973258/filament.neuhauser.cloud
mysql -u filament_user -p filament_management < database/schema.sql
mysql -u filament_user -p filament_management < database/initial-data.sql
```

## Schritt 4: Anwendungs-Konfiguration

### 4.1 Session-Konfiguration
```bash
nano config/session.php
```

Stellen Sie sicher, dass die Session-Einstellungen korrekt sind:
```php
return [
    'session_name' => 'FILAMENT_SESSION',
    'lifetime' => 3600,
    'path' => '/mnt/HC_Volume_101973258/filament.neuhauser.cloud/storage/sessions',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
];
```

### 4.2 Logging-Konfiguration
```bash
nano config/logging.php
```

### 4.3 Security-Konfiguration
```bash
nano config/security.php
```

Überprüfen Sie die Security-Einstellungen:
```php
return [
    'csrf_protection' => true,
    'rate_limiting' => true,
    'content_security_policy' => true,
    'input_validation' => true,
    'threat_detection' => true,
    'security_headers' => true
];
```

## Schritt 5: Berechtigungen und Sicherheit

### 5.1 Dateiberechtigungen setzen
```bash
cd /mnt/HC_Volume_101973258/filament.neuhauser.cloud
chown -R www-data:www-data .
chmod -R 755 .
chmod -R 777 storage/
chmod 600 config/database.php
chmod 600 config/session.php
```

### 5.2 Sensible Dateien schützen
```bash
# .htaccess für zusätzlichen Schutz (falls Apache als Fallback)
cat > .htaccess << EOF
<Files "*.php">
    <RequireAll>
        Require all denied
    </RequireAll>
</Files>

<Directory "public">
    <RequireAll>
        Require all granted
    </RequireAll>
</Directory>
EOF
```

## Schritt 6: NFC-Scanner Setup (Optional)

### 6.1 Python-Dependencies installieren
```bash
apt install -y python3-pip python3-venv
pip3 install nfc requests python-daemon
```

### 6.2 NFC-Service einrichten
```bash
cp scripts/nfc-scanner.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable nfc-scanner
systemctl start nfc-scanner
```

## Schritt 7: Testing und Verification

### 7.1 Nginx-Konfiguration testen
```bash
nginx -t
systemctl status nginx
```

### 7.2 PHP-FPM Status prüfen
```bash
systemctl status php8.2-fpm
```

### 7.3 SSL-Zertifikat verifizieren
```bash
certbot certificates
```

### 7.4 Website-Funktionalität testen
```bash
# Basis-Connectivity
curl -I https://filament.neuhauser.cloud

# API-Endpoints testen
curl -X GET https://filament.neuhauser.cloud/api/filaments

# Security Headers prüfen
curl -I https://filament.neuhauser.cloud | grep -E "(X-|Strict-|Content-Security)"
```

### 7.5 Frontend-Funktionen testen
Öffnen Sie die Website im Browser und testen Sie:
- [ ] Login-Funktionalität
- [ ] Filament-Inventar laden
- [ ] CRUD-Operationen
- [ ] Admin-Panel Zugriff
- [ ] NFC-Scanner Integration (falls aktiviert)
- [ ] Responsive Design
- [ ] CSRF-Protection
- [ ] Rate Limiting

## Schritt 8: Monitoring und Wartung

### 8.1 Log-Files überwachen
```bash
# Application Logs
tail -f /mnt/HC_Volume_101973258/filament.neuhauser.cloud/storage/logs/app.log

# Nginx Access Logs  
tail -f /var/log/nginx/filament.neuhauser.cloud.access.log

# Nginx Error Logs
tail -f /var/log/nginx/filament.neuhauser.cloud.error.log

# PHP-FPM Logs
tail -f /mnt/HC_Volume_101973258/filament.neuhauser.cloud/storage/logs/php-fpm.log
```

### 8.2 Backup-System testen
```bash
# Manuellen Backup ausführen
/usr/local/bin/backup-filament.neuhauser.cloud.sh

# Backup-Status prüfen
ls -la /mnt/HC_Volume_101973258/filament.neuhauser.cloud/storage/backups/
```

### 8.3 Monitoring-Dashboard
```bash
# System-Status prüfen
systemctl status nginx php8.2-fpm mariadb

# Monitoring-Script testen
/usr/local/bin/monitor-filament.neuhauser.cloud.sh
```

## Troubleshooting

### Häufige Probleme und Lösungen

#### Problem: 404 Fehler
```bash
# Nginx-Konfiguration prüfen
nginx -t
cat /etc/nginx/sites-enabled/filament.neuhauser.cloud.conf

# Document Root überprüfen
ls -la /mnt/HC_Volume_101973258/filament.neuhauser.cloud/public/
```

#### Problem: 500 Internal Server Error
```bash
# PHP-FPM Logs prüfen
tail -f /mnt/HC_Volume_101973258/filament.neuhauser.cloud/storage/logs/php-fpm.log

# Application Error Logs
tail -f /mnt/HC_Volume_101973258/filament.neuhauser.cloud/storage/logs/app.log

# Berechtigungen prüfen
ls -la /mnt/HC_Volume_101973258/filament.neuhauser.cloud/
```

#### Problem: SSL-Zertifikat Fehler
```bash
# Zertifikat-Status prüfen
certbot certificates

# Erneuern falls nötig
certbot renew --dry-run
```

#### Problem: Datenbank-Verbindung
```bash
# MySQL-Service prüfen
systemctl status mariadb

# Datenbankverbindung testen
mysql -u filament_user -p filament_management -e "SHOW TABLES;"
```

### Performance-Optimierung

#### PHP-FPM Tuning
```bash
nano /etc/php/8.2/fpm/pool.d/filament.neuhauser.cloud.conf
```

Optimieren Sie je nach Server-Ressourcen:
```ini
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
```

#### Nginx Caching
Zusätzliche Caching-Header in der Nginx-Konfiguration:
```nginx
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    add_header Vary Accept-Encoding;
}
```

## Wartungs-Checkliste

### Täglich
- [ ] Log-Files auf Errors prüfen
- [ ] Backup-Status verifizieren
- [ ] Website-Erreichbarkeit testen

### Wöchentlich
- [ ] SSL-Zertifikat Status prüfen
- [ ] Security Updates installieren
- [ ] Performance-Metriken analysieren

### Monatlich
- [ ] Vollständiger Security-Audit
- [ ] Backup-Restore testen
- [ ] Database-Optimierung
- [ ] Log-Files archivieren

## Support und Wartung

### Wichtige Befehle
```bash
# Services neustarten
systemctl restart nginx php8.2-fpm mariadb

# Logs in Echtzeit verfolgen
multitail /var/log/nginx/*.log /mnt/HC_Volume_101973258/filament.neuhauser.cloud/storage/logs/*.log

# SSL-Zertifikat erneuern
certbot renew

# Backup erstellen
/usr/local/bin/backup-filament.neuhauser.cloud.sh
```

### Monitoring URLs
- Hauptseite: https://filament.neuhauser.cloud
- API Health: https://filament.neuhauser.cloud/api/health
- Admin Panel: https://filament.neuhauser.cloud/admin

---

## Deployment erfolgreich abgeschlossen! 🎉

Die Anwendung ist jetzt produktiv verfügbar unter:
**https://filament.neuhauser.cloud**

Alle Sicherheitsmaßnahmen sind aktiviert und das System ist für den Produktionsbetrieb optimiert.
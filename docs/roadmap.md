# Roadmap: Filament-Lagerverwaltung (PHP + HTML) für `filament.neuhauser.cloud`

> Ziel: Webanwendung (PHP + HTML/JS/CSS) auf nginx zur Verwaltung von 3D-Druck-Filamenten mit NFC-Integration. Pflicht: Benutzer-Authentifizierung + Registrierung. Voreingestellte Auswahl der gängigsten Filamente, Typen, Farben.

---

Database:

  Name: filament
  User: filament
  Passwort: 7fLy2Ckr2NhyJYrA
  Url: localhost

## Inhaltsverzeichnis
1. Übersicht & Ziele
2. Systemanforderungen
3. Presets: Materialien, Typen, Farben, Durchmesser, Spulengrößen
4. Datenbank-Schema (SQL)
5. API-Übersicht (Detailierte Endpunkte)
6. Dateisystem / Projektstruktur
7. NFC-Integration — Scanner-Workflow
8. Frontend-Seiten & Verhalten
9. Sicherheit & Best Practices
10. Deployment (nginx, PHP-FPM, TLS, Cron)
11. Tests, Monitoring & Backups
12. Detaillierter Fahrplan (Tasks mit Prioritäten)

---

## 1) Übersicht & Ziele
- Leichtgewichtige PHP-App, die auf `filament.neuhauser.cloud` unter nginx läuft.
- Kernfunktionen:
  - Benutzerregistrierung, E-Mail-Verifizierung, Login/Logout, Passwortreset
  - CRUD für Filament-Spulen (Bindung an NFC-Tags)
  - Presets für Material, Typ, Farbe, Durchmesser
  - Bestandsverwaltung (Restgewicht, Verbrauchs-Logs)
  - Dashboard & Warnungen (z. B. Restgewicht < X g)
  - Adminbereich (Benutzerverwaltung, Preset-Verwaltung, Backups)
  - NFC-Scanner-Anbindung via lokalem Dienst (Script) oder WebNFC (sofern Browser unterstützt)

---

## 2) Systemanforderungen
- Server: Linux (Debian/Ubuntu empfohlen)
- nginx
- PHP 8.0+ mit FPM
- MySQL / MariaDB (oder PostgreSQL als optional)
- Composer (für PHP-Bibliotheken)
- Node.js (nur wenn du Frontend-Buildtools nutzen möchtest; optional)
- Certbot (Let's Encrypt) für TLS
- Optional: `systemd` für Scanner-Service

---

## 3) Presets (Voreinstellungen)
**Material (Beispiele):**
- PLA
- PETG
- ABS
- TPU (flexibel)
- Nylon
- ASA
- PVA (löslich)
- PC (Polycarbonat)
- Holz-Fill (Wood)
- Carbon-Fill (Verstärkt)

**Typ / Eigenschaft:**
- Standard
- Flexibel
- Hochtemperatur
- Faserverstärkt
- Löslich / Stützmaterial

**Durchmesser (gängig):**
- 1.75 mm
- 2.85 mm / 3.00 mm

**Spulengrößen (gängig):**
- 250 g
- 500 g
- 750 g
- 1.0 kg

**Farben (Beispiele, auch als Auswahl in UI):**
- Schwarz
- Weiß
- Natur (Natural)
- Grau
- Rot
- Blau
- Grün
- Gelb
- Orange
- Lila
- Braun
- Pink
- Transparent

> Diese Presets werden beim ersten Deploy in die DB eingespeist (Seed-Script). Admin kann später editieren.

---

## 4) Datenbank-Schema (MySQL/MariaDB)

```sql
-- users
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(150),
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  verified_at DATETIME NULL,
  verification_token VARCHAR(128) NULL,
  reset_token VARCHAR(128) NULL,
  reset_expires DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME NULL
) ENGINE=InnoDB;

-- filament_types (Preset-Liste)
CREATE TABLE filament_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  diameter VARCHAR(20),
  description TEXT NULL
) ENGINE=InnoDB;

-- colors (Preset-Liste)
CREATE TABLE colors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  hex VARCHAR(7) NULL
) ENGINE=InnoDB;

-- filaments / spools
CREATE TABLE filaments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  nfc_uid VARCHAR(128) UNIQUE NULL,
  type_id INT NOT NULL,
  material VARCHAR(100) NOT NULL,
  color_id INT NULL,
  total_weight INT NOT NULL,
  remaining_weight INT NOT NULL,
  diameter VARCHAR(20),
  purchase_date DATE NULL,
  location VARCHAR(255) NULL,
  batch_number VARCHAR(255) NULL,
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (type_id) REFERENCES filament_types(id) ON DELETE RESTRICT,
  FOREIGN KEY (color_id) REFERENCES colors(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- usage log
CREATE TABLE usage_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filament_id INT NOT NULL,
  used_grams INT NOT NULL,
  job_name VARCHAR(255) NULL,
  job_id VARCHAR(255) NULL,
  note TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (filament_id) REFERENCES filaments(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- presets for spool sizes / diameters if desired
CREATE TABLE spool_presets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  grams INT NOT NULL
) ENGINE=InnoDB;
```

> Zusätzlich: Seed-Queries (INSERTs) für filament_types und colors (siehe Abschnitt "Seeds" unten).

---

## 5) API-Übersicht (Detaillierte Endpunkte)
> Authentifizierung: Session-basiert (empfohlen) mit HttpOnly-Cookie oder optional JWT für externe Clients. Alle `/api/*`-Endpunkte erwarten `Content-Type: application/json`.

### A) Auth (Öffentlich)
- **POST** `/api/auth/register`
  - Body: `{ "email":"...", "name":"...", "password":"..." }`
  - Verhalten: erstellt User (is_active=0 until verified), sendet Verifikations-Mail mit Token
  - Response: `201 { "message":"verification_sent" }`
  - Fehler: `409` wenn Email existiert, `400` bei invalid data

- **GET** `/api/auth/verify?token=...`
  - Verifiziert Account (setzt `verified_at`), löscht Token
  - Response: `200 { "message":"verified" }` oder `400`/`410` bei Fehler

- **POST** `/api/auth/login`
  - Body: `{ "email":"...", "password":"..." }`
  - Behavior: prüft `verified_at` und `is_active`. Auf Erfolg: PHP-Session setzen (HttpOnly cookie). Antwort: `200 { "user":{...} }`
  - Fehler: `401` bei falschen Zugangsdaten

- **POST** `/api/auth/logout`
  - Behavior: Session zerstören. `200 {"message":"logged_out"}`

- **POST** `/api/auth/request-reset`
  - Body: `{ "email":"..." }` -> sendet Reset-Email mit Token

- **POST** `/api/auth/reset-password`
  - Body: `{ "token":"...", "password":"..." }`
  - Response: `200` oder `400`


### B) Benutzer (auth requ.)
- **GET** `/api/user/me`
  - Auth: Session
  - Response: `200 { "id":..., "email":"...","name":"...","role":"user" }`

- **PUT** `/api/user/me`
  - Body: fields to update (name, location, etc.)


### C) Filament CRUD (auth requ.)
- **GET** `/api/spools`  (Query: `?page=1&limit=50&material=PLA&type=...&color=...&location=...`)
  - Response: Pagination + array

- **GET** `/api/spools/:id`
  - einzelne Spule

- **POST** `/api/spools`
  - Body (JSON):
    ```json
    {
      "uuid":"optional-uuid",
      "nfc_uid":"optional-nfc",
      "type_id": 1,
      "material":"PLA",
      "color_id": 2,
      "total_weight": 1000,
      "remaining_weight": 1000,
      "diameter":"1.75",
      "purchase_date":"2025-09-01",
      "location":"Regal A",
      "notes":"..."
    }
    ```
  - Response: `201 { "id": 123, "uuid":"..." }`

- **PUT** `/api/spools/:id`
  - Update-Felder

- **DELETE** `/api/spools/:id` (soft-delete möglich via `is_active=false`)

- **POST** `/api/spools/:id/adjust`
  - Body: `{ "delta_grams": -50, "reason":"Druckauftrag XYZ", "job_name":"Thingy" }`
  - Behavior: schreibt in `usage_logs` und passt `remaining_weight` an. Antwort: `200 { "remaining": 950 }`

- **POST** `/api/spools/:id/bind-nfc`
  - Body: `{ "nfc_uid":"..." }` oder die Endpoint kann auch `nfc_uid` in Query akzeptieren
  - Auth: nur `user` oder `admin` (je nach Policy)
  - Response `200` oder `409` wenn UID bereits gebunden

- **POST** `/api/spools/:id/unbind-nfc` (optional)

- **GET** `/api/spools/:id/qrcode` -> liefert PNG/PNG-Data-URL oder `application/json` mit `data:`-URI


### D) NFC-Scanner / Realtime
- **POST** `/api/nfc/scan`
  - Body: `{ "nfc_uid":"...","scanner_id":"ops-laptop-01" }`
  - Behavior: Sucht `filaments` mit `nfc_uid`. Antwort:
    - `200 { "found": true, "spool": {...} }` oder
    - `404 { "found": false, "message":"no_spool" }`
  - Scanner-Skript lokal (systemd) liest Tag und POSTet hierhin.

- **POST** `/api/nfc/register` (falls Scanner neue Spule anlegen soll)
  - Body: minimal fields + `nfc_uid` -> erstellt spool-serverseitig

- **WS/SSE** `/api/nfc/stream` oder `/sse/nfc` (Server-Sent Events)
  - Frontend kann verbunden werden um Live-Scans zu empfangen (nur für eingeloggte Benutzer)


### E) Admin / Verwaltung (admin role required)
- **GET** `/api/admin/users` (list)
- **GET** `/api/admin/users/:id`
- **PUT** `/api/admin/users/:id` (role, is_active)
- **DELETE** `/api/admin/users/:id` (soft-delete)

- **GET/POST/PUT/DELETE** `/api/admin/presets/types`
- **GET/POST/PUT/DELETE** `/api/admin/presets/colors`
- **GET/POST/PUT/DELETE** `/api/admin/presets/spool_presets`

- **POST** `/api/admin/backup` -> startet DB-Dump, speichert in `/var/backups/filament/` und bietet Download-Link (oder S3 Upload)

- **GET** `/api/admin/stats` -> Verbrauch pro Monat, Spulen pro Typ, Low-stock Alerts


### F) Export / Import
- **GET** `/api/export/spools.csv` (Admin/User mit Permission) -> CSV-Download
- **POST** `/api/import/spools` -> CSV-Upload zum Batch-Import


### Fehlercodes / Response-Format (Standard)
- `200` OK
- `201` Created
- `400` Bad Request / Validation Error `{ "error":"...", "fields":{...} }`
- `401` Unauthorized
- `403` Forbidden
- `404` Not Found
- `409` Conflict
- `500` Server Error

Antwortformat (Beispiel):
```json
{ "success":true, "data":{...}, "message":"optional" }
```

---

## 6) Dateisystem / Projektstruktur (Empfehlung)
```
/var/www/filament.neuhauser.cloud/
├── public/                 # nginx root -> enthält index.php, assets
│   ├── index.php
│   ├── css/
│   ├── js/
│   └── assets/
├── src/
│   ├── controllers/        # api controllers (auth, spools, admin, nfc)
│   ├── models/             # DB-Model wrappers (PDO)
│   ├── services/           # mailer, backup, nfc, qrcode
│   ├── views/              # php view templates (header/footer)
│   └── middleware/         # auth, csrf, rate-limit
├── config/
│   └── config.php
├── scripts/                # scanner client, seed scripts
├── storage/
│   ├── backups/
│   └── qrcodes/
├── logs/
└── vendor/                 # composer
```

**Beispiel: wichtige PHP-Dateien**
- `public/index.php` – Router / Front-Controller
- `src/controllers/AuthController.php`
- `src/controllers/SpoolController.php`
- `src/controllers/AdminController.php`
- `src/services/ScannerPoster.php` – endpoint client helper

---

## 7) NFC-Integration — Scanner-Workflow
**Option A — Desktop/Server-Lokal (empfohlen für stabilen Betrieb):**
1. NFC-Reader an einem Rechner (z. B. Raspberry Pi/PC) angeschlossen.
2. Kleines Script (Python mit `nfcpy`, Node mit `nfc-pcsc` oder C++) liest Tag UID.
3. Script sendet HTTP POST an `/api/nfc/scan` mit `nfc_uid` und einem `scanner_id`.
4. Backend antwortet mit Spulendaten oder `404`.
5. Frontend kann per SSE/WS Live-Event anzeigen.
6. Wenn Spule nicht existiert, Scanner kann interaktiven Dialog starten oder Admin informiert werden.

**Systemd-Service (Beispielname):** `nfc-scanner.service` startet `scripts/nfc-scanner.py` beim Boot.

**Option B — Smartphone (WebNFC)**
- Chrome on Android unterstützt WebNFC; Browser kann UID lesen und POST an Server senden.
- Einschränkung: funktioniert nicht auf iOS (weitere Tests nötig).

**API-Flow Beispiele**
- Scan -> POST `/api/nfc/scan { nfc_uid }` -> 200 + spool -> UI öffnet Spool-Detail
- Neu -> POST `/api/nfc/register { nfc_uid, material, type_id, total_weight }` -> erstellt Eintrag

---

## 8) Frontend-Seiten & Verhalten
- **Login / Registrierung / Passwortreset / E-Mail-Verifizierung**
- **Dashboard**: Summaries, Low-stock alerts, Schnellzugriff auf Scanner-Live
- **Spool-Übersicht**: Suchfeld + Filter (Material, Typ, Farbe, Standort)
- **Spool-Detail**: Restgewicht, Verbrauchshistorie, NFC-UID (bind/unbind), QR-Code
- **Spule anlegen (Wizard)**: Möglichkeit NFC zu scannen, Felder automatisch füllen
- **Admin**: Benutzerverwaltung, Presets, Backups/Import/Export

UX-Hinweis: Nach NFC-Scan sollte die UI die Detailansicht der Spule automatisch öffnen (SSE/WS push oder Polling).

---

## 9) Sicherheit & Best Practices
- HTTPS (TLS) verpflichtend.
- Prepared Statements (PDO) / keine direkte String-Konkatenation in SQL.
- Passwörter mit `password_hash()` (Argon2 oder BCRYPT).
- Sessions: `session.cookie_httponly = 1`, `session.cookie_samesite = 'Lax'` oder `Strict`, `session.cookie_secure = 1`.
- CSRF-Schutz für alle POST/PUT/DELETE (Token in Forms / Double submit cookie für AJAX).
- XSS-Schutz: Ausgabe escapen (`htmlspecialchars`), Content-Security-Policy (CSP).
- Rate-Limiting (Login / Register / Reset) — einfache in-app Begrenzung oder nginx/fail2ban.
- Input-Validation + Server-Side Checks (z. B. Gewicht muss >= 0)
- E-Mail-Verification vor Aktivierung
- Admin-Aktionen audit-loggen (wer hat was geändert)
- Logging (rotierende Logs) und Health-Checks

---

## 10) Deployment (Beispiel nginx vHost)
**Beispiel nginx-Server-Block**

```nginx
server {
  listen 80;
  server_name filament.neuhauser.cloud;
  root /var/www/filament.neuhauser.cloud/public;

  index index.php index.html;

  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }

  location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.1-fpm.sock; # anpassen
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  }

  location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
    expires 30d;
    add_header Cache-Control "public";
  }
}
```

**TLS / Let's Encrypt**
- Certbot für automatisches Zertifikat und Renew (cron).

**Cronjobs / systemd**
- Cron: täglicher DB-Dump (z. B. `mysqldump`), rotieren/aufbewahrung
- systemd: `nfc-scanner.service` für den Scanner

---

## 11) Tests, Monitoring & Backups
- Unit-Tests: PHPUnit (Model-/Service-Layer)
- Integration-Tests: Postman / Newman Tests für API-Endpunkte
- E2E: Cypress (optional)
- Monitoring: einfache Health-Endpoint `/api/status` und externes Pingdom/Prometheus optional
- Backups: täglicher `mysqldump` nach `/var/backups/filament/`, Wochen- und Monatsrotation. Optional: Upload zu S3.

---

## 12) Detaillierter Fahrplan (Tasks)
> Priorität: **MUST** = unbedingt zuerst, **SHOULD** = danach, **COULD** = nice-to-have

### Phase 0 — Vorbereitung (1-2 Tage) — MUST
- [ ] Server vorbereiten (nginx + PHP-FPM + MariaDB). Sicherstellen, dass Subdomain auf IP zeigt.
- [ ] Repository anlegen (git), `.env` Beispiel, `README.md` führen.
- [ ] Composer init, Ordnerstruktur anlegen.

### Phase 1 — Basis-Backend & DB (2-4 Tage) — MUST
- [ ] DB-Schema erstellen (Nutze die SQL oben). Seed-Script für Presets (material/types/colors/spool_presets).
- [ ] DB-Wrapper (PDO) mit Prepared Statements.
- [ ] Auth: Register + Email-Verifikation (E-Mail via SMTP), Login (Session), Logout.
- [ ] Middlewares: Auth-Check, Role-Check.

### Phase 2 — CRUD & API (3-5 Tage) — MUST
- [ ] Implementiere Filament CRUD Endpoints (siehe API-Liste).
- [ ] Usage Log & Adjust Endpoint.
- [ ] Export CSV Endpoint.
- [ ] Unit-Tests für Model-Methoden.

### Phase 3 — Frontend (3-6 Tage) — MUST
- [ ] Basis-UI: Login/Register, Dashboard, Spool-List, Spool-Detail.
- [ ] Add-Spool Wizard mit NFC-Feld.
- [ ] SSE/WS Client für Live-Scans (falls Scanner verfügbar).
- [ ] Responsive CSS (Bootstrap oder Tailwind).

### Phase 4 — NFC & Scanner (2-4 Tage) — SHOULD
- [ ] Script für lokalen Scanner (Python or Node) schreiben.
- [ ] systemd Service + Autostart konfigurieren.
- [ ] Implementiere `/api/nfc/scan` und Live-Events.

### Phase 5 — Admin & Presets (2 Tage) — SHOULD
- [ ] Admin-Panels: Benutzerverwaltung, Presets (Material/Color/Types), Backups-Trigger.
- [ ] Sicherstellen: Admin-only Endpoints abgesichert.

### Phase 6 — Sicherheit / Hardening (1-2 Tage) — MUST
- [ ] CSRF-Token, Rate-Limit, CSP-Header, HTTPS erzwingen.
- [ ] Penetration-Check (einfach): SQLi, XSS, Auth-Bypass Tests.

### Phase 7 — Tests, Backup & Deployment (2-3 Tage) — MUST
- [ ] E2E Tests / Postman Collection.
- [ ] Cronjob Backup konfigurieren.
- [ ] Deploy auf Produktionsserver + LetsEncrypt.

### Phase 8 — Extras / Verbesserungen (optional) — COULD
- [ ] Mobile PWA (WebNFC Support auf Android)
- [ ] QR-Code Label Druck (QR + Text) direkt aus UI
- [ ] Integration mit Slicer (Cura/Prusa) um Verbrauch automatisch zu melden
- [ ] CSV-Import für Massenanlegen

---

## Seeds (Beispiel SQL für Materialien & Farben)
```sql
INSERT INTO filament_types (name, diameter, description) VALUES
('PLA','1.75','Polylactic Acid - einfach zu drucken'),
('PETG','1.75','Gute Festigkeit, feuchtebeständig'),
('ABS','1.75','Hochtemperaturbeständig'),
('TPU','1.75','Flexibel'),
('Nylon','1.75','Sehr zäh');

INSERT INTO colors (name, hex) VALUES
('Schwarz','#000000'),
('Weiß','#FFFFFF'),
('Grau','#808080'),
('Rot','#FF0000'),
('Blau','#0000FF'),
('Grün','#00FF00'),
('Gelb','#FFFF00'),
('Orange','#FFA500'),
('Lila','#800080'),
('Braun','#A52A2A');

INSERT INTO spool_presets (name, grams) VALUES
('250g',250),('500g',500),('750g',750),('1kg',1000);
```

---

## Beispiel: Minimaler Scanner-POST (cURL)
```bash
curl -X POST https://filament.neuhauser.cloud/api/nfc/scan \
  -H "Content-Type: application/json" \
  -d '{"nfc_uid":"04A2241B2C3380","scanner_id":"rpi-01"}'
```

Antwort (wenn gefunden):
```json
{ "found": true, "spool": { "id": 42, "material":"PLA", "remaining_weight": 820 } }
```

---

## Letzte Hinweise
- Beginne minimal (Auth + Spool CRUD + NFC scan endpoint) und iteriere. Das erlaubt dir, früh mit dem realen NFC-Reader zu testen.
- Halte Seed-Listen (Material/Colors) editierbar im Admin-Panel, damit du später neue Materialien ergänzen kannst.
- Wenn du möchtest, kann ich dir als nächsten Schritt automatisch generierte PHP-Controller-Vorlagen oder das `CREATE TABLE` SQL als separate Datei erzeugen.

---

*Ende der Roadmap*


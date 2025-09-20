# Filament NFC Scanner - Portable Version

## Installation
1. Stellen Sie sicher, dass der ACR122U Treiber installiert ist
2. Laden Sie den Treiber von: https://www.acs.com.hk/de/driver/3/acr122u-nfc-reader/
3. Schließen Sie den ACR122U USB NFC Reader an

## Verwendung
1. Doppelklick auf "filament_nfc_scanner.exe"
2. Das Programm startet automatisch
3. Halten Sie NFC Tags an den Reader
4. Spule-Informationen werden angezeigt
5. Strg+C zum Beenden

## Konfiguration
Bearbeiten Sie "config.ini" um Einstellungen zu ändern:
- API_URL: URL Ihrer Filament-App
- SCANNER_ID: Eindeutige Scanner-Kennung
- ENABLE_SOUND: Töne aktivieren/deaktivieren

## Problemlösung
- Stellen Sie sicher, dass keine andere Software den Reader verwendet
- Probieren Sie einen anderen USB-Port
- Prüfen Sie, ob der Treiber korrekt installiert ist
- Bei Problemen starten Sie das Programm über die Eingabeaufforderung

## System-Anforderungen
- Windows 10/11
- ACR122U NFC Reader
- USB-Port
- Internetverbindung für API-Zugriff

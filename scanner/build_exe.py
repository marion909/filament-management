"""
Build-Script für NFC Scanner EXE mit PyInstaller
"""

import os
import sys
import subprocess
import shutil
from pathlib import Path

def check_python():
    """Python Installation überprüfen"""
    try:
        result = subprocess.run([sys.executable, "--version"], capture_output=True, text=True)
        print(f"✅ Python gefunden: {result.stdout.strip()}")
        return True
    except:
        print("❌ Python nicht gefunden")
        return False

def install_dependencies():
    """Required packages installieren"""
    packages = [
        'pyinstaller',
        'requests',
        'pyscard',
        'Pillow'  # Für Icon-Erstellung
    ]
    
    print("📦 Installiere benötigte Pakete...")
    for package in packages:
        try:
            print(f"   Installiere {package}...")
            result = subprocess.run([
                sys.executable, "-m", "pip", "install", package
            ], capture_output=True, text=True, check=True)
            print(f"   ✅ {package} installiert")
        except subprocess.CalledProcessError as e:
            print(f"   ❌ Fehler bei {package}: {e}")
            return False
    
    return True

def create_icon():
    """Icon für die Anwendung erstellen"""
    print("\n🎨 Erstelle Anwendungs-Icon...")
    
    try:
        from create_icon import main as create_icon_main
        if create_icon_main():
            return True
    except Exception as e:
        print(f"   ⚠️  Icon-Erstellung fehlgeschlagen: {e}")
    
    return False

def build_exe():
    """EXE mit PyInstaller erstellen"""
    print("\n🔨 Erstelle GUI EXE mit PyInstaller...")
    
    script_path = "nfc_scanner_gui.py"
    
    # Icon verwenden falls vorhanden
    icon_param = "scanner_icon.ico" if os.path.exists("scanner_icon.ico") else "NONE"
    
    # PyInstaller Optionen für GUI
    cmd = [
        sys.executable, "-m", "PyInstaller",
        "--onefile",  # Einzelne EXE-Datei
        "--windowed",  # GUI-Anwendung (kein Console-Fenster)
        "--name", "FilamentNFCScanner",  # EXE-Name
        "--icon", icon_param,  # Icon verwenden
        "--add-data", "nfc_scanner_gui.py;.",  # Script einbetten
        "--hidden-import", "smartcard",
        "--hidden-import", "smartcard.System",
        "--hidden-import", "smartcard.util", 
        "--hidden-import", "smartcard.CardMonitoring",
        "--hidden-import", "smartcard.CardType",
        "--hidden-import", "smartcard.CardRequest",
        "--hidden-import", "smartcard.Exceptions",
        "--hidden-import", "requests",
        "--hidden-import", "json",
        "--hidden-import", "time",
        "--hidden-import", "winsound",
        "--hidden-import", "tkinter",
        "--hidden-import", "tkinter.ttk",
        "--hidden-import", "tkinter.messagebox",
        "--hidden-import", "tkinter.scrolledtext",
        "--hidden-import", "threading",
        "--hidden-import", "webbrowser",
        script_path
    ]
    
    try:
        result = subprocess.run(cmd, check=True, capture_output=True, text=True)
        print("✅ EXE erfolgreich erstellt!")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ PyInstaller Fehler: {e}")
        print(f"Stdout: {e.stdout}")
        print(f"Stderr: {e.stderr}")
        return False

def create_portable_package():
    """Portable Paket mit Konfiguration erstellen"""
    print("\n📁 Erstelle portable Paket...")
    
    # Zielordner erstellen
    package_dir = Path("filament_scanner_portable")
    package_dir.mkdir(exist_ok=True)
    
    # EXE kopieren
    exe_source = Path("dist/FilamentNFCScanner.exe")
    if exe_source.exists():
        shutil.copy2(exe_source, package_dir / "FilamentNFCScanner.exe")
        print("✅ GUI EXE kopiert")
    else:
        print("❌ GUI EXE nicht gefunden")
        return False
    
    # Konfigurationsdatei erstellen
    config_content = """# Filament NFC Scanner Konfiguration
# Diese Datei wird automatisch geladen

# API-Einstellungen
API_URL=https://filament.neuhauser.cloud
SCANNER_ID=acr122u_001

# Optional: Lokale Entwicklung
# API_URL=http://localhost:8000
# SCANNER_ID=dev_scanner

# Sound-Einstellungen (1=an, 0=aus)
ENABLE_SOUND=1

# Debug-Modus (1=an, 0=aus)  
DEBUG_MODE=0
"""
    
    with open(package_dir / "config.ini", "w", encoding="utf-8") as f:
        f.write(config_content)
    
    # README erstellen
    readme_content = """# Filament NFC Scanner - Portable Version

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
"""
    
    with open(package_dir / "README.txt", "w", encoding="utf-8") as f:
        f.write(readme_content)
    
    # Batch-Starter erstellen  
    batch_content = """@echo off
echo ================================================
echo Filament NFC Scanner GUI wird gestartet...
echo ================================================
echo.
echo Grafische Benutzeroberfläche öffnet sich...
echo Falls nichts passiert, prüfen Sie den Task Manager
echo.
FilamentNFCScanner.exe
"""
    
    with open(package_dir / "start_scanner.bat", "w", encoding="utf-8") as f:
        f.write(batch_content)
    
    print(f"✅ Portable Paket erstellt: {package_dir}")
    return True

def cleanup():
    """Temporäre Build-Dateien aufräumen"""
    print("\n🧹 Räume Build-Dateien auf...")
    
    # PyInstaller Ordner löschen
    for folder in ["build", "dist", "__pycache__"]:
        if os.path.exists(folder):
            shutil.rmtree(folder)
            print(f"   Gelöscht: {folder}")
    
    # .spec Datei löschen
    spec_file = "filament_nfc_scanner.spec"
    if os.path.exists(spec_file):
        os.remove(spec_file)
        print(f"   Gelöscht: {spec_file}")

def main():
    print("🏗️  Filament NFC Scanner - EXE Builder")
    print("=" * 50)
    
    # Schritt 1: Python prüfen
    if not check_python():
        return False
    
    # Schritt 2: Dependencies installieren
    if not install_dependencies():
        return False
    
    # Schritt 2.5: Icon erstellen
    create_icon()  # Nicht kritisch wenn fehlschlägt
        
    # Schritt 3: EXE erstellen
    if not build_exe():
        return False
        
    # Schritt 4: Portable Paket erstellen
    if not create_portable_package():
        return False
    
    print("\n🎉 GUI BUILD ERFOLGREICH!")
    print("=" * 50)
    print("Die portable GUI Scanner-Anwendung ist bereit:")
    print("📁 Ordner: filament_scanner_portable/")
    print("🚀 Start:  FilamentNFCScanner.exe (Doppelklick)")
    print("🚀 Alt:    start_scanner.bat")
    print("⚙️  Config: Über GUI-Einstellungen oder config.ini")
    
    # Cleanup anbieten
    cleanup_choice = input("\nBuild-Dateien aufräumen? (y/n): ").lower()
    if cleanup_choice in ['y', 'yes', 'j', 'ja']:
        cleanup()
    
    return True

if __name__ == "__main__":
    main()
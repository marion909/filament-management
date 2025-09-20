@echo off
echo =================================================
echo Filament NFC Scanner - ACR122U Windows Setup
echo =================================================
echo.

REM Check if Python is installed
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Python ist nicht installiert!
    echo Bitte installieren Sie Python von: https://python.org
    pause
    exit /b 1
)

echo ✅ Python gefunden
echo.

REM Install required packages for ACR122U
echo 📦 Installiere benötigte Pakete für ACR122U...
echo.

echo Installing pyscard...
pip install pyscard

echo Installing requests...
pip install requests

if %errorlevel% neq 0 (
    echo WARNING: Einige Pakete konnten nicht installiert werden
    echo Versuchen Sie: pip install --upgrade pip
    echo Oder installieren Sie manuell: pip install pyscard requests
    echo.
)

echo.
echo 🔧 ACR122U Setup Check...
echo Stellen Sie sicher dass:
echo   ✓ ACR122U über USB angeschlossen ist
echo   ✓ Treiber von https://www.acs.com.hk installiert sind
echo   ✓ Keine anderen NFC-Programme laufen
echo.

echo 🚀 ACR122U NFC Scanner starten...
python acr122u_scanner.py

pause
@echo off
echo ================================================
echo Filament NFC Scanner - EXE Builder
echo ================================================
echo.
echo Dieses Script erstellt eine ausführbare EXE-Datei
echo aus dem Python NFC Scanner.
echo.
echo Benötigte Software wird automatisch installiert:
echo - PyInstaller
echo - Requests
echo - PySCard
echo.
echo Drücken Sie eine beliebige Taste zum Fortfahren...
pause > nul

python build_exe.py

echo.
echo ================================================
echo Build abgeschlossen!
echo ================================================
echo.
pause
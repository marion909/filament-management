# -*- mode: python ; coding: utf-8 -*-


a = Analysis(
    ['nfc_scanner_gui.py'],
    pathex=[],
    binaries=[],
    datas=[('nfc_scanner_gui.py', '.')],
    hiddenimports=['smartcard', 'smartcard.System', 'smartcard.util', 'smartcard.CardMonitoring', 'smartcard.CardType', 'smartcard.CardRequest', 'smartcard.Exceptions', 'requests', 'json', 'time', 'winsound', 'tkinter', 'tkinter.ttk', 'tkinter.messagebox', 'tkinter.scrolledtext', 'threading', 'webbrowser'],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=[],
    noarchive=False,
    optimize=0,
)
pyz = PYZ(a.pure)

exe = EXE(
    pyz,
    a.scripts,
    a.binaries,
    a.datas,
    [],
    name='FilamentNFCScanner',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=False,
    disable_windowed_traceback=False,
    argv_emulation=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
    icon=['scanner_icon.ico'],
)

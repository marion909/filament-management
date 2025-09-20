#!/usr/bin/env python3
"""
NFC Scanner GUI für Windows mit ACR122U
Schöne Benutzeroberfläche mit tkinter
"""

import time
import json
import requests
import sys
import os
import configparser
import threading
import tkinter as tk
from tkinter import ttk, messagebox, scrolledtext
from typing import Optional, Dict, Any
import webbrowser

try:
    # Windows-kompatible Smart Card Bibliothek für ACR122U
    from smartcard.System import readers
    from smartcard.util import toHexString, toBytes
    from smartcard.CardMonitoring import CardMonitor, CardObserver
    from smartcard.CardType import AnyCardType
    from smartcard.CardRequest import CardRequest
    from smartcard.Exceptions import CardRequestTimeoutException, NoCardException
    PYSCARD_AVAILABLE = True
except ImportError:
    PYSCARD_AVAILABLE = False

class NFCScannerGUI:
    def __init__(self):
        self.root = tk.Tk()
        self.config = self.load_config()
        self.scanner = None
        self.scanning = False
        
        self.setup_ui()
        self.setup_scanner()
        
    def load_config(self) -> Dict[str, str]:
        """Konfiguration aus config.ini laden"""
        config = {
            'API_URL': 'http://localhost:8000',
            'SCANNER_ID': 'acr122u_001',
            'ENABLE_SOUND': '1',
            'DEBUG_MODE': '0'
        }
        
        config_file = 'config.ini'
        if os.path.exists(config_file):
            try:
                with open(config_file, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                for line in content.split('\n'):
                    line = line.strip()
                    if line and not line.startswith('#') and '=' in line:
                        key, value = line.split('=', 1)
                        config[key.strip()] = value.strip()
            except Exception as e:
                pass
        
        return config
    
    def setup_ui(self):
        """GUI erstellen"""
        self.root.title("🏷️ Filament NFC Scanner")
        self.root.geometry("800x700")
        self.root.resizable(True, True)
        
        # Icon setzen (falls vorhanden)
        try:
            self.root.iconbitmap("scanner_icon.ico")
        except:
            pass
            
        # Hauptcontainer
        main_frame = ttk.Frame(self.root, padding="20")
        main_frame.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        
        # Grid konfigurieren
        self.root.columnconfigure(0, weight=1)
        self.root.rowconfigure(0, weight=1)
        main_frame.columnconfigure(1, weight=1)
        
        # Header
        header_frame = ttk.Frame(main_frame)
        header_frame.grid(row=0, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=(0, 20))
        
        ttk.Label(header_frame, text="🏷️ Filament NFC Scanner", 
                 font=('Segoe UI', 16, 'bold')).grid(row=0, column=0, sticky=tk.W)
        
        # Status Frame
        status_frame = ttk.LabelFrame(main_frame, text="📡 Scanner Status", padding="15")
        status_frame.grid(row=1, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=(0, 15))
        status_frame.columnconfigure(1, weight=1)
        
        ttk.Label(status_frame, text="Reader:").grid(row=0, column=0, sticky=tk.W)
        self.reader_label = ttk.Label(status_frame, text="Suche Reader...", foreground="orange")
        self.reader_label.grid(row=0, column=1, sticky=tk.W, padx=(10, 0))
        
        ttk.Label(status_frame, text="API:").grid(row=1, column=0, sticky=tk.W)
        self.api_label = ttk.Label(status_frame, text=self.config['API_URL'], foreground="blue")
        self.api_label.grid(row=1, column=1, sticky=tk.W, padx=(10, 0))
        
        ttk.Label(status_frame, text="Status:").grid(row=2, column=0, sticky=tk.W)
        self.status_label = ttk.Label(status_frame, text="Bereit", foreground="green")
        self.status_label.grid(row=2, column=1, sticky=tk.W, padx=(10, 0))
        
        # Scan Control Frame
        control_frame = ttk.LabelFrame(main_frame, text="⚡ Scanner Kontrolle", padding="15")
        control_frame.grid(row=2, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=(0, 15))
        
        self.start_button = ttk.Button(control_frame, text="▶️ Scanner starten", 
                                      command=self.toggle_scanning, style="Accent.TButton")
        self.start_button.grid(row=0, column=0, padx=(0, 10))
        
        self.clear_button = ttk.Button(control_frame, text="🗑️ Löschen", 
                                      command=self.clear_results)
        self.clear_button.grid(row=0, column=1)
        
        # Results Frame
        results_frame = ttk.LabelFrame(main_frame, text="📋 Scan Ergebnisse", padding="15")
        results_frame.grid(row=3, column=0, columnspan=2, sticky=(tk.W, tk.E, tk.N, tk.S), pady=(0, 15))
        results_frame.columnconfigure(0, weight=1)
        results_frame.rowconfigure(0, weight=1)
        main_frame.rowconfigure(3, weight=1)
        
        # Notebook für Tabs
        self.notebook = ttk.Notebook(results_frame)
        self.notebook.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        
        # Tab 1: Aktuelle Spule
        self.current_frame = ttk.Frame(self.notebook)
        self.notebook.add(self.current_frame, text="📱 Aktueller Scan")
        self.setup_current_tab()
        
        # Tab 2: Scan Historie
        self.history_frame = ttk.Frame(self.notebook)
        self.notebook.add(self.history_frame, text="📚 Scan Historie")
        self.setup_history_tab()
        
        # Footer
        footer_frame = ttk.Frame(main_frame)
        footer_frame.grid(row=4, column=0, columnspan=2, sticky=(tk.W, tk.E))
        footer_frame.columnconfigure(0, weight=1)
        
        self.footer_label = ttk.Label(footer_frame, text="Bereit zum Scannen - halten Sie NFC Tags an den Reader", 
                                     foreground="gray")
        self.footer_label.grid(row=0, column=0, sticky=tk.W)
        
        # Settings Button
        ttk.Button(footer_frame, text="⚙️", command=self.show_settings, 
                  width=3).grid(row=0, column=1, sticky=tk.E)
    
    def setup_current_tab(self):
        """Tab für aktuelle Spule einrichten"""
        # Placeholder wenn keine Spule gescannt
        self.no_spool_frame = ttk.Frame(self.current_frame)
        self.no_spool_frame.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S), padx=20, pady=20)
        
        ttk.Label(self.no_spool_frame, text="📱", font=('Segoe UI', 48)).pack(pady=20)
        ttk.Label(self.no_spool_frame, text="Warten auf NFC Tag...", 
                 font=('Segoe UI', 14)).pack()
        ttk.Label(self.no_spool_frame, text="Halten Sie einen NFC Tag an den Reader", 
                 foreground="gray").pack()
        
        # Spule Info Frame (versteckt bis Scan erfolgt)
        self.spool_info_frame = ttk.Frame(self.current_frame)
        self.current_frame.columnconfigure(0, weight=1)
        self.current_frame.rowconfigure(0, weight=1)
        
    def setup_history_tab(self):
        """Tab für Scan-Historie einrichten"""
        # Treeview für Historie
        columns = ("Zeit", "NFC UID", "Status", "Material")
        self.history_tree = ttk.Treeview(self.history_frame, columns=columns, show="headings", height=15)
        
        # Column headers
        self.history_tree.heading("Zeit", text="🕐 Zeit")
        self.history_tree.heading("NFC UID", text="🏷️ NFC UID")
        self.history_tree.heading("Status", text="📊 Status")
        self.history_tree.heading("Material", text="🎨 Material")
        
        # Column widths
        self.history_tree.column("Zeit", width=100)
        self.history_tree.column("NFC UID", width=120)
        self.history_tree.column("Status", width=80)
        self.history_tree.column("Material", width=200)
        
        # Scrollbar
        history_scroll = ttk.Scrollbar(self.history_frame, orient=tk.VERTICAL, command=self.history_tree.yview)
        self.history_tree.configure(yscrollcommand=history_scroll.set)
        
        # Grid
        self.history_tree.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))
        history_scroll.grid(row=0, column=1, sticky=(tk.N, tk.S))
        
        self.history_frame.columnconfigure(0, weight=1)
        self.history_frame.rowconfigure(0, weight=1)
        
        # Double-click event
        self.history_tree.bind("<Double-1>", self.on_history_double_click)
        
    def setup_scanner(self):
        """Scanner initialisieren"""
        if PYSCARD_AVAILABLE:
            from acr122u_scanner import ACR122UNFCScanner
            self.scanner = ACR122UNFCScanner(
                self.config['API_URL'], 
                self.config['SCANNER_ID']
            )
            
            # Reader Status prüfen
            if self.scanner.connect_reader():
                self.reader_label.config(text="ACR122U verbunden ✅", foreground="green")
            else:
                self.reader_label.config(text="ACR122U nicht gefunden ❌", foreground="red")
        else:
            self.reader_label.config(text="pyscard nicht installiert ❌", foreground="red")
    
    def toggle_scanning(self):
        """Scanning starten/stoppen"""
        if not self.scanning:
            self.start_scanning()
        else:
            self.stop_scanning()
    
    def start_scanning(self):
        """Scanning starten"""
        if not PYSCARD_AVAILABLE or not self.scanner:
            messagebox.showerror("Fehler", "Scanner nicht verfügbar!\n\nInstallieren Sie pyscard:\npip install pyscard")
            return
        
        self.scanning = True
        self.start_button.config(text="⏹️ Scanner stoppen", style="Accent.TButton")
        self.status_label.config(text="Scanning aktiv...", foreground="green")
        self.footer_label.config(text="🔍 Scanning läuft - halten Sie NFC Tags an den Reader")
        
        # Scanning in eigenem Thread
        self.scan_thread = threading.Thread(target=self.scan_loop, daemon=True)
        self.scan_thread.start()
    
    def stop_scanning(self):
        """Scanning stoppen"""
        self.scanning = False
        self.start_button.config(text="▶️ Scanner starten")
        self.status_label.config(text="Bereit", foreground="orange")
        self.footer_label.config(text="Scanner gestoppt")
    
    def scan_loop(self):
        """Haupt-Scanning-Schleife"""
        last_uid = None
        scan_count = 0
        
        while self.scanning:
            try:
                nfc_uid = self.scanner.read_nfc_uid()
                
                if nfc_uid and nfc_uid != last_uid:
                    scan_count += 1
                    
                    # GUI Update in Main Thread
                    self.root.after(0, lambda: self.handle_nfc_scan(nfc_uid, scan_count))
                    
                    last_uid = nfc_uid
                
                elif nfc_uid is None and last_uid is not None:
                    # Tag entfernt
                    last_uid = None
                    self.root.after(0, lambda: self.update_footer("Tag entfernt - bereit für nächsten Scan"))
                
                time.sleep(0.3)
                
            except Exception as e:
                if self.scanning:  # Nur Fehler zeigen wenn wir noch scannen
                    self.root.after(0, lambda: messagebox.showerror("Scanner Fehler", str(e)))
                break
        
        # Cleanup
        self.root.after(0, lambda: self.update_footer(f"Scanning beendet - {scan_count} Scans durchgeführt"))
    
    def handle_nfc_scan(self, nfc_uid: str, scan_number: int):
        """NFC Scan verarbeiten"""
        self.update_footer(f"📡 Scan #{scan_number} - Lade Daten...")
        
        # API Anfrage in eigenem Thread
        threading.Thread(
            target=self.api_lookup, 
            args=(nfc_uid, scan_number), 
            daemon=True
        ).start()
    
    def api_lookup(self, nfc_uid: str, scan_number: int):
        """API Lookup in separatem Thread"""
        try:
            result = self.scanner.send_to_api(nfc_uid)
            
            # GUI Update zurück im Main Thread
            self.root.after(0, lambda: self.display_scan_result(nfc_uid, result, scan_number))
            
        except Exception as e:
            self.root.after(0, lambda: self.display_error(f"API Fehler: {e}"))
    
    def display_scan_result(self, nfc_uid: str, result: Dict[str, Any], scan_number: int):
        """Scan-Ergebnis anzeigen"""
        timestamp = time.strftime("%H:%M:%S")
        
        if result.get('found'):
            # Spule gefunden
            spool = result.get('spool', {})
            self.show_spool_info(spool, nfc_uid, result)
            
            # Historie hinzufügen
            self.history_tree.insert("", 0, values=(
                timestamp, nfc_uid, "✅ Gefunden", spool.get('material', 'N/A')
            ))
            
            self.update_footer(f"✅ Spule gefunden - {spool.get('material', 'N/A')}")
            self.play_success_sound()
            
        else:
            # Spule nicht gefunden
            self.show_unknown_tag(nfc_uid)
            
            # Historie hinzufügen
            self.history_tree.insert("", 0, values=(
                timestamp, nfc_uid, "❓ Unbekannt", "-"
            ))
            
            self.update_footer(f"❓ Unbekannte NFC UID: {nfc_uid}")
            self.play_unknown_sound()
        
        # Zum aktuellen Tab wechseln
        self.notebook.select(0)
    
    def show_spool_info(self, spool: Dict[str, Any], nfc_uid: str, result: Dict[str, Any] = None):
        """Spule-Informationen anzeigen"""
        # Verstecke "Warten auf Tag" Frame
        self.no_spool_frame.grid_remove()
        
        # Setup Spool Info Frame
        self.spool_info_frame.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S), padx=20, pady=20)
        
        # Clear previous content
        for widget in self.spool_info_frame.winfo_children():
            widget.destroy()
        
        # Header
        header = ttk.Label(self.spool_info_frame, text="🎯 SPULE GEFUNDEN!", 
                          font=('Segoe UI', 16, 'bold'), foreground="green")
        header.pack(pady=(0, 20))
        
        # Info Grid
        info_frame = ttk.Frame(self.spool_info_frame)
        info_frame.pack(fill=tk.X, pady=(0, 20))
        
        row = 0
        for label, value, icon in [
            ("ID", spool.get('id', 'N/A'), "🏷️"),
            ("Material", spool.get('material', 'N/A'), "🎨"),
            ("Typ", spool.get('filament_type', 'N/A'), "🔧"),
            ("Farbe", spool.get('color_name', 'Nicht angegeben'), "🌈"),
            ("Gewicht", f"{spool.get('remaining_weight', 0)}g / {spool.get('total_weight', 0)}g", "⚖️"),
            ("Standort", spool.get('location', 'Nicht angegeben'), "📍"),
            ("NFC UID", nfc_uid, "📱")
        ]:
            ttk.Label(info_frame, text=f"{icon} {label}:", font=('Segoe UI', 10, 'bold')).grid(
                row=row, column=0, sticky=tk.W, padx=(0, 20), pady=5
            )
            ttk.Label(info_frame, text=str(value), font=('Segoe UI', 10)).grid(
                row=row, column=1, sticky=tk.W, pady=5
            )
            row += 1
        
        # NFC-Tag Details (Multiple NFC-UIDs System)
        nfc_info = result.get('nfc_info', {})
        if nfc_info:
            # Separator
            separator = ttk.Separator(info_frame, orient='horizontal')
            separator.grid(row=row, column=0, columnspan=2, sticky=(tk.W, tk.E), pady=10)
            row += 1
            
            # NFC Tag Info Header
            ttk.Label(info_frame, text="📋 NFC-Tag Informationen:", font=('Segoe UI', 11, 'bold')).grid(
                row=row, column=0, columnspan=2, sticky=tk.W, pady=(5, 10)
            )
            row += 1
            
            # NFC Tag Details
            for label, value, icon in [
                ("Tag-Typ", nfc_info.get('tag_type', 'N/A'), "🏷️"),
                ("Position", nfc_info.get('tag_position', 'N/A'), "📍"),
                ("Primärer Tag", "✅ Ja" if nfc_info.get('is_primary') else "❌ Nein", "⭐")
            ]:
                ttk.Label(info_frame, text=f"  {icon} {label}:", font=('Segoe UI', 9)).grid(
                    row=row, column=0, sticky=tk.W, padx=(20, 20), pady=2
                )
                ttk.Label(info_frame, text=str(value), font=('Segoe UI', 9)).grid(
                    row=row, column=1, sticky=tk.W, pady=2
                )
                row += 1
        
        # Progress Bar für Gewicht
        remaining = spool.get('remaining_weight', 0)
        total = spool.get('total_weight', 1)
        percentage = (remaining / total) * 100 if total > 0 else 0
        
        ttk.Label(info_frame, text="📊 Verbrauch:", font=('Segoe UI', 10, 'bold')).grid(
            row=row, column=0, sticky=tk.W, padx=(0, 20), pady=5
        )
        
        progress_frame = ttk.Frame(info_frame)
        progress_frame.grid(row=row, column=1, sticky=(tk.W, tk.E), pady=5)
        
        progress = ttk.Progressbar(progress_frame, length=200, mode='determinate', value=percentage)
        progress.pack(side=tk.LEFT, padx=(0, 10))
        
        ttk.Label(progress_frame, text=f"{percentage:.1f}%").pack(side=tk.LEFT)
        
        # Buttons
        button_frame = ttk.Frame(self.spool_info_frame)
        button_frame.pack(fill=tk.X, pady=20)
        
        ttk.Button(button_frame, text="🌐 In Browser öffnen", 
                  command=lambda: self.open_in_browser(spool.get('id'))).pack(side=tk.LEFT, padx=(0, 10))
        
        ttk.Button(button_frame, text="📋 NFC UID kopieren", 
                  command=lambda: self.copy_to_clipboard(nfc_uid)).pack(side=tk.LEFT, padx=(0, 10))
    
    def show_unknown_tag(self, nfc_uid: str):
        """Unbekannten Tag anzeigen"""
        # Verstecke "Warten auf Tag" Frame
        self.no_spool_frame.grid_remove()
        
        # Setup Spool Info Frame
        self.spool_info_frame.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S), padx=20, pady=20)
        
        # Clear previous content
        for widget in self.spool_info_frame.winfo_children():
            widget.destroy()
        
        # Header
        header = ttk.Label(self.spool_info_frame, text="❓ UNBEKANNTER NFC TAG", 
                          font=('Segoe UI', 16, 'bold'), foreground="orange")
        header.pack(pady=(0, 20))
        
        # NFC UID
        uid_frame = ttk.LabelFrame(self.spool_info_frame, text="📱 NFC Unique ID", padding="15")
        uid_frame.pack(fill=tk.X, pady=(0, 20))
        
        uid_text = tk.Text(uid_frame, height=2, font=('Consolas', 12), wrap=tk.WORD)
        uid_text.insert(tk.END, nfc_uid)
        uid_text.config(state=tk.DISABLED)
        uid_text.pack(fill=tk.X, pady=(0, 10))
        
        # Buttons
        button_frame = ttk.Frame(uid_frame)
        button_frame.pack(fill=tk.X)
        
        ttk.Button(button_frame, text="📋 UID kopieren", 
                  command=lambda: self.copy_to_clipboard(nfc_uid),
                  style="Accent.TButton").pack(side=tk.LEFT)
        
        # Info Text
        info_text = """Dieser NFC Tag ist nicht in der Datenbank registriert.

Nächste Schritte:
1. UID in die Zwischenablage kopieren  
2. Web-App öffnen und neue Spule anlegen
3. NFC UID in das entsprechende Feld eintragen"""
        
        ttk.Label(self.spool_info_frame, text=info_text, foreground="gray", justify=tk.LEFT).pack(pady=20)
    
    def show_settings(self):
        """Einstellungen Dialog"""
        dialog = tk.Toplevel(self.root)
        dialog.title("⚙️ Einstellungen")
        dialog.geometry("500x300")
        dialog.transient(self.root)
        dialog.grab_set()
        
        # Center dialog
        dialog.geometry(f"+{self.root.winfo_rootx() + 50}+{self.root.winfo_rooty() + 50}")
        
        notebook = ttk.Notebook(dialog)
        notebook.pack(fill=tk.BOTH, expand=True, padx=20, pady=20)
        
        # API Tab
        api_frame = ttk.Frame(notebook)
        notebook.add(api_frame, text="🌐 API")
        
        ttk.Label(api_frame, text="API URL:").grid(row=0, column=0, sticky=tk.W, pady=5)
        api_var = tk.StringVar(value=self.config['API_URL'])
        ttk.Entry(api_frame, textvariable=api_var, width=40).grid(row=0, column=1, sticky=(tk.W, tk.E), pady=5, padx=(10, 0))
        
        ttk.Label(api_frame, text="Scanner ID:").grid(row=1, column=0, sticky=tk.W, pady=5)
        id_var = tk.StringVar(value=self.config['SCANNER_ID'])
        ttk.Entry(api_frame, textvariable=id_var, width=40).grid(row=1, column=1, sticky=(tk.W, tk.E), pady=5, padx=(10, 0))
        
        # Sound Tab
        sound_frame = ttk.Frame(notebook)
        notebook.add(sound_frame, text="🔊 Sound")
        
        sound_var = tk.BooleanVar(value=self.config.get('ENABLE_SOUND', '1') == '1')
        ttk.Checkbutton(sound_frame, text="Sounds aktivieren", variable=sound_var).grid(row=0, column=0, sticky=tk.W, pady=10)
        
        api_frame.columnconfigure(1, weight=1)
        
        def save_settings():
            self.config['API_URL'] = api_var.get()
            self.config['SCANNER_ID'] = id_var.get()
            self.config['ENABLE_SOUND'] = '1' if sound_var.get() else '0'
            
            # Config speichern
            try:
                with open('config.ini', 'w', encoding='utf-8') as f:
                    for key, value in self.config.items():
                        f.write(f"{key}={value}\n")
                messagebox.showinfo("Erfolg", "Einstellungen gespeichert!")
                dialog.destroy()
            except Exception as e:
                messagebox.showerror("Fehler", f"Einstellungen konnten nicht gespeichert werden: {e}")
        
        ttk.Button(dialog, text="💾 Speichern", command=save_settings).pack(side=tk.RIGHT, padx=20, pady=(0, 20))
    
    def clear_results(self):
        """Ergebnisse löschen"""
        # Current tab zurücksetzen
        self.spool_info_frame.grid_remove()
        self.no_spool_frame.grid()
        
        # Historie löschen
        for item in self.history_tree.get_children():
            self.history_tree.delete(item)
        
        self.update_footer("Ergebnisse gelöscht")
    
    def copy_to_clipboard(self, text: str):
        """Text in Zwischenablage kopieren"""
        self.root.clipboard_clear()
        self.root.clipboard_append(text)
        self.update_footer(f"📋 In Zwischenablage kopiert: {text}")
    
    def open_in_browser(self, spool_id: str):
        """Spule in Browser öffnen"""
        url = f"{self.config['API_URL']}/spools?id={spool_id}"
        webbrowser.open(url)
    
    def update_footer(self, message: str):
        """Footer-Nachricht aktualisieren"""
        self.footer_label.config(text=message)
    
    def display_error(self, message: str):
        """Fehler anzeigen"""
        messagebox.showerror("Fehler", message)
        self.update_footer(f"❌ {message}")
    
    def play_success_sound(self):
        """Erfolgssound abspielen"""
        if self.config.get('ENABLE_SOUND', '1') == '1':
            try:
                import winsound
                winsound.Beep(1000, 200)
                winsound.Beep(1200, 200)
            except:
                pass
    
    def play_unknown_sound(self):
        """Unbekannt-Sound abspielen"""
        if self.config.get('ENABLE_SOUND', '1') == '1':
            try:
                import winsound
                winsound.Beep(800, 300)
                winsound.Beep(600, 300)
                winsound.Beep(400, 300)
            except:
                pass
    
    def on_history_double_click(self, event):
        """Historie Doppelklick"""
        selection = self.history_tree.selection()
        if selection:
            item = self.history_tree.item(selection[0])
            nfc_uid = item['values'][1]
            self.copy_to_clipboard(nfc_uid)
    
    def on_closing(self):
        """App schließen"""
        self.scanning = False
        time.sleep(0.5)  # Kurz warten
        self.root.destroy()
    
    def run(self):
        """GUI starten"""
        self.root.protocol("WM_DELETE_WINDOW", self.on_closing)
        
        # Theme setzen
        try:
            self.root.tk.call("source", "azure.tcl")
            self.root.tk.call("set_theme", "light")
        except:
            pass  # Fallback to default theme
        
        self.root.mainloop()

# Importiere das originale Scanner-Modul
class ACR122UNFCScanner:
    def __init__(self, api_url: str, scanner_id: str = "acr122u_001"):
        self.api_url_base = api_url.rstrip('/')
        self.scanner_id = scanner_id
        self.running = False
        self.reader = None
        self.connection = None
        
    def find_acr122u_reader(self) -> Optional[str]:
        """ACR122U Reader finden"""
        try:
            available_readers = readers()
            
            for reader in available_readers:
                reader_name = str(reader).lower()
                if any(keyword in reader_name for keyword in ['acr122', 'acr 122', 'nfc']):
                    return reader
            
            if available_readers:
                return available_readers[0]
            
            return None
            
        except Exception as e:
            return None
    
    def connect_reader(self) -> bool:
        """Verbindung zum ACR122U herstellen"""
        try:
            self.reader = self.find_acr122u_reader()
            return self.reader is not None
        except Exception as e:
            return False
    
    def read_nfc_uid(self) -> Optional[str]:
        """NFC UID vom NTAG lesen"""
        if not self.reader:
            return None
        
        try:
            cardtype = AnyCardType()
            cardrequest = CardRequest(timeout=1, cardType=cardtype, readers=[self.reader])
            
            cardservice = cardrequest.waitforcard()
            cardservice.connection.connect()
            
            GET_UID = [0xFF, 0xCA, 0x00, 0x00, 0x00]
            response, sw1, sw2 = cardservice.connection.transmit(GET_UID)
            
            if sw1 == 0x90 and sw2 == 0x00:
                uid = toHexString(response).replace(' ', '').upper()
                cardservice.connection.disconnect()
                return uid
            else:
                cardservice.connection.disconnect()
                return None
                
        except (CardRequestTimeoutException, NoCardException):
            return None
        except Exception as e:
            return None
    
    def send_to_api(self, nfc_uid: str) -> Dict[str, Any]:
        """NFC UID über API lookup"""
        try:
            api_url = f"{self.api_url_base}/nfc_lookup.php"
            
            data = {
                'nfc_uid': nfc_uid,
                'scanner_id': self.scanner_id,
                'timestamp': int(time.time()),
                'reader_type': 'ACR122U'
            }
            
            headers = {
                'Content-Type': 'application/json',
                'User-Agent': f'ACR122U-Scanner/{self.scanner_id}'
            }
            
            response = requests.post(
                api_url,
                json=data,
                headers=headers,
                timeout=10
            )
            
            if response.status_code == 200:
                return response.json()
            else:
                return {'error': f'HTTP {response.status_code}'}
                
        except requests.RequestException as e:
            return {'error': str(e)}

def main():
    """Hauptfunktion"""
    app = NFCScannerGUI()
    app.run()

if __name__ == "__main__":
    main()
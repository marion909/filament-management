#!/usr/bin/env python3
"""
NFC Scanner Service für Windows mit ACR122U
Verwendet pyscard für bessere Windows-Kompatibilität
"""

import time
import json
import requests
import sys
import os
import configparser
from typing import Optional, Dict, Any

try:
    # Windows-kompatible Smart Card Bibliothek für ACR122U
    from smartcard.System import readers
    from smartcard.util import toHexString, toBytes
    from smartcard.CardMonitoring import CardMonitor, CardObserver
    from smartcard.CardType import AnyCardType
    from smartcard.CardRequest import CardRequest
    from smartcard.Exceptions import CardRequestTimeoutException, NoCardException
    PYSCARD_AVAILABLE = True
    print("✅ pyscard Bibliothek verfügbar")
except ImportError:
    print("⚠️  pyscard nicht installiert. Installieren Sie es mit: pip install pyscard")
    PYSCARD_AVAILABLE = False

class ACR122UNFCScanner:
    def __init__(self, api_url: str, scanner_id: str = "acr122u_001"):
        # WORKAROUND: Da /api/nfc/scan nicht verfügbar ist, verwenden wir eine alternative Methode
        # Wir können die Spool-API nutzen um nach UIDs zu suchen
        self.api_url_base = api_url.rstrip('/')
        self.scanner_id = scanner_id
        self.running = False
        self.reader = None
        self.connection = None
        
    def find_acr122u_reader(self) -> Optional[str]:
        """ACR122U Reader finden"""
        try:
            available_readers = readers()
            
            print(f"🔍 Verfügbare Reader: {len(available_readers)}")
            for i, reader in enumerate(available_readers):
                print(f"   {i+1}. {reader}")
                
                # ACR122U Reader identifizieren
                reader_name = str(reader).lower()
                if any(keyword in reader_name for keyword in ['acr122', 'acr 122', 'nfc']):
                    print(f"✅ ACR122U gefunden: {reader}")
                    return reader
            
            # Falls kein ACR122U spezifisch gefunden, ersten Reader verwenden
            if available_readers:
                print(f"⚠️  Kein ACR122U spezifisch gefunden, verwende ersten Reader: {available_readers[0]}")
                return available_readers[0]
            
            print("❌ Keine Smart Card Reader gefunden")
            return None
            
        except Exception as e:
            print(f"❌ Fehler beim Suchen der Reader: {e}")
            return None
    
    def connect_reader(self) -> bool:
        """Verbindung zum ACR122U herstellen"""
        try:
            self.reader = self.find_acr122u_reader()
            if not self.reader:
                return False
            
            print(f"🔗 Verbinde mit Reader: {self.reader}")
            return True
            
        except Exception as e:
            print(f"❌ Fehler beim Verbinden: {e}")
            return False
    
    def read_nfc_uid(self) -> Optional[str]:
        """NFC UID vom NTAG lesen"""
        if not self.reader:
            return None
        
        try:
            # Card Request mit Timeout
            cardtype = AnyCardType()
            cardrequest = CardRequest(timeout=1, cardType=cardtype, readers=[self.reader])
            
            # Warten auf Karte
            cardservice = cardrequest.waitforcard()
            
            # Verbindung zur Karte herstellen
            cardservice.connection.connect()
            
            # Get UID Command für ISO14443 Type A (NTAG)
            GET_UID = [0xFF, 0xCA, 0x00, 0x00, 0x00]
            
            response, sw1, sw2 = cardservice.connection.transmit(GET_UID)
            
            if sw1 == 0x90 and sw2 == 0x00:  # Success
                uid = toHexString(response).replace(' ', '').upper()
                print(f"📱 NTAG UID: {uid}")
                
                # Optional: NTAG Typ bestimmen
                self.detect_ntag_type(len(uid))
                
                cardservice.connection.disconnect()
                return uid
            else:
                print(f"❌ Fehler beim Lesen der UID: SW1={sw1:02X} SW2={sw2:02X}")
                cardservice.connection.disconnect()
                return None
                
        except CardRequestTimeoutException:
            # Normal - kein Tag vorhanden
            return None
        except NoCardException:
            # Normal - kein Tag vorhanden  
            return None
        except Exception as e:
            if "sharing violation" not in str(e).lower() and "timeout" not in str(e).lower():
                print(f"❌ Fehler beim Lesen: {e}")
            return None
    
    def detect_ntag_type(self, uid_hex_length: int):
        """NTAG Typ anhand UID-Länge bestimmen"""
        uid_bytes = uid_hex_length // 2
        
        if uid_bytes == 7:
            tag_type = "NTAG213 (180 bytes)"
        elif uid_bytes == 10:
            tag_type = "NTAG215/216 (540/944 bytes)"
        else:
            tag_type = f"Unbekannt ({uid_bytes} bytes UID)"
            
        print(f"   Typ: {tag_type}")
    
    def send_to_api(self, nfc_uid: str) -> Dict[str, Any]:
        """NFC UID über direkten NFC Lookup Service"""
        try:
            # Verwende direkten NFC Lookup Service
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
                result = response.json()
                return result
            else:
                print(f"❌ API Fehler: {response.status_code} - {response.text[:200]}")
                return {'error': f'HTTP {response.status_code}'}
                
        except requests.RequestException as e:
            print(f"❌ Netzwerk Fehler: {e}")
            return {'error': str(e)}
    
    def handle_scan_result(self, result: Dict[str, Any], nfc_uid: str):
        """Scan-Ergebnis verarbeiten und anzeigen"""
        if result.get('found'):
            spool = result.get('spool', {})
            print(f"🎯 SPOOL GEFUNDEN!")
            print(f"   ╭─────────────────────────────────────╮")
            print(f"   │ ID: {spool.get('id', 'N/A'):<30} │")
            print(f"   │ Material: {spool.get('material', 'N/A'):<24} │")
            print(f"   │ Gewicht: {spool.get('remaining_weight', 0)}g / {spool.get('total_weight', 0)}g{' '*(12-len(str(spool.get('remaining_weight', 0)))-len(str(spool.get('total_weight', 0))))} │")
            print(f"   │ Standort: {spool.get('location', 'Nicht angegeben'):<24} │")
            print(f"   ╰─────────────────────────────────────╯")
            
            # Windows-Systemsound für Erfolg
            try:
                import winsound
                # Erfolgreicher Scan - freundlicher Ton
                winsound.Beep(1000, 200)  # 1000 Hz für 200ms
                winsound.Beep(1200, 200)  # 1200 Hz für 200ms
            except:
                print("🔔 BEEP BEEP - Spool erkannt!")
                
        else:
            print(f"❓ UNBEKANNTE NFC UID: {nfc_uid}")
            print("   Spool nicht in der Datenbank gefunden")
            print("   → Registrieren Sie den Tag über die Web-App")
            
            # Windows-Systemsound für unbekannt
            try:
                import winsound
                # Unbekannter Tag - fragender Ton
                winsound.Beep(800, 300)   # 800 Hz für 300ms
                winsound.Beep(600, 300)   # 600 Hz für 300ms  
                winsound.Beep(400, 300)   # 400 Hz für 300ms
            except:
                print("🔔 BEEP BEEP BEEP - Unbekannter NFC Tag!")
    
    def run(self):
        """Hauptschleife des ACR122U Scanners"""
        print("🚀 ACR122U NFC Scanner für Windows startet...")
        print(f"   API Base URL: {self.api_url_base}")
        print(f"   NFC Lookup: {self.api_url_base}/nfc_lookup.php")
        print(f"   Scanner ID: {self.scanner_id}")
        print("   Drücken Sie Ctrl+C zum Beenden")
        print("")
        
        if not PYSCARD_AVAILABLE:
            print("❌ pyscard Bibliothek nicht verfügbar")
            print("   Installieren Sie mit: pip install pyscard")
            print("   Simulation wird gestartet...")
            self.run_simulation()
            return
        
        if not self.connect_reader():
            print("❌ Scanner kann nicht gestartet werden")
            print("")
            print("💡 Problemlösung:")
            print("   1. ACR122U USB-Kabel überprüfen")
            print("   2. Treiber von https://www.acs.com.hk installieren")
            print("   3. Andere Programme schließen die den Reader verwenden")
            print("   4. Reader an anderen USB-Port anschließen")
            return
        
        self.running = True
        last_uid = None
        scan_count = 0
        
        print("👁️  Bereit zum Scannen - halten Sie NFC Tags an den Reader...")
        print("")
        
        try:
            while self.running:
                # NFC Tag scannen
                nfc_uid = self.read_nfc_uid()
                
                if nfc_uid and nfc_uid != last_uid:
                    scan_count += 1
                    print(f"📡 Scan #{scan_count} - UID: {nfc_uid}")
                    
                    # An API senden
                    result = self.send_to_api(nfc_uid)
                    
                    # Ergebnis verarbeiten
                    if 'error' not in result:
                        self.handle_scan_result(result, nfc_uid)
                    else:
                        print(f"❌ API Fehler: {result.get('error', 'Unbekannt')}")
                    
                    last_uid = nfc_uid
                    print("")  # Leerzeile für bessere Lesbarkeit
                
                elif nfc_uid is None and last_uid is not None:
                    # Tag entfernt
                    last_uid = None
                    print("📱 NFC Tag entfernt - bereit für nächsten Scan...")
                
                # Kurze Pause um CPU zu schonen
                time.sleep(0.3)
                
        except KeyboardInterrupt:
            print("\n🛑 Scanner wird beendet...")
        finally:
            self.running = False
            print(f"📊 Gesamt Scans: {scan_count}")
    
    def run_simulation(self):
        """Simulation für Tests ohne Hardware"""
        print("🔄 SIMULATIONSMODUS")
        print("   Geben Sie NFC UIDs manuell ein zum Testen:")
        print("")
        
        try:
            while True:
                nfc_uid = input("NFC UID eingeben (oder 'quit'): ").strip().upper()
                
                if nfc_uid.lower() in ['quit', 'exit', 'q', '']:
                    break
                    
                if nfc_uid:
                    print(f"📡 Simuliere Scan: {nfc_uid}")
                    result = self.send_to_api(nfc_uid)
                    
                    if 'error' not in result:
                        self.handle_scan_result(result, nfc_uid)
                    else:
                        print(f"❌ Fehler: {result.get('error')}")
                    print("")
                        
        except KeyboardInterrupt:
            print("\n🛑 Simulation beendet")

def load_config() -> Dict[str, str]:
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
            parser = configparser.ConfigParser()
            
            # Config-Inhalt als DEFAULT section behandeln
            with open(config_file, 'r', encoding='utf-8') as f:
                content = f.read()
            
            # Einfache Key=Value Parser
            for line in content.split('\n'):
                line = line.strip()
                if line and not line.startswith('#') and '=' in line:
                    key, value = line.split('=', 1)
                    config[key.strip()] = value.strip()
                    
            print(f"✅ Konfiguration geladen aus {config_file}")
        except Exception as e:
            print(f"⚠️  Konfigurationsfehler: {e}, verwende Standard-Werte")
    else:
        print(f"ℹ️  Keine config.ini gefunden, verwende Standard-Konfiguration")
    
    return config

def main():
    # Konfiguration laden
    config = load_config()
    
    API_URL = config['API_URL']
    SCANNER_ID = config['SCANNER_ID']
    
    print("=" * 50)
    print("  🏷️  FILAMENT NFC SCANNER - ACR122U")
    print("=" * 50)
    print(f"  API: {API_URL}")
    print(f"  Scanner ID: {SCANNER_ID}")
    print("=" * 50)
    print("")
    
    # Scanner erstellen und starten
    scanner = ACR122UNFCScanner(API_URL, SCANNER_ID)
    scanner.run()

if __name__ == "__main__":
    main()
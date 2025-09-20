"""
Icon Creator für NFC Scanner
Erstellt ein einfaches Icon im ICO-Format
"""

from PIL import Image, ImageDraw, ImageFont
import os

def create_scanner_icon():
    """Erstellt ein NFC Scanner Icon"""
    
    # Icon Größen für Windows
    sizes = [16, 32, 48, 64, 128, 256]
    images = []
    
    for size in sizes:
        # Neue Bildgröße
        img = Image.new('RGBA', (size, size), (0, 0, 0, 0))
        draw = ImageDraw.Draw(img)
        
        # Farbschema
        bg_color = (33, 150, 243)  # Blau
        accent_color = (255, 193, 7)  # Gelb
        white = (255, 255, 255)
        
        # Hintergrund Kreis
        margin = size // 8
        circle_coords = [margin, margin, size - margin, size - margin]
        draw.ellipse(circle_coords, fill=bg_color)
        
        # NFC Symbol (vereinfacht)
        center = size // 2
        
        # Äußere Kurven (NFC Wellen)
        if size >= 32:
            wave_width = max(1, size // 20)
            
            # Erste Welle
            wave1_size = size // 3
            wave1_coords = [
                center - wave1_size//2, center - wave1_size//2,
                center + wave1_size//2, center + wave1_size//2
            ]
            draw.arc(wave1_coords, start=225, end=315, fill=white, width=wave_width)
            draw.arc(wave1_coords, start=45, end=135, fill=white, width=wave_width)
            
            # Zweite Welle  
            wave2_size = size // 2
            wave2_coords = [
                center - wave2_size//2, center - wave2_size//2,
                center + wave2_size//2, center + wave2_size//2
            ]
            draw.arc(wave2_coords, start=225, end=315, fill=accent_color, width=wave_width)
            draw.arc(wave2_coords, start=45, end=135, fill=accent_color, width=wave_width)
        
        # Zentraler Punkt
        dot_size = max(2, size // 10)
        dot_coords = [
            center - dot_size//2, center - dot_size//2,
            center + dot_size//2, center + dot_size//2
        ]
        draw.ellipse(dot_coords, fill=white)
        
        images.append(img)
    
    # Als ICO speichern
    images[0].save(
        'scanner_icon.ico',
        format='ICO',
        sizes=[(img.width, img.height) for img in images],
        append_images=images[1:]
    )
    
    print("✅ Icon erstellt: scanner_icon.ico")

def main():
    """Hauptfunktion"""
    try:
        create_scanner_icon()
        return True
    except ImportError:
        print("⚠️  Pillow nicht installiert - Icon wird übersprungen")
        print("   Installieren mit: pip install Pillow")
        return False
    except Exception as e:
        print(f"❌ Icon-Erstellung fehlgeschlagen: {e}")
        return False

if __name__ == "__main__":
    main()
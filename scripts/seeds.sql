-- Seed data for filament management system
-- Insert default filament types

INSERT INTO filament_types (name, diameter, description) VALUES
('PLA', '1.75', 'Polylactic Acid - einfach zu drucken, umweltfreundlich'),
('PETG', '1.75', 'Gute Festigkeit, chemikalienbeständig, transparent möglich'),
('ABS', '1.75', 'Hochtemperaturbeständig, schlagzäh, löslich in Aceton'),
('TPU', '1.75', 'Thermoplastisches Polyurethan - flexibel und elastisch'),
('Nylon', '1.75', 'Sehr zäh und verschleißfest, hohe Drucktemperatur'),
('ASA', '1.75', 'UV-beständig, wetterbeständig, ähnlich ABS'),
('PVA', '1.75', 'Wasserlöslich, ideal als Stützmaterial'),
('PC', '1.75', 'Polycarbonat - sehr stabil, transparent, hohe Temperatur'),
('Wood-Fill', '1.75', 'Holz-gefüllter PLA, schleif- und bearbeitbar'),
('Carbon-Fill', '1.75', 'Kohlefaser-verstärkt, sehr stabil und leicht'),
('Metal-Fill', '1.75', 'Metall-gefülltes Filament, polierbar'),
('HIPS', '1.75', 'Leicht, löslich in Limonen, gutes Stützmaterial'),
('PLA+', '1.75', 'Verbesserter PLA mit höherer Festigkeit'),
('PETG+', '1.75', 'Verstärktes PETG mit besseren mechanischen Eigenschaften');

-- Insert default colors
INSERT INTO colors (name, hex) VALUES
('Schwarz', '#000000'),
('Weiß', '#FFFFFF'),
('Natur/Transparent', '#F5F5F5'),
('Grau', '#808080'),
('Rot', '#FF0000'),
('Blau', '#0000FF'),
('Grün', '#00FF00'),
('Gelb', '#FFFF00'),
('Orange', '#FFA500'),
('Lila', '#800080'),
('Pink', '#FF69B4'),
('Braun', '#A52A2A'),
('Silber', '#C0C0C0'),
('Gold', '#FFD700'),
('Bronze', '#CD7F32'),
('Türkis', '#40E0D0'),
('Lime', '#00FF00'),
('Magenta', '#FF00FF'),
('Cyan', '#00FFFF'),
('Dunkelblau', '#000080'),
('Dunkelgrün', '#006400'),
('Dunkelrot', '#8B0000'),
('Beige', '#F5F5DC'),
('Khaki', '#F0E68C');

-- Insert standard spool sizes
INSERT INTO spool_presets (name, grams) VALUES
('250g Spule', 250),
('500g Spule', 500),
('750g Spule', 750),
('1kg Spule', 1000),
('1.75kg Spule', 1750),
('2kg Spule', 2000),
('2.5kg Spule', 2500),
('5kg Spule', 5000);

-- Create default admin user (password: admin123)
-- Password hash for 'admin123' using PHP password_hash()
INSERT INTO users (email, password_hash, name, role, verified_at, is_active) VALUES
('admin@filament.neuhauser.cloud', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', NOW(), 1);

-- Create test user (password: user123)
INSERT INTO users (email, password_hash, name, role, verified_at, is_active) VALUES
('user@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test User', 'user', NOW(), 1);
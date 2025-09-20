<?php
$title = 'Dashboard';
ob_start();
?>

<div class="card">
    <h1>Willkommen im Filament Management System</h1>
    <p>Verwalten Sie Ihre 3D-Druck-Filamente effizient mit NFC-Integration.</p>
</div>

<div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
    <div class="card">
        <h2>📦 Spulen-Übersicht</h2>
        <p>Aktuelle Anzahl: <strong id="total-spools">Lädt...</strong></p>
        <p>Niedrige Bestände: <strong id="low-stock-spools" style="color: #dc2626;">Lädt...</strong></p>
        <a href="/spools" class="btn btn-primary">Alle Spulen anzeigen</a>
    </div>
    
    <div class="card">
        <h2>⚖️ Gewichts-Statistik</h2>
        <p>Gesamtgewicht: <strong id="total-weight">Lädt...</strong></p>
        <p>Verbleibendes Gewicht: <strong id="remaining-weight">Lädt...</strong></p>
        <p>Verbrauchtes Gewicht: <strong id="used-weight">Lädt...</strong></p>
    </div>
</div>

<div class="card">
    <h2>📊 Verbrauchsstatistiken (7 Tage)</h2>
    <div id="usage-stats">
        <p>Lade Statistiken...</p>
    </div>
</div>

<div class="card">
    <h2>⚡ Schnellaktionen</h2>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="/spools" class="btn btn-primary">Spulen verwalten</a>
        <a href="/api/export/spools.csv" class="btn btn-secondary" download>📥 Daten exportieren (CSV)</a>
    </div>
</div>

<script nonce="<?= $cspNonce ?? '' ?>">
async function loadDashboardStats() {
    try {
        const response = await fetch('/api/spools/stats');
        const data = await response.json();
        
        // Update spool statistics
        document.getElementById('total-spools').textContent = data.spools.total_spools;
        document.getElementById('low-stock-spools').textContent = data.spools.low_stock_spools;
        
        // Update weight statistics
        document.getElementById('total-weight').textContent = (data.spools.total_weight / 1000).toFixed(1) + ' kg';
        document.getElementById('remaining-weight').textContent = (data.spools.remaining_weight / 1000).toFixed(1) + ' kg';
        document.getElementById('used-weight').textContent = (data.spools.used_weight / 1000).toFixed(1) + ' kg';
        
        // Display usage statistics
        displayUsageStats(data.usage);
        
    } catch (error) {
        console.error('Error loading dashboard stats:', error);
        document.getElementById('total-spools').textContent = 'Fehler';
        document.getElementById('low-stock-spools').textContent = 'Fehler';
    }
}

function displayUsageStats(usage) {
    const container = document.getElementById('usage-stats');
    
    if (usage.daily_usage.length === 0) {
        container.innerHTML = '<p>Keine Verbrauchsdaten in den letzten 7 Tagen.</p>';
        return;
    }
    
    const dailyHTML = usage.daily_usage.map(day => {
        return `<div style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #eee;">
            <span>${new Date(day.date).toLocaleDateString('de-DE')}</span>
            <strong>${day.grams}g</strong>
        </div>`;
    }).join('');
    
    const materialsHTML = usage.top_materials.map(material => {
        return `<div style="display: flex; justify-content: space-between; padding: 5px 0;">
            <span>${material.material}</span>
            <strong>${material.grams}g</strong>
        </div>`;
    }).join('');
    
    container.innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h4>Täglicher Verbrauch:</h4>
                ${dailyHTML}
                <div style="margin-top: 10px; font-weight: bold;">
                    Gesamt (7 Tage): ${usage.total_used}g
                </div>
            </div>
            <div>
                <h4>Meist verbrauchte Materialien:</h4>
                ${materialsHTML}
            </div>
        </div>
    `;
}

// Load stats on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardStats();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
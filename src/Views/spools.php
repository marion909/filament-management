<?php
$title = 'Filament-Spulen';
ob_start();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1>Filament-Spulen</h1>
    <button class="btn btn-primary" id="add-spool-btn">+ Neue Spule hinzufügen</button>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 20px;">
    <h3>Filter</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div class="form-group">
            <label>Material:</label>
            <input type="text" id="filter-material" placeholder="Material eingeben...">
        </div>
        <div class="form-group">
            <label>Typ:</label>
            <select id="filter-type">
                <option value="">Alle Typen</option>
            </select>
        </div>
        <div class="form-group">
            <label>Farbe:</label>
            <select id="filter-color">
                <option value="">Alle Farben</option>
            </select>
        </div>
        <div class="form-group">
            <label>Standort:</label>
            <input type="text" id="filter-location" placeholder="Standort eingeben...">
        </div>
    </div>
    <div style="margin-top: 15px;">
        <label>
            <input type="checkbox" id="filter-low-stock">
            Nur niedrige Bestände anzeigen
        </label>
        <button class="btn btn-secondary" style="margin-left: 10px;" id="clear-filters-btn">Filter zurücksetzen</button>
    </div>
</div>

<!-- Spools Grid -->
<div id="spools-container">
    <div class="loading" style="text-align: center; padding: 50px;">
        <p>Lade Spulen...</p>
    </div>
</div>

<!-- Pagination -->
<div id="pagination-container" style="margin-top: 20px; text-align: center;"></div>


<!-- Spool Details Modal -->
<div id="spool-details-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Spule Details</h2>
            <button id="close-details-modal" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        
        <div id="spool-details-content">
            <div class="loading">Lade Spule-Details...</div>
        </div>
    </div>
</div>

</div>

<style>
.spool-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.spool-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid #ddd;
}

.spool-card.low-stock {
    border-left-color: #dc2626;
}

.spool-card.good-stock {
    border-left-color: #10b981;
}

.spool-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}

.spool-material {
    font-size: 18px;
    font-weight: bold;
    color: #1f2937;
}

.spool-type {
    color: #6b7280;
    font-size: 14px;
}

.spool-weight {
    text-align: right;
}

.weight-bar {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin: 10px 0;
}

.weight-fill {
    height: 100%;
    background: #10b981;
    transition: width 0.3s ease;
}

.weight-fill.low {
    background: #dc2626;
}

.weight-fill.medium {
    background: #f59e0b;
}

.spool-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}
</style>

<!-- Spools Grid -->
<div id="spools-container">
    <div class="loading" style="text-align: center; padding: 50px;">
        <p>Lade Spulen...</p>
    </div>
</div>

<!-- Pagination -->
<div id="pagination-container" style="margin-top: 20px; text-align: center;"></div>

<!-- Add Spool Modal -->
<div id="add-spool-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Neue Spule hinzufügen</h2>
            <button id="close-add-spool-modal" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        
        <form id="add-spool-form">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="spool-type">Filament-Typ: *</label>
                    <select id="spool-type" name="type_id" required>
                        <option value="">Typ auswählen...</option>
                    </select>
                </div>

                <div class="form-group" id="new-type-group" style="display: none;">
                    <label for="new-type-input">Neuer Typ-Name:</label>
                    <input type="text" id="new-type-input" name="new_type" placeholder="z.B. PETG-CF, TPU-95A">
                    <button type="button" id="create-type-btn" class="btn btn-secondary">Typ erstellen</button>
                </div>
                
                <div class="form-group">
                    <label for="spool-material">Material: *</label>
                    <input type="text" id="spool-material" name="material" required>
                </div>
                
                <div class="form-group">
                    <label for="spool-color">Farbe:</label>
                    <select id="spool-color" name="color_id">
                        <option value="">Farbe auswählen...</option>
                    </select>
                </div>
                
                <div class="form-group" id="new-color-group" style="display: none;">
                    <label for="new-color-input">Neue Farbe eingeben:</label>
                    <input type="text" id="new-color-input" name="new_color" placeholder="Farbname eingeben..." />
                </div>
                
                <div class="form-group">
                    <label for="spool-diameter">Durchmesser:</label>
                    <select id="spool-diameter" name="diameter">
                        <option value="1.75">1.75 mm</option>
                        <option value="2.85">2.85 mm</option>
                        <option value="3.00">3.00 mm</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="spool-total-weight">Gesamtgewicht (g): *</label>
                    <input type="number" id="spool-total-weight" name="total_weight" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="spool-remaining-weight">Restgewicht (g):</label>
                    <input type="number" id="spool-remaining-weight" name="remaining_weight" min="0">
                </div>
                
                <div class="form-group">
                    <label for="spool-location">Standort:</label>
                    <input type="text" id="spool-location" name="location" placeholder="z.B. Regal A, Schublade 2">
                </div>
                
                <div class="form-group">
                    <label for="spool-purchase-date">Kaufdatum:</label>
                    <input type="date" id="spool-purchase-date" name="purchase_date">
                </div>
                
                <div class="form-group">
                    <label for="spool-batch">Chargen-Nr.:</label>
                    <input type="text" id="spool-batch" name="batch_number">
                </div>
                
                <div class="form-group">
                    <label for="spool-nfc">NFC-UID:</label>
                    <input type="text" id="spool-nfc" name="nfc_uid" placeholder="Optional">
                </div>
            </div>
            
            <div class="form-group">
                <label for="spool-notes">Notizen:</label>
                <textarea id="spool-notes" name="notes" rows="3"></textarea>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" id="cancel-add-spool">Abbrechen</button>
                <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Spule anlegen</button>
            </div>
        </form>
        
        <div id="add-spool-message"></div>
    </div>
</div>

<!-- Spool Details Modal -->
<div id="spool-details-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Spule Details</h2>
            <button id="close-details-modal" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        
        <div id="spool-details-content">
            <div class="loading">Lade Spule-Details...</div>
        </div>
    </div>
</div>

<!-- Edit Spool Modal -->
<div id="edit-spool-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Spule bearbeiten</h2>
            <button id="close-edit-modal" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        
        <form id="edit-spool-form">
            <input type="hidden" id="edit-spool-id" name="id">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="edit-spool-type">Filament-Typ: *</label>
                    <select id="edit-spool-type" name="type_id" required>
                        <option value="">Typ auswählen...</option>
                    </select>
                </div>

                <div class="form-group" id="edit-new-type-group" style="display: none;">
                    <label for="edit-new-type-input">Neuer Typ-Name:</label>
                    <input type="text" id="edit-new-type-input" name="new_type" placeholder="z.B. PETG-CF, TPU-95A">
                    <button type="button" id="edit-create-type-btn" class="btn btn-secondary">Typ erstellen</button>
                </div>
                
                <div class="form-group">
                    <label for="edit-spool-material">Material: *</label>
                    <input type="text" id="edit-spool-material" name="material" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-spool-color">Farbe:</label>
                    <select id="edit-spool-color" name="color_id">
                        <option value="">Farbe auswählen...</option>
                    </select>
                </div>
                
                <div class="form-group" id="edit-new-color-group" style="display: none;">
                    <label for="edit-new-color-input">Neue Farbe eingeben:</label>
                    <input type="text" id="edit-new-color-input" name="new_color" placeholder="Farbname eingeben..." />
                </div>
                
                <div class="form-group">
                    <label for="edit-spool-diameter">Durchmesser:</label>
                    <select id="edit-spool-diameter" name="diameter">
                        <option value="1.75">1.75 mm</option>
                        <option value="2.85">2.85 mm</option>
                        <option value="3.00">3.00 mm</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit-spool-total-weight">Gesamtgewicht (g): *</label>
                    <input type="number" id="edit-spool-total-weight" name="total_weight" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-spool-remaining-weight">Restgewicht (g):</label>
                    <input type="number" id="edit-spool-remaining-weight" name="remaining_weight" min="0">
                </div>
                
                <div class="form-group">
                    <label for="edit-spool-location">Standort:</label>
                    <input type="text" id="edit-spool-location" name="location" placeholder="z.B. Regal A, Schublade 2">
                </div>
                
                <div class="form-group">
                    <label for="edit-spool-purchase-date">Kaufdatum:</label>
                    <input type="date" id="edit-spool-purchase-date" name="purchase_date">
                </div>
                
                <div class="form-group">
                    <label for="edit-spool-batch">Chargen-Nr.:</label>
                    <input type="text" id="edit-spool-batch" name="batch_number">
                </div>
                
                <div class="form-group">
                    <label for="edit-spool-nfc">NFC-UID:</label>
                    <input type="text" id="edit-spool-nfc" name="nfc_uid" placeholder="Optional">
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit-spool-notes">Notizen:</label>
                <textarea id="edit-spool-notes" name="notes" rows="3"></textarea>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-danger" id="delete-spool-btn" style="margin-right: auto; float: left;">Löschen</button>
                <button type="button" class="btn btn-secondary" id="cancel-edit-spool">Abbrechen</button>
                <button type="submit" class="btn btn-primary" style="margin-left: 10px;">Änderungen speichern</button>
            </div>
        </form>
        
        <div id="edit-spool-message"></div>
    </div>
</div>

<style>
.spool-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.spool-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid #ddd;
}

.spool-card.low-stock {
    border-left-color: #dc2626;
}

.spool-card.good-stock {
    border-left-color: #10b981;
}

.spool-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}

.spool-material {
    font-size: 18px;
    font-weight: bold;
    color: #1f2937;
}

.spool-type {
    color: #6b7280;
    font-size: 14px;
}

.spool-weight {
    text-align: right;
}

.weight-bar {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin: 10px 0;
}

.weight-fill {
    height: 100%;
    background: #10b981;
    transition: width 0.3s ease;
}

.weight-fill.low {
    background: #dc2626;
}

.weight-fill.medium {
    background: #f59e0b;
}

.spool-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}
</style>

<script nonce="<?= $cspNonce ?? '' ?>">
// Function to create a new color
async function createNewColor(colorName) {
    try {
        const response = await fetch('/api/colors', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ name: colorName })
        });
        
        if (response.ok) {
            const result = await response.json();
            return result.color.id;
        } else {
            throw new Error('Failed to create color');
        }
    } catch (error) {
        throw error;
    }
}

// Function to create a new type
async function createNewType(typeName) {
    try {
        const response = await fetch('/api/types', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ name: typeName })
        });
        
        if (response.ok) {
            const result = await response.json();
            return result.type.id;
        } else {
            throw new Error('Failed to create type');
        }
    } catch (error) {
        throw error;
    }
}

let currentPage = 1;
let currentFilters = {};
let presets = {};

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Load initial data
    loadPresets().then(() => {
        loadSpools();
    });
    
    // Add Spool Modal Controls
    document.getElementById('add-spool-btn').addEventListener('click', showAddSpoolModal);
    document.getElementById('close-add-spool-modal').addEventListener('click', hideAddSpoolModal);
    document.getElementById('cancel-add-spool').addEventListener('click', hideAddSpoolModal);
    
    // Type creation button handlers
    document.getElementById('create-type-btn').addEventListener('click', async function() {
        const typeName = document.getElementById('new-type-input').value.trim();
        if (!typeName) {
            alert('Bitte geben Sie einen Typ-Namen ein.');
            return;
        }

        try {
            const newTypeId = await createNewType(typeName);
            await loadPresets(); // Reload types to refresh dropdowns
            
            // Select the newly created type
            document.getElementById('spool-type').value = newTypeId;
            
            // Hide the input field
            document.getElementById('new-type-group').style.display = 'none';
            document.getElementById('new-type-input').value = '';
            document.getElementById('new-type-input').required = false;
            
            alert('Neuer Typ erfolgreich erstellt!');
        } catch (error) {
            console.error('Error creating type:', error);
            alert('Fehler beim Erstellen des Typs: ' + error.message);
        }
    });

    document.getElementById('edit-create-type-btn').addEventListener('click', async function() {
        const typeName = document.getElementById('edit-new-type-input').value.trim();
        if (!typeName) {
            alert('Bitte geben Sie einen Typ-Namen ein.');
            return;
        }

        try {
            const newTypeId = await createNewType(typeName);
            await loadPresets(); // Reload types to refresh dropdowns
            
            // Select the newly created type
            document.getElementById('edit-spool-type').value = newTypeId;
            
            // Hide the input field
            document.getElementById('edit-new-type-group').style.display = 'none';
            document.getElementById('edit-new-type-input').value = '';
            document.getElementById('edit-new-type-input').required = false;
            
            alert('Neuer Typ erfolgreich erstellt!');
        } catch (error) {
            console.error('Error creating type:', error);
            alert('Fehler beim Erstellen des Typs: ' + error.message);
        }
    });
    
    // Edit Spool Modal Controls
    document.getElementById('close-edit-modal').addEventListener('click', hideEditSpoolModal);
    document.getElementById('cancel-edit-spool').addEventListener('click', hideEditSpoolModal);
    document.getElementById('delete-spool-btn').addEventListener('click', deleteSpool);
    
    // Details Modal Controls
    document.getElementById('close-details-modal').addEventListener('click', hideSpoolDetailsModal);
    
    // Filter Controls
    document.getElementById('clear-filters-btn').addEventListener('click', clearFilters);
    document.getElementById('filter-low-stock').addEventListener('change', applyFilters);
    
    // Add filter event listeners for all filter fields
    document.getElementById('filter-material').addEventListener('input', applyFilters);
    document.getElementById('filter-type').addEventListener('change', applyFilters);
    document.getElementById('filter-color').addEventListener('change', applyFilters);
    document.getElementById('filter-location').addEventListener('input', applyFilters);
    
    // Color selection handlers for "Other..." option
    document.getElementById('spool-color').addEventListener('change', function() {
        const newColorGroup = document.getElementById('new-color-group');
        if (this.value === 'other') {
            newColorGroup.style.display = 'block';
            document.getElementById('new-color-input').required = true;
        } else {
            newColorGroup.style.display = 'none';
            document.getElementById('new-color-input').required = false;
            document.getElementById('new-color-input').value = '';
        }
    });
    
    document.getElementById('edit-spool-color').addEventListener('change', function() {
        const editNewColorGroup = document.getElementById('edit-new-color-group');
        if (this.value === 'other') {
            editNewColorGroup.style.display = 'block';
            document.getElementById('edit-new-color-input').required = true;
        } else {
            editNewColorGroup.style.display = 'none';
            document.getElementById('edit-new-color-input').required = false;
            document.getElementById('edit-new-color-input').value = '';
        }
    });

    // Type selection handlers for "Other..." option
    document.getElementById('spool-type').addEventListener('change', function() {
        const newTypeGroup = document.getElementById('new-type-group');
        if (this.value === 'other') {
            newTypeGroup.style.display = 'block';
            document.getElementById('new-type-input').required = true;
        } else {
            newTypeGroup.style.display = 'none';
            document.getElementById('new-type-input').required = false;
            document.getElementById('new-type-input').value = '';
        }
    });
    
    document.getElementById('edit-spool-type').addEventListener('change', function() {
        const editNewTypeGroup = document.getElementById('edit-new-type-group');
        if (this.value === 'other') {
            editNewTypeGroup.style.display = 'block';
            document.getElementById('edit-new-type-input').required = true;
        } else {
            editNewTypeGroup.style.display = 'none';
            document.getElementById('edit-new-type-input').required = false;
            document.getElementById('edit-new-type-input').value = '';
        }
    });
    
    // Event delegation for dynamically created elements
    document.addEventListener('click', function(e) {
        // Handle spool action buttons (Details, Edit, Usage)
        if (e.target.dataset.action === 'view') {
            const spoolId = e.target.dataset.spoolId;
            viewSpool(spoolId);
        } else if (e.target.dataset.action === 'edit') {
            const spoolId = e.target.dataset.spoolId;
            editSpool(spoolId);
        } else if (e.target.dataset.action === 'adjust') {
            const spoolId = e.target.dataset.spoolId;
            adjustWeight(spoolId);
        } else if (e.target.dataset.action === 'pagination') {
            const page = parseInt(e.target.dataset.page);
            changePage(page);
        } else if (e.target.id === 'close-details-footer') {
            hideSpoolDetailsModal();
        } else if (e.target.id === 'edit-from-details') {
            const spoolId = e.target.dataset.spoolId;
            hideSpoolDetailsModal();
            showEditSpoolModal(spoolId);
        }
    });
});

async function loadPresets() {
    try {
        const response = await fetch('/api/presets');
        const data = await response.json();
        
        presets = data;
        
        // Populate filter dropdowns
        populateSelect('filter-type', data.types, 'name', 'id');
        populateSelect('filter-color', data.colors, 'name', 'id');
        
        // Populate modal dropdowns  
        populateSelect('spool-type', data.types, 'name', 'id');
        populateSelect('spool-color', data.colors, 'name', 'id');
        
        // Populate edit modal dropdowns
        populateSelect('edit-spool-type', data.types, 'name', 'id');
        populateSelect('edit-spool-color', data.colors, 'name', 'id');
        
    } catch (error) {
        console.error('Error loading presets:', error);
    }
}

function populateSelect(elementId, options, textField, valueField) {
    const select = document.getElementById(elementId);
    if (!select) {
        console.warn(`Element with ID '${elementId}' not found`);
        return;
    }
    
    // Store the current selected value
    const currentValue = select.value;
    
    // Clear all options completely
    select.innerHTML = '';
    
    // Add placeholder option back
    const placeholderOption = document.createElement('option');
    placeholderOption.value = '';
    
    if (elementId.includes('color')) {
        placeholderOption.textContent = 'Farbe auswählen...';
    } else if (elementId.includes('type')) {
        placeholderOption.textContent = 'Typ auswählen...';
    } else {
        placeholderOption.textContent = 'Auswählen...';
    }
    select.appendChild(placeholderOption);
    
    // Add all options with correct IDs
    options.forEach(option => {
        const optionElement = document.createElement('option');
        optionElement.value = option[valueField];
        optionElement.textContent = option[textField];
        select.appendChild(optionElement);
    });
    
    // Add "Other..." option for color selects
    if (elementId.includes('color')) {
        const otherOption = document.createElement('option');
        otherOption.value = 'other';
        otherOption.textContent = 'Andere...';
        select.appendChild(otherOption);
    }

    // Add "Other..." option for type selects (excluding filter)
    if (elementId.includes('type') && !elementId.includes('filter')) {
        const otherOption = document.createElement('option');
        otherOption.value = 'other';
        otherOption.textContent = 'Andere...';
        select.appendChild(otherOption);
    }
    
    // Restore selected value if it still exists
    if (currentValue && select.querySelector(`option[value="${currentValue}"]`)) {
        select.value = currentValue;
    }
}

async function loadSpools(page = 1) {
    try {
        const params = new URLSearchParams({
            page: page,
            limit: 20,
            ...currentFilters
        });
        
        console.log('Loading spools with params:', params.toString());
        
        const response = await fetch(`/api/spools?${params}`);
        const data = await response.json();
        
        console.log('Received spools data:', data);
        
        displaySpools(data.spools);
        displayPagination(data.pagination);
        
    } catch (error) {
        console.error('Error loading spools:', error);
        document.getElementById('spools-container').innerHTML = '<p>Fehler beim Laden der Spulen.</p>';
    }
}

function displaySpools(spools) {
    const container = document.getElementById('spools-container');
    
    if (spools.length === 0) {
        container.innerHTML = '<div class="card"><p>Keine Spulen gefunden.</p></div>';
        return;
    }
    
    const spoolsHTML = spools.map(spool => {
        const percentage = Math.round((spool.remaining_weight / spool.total_weight) * 100);
        const stockClass = percentage < 20 ? 'low-stock' : 'good-stock';
        const fillClass = percentage < 20 ? 'low' : percentage < 50 ? 'medium' : '';
        
        return `
            <div class="spool-card ${stockClass}">
                <div class="spool-header">
                    <div>
                        <div class="spool-material">${spool.material || 'Unbekannt'}</div>
                        <div class="spool-type">${spool.type_name || 'Kein Typ'} • ${spool.color_name || 'Keine Farbe'}</div>
                    </div>
                    <div class="spool-weight">
                        <strong>${spool.remaining_weight}g</strong><br>
                        <small>von ${spool.total_weight}g</small>
                    </div>
                </div>
                
                <div class="weight-bar">
                    <div class="weight-fill ${fillClass}" style="width: ${percentage}%"></div>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #6b7280; margin-bottom: 15px;">
                    <span>${percentage}% verbleibend</span>
                    ${spool.location ? `<span>📍 ${spool.location}</span>` : ''}
                </div>
                
                ${spool.nfc_uid ? `<div style="font-size: 12px; color: #10b981; margin-bottom: 10px;">🏷️NFC gebunden</div>` : ''}
                
                <div class="spool-actions">
                    <button class="btn btn-sm btn-primary" data-spool-id="${spool.id}" data-action="view">Details</button>
                    <button class="btn btn-sm btn-secondary" data-spool-id="${spool.id}" data-action="edit">Bearbeiten</button>
                    <button class="btn btn-sm btn-secondary" data-spool-id="${spool.id}" data-action="adjust">Verbrauch</button>
                </div>
            </div>
        `;
    }).join('');
    
    container.innerHTML = `<div class="spool-grid">${spoolsHTML}</div>`;
}

function displayPagination(pagination) {
    const container = document.getElementById('pagination-container');
    
    if (pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let paginationHTML = '';
    
    // Previous button
    if (pagination.current_page > 1) {
        paginationHTML += `<button class="btn btn-secondary" data-page="${pagination.current_page - 1}" data-action="pagination">« Zurück</button> `;
    }
    
    // Page numbers
    for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
        if (i === pagination.current_page) {
            paginationHTML += `<button class="btn btn-primary" disabled>${i}</button> `;
        } else {
            paginationHTML += `<button class="btn btn-secondary" data-page="${i}" data-action="pagination">${i}</button> `;
        }
    }
    
    // Next button
    if (pagination.current_page < pagination.total_pages) {
        paginationHTML += `<button class="btn btn-secondary" data-page="${pagination.current_page + 1}" data-action="pagination">Weiter »</button>`;
    }
    
    container.innerHTML = paginationHTML;
}

function applyFilters() {
    currentFilters = {};
    
    const material = document.getElementById('filter-material').value;
    if (material) currentFilters.material = material;
    
    const type = document.getElementById('filter-type').value;  
    if (type) currentFilters.type_id = type;
    
    const color = document.getElementById('filter-color').value;
    if (color) currentFilters.color_id = color;
    
    const location = document.getElementById('filter-location').value;
    if (location) currentFilters.location = location;
    
    if (document.getElementById('filter-low-stock').checked) {
        currentFilters.low_stock = '1';
    }
    
    console.log('Applied filters:', currentFilters);
    
    currentPage = 1;
    loadSpools(currentPage);
}

function clearFilters() {
    document.getElementById('filter-material').value = '';
    document.getElementById('filter-type').value = '';
    document.getElementById('filter-color').value = '';
    document.getElementById('filter-location').value = '';
    document.getElementById('filter-low-stock').checked = false;
    
    currentFilters = {};
    currentPage = 1;
    loadSpools(currentPage);
}

function changePage(page) {
    currentPage = page;
    loadSpools(page);
}

function showAddSpoolModal() {
    document.getElementById('add-spool-modal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function hideAddSpoolModal() {
    document.getElementById('add-spool-modal').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('add-spool-form').reset();
    document.getElementById('add-spool-message').innerHTML = '';
    // Hide and reset new color input
    document.getElementById('new-color-group').style.display = 'none';
    document.getElementById('new-color-input').required = false;
    document.getElementById('new-color-input').value = '';
}

document.getElementById('add-spool-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    const messageDiv = document.getElementById('add-spool-message');
    messageDiv.innerHTML = '';
    
    console.log('Form data before processing:', data);
    
    try {
        // Handle new type creation
        if (data.type_id === 'other' && data.new_type) {
            const newTypeId = await createNewType(data.new_type.trim());
            data.type_id = newTypeId;
            delete data.new_type;
        } else if (data.type_id === 'other') {
            messageDiv.innerHTML = '<div style="color: red;">Bitte geben Sie einen Typ-Namen ein.</div>';
            return;
        }
        
        // Handle new color creation
        if (data.color_id === 'other' && data.new_color) {
            const newColorId = await createNewColor(data.new_color.trim());
            data.color_id = newColorId;
            delete data.new_color;
        } else if (data.color_id === 'other') {
            messageDiv.innerHTML = '<div style="color: red;">Bitte geben Sie einen Farbnamen ein.</div>';
            return;
        }
        
        // Convert empty strings to null for optional fields
        Object.keys(data).forEach(key => {
            if (data[key] === '') {
                data[key] = null;
            }
        });
        
        // Set remaining weight to total weight if not specified
        if (!data.remaining_weight) {
            data.remaining_weight = data.total_weight;
        }
        
        console.log('Final data being sent to API:', data);
        
        const response = await fetch('/api/spools', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            messageDiv.innerHTML = '<div class="alert alert-success">Spule erfolgreich angelegt!</div>';
            setTimeout(() => {
                hideAddSpoolModal();
                loadSpools(currentPage);
                // Reload presets to update color options if a new color was created
                loadPresets();
            }, 1500);
        } else {
            messageDiv.innerHTML = '<div class="alert alert-error">' + (result.error || 'Fehler beim Anlegen der Spule') + '</div>';
        }
    } catch (error) {
        messageDiv.innerHTML = '<div class="alert alert-error">Netzwerkfehler aufgetreten</div>';
    }
});

// Auto-fill remaining weight when total weight changes
document.getElementById('spool-total-weight').addEventListener('input', function() {
    const remainingInput = document.getElementById('spool-remaining-weight');
    if (!remainingInput.value) {
        remainingInput.value = this.value;
    }
});

function viewSpool(id) {
    // Spool-Details in einem Modal anzeigen
    showSpoolDetailsModal(id);
}

function editSpool(id) {
    // Spool-Bearbeitungsmodal anzeigen
    showEditSpoolModal(id);
}

function adjustWeight(id) {
    // Implement weight adjustment
    const usage = prompt('Verbrauch in Gramm eingeben (negative Zahl):');
    if (usage && !isNaN(usage)) {
        updateSpoolWeight(id, parseInt(usage));
    }
}

async function updateSpoolWeight(id, deltaGrams) {
    try {
        const response = await fetch(`/api/spools/${id}/adjust`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                delta_grams: deltaGrams,
                reason: 'Manueller Verbrauch'
            })
        });
        
        if (response.ok) {
            loadSpools(currentPage); // Reload current page
        } else {
            const result = await response.json();
            alert('Fehler: ' + (result.error || 'Konnte Gewicht nicht aktualisieren'));
        }
    } catch (error) {
        alert('Netzwerkfehler aufgetreten');
    }
}

// Spool Details Modal Functions
function showSpoolDetailsModal(id) {
    document.getElementById('spool-details-modal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    loadSpoolDetails(id);
}

function hideSpoolDetailsModal() {
    document.getElementById('spool-details-modal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

async function loadSpoolDetails(id) {
    const content = document.getElementById('spool-details-content');
    content.innerHTML = '<div class="loading">Lade Spule-Details...</div>';
    
    try {
        const response = await fetch(`/api/spools/${id}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (response.ok) {
            const result = await response.json();
            const spool = result.spool;
            
            content.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div><strong>Material:</strong> ${spool.material}</div>
                    <div><strong>Typ:</strong> ${spool.filament_type}</div>
                    <div><strong>Farbe:</strong> ${spool.color_name || 'Nicht angegeben'}</div>
                    <div><strong>Durchmesser:</strong> ${spool.diameter} mm</div>
                    <div><strong>Gesamtgewicht:</strong> ${spool.total_weight}g</div>
                    <div><strong>Restgewicht:</strong> ${spool.remaining_weight}g</div>
                    <div><strong>Standort:</strong> ${spool.location || 'Nicht angegeben'}</div>
                    <div><strong>Kaufdatum:</strong> ${spool.purchase_date || 'Nicht angegeben'}</div>
                    <div><strong>Chargen-Nr.:</strong> ${spool.batch_number || 'Nicht angegeben'}</div>
                    <div><strong>NFC-UID:</strong> ${spool.nfc_uid || 'Nicht verknüpft'}</div>
                </div>
                ${spool.notes ? `<div style="margin-top: 15px;"><strong>Notizen:</strong><br>${spool.notes}</div>` : ''}
                <div style="margin-top: 20px;">
                    <div style="background: #f3f4f6; padding: 15px; border-radius: 6px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Verbrauch: ${Math.round(((spool.total_weight - spool.remaining_weight) / spool.total_weight) * 100)}%</span>
                            <div style="width: 200px; height: 10px; background: #e5e7eb; border-radius: 5px; overflow: hidden;">
                                <div style="width: ${Math.round(((spool.total_weight - spool.remaining_weight) / spool.total_weight) * 100)}%; height: 100%; background: ${spool.remaining_weight < (spool.total_weight * 0.1) ? '#dc2626' : '#10b981'}; transition: width 0.3s;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button class="btn btn-secondary" id="close-details-footer">Schließen</button>
                    <button class="btn btn-primary" style="margin-left: 10px;" id="edit-from-details" data-spool-id="${spool.id}">Bearbeiten</button>
                </div>
            `;
        } else {
            content.innerHTML = '<div style="color: red;">Fehler beim Laden der Spule-Details</div>';
        }
    } catch (error) {
        content.innerHTML = '<div style="color: red;">Netzwerkfehler aufgetreten</div>';
    }
}

// Edit Spool Modal Functions
function showEditSpoolModal(id) {
    document.getElementById('edit-spool-modal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    loadSpoolForEdit(id);
}

function hideEditSpoolModal() {
    document.getElementById('edit-spool-modal').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('edit-spool-form').reset();
    document.getElementById('edit-spool-message').innerHTML = '';
    // Hide and reset new color input
    document.getElementById('edit-new-color-group').style.display = 'none';
    document.getElementById('edit-new-color-input').required = false;
    document.getElementById('edit-new-color-input').value = '';
}

async function loadSpoolForEdit(id) {
    try {
        // Ensure presets are loaded first
        if (!presets || !presets.types || !presets.colors) {
            await loadPresets();
        }
        
        const response = await fetch(`/api/spools/${id}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (response.ok) {
            const result = await response.json();
            const spool = result.spool;
            
            // Fill form fields
            document.getElementById('edit-spool-id').value = spool.id;
            document.getElementById('edit-spool-material').value = spool.material;
            document.getElementById('edit-spool-type').value = spool.type_id;
            document.getElementById('edit-spool-color').value = spool.color_id || '';
            document.getElementById('edit-spool-diameter').value = spool.diameter;
            document.getElementById('edit-spool-total-weight').value = spool.total_weight;
            document.getElementById('edit-spool-remaining-weight').value = spool.remaining_weight;
            document.getElementById('edit-spool-location').value = spool.location || '';
            document.getElementById('edit-spool-purchase-date').value = spool.purchase_date || '';
            document.getElementById('edit-spool-batch').value = spool.batch_number || '';
            document.getElementById('edit-spool-nfc').value = spool.nfc_uid || '';
            document.getElementById('edit-spool-notes').value = spool.notes || '';
            
        } else {
            document.getElementById('edit-spool-message').innerHTML = '<div style="color: red;">Fehler beim Laden der Spule</div>';
        }
    } catch (error) {
        document.getElementById('edit-spool-message').innerHTML = '<div style="color: red;">Netzwerkfehler aufgetreten</div>';
    }
}

// Edit Spool Form Submit Handler
document.getElementById('edit-spool-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    const spoolId = data.id;
    
    const messageDiv = document.getElementById('edit-spool-message');
    messageDiv.innerHTML = '';
    
    console.log('Edit form data before processing:', data);
    
    try {
        // Handle new type creation
        if (data.type_id === 'other' && data.new_type) {
            const newTypeId = await createNewType(data.new_type.trim());
            data.type_id = newTypeId;
            delete data.new_type;
        } else if (data.type_id === 'other') {
            messageDiv.innerHTML = '<div style="color: red;">Bitte geben Sie einen Typ-Namen ein.</div>';
            return;
        }
        
        // Handle new color creation
        if (data.color_id === 'other' && data.new_color) {
            const newColorId = await createNewColor(data.new_color.trim());
            data.color_id = newColorId;
            delete data.new_color;
        } else if (data.color_id === 'other') {
            messageDiv.innerHTML = '<div style="color: red;">Bitte geben Sie einen Farbnamen ein.</div>';
            return;
        }
        
        // Convert empty strings to null for optional fields
        Object.keys(data).forEach(key => {
            if (data[key] === '') {
                data[key] = null;
            }
        });

        console.log('Final edit data being sent to API:', data);

        const response = await fetch(`/api/spools/${spoolId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            document.getElementById('edit-spool-message').innerHTML = '<div style="color: green; margin-top: 10px;">Spule erfolgreich aktualisiert!</div>';
            setTimeout(() => {
                hideEditSpoolModal();
                loadSpools(); // Refresh the spools list
                // Reload presets to update color options if a new color was created
                loadPresets();
            }, 1500);
        } else {
            document.getElementById('edit-spool-message').innerHTML = `<div style="color: red; margin-top: 10px;">Fehler: ${result.message || 'Unbekannter Fehler'}</div>`;
        }
    } catch (error) {
        document.getElementById('edit-spool-message').innerHTML = '<div style="color: red; margin-top: 10px;">Netzwerkfehler aufgetreten</div>';
    }
});

// Delete Spool Function
async function deleteSpool() {
    const spoolId = document.getElementById('edit-spool-id').value;
    
    if (confirm('Sind Sie sicher, dass Sie diese Spule löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.')) {
        try {
            const response = await fetch(`/api/spools/${spoolId}`, {
                method: 'DELETE'
            });
            
            if (response.ok) {
                hideEditSpoolModal();
                loadSpools(); // Refresh the spools list
                alert('Spule erfolgreich gelöscht!');
            } else {
                const result = await response.json();
                alert(`Fehler beim Löschen: ${result.message || 'Unbekannter Fehler'}`);
            }
        } catch (error) {
            alert('Netzwerkfehler beim Löschen aufgetreten');
        }
    }
}

</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>







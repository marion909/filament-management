<?php
$title = 'Registrierung';
ob_start();
?>

<div style="max-width: 400px; margin: 50px auto;">
    <div class="card">
        <h1 style="text-align: center; margin-bottom: 30px;">Registrierung</h1>
        
        <form id="register-form">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="email">E-Mail-Adresse:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Passwort:</label>
                <input type="password" id="password" name="password" required minlength="8">
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Passwort bestätigen:</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Registrieren</button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="/login">Bereits registriert? Hier anmelden</a>
        </div>
        
        <div id="message" style="margin-top: 15px;"></div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? '' ?>">
document.getElementById('register-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    const messageDiv = document.getElementById('message');
    
    // Client-side validation
    if (data.password !== data.password_confirm) {
        messageDiv.innerHTML = '<div class="alert alert-error">Passwörter stimmen nicht überein</div>';
        return;
    }
    
    if (data.password.length < 8) {
        messageDiv.innerHTML = '<div class="alert alert-error">Passwort muss mindestens 8 Zeichen lang sein</div>';
        return;
    }
    
    try {
        const response = await fetch('/api/auth/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            messageDiv.innerHTML = '<div class="alert alert-success">Registrierung erfolgreich! Bitte bestätigen Sie Ihre E-Mail.</div>';
            this.reset();
        } else {
            messageDiv.innerHTML = '<div class="alert alert-error">' + (result.message || 'Registrierung fehlgeschlagen') + '</div>';
        }
    } catch (error) {
        messageDiv.innerHTML = '<div class="alert alert-error">Netzwerkfehler aufgetreten</div>';
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
<?php
$title = 'Login';
ob_start();
?>

<div style="max-width: 400px; margin: 50px auto;">
    <div class="card">
        <h1 style="text-align: center; margin-bottom: 30px;">Login</h1>
        
        <form id="login-form">
            <div class="form-group">
                <label for="email">E-Mail-Adresse:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Passwort:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Anmelden</button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="/register">Noch kein Konto? Hier registrieren</a>
        </div>
        
        <div id="message" style="margin-top: 15px;"></div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? '' ?>">
document.getElementById('login-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    const messageDiv = document.getElementById('message');
    
    try {
        const response = await fetch('/api/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            messageDiv.innerHTML = '<div class="alert alert-success">Login erfolgreich! Weiterleitung...</div>';
            setTimeout(() => {
                window.location.href = '/';
            }, 1000);
        } else {
            messageDiv.innerHTML = '<div class="alert alert-error">' + (result.message || 'Login fehlgeschlagen') + '</div>';
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
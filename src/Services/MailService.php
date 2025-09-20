<?php

declare(strict_types=1);

namespace Filament\Services;

/**
 * Simple Mail Service (without PHPMailer for now)
 */
class MailService
{
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config['mail'] ?? [];
    }
    
    /**
     * Send verification email
     */
    public function sendVerificationEmail(string $email, string $name, string $token): bool
    {
        $subject = 'E-Mail-Verifizierung - Filament Management';
        $verificationUrl = ($_ENV['APP_URL'] ?? 'http://localhost:8000') . '/api/auth/verify?token=' . $token;
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>E-Mail-Verifizierung</title>
        </head>
        <body>
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h1>Willkommen bei Filament Management, {$name}!</h1>
                
                <p>Vielen Dank für Ihre Registrierung. Bitte bestätigen Sie Ihre E-Mail-Adresse, indem Sie auf den folgenden Link klicken:</p>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$verificationUrl}' style='background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;'>E-Mail bestätigen</a>
                </p>
                
                <p>Falls der Button nicht funktioniert, kopieren Sie bitte diesen Link in Ihren Browser:</p>
                <p style='word-break: break-all; color: #666; font-size: 12px;'>{$verificationUrl}</p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
                <p style='color: #666; font-size: 12px;'>
                    Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht darauf.<br>
                    Filament Management System
                </p>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendMail($email, $subject, $body);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(string $email, string $name, string $token): bool
    {
        $subject = 'Passwort zurücksetzen - Filament Management';
        $resetUrl = ($_ENV['APP_URL'] ?? 'http://localhost:8000') . '/reset-password?token=' . $token;
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Passwort zurücksetzen</title>
        </head>
        <body>
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h1>Passwort zurücksetzen</h1>
                
                <p>Hallo {$name},</p>
                
                <p>Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts erhalten. Klicken Sie auf den folgenden Link, um ein neues Passwort zu erstellen:</p>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetUrl}' style='background: #dc2626; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;'>Passwort zurücksetzen</a>
                </p>
                
                <p>Dieser Link ist nur für eine Stunde gültig.</p>
                
                <p>Falls Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren.</p>
                
                <p style='word-break: break-all; color: #666; font-size: 12px;'>{$resetUrl}</p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
                <p style='color: #666; font-size: 12px;'>
                    Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht darauf.<br>
                    Filament Management System
                </p>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendMail($email, $subject, $body);
    }
    
    /**
     * Send email (simple PHP mail function for now)
     */
    private function sendMail(string $to, string $subject, string $body): bool
    {
        // For development, just log the email instead of sending
        $logFile = __DIR__ . '/../../logs/emails.log';
        $logEntry = sprintf(
            "[%s] TO: %s | SUBJECT: %s\n%s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            str_repeat('=', 80),
            strip_tags($body)
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // In production, use PHPMailer or similar
        /*
        $headers = [
            'From: ' . $this->config['from']['name'] . ' <' . $this->config['from']['address'] . '>',
            'Reply-To: ' . $this->config['from']['address'],
            'Content-Type: text/html; charset=UTF-8',
            'MIME-Version: 1.0'
        ];
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
        */
        
        return true; // Always return true for development
    }
}
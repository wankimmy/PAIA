<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class PhpMailerService
{
    protected PHPMailer $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);

        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = env('MAIL_HOST', 'smtp.gmail.com');
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = env('MAIL_USERNAME');
            $this->mailer->Password = env('MAIL_PASSWORD');
            
            // Handle encryption (tls, ssl, or empty string)
            $encryption = env('MAIL_ENCRYPTION', 'tls');
            if ($encryption === 'ssl') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $this->mailer->SMTPSecure = '';
            }
            
            $this->mailer->Port = env('MAIL_PORT', 587);
            
            // Enable verbose debug output in development
            if (config('app.debug')) {
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            } else {
                $this->mailer->SMTPDebug = SMTP::DEBUG_OFF;
            }
            
            // Character set
            $this->mailer->CharSet = 'UTF-8';
            
            // From address
            $this->mailer->setFrom(
                env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
                env('MAIL_FROM_NAME', config('app.name', 'PAIA'))
            );
        } catch (Exception $e) {
            \Log::error('PHPMailer initialization failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send an email
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (plain text)
     * @param string|null $htmlBody Optional HTML body
     * @return bool
     * @throws Exception
     */
    public function send(string $to, string $subject, string $body, ?string $htmlBody = null): bool
    {
        try {
            // Recipients
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);

            // Content
            $this->mailer->isHTML($htmlBody !== null);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody ?? $body;
            $this->mailer->AltBody = $body; // Plain text version

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            \Log::error('PHPMailer Error', [
                'to' => $to,
                'error' => $this->mailer->ErrorInfo,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send OTP email
     *
     * @param string $to Recipient email address
     * @param string $code OTP code
     * @return bool
     * @throws Exception
     */
    public function sendOtp(string $to, string $code): bool
    {
        $subject = 'Your Login Code';
        $body = "Your login code is: {$code}\n\nThis code will expire in 10 minutes.";
        
        $htmlBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .code { font-size: 32px; font-weight: bold; color: #4f46e5; text-align: center; padding: 20px; background: #f3f4f6; border-radius: 8px; margin: 20px 0; }
                .footer { margin-top: 30px; font-size: 12px; color: #6b7280; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Your Login Code</h2>
                <p>Use the following code to log in to your account:</p>
                <div class='code'>{$code}</div>
                <p>This code will expire in 10 minutes.</p>
                <p>If you didn't request this code, please ignore this email.</p>
                <div class='footer'>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $body, $htmlBody);
    }
}


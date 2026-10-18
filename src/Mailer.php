<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    public static function send(string $toEmail, string $toName, string $subject, string $body): bool
    {
        $localFile = __DIR__ . '/../config/mail.local.php';
        $local = is_file($localFile) ? require $localFile : [];
        $local = is_array($local) ? $local : [];
        $username = getenv('SMTP_USERNAME') ?: ($local['username'] ?? '');
        $password = getenv('SMTP_PASSWORD') ?: ($local['password'] ?? '');
        if ($username === '' || $password === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) return false;
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = getenv('SMTP_HOST') ?: ($local['host'] ?? 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $mail->Host === 'smtp.gmail.com' ? preg_replace('/\s+/', '', $password) : $password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->Timeout = 15;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(getenv('SMTP_FROM') ?: ($local['from'] ?? $username), 'Uni Calendar');
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8');
            return $mail->send();
        } catch (Exception $e) {
            error_log('Uni Calendar email delivery failed; check SMTP settings.');
            return false;
        }
    }
}

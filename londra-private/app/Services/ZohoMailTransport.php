<?php
declare(strict_types=1);
namespace App\Services;
use App\Contracts\EmailTransport;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
final class ZohoMailTransport implements EmailTransport
{
    public function send(string $recipient, string $subject, string $html, string $plain, ?string $replyTo = null): void
    {
        $config = config('mail.mailers.smtp');
        $host = trim((string) ($config['host'] ?? ''));
        $port = (int) ($config['port'] ?? 0);
        $username = trim((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');
        $from = trim((string) config('mail.from.address'));
        $replyTo ??= (string) config('mail.reply_to.address');
        if ($host === '' || $username === '' || $password === '' || !in_array($port, [465, 587], true)) { throw new RuntimeException('Zoho Mail SMTP configuration is incomplete.'); }
        foreach ([$recipient, $from, $replyTo] as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('An email address is invalid.'); }
        }
        $mail = new PHPMailer(true);
        $mail->isSMTP(); $mail->Host = $host; $mail->Port = $port; $mail->SMTPAuth = true;
        $mail->Username = $username; $mail->Password = $password;
        $mail->SMTPSecure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAutoTLS = false; $mail->SMTPDebug = 0; $mail->Timeout = 15; $mail->SMTPKeepAlive = false;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
        $mail->CharSet = PHPMailer::CHARSET_UTF8; $mail->Encoding = PHPMailer::ENCODING_BASE64;
        $mail->setFrom($from, (string) config('mail.from.name', 'Londra Transfers'));
        $mail->addAddress($recipient); $mail->addReplyTo($replyTo);
        $mail->isHTML(true); $mail->Subject = $subject; $mail->Body = $html; $mail->AltBody = $plain;
        $mail->send();
    }
}

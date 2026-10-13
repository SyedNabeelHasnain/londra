<?php
declare(strict_types=1);
namespace App\Contracts;
interface EmailTransport
{
    public function send(string $recipient, string $subject, string $html, string $plain, ?string $replyTo = null): void;
}

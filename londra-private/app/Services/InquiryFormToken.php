<?php
declare(strict_types=1);
namespace App\Services;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
final class InquiryFormToken
{
    public function issue(string $sessionId, string $locale): string
    {
        return Crypt::encryptString(json_encode(['session' => hash('sha256', $sessionId), 'locale' => $locale, 'issued_at' => time()], JSON_THROW_ON_ERROR));
    }
    public function valid(string $token, string $sessionId, string $locale): bool
    {
        try { $data = json_decode(Crypt::decryptString($token), true, 16, JSON_THROW_ON_ERROR); }
        catch (DecryptException|\JsonException $exception) { return false; }
        if (!is_array($data) || !is_string($data['session'] ?? null) || !is_string($data['locale'] ?? null) || !is_int($data['issued_at'] ?? null)) { return false; }
        $now = time();
        return hash_equals(hash('sha256', $sessionId), $data['session']) && $data['locale'] === $locale && $data['issued_at'] <= $now && $data['issued_at'] >= $now - 7200;
    }
}

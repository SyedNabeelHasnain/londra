<?php
declare(strict_types=1);
namespace App\Services;
use MaxMind\Db\Reader;
final class LocalCountryResolver implements CountryResolver
{
    public function country(?string $ip): ?string
    {
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { return null; }
        $path = storage_path('app/geoip/country.mmdb');
        if (!is_file($path)) { return null; }
        try {
            $reader = new Reader($path);
            try { $code = $reader->get($ip)['country']['iso_code'] ?? null; } finally { $reader->close(); }
            return is_string($code) && preg_match('/^[A-Z]{2}$/D', $code) ? $code : null;
        } catch (\Throwable) { return null; }
    }
}

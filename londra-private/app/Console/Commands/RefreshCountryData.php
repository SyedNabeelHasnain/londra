<?php
declare(strict_types=1);
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use MaxMind\Db\Reader;
final class RefreshCountryData extends Command
{
    protected $signature = 'londra:refresh-country-data';
    protected $description = 'Refresh the licensed local country database without transmitting visitor addresses.';
    public function handle(): int
    {
        if (!config('londra.country_lookup')) { $this->info('Country lookup is disabled.'); return self::SUCCESS; }
        return Cache::lock('londra-country-refresh', 120)->block(2, function (): int {
            $edition = now()->format('Y-m');
            $directory = storage_path('app/geoip');
            $versionPath = $directory.'/country-version.json';
            $old = is_file($versionPath) ? json_decode((string) file_get_contents($versionPath), true) : [];
            if (($old['edition'] ?? null) === $edition) { return self::SUCCESS; }
            $temporary = $directory.'/country-'.bin2hex(random_bytes(8)).'.mmdb';
            try {
                $response = Http::timeout(35)->connectTimeout(10)->withHeaders(['User-Agent' => 'LondraTransfersCountryData/1.0', 'Referer' => 'https://db-ip.com/db/download/ip-to-country-lite'])->get('https://download.db-ip.com/free/dbip-country-lite-'.$edition.'.mmdb.gz');
                $response->throw(); $packed = $response->body();
                if (strlen($packed) > 16 * 1024 * 1024) { throw new \RuntimeException('Country database exceeds the permitted download size.'); }
                $bytes = gzdecode($packed, 32 * 1024 * 1024);
                if ($bytes === false || strlen($bytes) < 1024) { throw new \RuntimeException('Country database is invalid.'); }
                if (!is_dir($directory)) { mkdir($directory, 0700, true); }
                if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes)) { throw new \RuntimeException('Country database could not be staged.'); }
                $reader = new Reader($temporary); $metadata = $reader->metadata(); $reader->close();
                if (!str_contains(strtolower($metadata->databaseType), 'country')) { throw new \RuntimeException('Expected a country-only database.'); }
                if (!rename($temporary, $directory.'/country.mmdb')) { throw new \RuntimeException('Country database could not be installed.'); }
                file_put_contents($versionPath, json_encode(['edition' => $edition, 'sha256' => hash('sha256', $bytes), 'source' => 'https://db-ip.com/db/download/ip-to-country-lite', 'licence' => 'CC BY 4.0'], JSON_THROW_ON_ERROR));
                $this->info('Local country data refreshed.'); return self::SUCCESS;
            } catch (\Throwable) {
                @unlink($temporary); $this->warn('Country data refresh failed. The previous local database remains available.'); return self::FAILURE;
            }
        });
    }
}

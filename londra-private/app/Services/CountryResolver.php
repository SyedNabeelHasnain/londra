<?php
declare(strict_types=1);
namespace App\Services;
interface CountryResolver
{
    public function country(?string $ip): ?string;
}

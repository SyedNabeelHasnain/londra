<?php
declare(strict_types=1);
namespace App\Services;
use App\Domain\LocalePolicy;
final class ContentPathPolicy
{
    public static function isReserved(string $path): bool
    {
        return $path==='form-tokens'||preg_match('~^privacy-request/verify/[a-f0-9]{64}$~D',$path)===1;
    }
    public static function isPublicPath(string $path): bool { return LocalePolicy::safePath($path)&&!self::isReserved($path); }
}

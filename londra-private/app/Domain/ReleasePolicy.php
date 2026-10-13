<?php
declare(strict_types=1);
namespace App\Domain;
final class ReleasePolicy
{
    public static function inquiryAllowed(array $flags): bool
    {
        return !empty($flags['accept_inquiries']) && !empty($flags['privacy_approved']) && !empty($flags['publication_approved']);
    }
    public static function indexAllowed(array $flags): bool
    {
        return !empty($flags['indexing_enabled']) && !empty($flags['publication_approved']);
    }
    public static function canPublish(bool $approvalRequired, bool $approved, array $locales): bool
    {
        if ($approvalRequired && !$approved) { return false; }
        $actual = array_values(array_unique($locales)); sort($actual);
        return $actual === ['en', 'es', 'it'];
    }
}

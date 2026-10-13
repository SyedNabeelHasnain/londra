<?php
declare(strict_types=1);
namespace App\Domain;
final class CsvSafety
{
    public static function cell(mixed $value): string
    {
        $text = (string) ($value ?? '');
        return preg_match('/^[\s]*[=+@\-\t\r]/u', $text) ? "'".$text : $text;
    }
}

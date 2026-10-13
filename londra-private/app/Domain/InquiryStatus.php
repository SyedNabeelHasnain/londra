<?php
declare(strict_types=1);
namespace App\Domain;
enum InquiryStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case AwaitingResponse = 'awaiting_response';
    case Closed = 'closed';
    public function label(): string
    {
        return match ($this) { self::New => 'New', self::Contacted => 'Contacted', self::AwaitingResponse => 'Awaiting response', self::Closed => 'Closed' };
    }
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $status) { $options[$status->value] = $status->label(); }
        return $options;
    }
}

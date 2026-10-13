<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Setting;
final class PrivacyConfiguration
{
    public function values(): array
    {
        return (Setting::find('privacy-operations')?->value ?? []) + ['retention_days' => 180, 'retention_enabled' => false, 'retention_approved' => false, 'mailbox_process_confirmed' => false, 'backup_process_confirmed' => false];
    }
    public function retentionEnabled(): bool
    {
        $v = $this->values();
        $days = filter_var($v['retention_days'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3650]]);
        return $days !== false && $v['retention_enabled'] && $v['retention_approved'] && $v['mailbox_process_confirmed'] && $v['backup_process_confirmed'];
    }
}

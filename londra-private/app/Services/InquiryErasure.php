<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Inquiry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class InquiryErasure
{
    public function erase(Inquiry $inquiry, string $reason, ?int $actor = null): void
    {
        if ($inquiry->legal_hold_at || $inquiry->converted_at) { throw ValidationException::withMessages(['request' => 'This record has a legal hold or a confirmed service. Review the applicable retention basis before removing that protection.']); }
        $ids = $inquiry->deliveries()->pluck('id')->all();
        if ($ids !== []) {
            DB::table('jobs')->whereIn('queue', ['default', 'mail'])->get(['id', 'payload'])->each(function ($job) use ($ids): void {
                if ($this->isInquiryJob((string) $job->payload, $ids)) { DB::table('jobs')->where('id', $job->id)->delete(); }
            });
            DB::table('failed_jobs')->get(['id', 'payload'])->each(function ($job) use ($ids): void {
                if ($this->isInquiryJob((string) $job->payload, $ids)) { DB::table('failed_jobs')->where('id', $job->id)->delete(); }
            });
        }
        $inquiry->delete();
        DB::table('erasure_events')->insert(['reason' => $reason, 'record_count' => 1, 'user_id' => $actor, 'created_at' => now()]);
    }
    private function isInquiryJob(string $payload, array $ids): bool
    {
        try { $data = json_decode($payload, true, 32, JSON_THROW_ON_ERROR); } catch (\JsonException) { return false; }
        if (($data['displayName'] ?? '') !== \App\Jobs\SendInquiryEmail::class) { return false; }
        $command = (string) ($data['data']['command'] ?? '');
        foreach ($ids as $id) { if (preg_match('/s:10:"deliveryId";i:'.preg_quote((string) $id, '/').';/', $command)) { return true; } }
        return false;
    }
}

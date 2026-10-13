<?php
declare(strict_types=1);
namespace App\Services;
use App\Actions\SubmitDataRequest;
use App\Models\DataRequest;
use App\Models\Inquiry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class DataRequestReview
{
    public function eraseDatabase(DataRequest $request, int $actor): void
    {
        DB::transaction(function () use ($request, $actor): void {
            $locked = DataRequest::lockForUpdate()->findOrFail($request->id);
            if (!$locked->verified_at || $locked->status !== 'in_review') { throw ValidationException::withMessages(['request' => 'Verify the email address and review the request before deleting records.']); }
            $records = Inquiry::where('email_lookup', $locked->email_lookup)->where('id', '<=', $locked->inquiry_cutoff_id)->orderBy('id')->lockForUpdate()->get();
            if ($records->contains(fn(Inquiry $i) => $i->legal_hold_at || $i->converted_at)) { throw ValidationException::withMessages(['request' => 'A matching record has a legal hold or confirmed-service status. Review the legal retention basis and send an appropriate response instead of automatically erasing it.']); }
            foreach ($records as $inquiry) { app(InquiryErasure::class)->erase($inquiry, 'customer_request', $actor); }
            $locked->update(['status' => 'database_erased', 'database_erased_at' => now(), 'erased_count' => $records->count(), 'reviewed_by' => $actor]);
        }, 3);
    }
    public function complete(DataRequest $request, int $actor, bool $mailboxReviewed, bool $backupsReviewed): void
    {
        if (!$mailboxReviewed || !$backupsReviewed) { throw ValidationException::withMessages(['request' => 'Complete the mailbox, export and backup review before acknowledging completion.']); }
        DB::transaction(function () use ($request, $actor): void {
            $locked = DataRequest::lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== 'database_erased') { throw ValidationException::withMessages(['request' => 'Complete the database erasure first.']); }
            $records = Inquiry::where('email_lookup', $locked->email_lookup)->where('id', '<=', $locked->inquiry_cutoff_id)->orderBy('id')->lockForUpdate()->get();
            if ($records->contains(fn(Inquiry $i) => $i->legal_hold_at || $i->converted_at)) { throw ValidationException::withMessages(['request' => 'A protected matching record now exists. Review it before completing this request.']); }
            foreach ($records as $inquiry) { app(InquiryErasure::class)->erase($inquiry, 'customer_request', $actor); }
            foreach (DataRequest::where('email_lookup', $locked->email_lookup)->where('id', '<', $locked->id)->lockForUpdate()->get() as $other) {
                $other->deliveries()->whereNull('smtp_accepted_at')->update(['status' => 'cancelled']);
                $other->scrubContact();
                if (!in_array($other->status, ['completed', 'refused'], true)) { $other->update(['status' => 'cancelled']); }
            }
            $locked->update(['status' => 'completed', 'completed_at' => now(), 'reviewed_by' => $actor, 'erased_count' => $locked->erased_count + $records->count()]);
            app(SubmitDataRequest::class)->queue($locked, 'complete');
        }, 3);
    }
    public function refuse(DataRequest $request, int $actor, string $reason): void
    {
        if (mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 2000) { throw ValidationException::withMessages(['decision_reason' => 'Explain the applicable legal reason clearly.']); }
        DB::transaction(function () use ($request, $actor, $reason): void {
            $locked = DataRequest::lockForUpdate()->findOrFail($request->id);
            if (!$locked->verified_at || $locked->status !== 'in_review') { throw ValidationException::withMessages(['request' => 'Only a verified request awaiting review can receive this decision.']); }
            $locked->update(['status' => 'refused', 'decision_reason' => trim($reason), 'reviewed_by' => $actor, 'completed_at' => now()]);
            app(SubmitDataRequest::class)->queue($locked, 'decision');
        }, 3);
    }
}

<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Actions\SubmitDataRequest;
use App\Http\Requests\StoreDataRequest;
use App\Models\DataRequest;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Services\InquiryFormToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
final class DataRequestController
{
    public function store(StoreDataRequest $request, SubmitDataRequest $submit, InquiryFormToken $tokens)
    {
        $locale = (string) $request->route('locale');
        abort_unless($tokens->valid((string) $request->input('form_token'), $request->session()->getId(), $locale.':privacy'), 419);
        $target = $request->expectsJson() ? null : $this->receiptTarget($locale);
        $submit->handle($request->validated(), $locale, $request->session()->getId());
        $result = ['message' => __('ui.privacy_received')];
        if ($request->expectsJson()) { return response()->json($result, 202); }
        return redirect($target->url(), 303)->with('privacy_receipt', $result['message']);
    }
    public function confirm(Request $request, string $locale, string $token)
    {
        $candidate = DataRequest::where('verification_hash', hash('sha256', $token))->where('locale', $locale)->where('status', 'received')->whereNull('verified_at')->where('verification_expires_at', '>', now())->first();
        $translation = Page::published()->with('translations')->where('key', 'privacy-request')->first()?->translation($locale);
        return response()->view('privacy-confirm', ['valid' => $candidate !== null, 'locale' => $locale, 'token' => $token, 'privacy_url' => $translation?->ready ? $translation->url() : null])->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store, private');
    }
    public function verify(Request $request, string $locale, string $token)
    {
        $target = $this->receiptTarget($locale);
        $verified = DB::transaction(function () use ($locale, $token): bool {
            $record = DataRequest::where('verification_hash', hash('sha256', $token))->where('locale', $locale)->lockForUpdate()->first();
            if (!$record || $record->status !== 'received' || $record->verified_at || !$record->verification_expires_at || $record->verification_expires_at->lte(now())) { return false; }
            $record->update(['status' => 'in_review', 'verified_at' => now(), 'verification_token' => null, 'verification_hash' => null]);
            app(SubmitDataRequest::class)->queue($record, 'admin');
            return true;
        }, 3);
        return redirect($target->url(), 303)->with('privacy_receipt', __($verified ? 'ui.privacy_verified' : 'ui.privacy_expired'));
    }
    private function receiptTarget(string $locale): PageTranslation
    {
        $target = Page::published()->with('translations')->where('key', 'privacy-request')->first()?->translation($locale);
        abort_unless($target?->ready, 503);
        return $target;
    }
}

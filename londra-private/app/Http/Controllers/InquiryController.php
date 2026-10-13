<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Actions\SubmitInquiry;
use App\Http\Requests\StoreInquiryRequest;
use App\Services\InquiryFormToken;
use App\Services\PublicationState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
final class InquiryController
{
    public function refresh(Request $request, string $locale, InquiryFormToken $tokens, PublicationState $state): JsonResponse
    {
        $inquiriesAllowed = $state->inquiriesAllowed();
        $privacyRequestsAllowed = $state->privacyRequestsAllowed();
        $sessionId = $request->session()->getId();
        $result = [
            'csrf_token' => csrf_token(),
            'form_token' => $inquiriesAllowed ? $tokens->issue($sessionId, $locale) : null,
            'privacy_form_token' => $privacyRequestsAllowed ? $tokens->issue($sessionId, $locale.':privacy') : null,
            'inquiries_allowed' => $inquiriesAllowed,
            'privacy_requests_allowed' => $privacyRequestsAllowed,
        ];
        if (!$inquiriesAllowed && !$privacyRequestsAllowed) { $result['message'] = __('ui.forms_unavailable'); }
        return response()->json($result, $inquiriesAllowed || $privacyRequestsAllowed ? 200 : 403)
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
    public function store(StoreInquiryRequest $request, SubmitInquiry $submit, InquiryFormToken $tokens): JsonResponse|RedirectResponse
    {
        $locale = (string) $request->route('locale');
        abort_unless($tokens->valid((string) $request->input('form_token'), $request->session()->getId(), $locale), 419);
        $data = $request->validated();
        $target = null;
        if (!$request->expectsJson()) {
            $page = \App\Models\Page::published()->with('translations')->where('key', $data['type'] === 'contact' ? 'contact' : 'enquiry')->first();
            $target = $page?->translation($locale);
            abort_unless($target?->ready, 503);
        }
        $inquiry = $submit->handle($data, $locale, $request->session()->getId());
        $result = ['reference' => $inquiry->reference, 'message' => __('ui.received'), 'booking_confirmed' => false, 'payment_taken' => false];
        if ($request->expectsJson()) { return response()->json($result, $inquiry->wasRecentlyCreated ? 201 : 200); }
        return redirect($target->url(), 303)->with('inquiry_receipt', $result);
    }
}

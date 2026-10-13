<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Inquiry;
use RuntimeException;
final class InquirySmtpSender
{
    public function send(Inquiry $inquiry, string $kind): void
    {
        if (!in_array($kind, ['admin', 'customer'], true)) {
            throw new RuntimeException('Unknown notification category.');
        }
        $locale = $kind === 'admin' ? 'en' : $inquiry->locale;
        if (!in_array($locale, ['en', 'it', 'es'], true)) {
            throw new RuntimeException('Unsupported notification locale.');
        }
        $recipient = $kind === 'admin' ? (string) config('londra.contact.email') : (string) $inquiry->email;
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('SMTP recipient address is invalid.');
        }
        app(PublicCopy::class)->applyLocale($locale);
        $contact = app(PublicContact::class)->current();
        $subject = ($kind === 'admin'
            ? 'New '.($inquiry->type === 'contact' ? 'contact' : 'journey').' enquiry'
            : (string) __('ui.email_subject', [], $locale)).' | '.$inquiry->reference;
        $viewData = ['inquiry' => $inquiry, 'kind' => $kind, 'locale' => $locale, 'contact' => $contact];
        $html = view('emails.inquiry-received', $viewData)->render();
        $plain = view('emails.inquiry-received-text', $viewData)->render();
        app(\App\Contracts\EmailTransport::class)->send($recipient, $subject, $html, $plain, $kind === 'admin' ? (string) $inquiry->email : null);
    }
}

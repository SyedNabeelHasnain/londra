<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ __('ui.privacy_email_subject', [], $locale) }}</title>
<style>
@media screen and (max-width:620px) {
  .email-outer { padding:16px 10px !important; }
  .email-pad { padding:28px 24px !important; }
}
</style>
</head>
<body style="margin:0;padding:0;width:100%;background-color:#F7F7F5;color:#0B0B0B;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;background-color:#F7F7F5;">
<tr><td class="email-outer" align="center" style="padding:32px 16px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;max-width:600px;border-collapse:collapse;background-color:#FFFFFF;">
<tr><td align="center" bgcolor="#000000" style="background:#000000;padding:29px 22px 27px;text-align:center;">
<p style="margin:0;color:#BDA177;font-family:Georgia,'Times New Roman',serif;font-size:23px;letter-spacing:0.10em;line-height:1.35;">LONDRA TRANSFERS</p>
<p style="margin:11px 0 0;color:#F1ECD9;font-size:10px;line-height:1.6;letter-spacing:0.23em;text-transform:uppercase;">{{ __('ui.privacy_email_subject', [], $locale) }}</p>
</td></tr>
<tr><td height="3" bgcolor="#BDA177" style="height:3px;background-color:#BDA177;font-size:0;line-height:0;">&nbsp;</td></tr>
<tr><td class="email-pad" style="padding:44px 46px 34px;text-align:left;">
@foreach(preg_split('/\n\s*\n/u', $text) as $paragraph)
<p style="margin:0 0 20px;color:#0B0B0B;font-size:15px;line-height:1.85;">{{ $paragraph }}</p>
@endforeach
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;background:#F7F7F5;border-left:3px solid #BDA177;margin-top:6px;">
<tr><td style="padding:19px 21px;">
<p style="margin:0 0 5px;color:#0B0B0B;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;">{{ __('ui.reference', [], $locale) }}</p>
<p style="margin:0;color:#000000;font-size:16px;font-weight:bold;line-height:1.4;word-break:break-word;">{{ $reference }}</p>
</td></tr></table>
@if($url)
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:27px 0 14px;"><tr><td bgcolor="#0B0B0B" style="background:#0B0B0B;padding:13px 21px;">
<a href="{{ $url }}" style="font-size:13px;font-weight:bold;color:#FFFFFF;text-decoration:none;">{{ __('ui.privacy_verify_button', [], $locale) }} &rarr;</a>
</td></tr></table>
<p style="margin:0;color:#0B0B0B;font-size:12px;line-height:1.7;word-break:break-all;"><a href="{{ $url }}" style="color:#0B0B0B;">{{ $url }}</a></p>
@endif
</td></tr>
<tr><td style="padding:0 46px;"><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;"><tr><td bgcolor="#F1ECD9" height="1" style="height:1px;background:#F1ECD9;font-size:0;line-height:0;">&nbsp;</td></tr></table></td></tr>
<tr><td align="center" bgcolor="#FFFFFF" style="padding:27px 22px 32px;text-align:center;">
<a href="tel:{{ $contact['phone_link'] }}" style="font-size:13px;line-height:1.8;color:#0B0B0B;text-decoration:none;">{{ $contact['phone_display'] }}</a>
<span style="padding:0 11px;color:#BDA177;">|</span>
<a href="mailto:{{ $contact['email'] }}" style="font-size:13px;line-height:1.8;color:#0B0B0B;text-decoration:none;">{{ $contact['email'] }}</a>
<p style="margin:11px 0 0;color:#0B0B0B;font-size:11px;line-height:1.7;">Londra Transfers Limited · {{ __('ui.location', [], $locale) }}</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>

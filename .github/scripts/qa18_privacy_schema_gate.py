from pathlib import Path

path=Path('londra/app/Http/Controllers/PrivacyRequestController.php')
text=path.read_text(encoding='utf-8')
needle='use App\\Models\\Page;\n'
if text.count(needle)!=1:
    raise SystemExit('Expected privacy controller imports not found')
text=text.replace(needle,needle+'use Illuminate\\Support\\Facades\\Schema;\n')
anchor="'formAllowed'=>true,'privacyNoticeAvailable'"
if text.count(anchor)!=1:
    raise SystemExit('Expected public form gate not found')
text=text.replace(anchor,"'formAllowed'=>Schema::hasTable('privacy_requests') && Schema::hasTable('privacy_request_notices'),'privacyNoticeAvailable'")
before="        abort_unless(isset(self::LANGUAGES[$locale]),404);\n        $data=$request->validate(["
after="        abort_unless(isset(self::LANGUAGES[$locale]),404);\n        abort_unless(Schema::hasTable('privacy_requests') && Schema::hasTable('privacy_request_notices'),503);\n        $data=$request->validate(["
if text.count(before)!=1:
    raise SystemExit('Expected privacy post location not found')
path.write_text(text.replace(before,after),encoding='utf-8')
print('Privacy request form requires installed privacy tables; direct email rights route remains available')

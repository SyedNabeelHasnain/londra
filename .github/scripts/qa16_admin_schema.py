from pathlib import Path

base = Path('londra')

readiness = base / 'app/Filament/Pages/Readiness.php'
text = readiness.read_text()
old_import = 'use Filament\\Pages\\Page;'
if text.count(old_import) != 1 or 'getHeaderActions' in text:
    raise SystemExit('Unexpected Readiness baseline')
text = text.replace(old_import, old_import + '''
use Filament\\Actions\\Action;
use Filament\\Forms\\Components\\Checkbox;
use Filament\\Notifications\\Notification;
use Illuminate\\Support\\Facades\\Artisan;
use Illuminate\\Support\\Facades\\Schema;
use Illuminate\\Validation\\ValidationException;''')
needle = '    public function checks(): array { return app(ReadinessReport::class)->checks(); }'
if text.count(needle) != 1:
    raise SystemExit('Readiness insertion point missing')
action = '''    protected function getHeaderActions(): array
    {
        return [
            Action::make('applyPrivacySchema')
                ->label('Install privacy-request database tables')
                ->color('warning')
                ->visible(fn(): bool => Schema::hasTable('inquiries') && !Schema::hasTable('privacy_requests'))
                ->requiresConfirmation()
                ->modalHeading('Apply the privacy database update')
                ->modalDescription('Make a current MySQL backup in Hostinger hPanel before proceeding. This additive update creates privacy-request tables and adds enquiry retention fields without replacing existing enquiries or users.')
                ->schema([
                    Checkbox::make('backup_confirmed')
                        ->label('I have downloaded and checked a current MySQL database backup')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    if (!($data['backup_confirmed'] ?? false)) {
                        throw ValidationException::withMessages(['backup_confirmed'=>'A verified database backup is required.']);
                    }
                    if (Schema::hasTable('privacy_requests')) {
                        Notification::make()->title('Privacy tables are already installed')->success()->send();
                        return;
                    }
                    $exit = Artisan::call('migrate', [
                        '--path'=>'database/migrations/2026_10_12_000002_add_privacy_requests.php',
                        '--force'=>true,
                        '--no-interaction'=>true,
                    ]);
                    if ($exit !== 0 || !Schema::hasTable('privacy_requests') || !Schema::hasTable('privacy_request_notices')) {
                        throw new \\RuntimeException('Privacy database update incomplete. Review the secure application logs.');
                    }
                    Notification::make()->title('Privacy database update completed')->success()->send();
                }),
        ];
    }'''
readiness.write_text(text.replace(needle,needle + '\n' + action))

report = base / 'app/Services/ReadinessReport.php'
text = report.read_text()
old = 'use Illuminate\\Support\\Facades\\DB;'
if text.count(old) != 1:
    raise SystemExit('Unexpected readiness report imports')
text = text.replace(old,old + '\nuse Illuminate\\Support\\Facades\\Schema;')
needle = "            ['label'=>'Exactly one administrator','ok'=>$connection&&User::count()===1,'detail'=>'Single administrator only; registration is disabled.'],\n"
if text.count(needle) != 1:
    raise SystemExit('Readiness report insertion point missing')
text = text.replace(needle,needle + "            ['label'=>'Privacy-request schema','ok'=>$connection&&Schema::hasTable('privacy_requests')&&Schema::hasTable('privacy_request_notices')&&Schema::hasColumn('inquiries','retention_hold'),'detail'=>'Complete this additive update from the administrator Readiness page after backing up MySQL.'],\n")
report.write_text(text)

test = base / 'tests/Feature/PrivacyRightsTest.php'
text = test.read_text()
needle = '    public function test_privacy_request_form_is_available_and_has_no_indexing():void\n'
if text.count(needle) != 1:
    raise SystemExit('Unexpected schema test anchor')
test_case = '''    public function test_privacy_schema_exists_and_retains_original_enquiry_structure():void
    {
        $this->assertTrue(\\Illuminate\\Support\\Facades\\Schema::hasTable('inquiries'));
        $this->assertTrue(\\Illuminate\\Support\\Facades\\Schema::hasTable('privacy_requests'));
        $this->assertTrue(\\Illuminate\\Support\\Facades\\Schema::hasTable('privacy_request_notices'));
        $this->assertTrue(\\Illuminate\\Support\\Facades\\Schema::hasColumn('inquiries','retention_hold'));
    }
'''
test.write_text(text.replace(needle,test_case + needle))
print('Prepared protected browser-side privacy migration action and schema verification test')

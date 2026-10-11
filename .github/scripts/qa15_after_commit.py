from pathlib import Path

base = Path('londra')
paths = [
    'app/Http/Controllers/PrivacyRequestController.php',
    'app/Services/ErasureService.php',
    'app/Actions/SubmitInquiry.php',
    'app/Console/Commands/RetryPendingNotifications.php',
    'app/Filament/Resources/NotificationDeliveryResource.php',
    'app/Filament/Resources/PrivacyRequestResource.php',
]

for relative in paths:
    path = base / relative
    text = path.read_text(encoding='utf-8')
    if text.count('->beforeCommit()') != 1 or '->afterCommit()' in text:
        raise SystemExit('Unexpected queue dispatch baseline: ' + relative)
    path.write_text(text.replace('->beforeCommit()', '->afterCommit()'), encoding='utf-8')
    print('Applied after-commit dispatch:', relative)

path = base / 'tests/Feature/PrivacyRightsTest.php'
text = path.read_text(encoding='utf-8')
anchor = '    public function test_identity_verification_required_before_erasure_and_related_data_cascades():void\n'
if text.count(anchor) != 1 or 'test_notification_jobs_are_queued_after_privacy_request_commit' in text:
    raise SystemExit('Unexpected privacy feature-test baseline')
new_test = '''    public function test_notification_jobs_are_queued_after_privacy_request_commit():void
    {
        config(['londra.send_notifications'=>true]);
        $this->post('/en/privacy-request',[
            'name'=>'Synthetic Requester',
            'email'=>'privacy-queued@example.test',
            'website'=>'',
        ])->assertRedirect('/en/privacy-request');
        $this->assertDatabaseCount('privacy_requests',1);
        $this->assertDatabaseCount('privacy_request_notices',2);
        $this->assertDatabaseCount('jobs',2);
        $this->assertDatabaseHas('privacy_request_notices',['kind'=>'admin','status'=>'queued']);
        $this->assertDatabaseHas('privacy_request_notices',['kind'=>'received','status'=>'queued']);
    }
'''
path.write_text(text.replace(anchor,new_test+anchor),encoding='utf-8')
print('Added privacy notification commit test')

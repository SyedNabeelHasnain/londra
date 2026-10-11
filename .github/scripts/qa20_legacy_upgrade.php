<?php
declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../../londra/vendor/autoload.php';
$app = require __DIR__ . '/../../londra/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ((string)config('database.connections.mysql.database') !== 'londra_test' || app()->environment() !== 'testing') {
    throw new RuntimeException('Legacy upgrade test refuses to run outside the dedicated temporary CI database.');
}
$mode = $argv[1] ?? '';
if ($mode === 'prepare') {
    if (Schema::hasTable('privacy_requests') || Schema::hasColumn('inquiries','retention_hold')) {
        throw new RuntimeException('Legacy baseline unexpectedly contains new privacy schema.');
    }
    DB::table('users')->insert([
        'name'=>'Synthetic preserved administrator',
        'email'=>'legacy-preserved@example.test',
        'password'=>password_hash('non-production-synthetic-password', PASSWORD_BCRYPT),
        'created_at'=>now(),'updated_at'=>now(),
    ]);
    DB::table('inquiries')->insert([
        'reference'=>'LT-CI-SYNTHETIC-LEGACY','submission_key'=>str_repeat('a',64),
        'payload_fingerprint'=>str_repeat('b',64),'type'=>'booking','locale'=>'en',
        'status'=>'new','name'=>'SYNTHETIC_ENCRYPTED_NAME','email'=>'SYNTHETIC_ENCRYPTED_EMAIL',
        'phone'=>'SYNTHETIC_ENCRYPTED_PHONE','email_lookup'=>str_repeat('c',64),
        'privacy_notice_at'=>now(),'privacy_notice_version'=>'legacy-synthetic',
        'created_at'=>now(),'updated_at'=>now(),
    ]);
    if (DB::table('inquiries')->where('reference','LT-CI-SYNTHETIC-LEGACY')->count() !== 1) {
        throw new RuntimeException('Synthetic legacy inquiry seed failed.');
    }
    echo "Prepared isolated legacy schema with original administrator and inquiry\n";
} elseif ($mode === 'verify') {
    foreach (['privacy_requests','privacy_request_notices','inquiries','users'] as $table) {
        if (!Schema::hasTable($table)) {
            throw new RuntimeException('Migration missing table: '.$table);
        }
    }
    foreach (['last_contact_at','converted_offline','retention_hold','retention_hold_reason'] as $column) {
        if (!Schema::hasColumn('inquiries',$column)) {
            throw new RuntimeException('Missing additive inquiry field: '.$column);
        }
    }
    $inquiry = DB::table('inquiries')->where('reference','LT-CI-SYNTHETIC-LEGACY')->first();
    if (!$inquiry || $inquiry->name !== 'SYNTHETIC_ENCRYPTED_NAME'
        || $inquiry->email !== 'SYNTHETIC_ENCRYPTED_EMAIL'
        || $inquiry->phone !== 'SYNTHETIC_ENCRYPTED_PHONE'
        || (int)$inquiry->retention_hold !== 0 || (int)$inquiry->converted_offline !== 0) {
        throw new RuntimeException('Original enquiry data was modified or lost during migration.');
    }
    if (DB::table('users')->where('email','legacy-preserved@example.test')->count() !== 1) {
        throw new RuntimeException('Administrator was modified or lost during migration.');
    }
    echo "Verified additive legacy upgrade: original administrator and inquiry preserved, new privacy tables and fields present\n";
} else {
    throw new RuntimeException('Use prepare or verify mode.');
}

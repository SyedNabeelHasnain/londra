<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\{MediaAsset,Page};
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
final class ReleaseUpdater
{
    public const VERSION = '2026-11-05.2';
    public function run(): void
    {
        Cache::lock('londra-release-update', 300)->block(2, function (): void {
            if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) { throw new \RuntimeException('Database migrations did not complete.'); }
            if (Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]) !== 0) { throw new \RuntimeException('New content could not be imported.'); }
            if (!Setting::find('release-2026-10-12.1')) {
                $payload = json_decode((string) file_get_contents(database_path('content/policies.json')), true, 128, JSON_THROW_ON_ERROR);
                DB::transaction(function () use ($payload): void {
                    foreach ($payload['records'] as $record) {
                        $page = Page::where('key', $record['key'])->lockForUpdate()->firstOrFail();
                        $page->update(['published' => false, 'approved_at' => null]);
                        foreach ($record['translations'] as $locale => $translation) {
                            $current = $page->translations()->where('locale', $locale)->firstOrFail();
                            $current->update(array_intersect_key($translation, array_flip(['slug', 'title', 'intro', 'body', 'seo_title', 'meta_description'])) + ['ready' => false]);
                        }
                    }
                    Setting::create(['key' => 'release-2026-10-12.1', 'value' => ['applied_at' => now()->toIso8601String(), 'applied_by' => auth()->id()]]);
                }, 3);
            }
            if (!Setting::find('release-'.self::VERSION)) {
                DB::transaction(function (): void {
                    MediaAsset::query()->whereNotNull('upload_path')->each(function(MediaAsset $asset): void {
                        if(!$asset->variants){$asset->save();}
                    });
                    app(PublicContentUpdater::class)->run();
                    Setting::create(['key' => 'release-'.self::VERSION, 'value' => ['applied_at' => now()->toIso8601String(), 'applied_by' => auth()->id()]]);
                }, 3);
            }
            $operation = Setting::find('launch-operations')?->value ?? [];
            unset($operation['preview']);
            Setting::updateOrCreate(['key' => 'launch-operations'], ['value' => $operation]);
            $cleanup=app(ReleaseCleanup::class)->run();
            Setting::updateOrCreate(['key'=>'release-cleanup-'.self::VERSION],['value'=>$cleanup+['applied_at'=>now()->toIso8601String()]]);
            Artisan::call('view:clear'); Artisan::call('route:clear'); Artisan::call('config:clear');
        });
    }
}

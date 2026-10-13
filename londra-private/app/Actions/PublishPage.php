<?php
declare(strict_types=1);
namespace App\Actions;
use App\Models\Page;
use Illuminate\Support\Facades\DB;
final class PublishPage
{
    public function handle(Page $page): void
    {
        DB::transaction(function () use ($page): void {
            $locked = Page::query()->lockForUpdate()->findOrFail($page->id);
            $locked->published = true;
            $locked->save();
        }, 3);
        $page->refresh();
    }
}

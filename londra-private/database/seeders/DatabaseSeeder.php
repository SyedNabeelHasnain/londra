<?php
declare(strict_types=1);
namespace Database\Seeders;
use App\Models\{MediaAsset,Page,Vehicle};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function():void{
            $media=json_decode(file_get_contents(database_path('content/media.json')),true,512,JSON_THROW_ON_ERROR);
            $alts=json_decode(file_get_contents(database_path('content/media-alt.json')),true,512,JSON_THROW_ON_ERROR);
            foreach($media as $entry){MediaAsset::firstOrCreate(['key'=>$entry['key']],['public_path'=>$entry['original'],'width'=>$entry['width'],'height'=>$entry['height'],'variants'=>$entry['variants'],'alt_en'=>$alts[$entry['key']]['en'],'alt_it'=>$alts[$entry['key']]['it'],'alt_es'=>$alts[$entry['key']]['es']]);}
            $catalog=json_decode(file_get_contents(database_path('content/pages.json')),true,512,JSON_THROW_ON_ERROR);
            foreach($catalog as $entry){
                if(Page::where('key',$entry['key'])->exists()){continue;}
                $page=Page::create(['key'=>$entry['key'],'kind'=>$entry['kind'],'image_key'=>$entry['image_key'],'published'=>false,'approval_required'=>$entry['approval_required'],'sort_order'=>$entry['sort_order'],'source_reference'=>$entry['source_reference'] ?? null]);
                foreach($entry['translations'] as $translation){$page->translations()->create(array_intersect_key($translation, array_flip(['locale','slug','title','eyebrow','intro','body','detail_heading','highlights','faq_items','seo_title','meta_description','image_alt','ready'])));}
                if(!$entry['approval_required']){$page->update(['published'=>true]);}
            }
            foreach(json_decode(file_get_contents(database_path('content/vehicles.json')),true,512,JSON_THROW_ON_ERROR) as $entry){$page=Page::where('key',$entry['page_key'])->firstOrFail();unset($entry['page_key']);Vehicle::firstOrCreate(['code'=>$entry['code']],[...$entry,'page_id'=>$page->id]);}
        });
    }
}

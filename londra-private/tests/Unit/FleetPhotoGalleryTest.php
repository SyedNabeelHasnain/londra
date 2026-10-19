<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Services\FleetPhotoGallery;
use PHPUnit\Framework\TestCase;

final class FleetPhotoGalleryTest extends TestCase
{
    public function test_exact_user_approved_counts_and_exclusions(): void
    {
        $counts = ['e-class'=>5, 's-class'=>7, 'v-class'=>7, 'vito'=>5];
        foreach ($counts as $code => $number) {
            self::assertCount($number, FleetPhotoGallery::allKeys($code));
        }
        self::assertCount(24, array_merge(...array_map(
            static fn(string $key): array => FleetPhotoGallery::allKeys($key),
            array_keys($counts)
        )));
        self::assertNotContains('fleet-e-class-e06', FleetPhotoGallery::allKeys('e-class'));
        self::assertNotContains('fleet-e-class-e07', FleetPhotoGallery::allKeys('e-class'));
    }

    public function test_no_rights_or_missing_photo_blocks_whole_gallery(): void
    {
        foreach (FleetPhotoGallery::APPROVED_REFERENCES as $code => $ids) {
            $keys = FleetPhotoGallery::allKeys($code);
            $media = [];
            foreach ($keys as $key) {
                $media[$key] = ['url'=>'/media/example.webp', 'width'=>1600,'height'=>900,'alt'=>['en'=>'A photographed vehicle angle','it'=>'Dettaglio della vettura','es'=>'Detalle del vehículo']];
            }
            $record = [$code=>['photos'=>$keys,'rights_confirmed'=>false,'rights_note'=>'Documented copyright and model releases granted.']];
            self::assertSame([], FleetPhotoGallery::visible($code, $record, $media));
            $record[$code]['rights_confirmed'] = true;
            $record[$code]['rights_note'] = '';
            self::assertSame([], FleetPhotoGallery::visible($code, $record, $media));
            $record[$code]['rights_note'] = 'Detailed rights holder and commercial image permissions recorded.';
            self::assertCount(count($keys), FleetPhotoGallery::visible($code, $record, $media));
            unset($media[$keys[0]]);
            self::assertSame([], FleetPhotoGallery::visible($code, $record, $media));
            $record[$code]['photos']=array_slice($keys,1);
            self::assertSame([], FleetPhotoGallery::visible($code, $record, $media));
        }
    }

    public function test_gallery_order_and_language_alt_are_fixed_by_approved_references(): void
    {
        $keys=FleetPhotoGallery::allKeys('s-class');
        $media=[];
        foreach ($keys as $index=>$key) {
            $media[$key]=['url'=>'/media/'.($index+1).'.webp','width'=>1600,'height'=>900,'alt'=>['en'=>'EN '.$key,'it'=>'IT '.$key,'es'=>'ES '.$key]];
        }
        $setting=['s-class'=>['photos'=>array_reverse($keys),'rights_confirmed'=>true,'rights_note'=>'All individual license grants and permissions are recorded.']];
        $italian=FleetPhotoGallery::visible('s-class',$setting,$media,'it');
        self::assertSame(['S01','S02','S03','S04','S05','S06','S07'],array_column($italian,'reference'));
        self::assertSame('IT '.$keys[0],$italian[0]['alt']);
        self::assertSame(['Fleet photograph gallery: s-class'],FleetPhotoGallery::referencesFor($keys[0],$setting));
    }
}

<?php
declare(strict_types=1);
namespace App\Filament\AvatarProviders;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;
final class LocalAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $segments=preg_split('/\s+/u',trim((string)$record->getAttribute('name')))?:[];
        $letters=[];
        foreach($segments as $segment){$clean=preg_replace('/^[^\p{L}\p{N}]+/u','',$segment);if($clean!==null&&$clean!==''){$letters[]=mb_substr($clean,0,1);}}
        $initials=$letters===[]?'':mb_strtoupper($letters[0].(count($letters)>1?$letters[count($letters)-1]:''));
        $text=htmlspecialchars($initials,ENT_XML1|ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $svg='<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80"><circle cx="40" cy="40" r="40" fill="#0B0B0B"/><text x="40" y="40" dy=".35em" text-anchor="middle" fill="#BDA177" font-family="Arial, Helvetica, sans-serif" font-size="30" font-weight="600">'.$text.'</text></svg>';
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}

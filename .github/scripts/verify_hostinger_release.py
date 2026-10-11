from pathlib import Path
from zipfile import ZipFile
from pathlib import PurePosixPath
import stat

path=Path('output/Londra_Hostinger_Private_Public_Release.zip')
if not path.exists():
    raise SystemExit('Release archive missing')

with ZipFile(path) as archive:
    names=archive.namelist()
    if archive.testzip() is not None:
        raise SystemExit('ZIP member integrity failed')
    if len(names)!=len(set(names)):
        raise SystemExit('Duplicate ZIP path')
    for entry in archive.infolist():
        name=entry.filename
        pure=PurePosixPath(name)
        parts=pure.parts
        if pure.is_absolute() or '..' in parts or any('\\' in item for item in parts):
            raise SystemExit('Unsafe ZIP extraction path')
        if not parts or parts[0] not in ('public_html','londra-private'):
            raise SystemExit('Unexpected archive root: '+name)
        if stat.S_ISLNK(entry.external_attr >> 16):
            raise SystemExit('Symlinks cannot be safely extracted by hPanel: '+name)
        lowered=name.lower()
        if not entry.is_dir() and (lowered.endswith('/.env') or lowered.endswith('/.env.testing') or '/storage/logs/' in lowered or '/storage/framework/sessions/' in lowered):
            raise SystemExit('Runtime secret/session/log embedded: '+name)
        if lowered.startswith('public_html/') and any(token in lowered for token in ('/vendor/','/.git/','/storage/','/config/','/database/')):
            raise SystemExit('Private application directory exposed in webroot: '+name)
    required={
        'public_html/index.php',
        'public_html/.htaccess',
        'londra-private/vendor/autoload.php',
        'londra-private/artisan',
        'londra-private/app/Services/ErasureService.php',
        'londra-private/app/Http/Controllers/PrivacyRequestController.php',
        'londra-private/app/Filament/Pages/Readiness.php',
        'londra-private/database/migrations/2026_10_12_000002_add_privacy_requests.php',
    }
    absent=required-set(names)
    if absent:
        raise SystemExit('Release missing required files: '+', '.join(sorted(absent)))
    print(f'Hostinger ZIP integrity, two-folder isolation and required privacy components passed: {len(names)} entries')

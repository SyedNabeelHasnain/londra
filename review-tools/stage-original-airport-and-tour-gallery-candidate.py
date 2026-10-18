#!/usr/bin/env python3
"""Stage approved original WebP airport and destination images in an isolated, reproducible
source checkout. No production/database/network upload; ZIP created only by CI."""
import hashlib, io, json, os, requests, zipfile
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
PUB=ROOT/"public_html"
PRIV=ROOT/"londra-private"
REPO=os.environ["GITHUB_REPOSITORY"]
TOKEN=os.environ["GH_TOKEN"]
API=f"https://api.github.com/repos/{REPO}/actions/artifacts/12141788364/zip"
headers={"Authorization":f"Bearer {TOKEN}","Accept":"application/vnd.github+json","User-Agent":"LondraPrivateReviewBuild"}
res=requests.get(API,headers=headers,timeout=100)
res.raise_for_status()
with zipfile.ZipFile(io.BytesIO(res.content)) as outer:
    assert outer.testzip() is None
    matches=[s for s in outer.namelist() if s.endswith("Londra_Finalized_Images_WebP_and_Content.zip")]
    if len(matches)!=1:
        raise RuntimeError("Unexpected archive-in-artifact layout: "+str(outer.namelist()))
    innerBytes=outer.read(matches[0])
with zipfile.ZipFile(io.BytesIO(innerBytes)) as inner:
    assert inner.testzip() is None
    manifest=json.loads(inner.read("image-manifest.json"))
    images=manifest["images"]
    assert not manifest.get("failures")
    assert len([i for i in images if i["kind"]=="tour"])==20
    assert len([i for i in images if i["kind"]=="airport"])==12
    assert len(manifest["fleet_existing_art"])==4
    source_map={record["code"]:record for record in images}
    mapping={"heathrow":"LHR-03","gatwick":"LGW-01","stansted":"STN-03","luton":"LTN-03","london-city":"LCY-01","southend":"SEN-02"}
    for airport,code in mapping.items():
        assert source_map[code]["kind"]=="airport"
        src=source_map[code]["output"]["file"]
        data=inner.read(src)
        assert hashlib.sha256(data).hexdigest()==source_map[code]["output"]["sha256"]
        dst=PUB/"assets/approved/airports"/(code+".webp")
        dst.parent.mkdir(parents=True,exist_ok=True)
        dst.write_bytes(data)
    for r in images:
        if r["kind"]!="tour":continue
        data=inner.read(r["output"]["file"])
        assert hashlib.sha256(data).hexdigest()==r["output"]["sha256"]
        dst=PUB/"assets/approved/tours"/(r["code"]+".webp")
        dst.parent.mkdir(parents=True,exist_ok=True)
        dst.write_bytes(data)
    for r in manifest["fleet_existing_art"]:
        if not r.get("file"):raise RuntimeError("Missing existing fleet artwork: "+r["model"])
        assert hashlib.sha256(inner.read(r["file"])).hexdigest()==r["sha256"]
    rights_path=PUB/"assets/approved/IMAGE-CREDITS.txt"
    rights_path.write_bytes(inner.read("Londra_Finalized_Images_Content.txt"))
    gallery_data=[{**{k:r.get(k) for k in ["code","group","destination","title","credit","license_url","source_url","alt","caution","role"]}, "width":int(r["output"]["converted_dimensions"][0]),"height":int(r["output"]["converted_dimensions"][1])} for r in images if r["kind"]=="tour"]
    assert len(gallery_data)==20
    (PRIV/"database/content/finalized-photo-gallery.json").write_text(json.dumps(gallery_data,ensure_ascii=False,indent=2),encoding="utf-8")
    manifest_public={
        "images":{code:{"reference":code,"airport":airport,"source":source_map[code]["source_url"],"credit":source_map[code]["credit"],"license_url":source_map[code]["license_url"],"date":source_map[code]["date"],"dimensions":source_map[code]["output"]["converted_dimensions"]} for airport,code in mapping.items()},
        "fleet_artwork_note":"Four already-existing website artworks, not commercial rights approval for third-party photographs.",
        "no_vehicle_composites":True
    }
    (PRIV/"database/content/finalized-airport-gallery.json").write_text(json.dumps(manifest_public,ensure_ascii=False,indent=2),encoding="utf-8")

view=PRIV/"resources/views/atelier/page.php"
txt=view.read_text(encoding="utf-8")
old='</div></div><?php if($heroImage): ?><figure class="page-hero-photo media-frame"><?= $heroImage ?></figure><?php endif; ?></section>'
new='</div></div><?php if($kind === "airport"): ?><?php require __DIR__."/airport-hero.php"; ?><?php elseif($heroImage): ?><figure class="page-hero-photo media-frame"><?= $heroImage ?></figure><?php endif; ?></section>'
assert txt.count(old)==1,"Missing or ambiguous airport hero insertion point; refusing to guess"
txt=txt.replace(old,new)
old2='<?php elseif($kind === \'services\'): ?>'
new2='<?php elseif($kind === \'services\'): ?>'
assert txt.count(old2)==1
# Tour galleries: inject only for these two existing tour detail pages; not for service or generic tour overview.
place='<?php if($vehicle): ?>\n<section class="related-section section wrap">'
assert txt.count(place)==1
txt=txt.replace(place,'<?php if($kind === "tour" && in_array($p["key"], ["tours-london", "tours-uk"], true)): ?><?php require __DIR__."/approved-tour-gallery.php"; ?><?php endif; ?>\n'+place)
view.write_text(txt,encoding="utf-8")

airport_view=r'''<?php
$airportCodes = ['heathrow'=>'LHR-03','gatwick'=>'LGW-01','stansted'=>'STN-03','luton'=>'LTN-03','london-city'=>'LCY-01','southend'=>'SEN-02'];
$airportCode = $airportCodes[$p['key']] ?? null;
if ($airportCode === null) { throw new \LogicException('Unexpected airport photo mapping'); }
$photoPath = 'assets/approved/airports/'.$airportCode.'.webp';
$photoFull = public_path($photoPath);
if (!is_file($photoFull)) { throw new \RuntimeException('An approved airport photograph is missing'); }
$airportSizes=json_decode((string)file_get_contents(database_path('content/finalized-airport-gallery.json')),true,64,JSON_THROW_ON_ERROR)['images'][$airportCode]['dimensions'];
$airportDisplay = [
 'heathrow'=>'Heathrow Airport','gatwick'=>'Gatwick Airport','stansted'=>'Stansted Airport',
 'luton'=>'Luton Airport','london-city'=>'London City Airport','southend'=>'Southend Airport'
][$p['key']];
$airportAlt = [
 'en'=>$airportDisplay.' — selected original airport photograph, without added vehicles.',
 'it'=>$airportDisplay.' — fotografia originale selezionata, senza veicoli aggiunti.',
 'es'=>$airportDisplay.' — fotografía original seleccionada, sin vehículos añadidos.'
][$v['locale']];
$vehiclesFeatured = [
 ['key'=>'e-class','file'=>'mercedes-e-class-480.webp','label'=>'Mercedes-Benz E-Class'],
 ['key'=>'s-class','file'=>'mercedes-s-class-480.webp','label'=>'Mercedes-Benz S-Class'],
 ['key'=>'v-class','file'=>'mercedes-v-class-480.webp','label'=>'Mercedes-Benz V-Class'],
 ['key'=>'vito','file'=>'mercedes-vito-480.webp','label'=>'Mercedes-Benz Vito'],
];
$labelFleet = ['en'=>'Explore our fleet','it'=>'Scopri la nostra flotta','es'=>'Descubra nuestra flota'][$v['locale']];
?>
<figure class="page-hero-photo media-frame airport-original">
  <img class="airport-original-photo" src="<?= $e($base.$photoPath) ?>"
    alt="<?= $e($airportAlt) ?>" width="<?= (int)$airportSizes[0] ?>" height="<?= (int)$airportSizes[1] ?>" loading="eager" decoding="async" fetchpriority="high">
  <nav class="airport-fleet-thumbnails" aria-label="<?= $e($labelFleet) ?>">
    <?php foreach($vehiclesFeatured as $featured):
        if (!isset($cat[$featured['key']])) { continue; }
        $destination=$cat[$featured['key']]['url'];
    ?>
    <a class="airport-fleet-thumbnail" href="<?= $e($destination) ?>" aria-label="<?= $e($featured['label']) ?>">
      <img src="<?= $e($base.'assets/photos/'.$featured['file']) ?>"
        alt="" width="480" height="300" loading="lazy" decoding="async">
      <span aria-hidden="true"><?= $e(str_replace('Mercedes-Benz ', '', $featured['label'])) ?></span>
    </a>
    <?php endforeach ?>
  </nav>
</figure>
'''
(PRIV/"resources/views/atelier/airport-hero.php").write_text(airport_view,encoding="utf-8")

tour_view=r'''<?php
$photoData=json_decode((string)file_get_contents(database_path('content/finalized-photo-gallery.json')),true,512,JSON_THROW_ON_ERROR);
$isLondon=$p['key']==='tours-london';
$group=$isLondon?'London':'Beyond London';
$lead=$isLondon?'L01':'U04';
$selected=array_values(array_filter($photoData,static fn(array $r):bool => $r['group']===$group));
usort($selected,static fn(array $a,array $b):int => (($a['code']===$lead)?0:1)<=> (($b['code']===$lead)?0:1));
if (count($selected)!==($isLondon?11:9)) {throw new \RuntimeException('Approved tour gallery is incomplete');}
$title=['en'=>'Discover the destination','it'=>'Scopri la destinazione','es'=>'Descubra el destino'][$v['locale']];
$attribution=['en'=>'Photograph credit','it'=>'Crediti fotografici','es'=>'Créditos fotográficos'][$v['locale']];
?>
<section class="section wrap approved-tour-gallery" id="photographs" aria-labelledby="approved-tour-gallery-title">
  <div class="section-heading"><div><p class="eyebrow">Londra Transfers</p>
   <h2 id="approved-tour-gallery-title"><?= $e($title) ?></h2></div>
   <p class="section-intro"><?= $e($p['intro']) ?></p></div>
  <div class="approved-tour-grid">
    <?php foreach($selected as $r): $photoPath='assets/approved/tours/'.$r['code'].'.webp'; ?>
    <figure class="approved-tour-card">
       <img src="<?= $e($base.$photoPath) ?>"
         alt="<?= $e($r['alt'] ?: $r['destination'].' — '.$r['title']) ?>" width="<?= (int)$r['width'] ?>" height="<?= (int)$r['height'] ?>"
         loading="<?= $r['code']===$lead?'eager':'lazy' ?>" decoding="async">
       <figcaption>
         <strong><?= $e($r['destination']) ?></strong>
         <span><?= $e($r['title']) ?></span>
         <small><?= $e($attribution) ?>:
            <a href="<?= $e($r['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= $e($r['credit']) ?></a>
            · <a href="<?= $e($r['license_url']) ?>" target="_blank" rel="noopener noreferrer">License</a>
            · WebP conversion and resizing
         </small>
       </figcaption>
    </figure>
    <?php endforeach ?>
  </div>
</section>
'''
(PRIV/"resources/views/atelier/approved-tour-gallery.php").write_text(tour_view,encoding="utf-8")
css=PRIV/"resources/css/site.css"
existing=css.read_text(encoding="utf-8")
existing+="""

/* Approved airport original + editorially restrained fleet thumbnails.
   Vehicle imagery here uses existing site artwork, not third-party E01/T01 photography. */
.airport-original{position:relative;display:block;overflow:visible;min-width:0;align-self:start}
.airport-original-photo{display:block;width:100%;height:auto;max-height:620px;object-fit:contain;background:var(--surface)}
.airport-fleet-thumbnails{position:absolute;right:clamp(9px,1.5vw,18px);bottom:clamp(9px,1.5vw,18px);display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;width:min(94%,440px);padding:9px;border:1px solid rgba(189,161,119,.64);background:rgba(11,11,11,.91);backdrop-filter:blur(8px);z-index:1}
.airport-fleet-thumbnail{position:relative;min-width:0;display:flex;flex-direction:column;align-items:center;justify-content:space-between;gap:5px;border:1px solid rgba(241,236,217,.3);padding:5px;background:#0b0b0b;color:#f7f7f5;text-align:center;text-decoration:none}
.airport-fleet-thumbnail:hover,.airport-fleet-thumbnail:focus-visible{border-color:#bda177;background:#000}
.airport-fleet-thumbnail img{width:100%;height:65px;object-fit:contain;background:#000}
.airport-fleet-thumbnail span{font:500 10px/1.25 var(--sans);color:#f1ecd9;overflow-wrap:anywhere}
.approved-tour-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}
.approved-tour-card{min-width:0;background:var(--surface);border:1px solid var(--line)}
.approved-tour-card img{width:100%;aspect-ratio:3/2;object-fit:contain;background:var(--black)}
.approved-tour-card figcaption{padding:18px;display:grid;gap:3px}
.approved-tour-card strong{font:400 clamp(19px,2vw,25px)/1.3 var(--serif)}
.approved-tour-card span{font-size:13px;color:var(--muted)}
.approved-tour-card small{font-size:11px;color:var(--muted);line-height:1.6;overflow-wrap:anywhere}
.approved-tour-card small a{text-decoration:underline;text-underline-offset:3px}
@media(max-width:1020px){
  .airport-fleet-thumbnails{position:static;width:100%;grid-template-columns:repeat(4,minmax(0,1fr));padding:7px;backdrop-filter:none}
  .airport-original{overflow:hidden}
}
@media(max-width:570px){
  .airport-fleet-thumbnails{grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}
  .airport-fleet-thumbnail img{height:72px}
  .approved-tour-grid{grid-template-columns:1fr}
}
@media(prefers-reduced-motion:reduce){.airport-fleet-thumbnail{transition:none}}
"""
css.write_text(existing,encoding="utf-8")
# A new code audit must examine the source version vs uploaded 14.3 archive.
out={"status":"candidate-only","repo_baseline":"main; NOT YET RECONCILED WITH latest user 2026-10-14.3 ZIP",
     "files":{"airport_photo_count":6,"tour_photo_count":20,"existing_fleet_thumbnails":4},
     "user_photographs":{"newly_composited":False,"rejected":sorted(["LGW-03","STN-02","LTN-02","LCY-02","L02","E06","E07"])},
     "database_modified":False,"live_site_modified":False}
Path("review-candidate-status.json").write_text(json.dumps(out,indent=2),encoding="utf-8")
print("APPROVED_IMAGE_STAGE_SUCCEEDED",json.dumps(out),flush=True)

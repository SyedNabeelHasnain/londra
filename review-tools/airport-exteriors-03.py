from __future__ import annotations
import base64
import hashlib
import html
import io
import json
import re
import time
import urllib.parse
import urllib.request
from pathlib import Path
from PIL import Image, ImageOps

ROOT = Path('airport-review-output')
ROOT.mkdir(exist_ok=True)
QA = Path('airport-review-qa')
QA.mkdir(exist_ok=True)
SOURCES = Path('airport-review-sources')
SOURCES.mkdir(exist_ok=True)
ITEMS = [
 ('LHR-03','Heathrow','Heathrow Airport , Terminal 5 - Drop Off Point - geograph.org.uk - 4226587.jpg','Lewis Clarke','2014-10-22','CC BY-SA 2.0','https://creativecommons.org/licenses/by-sa/2.0/','https://www.geograph.org.uk/photo/4226587','New exterior candidate. The source identifies the Terminal 5 drop-off frontage. This is a historical photograph, not confirmation of current signs or pickup rules.','new'),
 ('LGW-03','Gatwick','Gatwick Airport North Terminal - geograph.org.uk - 3981333.jpg','Richard Cooke','2014-04-18','CC BY-SA 2.0','https://creativecommons.org/licenses/by-sa/2.0/','https://www.geograph.org.uk/photo/3981333','New road-level candidate. Evaluate vehicle scale, available road space and background clutter. The 2014 layout is not certified as current.','new'),
 ('STN-03','Stansted','A deserted Stansted Airport - geograph.org.uk - 6644866.jpg','Richard Humphrey','2020-10','CC BY-SA 2.0','https://creativecommons.org/licenses/by-sa/2.0/','https://www.geograph.org.uk/photo/6644866','New terminal-frontage candidate. Previously inspected as promising for a road-level composition; final perspective and vehicle placement still require review.','new'),
 ('LTN-03','Luton','Luton Airport - terminal building - geograph.org.uk - 2874551.jpg','Stacey Harris','2012-03-20','CC BY-SA 2.0','https://creativecommons.org/licenses/by-sa/2.0/','https://www.geograph.org.uk/photo/2874551','Historical exterior candidate, not a current-site representation. The age of this image is a material limitation for use on the live website.','new'),
 ('LCY-01','London City','London City Airport Terminal outside.jpg','Arne Müseler','2022-10-11','CC BY-SA 3.0 DE','https://creativecommons.org/licenses/by-sa/3.0/de/','https://www.arne-mueseler.com/','Your preferred exterior, retained unchanged. Any future vehicle placement must respect the visible road, pedestrian crossing, kerbs and bollards.','retained'),
 ('SEN-02','Southend','London Southend Airport - geograph.org.uk - 7266157.jpg','Trevor Harris','2022-07-22','CC BY-SA 2.0','https://creativecommons.org/licenses/by-sa/2.0/','https://www.geograph.org.uk/photo/7266157','Your preferred exterior, retained unchanged. The foreground includes accessible parking markings; a future composition must not imply inappropriate parking. Native width is 1600 pixels.','retained'),
 ('LHR-01','Heathrow','Heathrow Airport Terminal 5.jpg','CedarGlen86','2025-10-10','CC0 1.0','https://creativecommons.org/publicdomain/zero/1.0/','','Preferred airport photograph. Terminal interior: retain as airport photography, not a road-level vehicle background.','history'),
 ('LHR-02','Heathrow','Heathrow T5B.jpg','JCSigma2000','2026-08-13','CC BY-SA 4.0','https://creativecommons.org/licenses/by-sa/4.0/','','Preferred airport photograph. Aircraft and airfield view: not a landside chauffeur pickup background.','history'),
 ('LGW-01','Gatwick','Gatwick Airport North Terminal entrance.jpg','Sunil060902','2016-04-23','CC BY-SA 4.0','https://creativecommons.org/licenses/by-sa/4.0/','','Preferred airport photograph. Pedestrian entrance: do not place a vehicle inside this walkway.','history'),
 ('LGW-02','Gatwick','Gatwick Airport stn South Terminal entrance 2016.jpg','Sunil060902','2016-04-23','CC BY-SA 4.0','https://creativecommons.org/licenses/by-sa/4.0/','','Preferred airport photograph. Station and terminal interior: not a vehicle-compositing background.','history'),
 ('STN-01','Stansted','London Stansted Airport Terminal.jpg','Christopher Kern / (WT-shared) Albion','2009-04-01','CC BY-SA 3.0','https://creativecommons.org/licenses/by-sa/3.0/','','Preferred airport photograph. Terminal interior; native resolution is 1024 × 768, not full HD.','history'),
 ('LTN-01','Luton','London Luton Airport in 2025.03.jpg','CAPTAIN RAJU','2025-03-17','CC0 1.0','https://creativecommons.org/publicdomain/zero/1.0/','','Preferred airport photograph. Terminal entrance / interior: not a vehicle-compositing background.','history'),
 ('SEN-01','Southend','Southend Airport terminal building 01.jpg','Chris j wood','2012-07-15','CC BY-SA 3.0','https://creativecommons.org/licenses/by-sa/3.0/','','Preferred airport photograph. Terminal interior: keep as airport photography, without adding vehicles.','history'),
]
DECISIONS = {'LHR-01':'Prefer this photograph','LHR-02':'Prefer this photograph','LGW-01':'Prefer this photograph','LGW-02':'Prefer this photograph','STN-01':'Prefer this photograph','STN-02':'Reject','LTN-01':'Prefer this photograph','LTN-02':'Reject','LCY-01':'Prefer this photograph','LCY-02':'Reject','SEN-01':'Prefer this photograph','SEN-02':'Prefer this photograph'}
FLEET = {'E-Class':['E01','E02','E03','E04','E05'],'S-Class':['S01','S02','S03','S04','S05','S06','S07'],'V-Class':['V01','V02','V03','V04','V05','V06','V07'],'Vito':['T01','T02','T03','T04','T05']}

def fetch(url: str) -> bytes:
    error = None
    for delay in (0, 2, 6):
        time.sleep(delay)
        try:
            req = urllib.request.Request(url, headers={'User-Agent':'LondraPhotoReview/3.0 (private image source review; https://londratransfers.co.uk)'})
            with urllib.request.urlopen(req, timeout=45) as response:
                return response.read()
        except Exception as exc:
            error = exc
    raise RuntimeError(f'Could not retrieve {url}: {error}')

def plain(value: str) -> str:
    return html.unescape(re.sub(r'<[^>]+>', '', value)).strip()

def e(value) -> str:
    return html.escape(str(value), quote=True)

records = []
for code, airport, title, author, date, licence, licence_url, author_url, note, group in ITEMS:
    canonical = title.replace(' ', '_')
    digest = hashlib.md5(canonical.encode()).hexdigest()
    raw_url = 'https://upload.wikimedia.org/wikipedia/commons/'+digest[0]+'/'+digest[:2]+'/'+urllib.parse.quote(canonical)
    page_url = 'https://commons.wikimedia.org/wiki/File:'+urllib.parse.quote(canonical)
    api_url = 'https://commons.wikimedia.org/w/api.php?' + urllib.parse.urlencode({'action':'query','format':'json','prop':'imageinfo','titles':'File:'+title,'iiprop':'url|size|extmetadata|sha1','redirects':'1'})
    metadata_ok = False
    metadata_error = ''
    try:
        metadata = json.loads(fetch(api_url))
        (SOURCES/(code+'-metadata.json')).write_text(json.dumps(metadata,indent=2),encoding='utf-8')
        pages = metadata.get('query',{}).get('pages',{})
        info = next((p['imageinfo'][0] for p in pages.values() if p.get('imageinfo')),None)
        if info:
            raw_url = info['url']
            meta = info.get('extmetadata',{})
            actual = plain(meta.get('LicenseShortName',{}).get('value',''))
            if actual:
                licence = actual
                licence_url = meta.get('LicenseUrl',{}).get('value',licence_url)
                if licence_url.startswith('//'):
                    licence_url = 'https:'+licence_url
            metadata_ok = bool(actual)
    except Exception as exc:
        metadata_error = str(exc)
    data = fetch(raw_url)
    with Image.open(io.BytesIO(data)) as verify:
        verify.verify()
    image = ImageOps.exif_transpose(Image.open(io.BytesIO(data))).convert('RGB')
    original_size = list(image.size)
    (SOURCES/(code+'-original.jpg')).write_bytes(data)
    image.thumbnail((1600,1600),Image.Resampling.LANCZOS)
    buffer = io.BytesIO()
    image.save(buffer,format='WEBP',quality=91,method=6)
    preview = buffer.getvalue()
    uri = 'data:image/webp;base64,'+base64.b64encode(preview).decode()
    record = {'code':code,'airport':airport,'title':title,'author':author,'date':date,'license':licence,'license_url':licence_url,'author_url':author_url,'source_url':page_url,'original_url':raw_url,'original_dimensions':original_size,'preview_dimensions':list(image.size),'original_sha256':hashlib.sha256(data).hexdigest(),'preview_sha256':hashlib.sha256(preview).hexdigest(),'group':group,'note':note,'license_metadata_retrieved':metadata_ok,'metadata_error':metadata_error,'data_uri':uri}
    records.append(record)
    print('IMAGE_OK',code,original_size,licence,'METADATA',metadata_ok,flush=True)

css = '''
:root{--black:#000000;--onyx:#0b0b0b;--white:#ffffff;--porcelain:#f7f7f5;--gold:#bda177;--champagne:#f1ecd9;--line:rgba(189,161,119,.28)}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--onyx);color:var(--porcelain);font:15px/1.65 Arial,sans-serif}a{color:var(--champagne)}main{max-width:1360px;margin:auto;padding:0 36px 100px}header{padding:32px 0 45px;border-bottom:1px solid var(--line)}.mast{display:flex;justify-content:space-between;gap:20px;border-bottom:1px solid var(--line);padding-bottom:25px;margin-bottom:45px;letter-spacing:.2em;text-transform:uppercase;font-size:11px;color:var(--gold)}.eyebrow{letter-spacing:.2em;text-transform:uppercase;font-size:11px;color:var(--gold)}h1,h2,h3{font-family:Georgia,serif;font-weight:400;line-height:1.14}h1{font-size:clamp(36px,5.5vw,70px);max-width:920px;margin:18px 0 25px}h2{font-size:38px;margin:15px 0 20px}h3{font-size:27px;margin:5px 0 12px}p{max-width:95ch}.lead{font-size:18px;max-width:820px;color:var(--champagne)}.summary{display:flex;gap:34px;flex-wrap:wrap;margin-top:30px}.summary b{display:block;color:var(--gold);font:32px Georgia,serif}.summary span{font-size:12px}.notice{padding:20px 24px;border:1px solid var(--line);margin:25px 0;background:var(--black)}.notice p{margin:6px 0}.toolbar{display:flex;gap:10px;flex-wrap:wrap;margin:26px 0}button,.button,select{min-height:48px;border:1px solid var(--gold);background:transparent;color:var(--porcelain);font:14px Arial,sans-serif;padding:10px 16px;border-radius:0}button,.button{width:190px;max-width:100%;display:inline-flex;justify-content:center;align-items:center;gap:8px;text-decoration:none;cursor:pointer}button:hover,.button:hover,button.primary{background:var(--gold);color:var(--black)}a:focus-visible,button:focus-visible,select:focus-visible,textarea:focus-visible,summary:focus-visible{outline:2px solid var(--champagne);outline-offset:4px}select{width:100%;background:var(--black)}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:28px}.card{border:1px solid var(--line);background:var(--black);min-width:0;scroll-margin-top:20px}.image-button{width:100%;height:auto;max-width:none;padding:0;border:0;display:block;background:var(--black)}.image-button:hover{background:var(--black)}.card img{display:block;width:100%;aspect-ratio:4/3;object-fit:contain;background:var(--black)}.copy{padding:24px}.tag{display:inline-block;color:var(--gold);font-size:11px;letter-spacing:.14em;margin:0 0 14px}.flag{border-left:2px solid var(--gold);padding-left:12px;font-size:13px;color:var(--champagne)}dl{display:grid;grid-template-columns:110px minmax(0,1fr);gap:8px 14px;font-size:12px}dt{color:var(--gold)}dd{margin:0;overflow-wrap:anywhere}.links{display:flex;gap:10px;flex-wrap:wrap;margin:20px 0}.decision{border-top:1px solid var(--line);padding-top:20px;display:grid;gap:8px}.decision label{font-size:12px;color:var(--champagne)}.section{margin-top:55px;padding-top:20px;border-top:1px solid var(--line)}.section>p{color:var(--champagne)}.history summary{cursor:pointer;font:27px Georgia,serif;margin-bottom:25px}.record-table{width:100%;border-collapse:collapse;font-size:13px}td,th{padding:12px 10px;text-align:left;border-bottom:1px solid var(--line)}th{color:var(--gold)}.tablewrap{overflow:auto}textarea{width:100%;min-height:380px;background:var(--black);color:var(--porcelain);font:13px/1.6 Consolas,monospace;border:1px solid var(--line);padding:18px;resize:vertical}.status{font-size:12px;color:var(--champagne)}dialog{background:var(--black);color:var(--porcelain);border:1px solid var(--gold);width:min(1450px,96vw);max-height:96vh;padding:20px}dialog::backdrop{background:rgba(0,0,0,.92)}dialog img{display:block;width:100%;max-height:72vh;object-fit:contain;margin-top:14px}dialog p{font-size:12px}.dialog-bar{display:flex;justify-content:space-between;gap:15px;align-items:center}footer{margin-top:50px;border-top:1px solid var(--line);padding-top:20px;font-size:12px;color:var(--champagne)}.matrix th:not(:first-child),.matrix td:not(:first-child){text-align:center}.pending{color:var(--champagne)}.small{font-size:12px}.skip{position:absolute;left:10px;top:-100px;background:var(--gold);color:var(--black);padding:10px;z-index:100}.skip:focus{top:10px}@media(max-width:720px){main{padding:0 16px 60px}.grid{grid-template-columns:1fr}.mast{flex-direction:column;margin-bottom:30px;gap:8px}h2{font-size:30px}.copy{padding:18px}dl{grid-template-columns:86px minmax(0,1fr)}.links{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.links .button{width:100%;font-size:12px;padding:8px}.toolbar button,.toolbar .button{flex:1 1 145px}.notice{padding:16px}.summary{gap:20px}.dialog-bar button{width:100px}.tablewrap{margin-inline:-5px}}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}@media print{body{background:white;color:black}.toolbar,.image-button+.copy .links,.decision,dialog,.skip{display:none}.grid{grid-template-columns:1fr}.card{break-inside:avoid}.card,.notice{background:white}.card img{max-height:360px}h1{font-size:36px}a,.tag,dt,.flag,.lead,.section>p{color:black}}
'''

def card(r):
    code=r['code']
    dims=' × '.join(map(str,r['original_dimensions']))
    preview_dims=' × '.join(map(str,r['preview_dimensions']))
    tag='NEW EXTERIOR CANDIDATE' if r['group']=='new' else 'YOUR PREFERENCE RETAINED'
    licence_note='Commons licence metadata retrieved in this build.' if r['license_metadata_retrieved'] else 'Licence recorded from the cited source page; automated metadata recheck unavailable.'
    creator=e(r['author'])
    if r['author_url']:
        creator='<a href="'+e(r['author_url'])+'" target="_blank" rel="noopener noreferrer">'+creator+'</a>'
    choice=''
    if r['group']=='new':
        choice=f'<div class="decision"><label for="choice-{code}">{code}: your exterior-background decision</label><select id="choice-{code}" data-code="{code}"><option value="Unreviewed">Unreviewed</option><option>Prefer for a composite trial</option><option>Keep as airport photo only</option><option>Reject</option></select><label for="note-{code}">Optional notes</label><input id="note-{code}" data-note="{code}" maxlength="600" style="width:100%;padding:12px;background:var(--onyx);color:var(--porcelain);border:1px solid var(--line);font:14px Arial,sans-serif" autocomplete="off"></div>'
    else:
        choice='<p class="flag">Your recorded decision: <strong>Prefer this photograph</strong>. No new composite approval is inferred.</p>'
    return f'''<article class="card" id="{code}" data-ref="{code}"><button class="image-button" type="button" data-enlarge="{code}" aria-label="Enlarge {code}: {e(r['airport'])}"><img src="{r['data_uri']}" alt="{e(r['title'])}" width="{r['preview_dimensions'][0]}" height="{r['preview_dimensions'][1]}" loading="eager"></button><div class="copy"><p class="tag">{code} · {tag}</p><h3>{e(r['airport'])}</h3><p class="flag">{e(r['note'])}</p><dl><dt>Photographed</dt><dd>{e(r['date'])}</dd><dt>Original size</dt><dd>{dims} pixels</dd><dt>Review copy</dt><dd>{preview_dims} pixels; resized, not upscaled</dd><dt>Photographer</dt><dd>{creator}</dd><dt>Licence</dt><dd><a href="{e(r['license_url'])}" target="_blank" rel="noopener noreferrer">{e(r['license'])}</a></dd></dl><p class="small">{licence_note}</p><div class="links"><a class="button" href="{e(r['source_url'])}" target="_blank" rel="noopener noreferrer">Source and rights</a><a class="button" href="{e(r['original_url'])}" target="_blank" rel="noopener noreferrer">Full-resolution source</a></div>{choice}</div></article>'''

parts=['<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'; script-src \'unsafe-inline\'; connect-src \'none\'; base-uri \'none\'; form-action \'none\'"><title>Londra Transfers | Airport Exterior Review 03</title><style>'+css+'</style></head><body><a class="skip" href="#exteriors">Skip to exterior photographs</a><main><header><div class="mast"><span>Londra Transfers</span><span>Private visual review · Edition 03</span></div><p class="eyebrow">Airport photography / next source review</p><h1>The setting for<br>an authentic arrival.</h1><p class="lead">Four additional road-level candidates, alongside the two exterior photographs you already preferred. Your original decisions remain intact.</p><div class="summary"><span><b>4</b>New exterior candidates</span><span><b>9</b>Earlier preferences retained</span><span><b>3</b>Earlier rejections retained</span><span><b>24</b>Planned airport × vehicle combinations</span></div><div class="notice"><strong>Original photographs, not completed composites.</strong><p>This edition contains the actual exterior source images for inspection. No cars, buildings, signs, roads or skies have been generated or replaced. No vehicle-composite preview has been completed in this package, and no website files have been modified.</p><p>The source dates are shown on every card. These images do not certify current airport layouts, stopping permissions or access arrangements.</p></div><nav class="toolbar" aria-label="Review navigation"><a class="button" href="#exteriors">Exterior candidates</a><a class="button" href="#history">Earlier selections</a><a class="button" href="#review">Export review</a></nav><p class="status" id="load-status" role="status">Checking embedded images…</p></header><section id="exteriors" class="section"><p class="eyebrow">Source photographs / no scene alterations</p><h2>Exterior backgrounds</h2><p>Click any image to enlarge it without leaving this file. New candidates are labelled with new codes; LCY-01 and SEN-02 keep their existing identities and preferences.</p><div class="grid">']
parts.extend(card(r) for r in records if r['group']!='history')
parts.append('</div></section><section class="section" id="history"><p class="eyebrow">Preserved exactly</p><h2>Your original selection record</h2><div class="tablewrap"><table class="record-table"><thead><tr><th>Airport</th><th>Preferred</th><th>Rejected</th></tr></thead><tbody>')
for airport,pref,reject in [('Heathrow','LHR-01, LHR-02','None'),('Gatwick','LGW-01, LGW-02','None'),('Stansted','STN-01','STN-02'),('Luton','LTN-01','LTN-02'),('London City','LCY-01','LCY-02'),('Southend','SEN-01, SEN-02','None')]:
    parts.append(f'<tr><td>{airport}</td><td>{pref}</td><td>{reject}</td></tr>')
parts.append('</tbody></table></div><p>The three rejected images are not downloaded, embedded or used. The two preferred exteriors appear above; the other seven preferred airport photographs remain available below.</p><details class="history" id="history-images"><summary>Show the other seven preferred photographs</summary><div class="grid">')
parts.extend(card(r) for r in records if r['group']=='history')
parts.append('</div></details></section><section class="section"><p class="eyebrow">Production scope / not a completion claim</p><h2>The 24-image register</h2><p>One E-Class, S-Class, V-Class and Vito composition per airport. Every combination is still pending; this table prevents missing combinations being presented as a completed set.</p><div class="tablewrap"><table class="record-table matrix"><thead><tr><th>Airport</th><th>E-Class</th><th>S-Class</th><th>V-Class</th><th>Vito</th></tr></thead><tbody>')
for airport in ['Heathrow','Gatwick','Stansted','Luton','London City','Southend']:
    parts.append('<tr><td>'+airport+'</td>'+''.join('<td class="pending">Not produced</td>' for _ in range(4))+'</tr>')
parts.append('</tbody></table></div><p class="small">Fleet appearance approvals retained: E01–E05; S01–S07; V01–V07; T01–T05. E06–E07 remain excluded. The source review did not establish commercial reuse or editing permission for those fleet photographs. No permission or proof of ownership is inferred from appearance approval.</p></section><section class="section" id="review"><p class="eyebrow">Your decisions</p><h2>Review and export</h2><p>Existing preferences and rejections are locked in the export. Only the four new exterior decisions and their notes are editable. Selection changes are saved in this browser when local storage is available.</p><div class="toolbar"><button class="primary" id="export" type="button">Export review as text</button><button id="copy" type="button">Copy review text</button></div><p class="status" id="save-status" role="status"></p><label for="review-text">Review text, also available to select and copy manually</label><textarea id="review-text" readonly spellcheck="false"></textarea></section><footer><p><strong>Source and rights notes.</strong> Wikimedia Commons source pages and the stated image licences are linked on each card. CC BY-SA conditions include credit, a licence link, identification of changes and the applicable ShareAlike terms. The resized review copies retain their source licences. Vehicle photograph rights remain a separate question.</p><p>Photo credit for LCY-01: Arne Müseler / <a href="https://www.arne-mueseler.com/" target="_blank" rel="noopener noreferrer">arne-mueseler.com</a> / <a href="https://creativecommons.org/licenses/by-sa/3.0/de/" target="_blank" rel="noopener noreferrer">CC BY-SA 3.0 DE</a>. Review-copy change: reduced dimensions and WebP encoding only.</p><p>All displayed images are embedded in this HTML. No external image requests, fonts, analytics, libraries or website/database connections are used. Source and licence links require internet access.</p></footer></main><dialog id="lightbox" aria-labelledby="modal-title"><div class="dialog-bar"><strong id="modal-title"></strong><button type="button" id="close">Close</button></div><img id="modal-image" alt=""><p id="modal-credit"></p></dialog>')
public_records=[{k:v for k,v in r.items() if k not in ('data_uri','metadata_error')} for r in records]
parts.append('<script id="source-data" type="application/json">'+json.dumps(public_records,ensure_ascii=False).replace('<','\\u003c')+'</script>')
parts.append('<script>const oldDecisions='+json.dumps(DECISIONS)+';const fleet='+json.dumps(FLEET)+';'+'''
const records=JSON.parse(document.getElementById('source-data').textContent);const key='londra-exteriors-03-20261014';const newCodes=['LHR-03','LGW-03','STN-03','LTN-03'];const status=document.getElementById('save-status');const output=document.getElementById('review-text');let canStore=true;
function refresh(){let lines=['Londra Transfers — Airport exterior review','Supplementary source photographs — offline edition 03','','ORIGINAL DECISIONS — UNCHANGED'];Object.entries(oldDecisions).forEach(([k,v])=>lines.push(k+' | '+v));lines.push('','NEW EXTERIOR CANDIDATES');newCodes.forEach(code=>{lines.push(code+' | '+document.querySelector('[data-code="'+code+'"]').value);let note=document.querySelector('[data-note="'+code+'"]').value.trim();if(note)lines.push(code+' note | '+note.replace(/[\\r\\n]+/g,' '));});lines.push('','COMPOSITE APPROVAL','No completed vehicle composites are included in this edition.','No composite or publication approval is inferred.','','FLEET APPEARANCE RECORD');Object.entries(fleet).forEach(([model,codes])=>lines.push(model+' | '+codes.join(', ')));lines.push('Excluded | E06, E07');output.value=lines.join('\\n');}
function save(){const data={};newCodes.forEach(code=>data[code]={choice:document.querySelector('[data-code="'+code+'"]').value,note:document.querySelector('[data-note="'+code+'"]').value});try{localStorage.setItem(key,JSON.stringify(data));status.textContent='Selections saved in this browser.';}catch(e){canStore=false;status.textContent='Browser storage is unavailable. Export or copy the review before closing.';}refresh();}
try{const data=JSON.parse(localStorage.getItem(key)||'{}');newCodes.forEach(code=>{const row=data[code];if(row){const select=document.querySelector('[data-code="'+code+'"]');if(Array.from(select.options).some(o=>o.value===row.choice))select.value=row.choice;if(typeof row.note==='string')document.querySelector('[data-note="'+code+'"]').value=row.note.slice(0,600);}});}catch(e){canStore=false;}
document.querySelectorAll('select').forEach(el=>el.addEventListener('change',save));document.querySelectorAll('[data-note]').forEach(el=>el.addEventListener('input',save));refresh();status.textContent=canStore?'Your previous decisions are preserved. New candidates remain unreviewed until you choose.':'Local storage is unavailable; use the text export.';
document.getElementById('export').addEventListener('click',()=>{refresh();const blob=new Blob([output.value],{type:'text/plain;charset=utf-8'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download='Londra_Airport_Exterior_Review_03_Decisions.txt';document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),30000);});
document.getElementById('copy').addEventListener('click',async()=>{refresh();try{if(!navigator.clipboard)throw new Error('No clipboard');await navigator.clipboard.writeText(output.value);status.textContent='Review text copied.';}catch(e){output.focus();output.select();status.textContent='Review text selected. Press Ctrl+C or use Copy on your device.';}});
const dialog=document.getElementById('lightbox');const modalImage=document.getElementById('modal-image');let opener=null;document.querySelectorAll('[data-enlarge]').forEach(button=>button.addEventListener('click',()=>{opener=button;const code=button.dataset.enlarge;const record=records.find(r=>r.code===code);modalImage.src=button.querySelector('img').src;modalImage.alt=record.title;document.getElementById('modal-title').textContent=code+' · '+record.airport;document.getElementById('modal-credit').textContent=record.author+' · '+record.license+' · '+record.date+' · Resized review copy; no composite.';dialog.showModal();}));document.getElementById('close').addEventListener('click',()=>dialog.close());dialog.addEventListener('close',()=>{modalImage.removeAttribute('src');if(opener)opener.focus();});
Promise.all(Array.from(document.querySelectorAll('.card img')).map(img=>img.decode().then(()=>true,()=>false))).then(results=>{document.getElementById('load-status').textContent=results.filter(Boolean).length+' / '+results.length+' embedded source photographs loaded. No network needed for images.';});
</script></body></html>''')
text=''.join(parts)
output=ROOT/'Londra_Airport_Exterior_Review_03.html'
output.write_text(text,encoding='utf-8')
manifest={'edition':'03','kind':'original-source-photo-review-not-composites','html_file':output.name,'html_bytes':output.stat().st_size,'html_sha256':hashlib.sha256(output.read_bytes()).hexdigest(),'original_decisions':DECISIONS,'fleet_appearance_approvals':FLEET,'excluded_fleet':['E06','E07'],'source_records':public_records,'production_application_accessed':False,'production_database_accessed':False,'composites_produced':0}
(ROOT/'SOURCE-MANIFEST.json').write_text(json.dumps(manifest,indent=2,ensure_ascii=False),encoding='utf-8')
(ROOT/'READ-ME.txt').write_text('Londra Transfers — Airport Exterior Review 03\n\nOpen Londra_Airport_Exterior_Review_03.html in your browser. Images are embedded and work offline.\n\nThis is a private source-photo review, not a website release and not completed vehicle composites. Four new exterior candidates are presented; all earlier selections are preserved. No deployment files or credentials are included.\n\nUse the decision controls and Export review as text. Source links need internet.\n',encoding='utf-8')
print('HTML_CREATED',output.stat().st_size,manifest['html_sha256'],flush=True)

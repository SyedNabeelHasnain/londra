from __future__ import annotations
import base64
import hashlib
import html
import io
import json
import os
import shutil
import urllib.request
import zipfile
from pathlib import Path
import cv2
import numpy as np
import torch
from PIL import Image, ImageDraw, ImageFilter, ImageOps
from rembg import new_session, remove
from transformers import AutoImageProcessor, AutoModelForSemanticSegmentation

ROOT=Path('heathrow-composite-trial-04')
ROOT.mkdir(exist_ok=True)
SOURCES=ROOT/'sources'
SOURCES.mkdir(exist_ok=True)
QA=Path('heathrow-trial-04-qa')
QA.mkdir(exist_ok=True)

def sha(data:bytes)->str:
    return hashlib.sha256(data).hexdigest()

def gh_get(url:str)->bytes:
    req=urllib.request.Request(url,headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json','User-Agent':'LondraPrivateTrial'})
    with urllib.request.urlopen(req,timeout=90) as response:
        return response.read()

source_archive=gh_get('https://api.github.com/repos/'+os.environ['REPOSITORY']+'/actions/artifacts/11918644263/zip')
assert sha(source_archive)=='f12be7e3b71f51d5cb145b2388a0621abb8a65b7c6ae6d505f3a3de45c29ca4d','Unexpected source archive'
with zipfile.ZipFile(io.BytesIO(source_archive)) as archive:
    assert archive.testzip() is None
    recovery=json.loads(archive.read('SOURCE-RECOVERY.json'))
    for record in recovery['sources']:
        code=record['code']
        data=archive.read(code+'-original.jpg')
        assert sha(data)==record['original_sha256'],code+' source mismatch'
        (SOURCES/(code+'-original.jpg')).write_bytes(data)
    (SOURCES/'LHR-03-source-metadata.json').write_bytes(archive.read('LHR-03-source-metadata.json'))
(SOURCES/'SOURCE-RECOVERY.json').write_text(json.dumps(recovery,indent=2),encoding='utf-8')
print('EXACT_ORIGINALS_VERIFIED',[(r['code'],r['original_dimensions']) for r in recovery['sources']],flush=True)

source_car=ImageOps.exif_transpose(Image.open(SOURCES/'E02-original.jpg')).convert('RGB')
source_airport=ImageOps.exif_transpose(Image.open(SOURCES/'LHR-03-original.jpg')).convert('RGB')
base=source_airport.copy()
base.thumbnail((2560,2560),Image.Resampling.LANCZOS)
W,H=base.size
base.save(ROOT/'LHR-03-working-background.png')

session=new_session('u2net',providers=['CPUExecutionProvider'])
raw_mask=remove(source_car,session=session,only_mask=True,post_process_mask=False).convert('L')
alpha=np.asarray(raw_mask).copy()
count,labels,stats,centroids=cv2.connectedComponentsWithStats((alpha>40).astype(np.uint8),8)
assert count>1,'No foreground isolated'
ranked=[]
for index in range(1,count):
    x,y,w,h,area=stats[index]
    cx,cy=centroids[index]
    central=1-0.4*abs(cx/source_car.width-0.5)
    ranked.append((float(area)*central,index))
chosen=max(ranked)[1]
component=(labels==chosen).astype(np.uint8)
component=cv2.dilate(component,np.ones((7,7),np.uint8),iterations=1)
alpha[component==0]=0
alpha[alpha<5]=0
alpha[alpha>250]=255
ys,xs=np.where(alpha>10)
assert len(xs)>1000,'Insufficient vehicle foreground'
x0,y0,x1,y1=max(0,int(xs.min())-3),max(0,int(ys.min())-3),min(source_car.width,int(xs.max())+4),min(source_car.height,int(ys.max())+4)
cutout=source_car.convert('RGBA')
cutout.putalpha(Image.fromarray(alpha))
cutout=cutout.crop((x0,y0,x1,y1))
assert 0.18 < cutout.height/cutout.width < 0.85,'Foreground aspect ratio does not resemble a saloon'
assert 0.04<float((alpha>128).mean())<0.65,'Unreliable foreground mask'
cutout.save(ROOT/'E02-vehicle-cutout.png')
Image.fromarray(alpha).save(QA/'E02-alpha-mask.png')
source_crop=np.asarray(source_car)[y0:y1,x0:x1,:]
assert np.array_equal(np.asarray(cutout)[:,:,:3],source_crop),'Vehicle RGB pixels changed during extraction'
print('VEHICLE_MASK_READY',cutout.size,'SOURCE_BOX',[x0,y0,x1,y1],flush=True)

torch.set_num_threads(2)
model_name='nvidia/segformer-b0-finetuned-cityscapes-1024-1024'
processor=AutoImageProcessor.from_pretrained(model_name,use_fast=False)
model=AutoModelForSemanticSegmentation.from_pretrained(model_name)
model.eval()
with torch.inference_mode():
    inputs=processor(images=base,return_tensors='pt')
    outputs=model(**inputs)
    segmented=processor.post_process_semantic_segmentation(outputs,target_sizes=[(H,W)])[0].cpu().numpy().astype(np.uint8)
road_id=next(int(k) for k,v in model.config.id2label.items() if v.lower()=='road')
person_ids=[int(k) for k,v in model.config.id2label.items() if v.lower() in ('person','rider')]
vehicle_ids=[int(k) for k,v in model.config.id2label.items() if v.lower() in ('car','truck','bus','motorcycle','bicycle')]
road=segmented==road_id
people=np.isin(segmented,person_ids)
vehicles=np.isin(segmented,vehicle_ids)
Image.fromarray((road*255).astype(np.uint8)).save(QA/'LHR-03-road-estimate.png')
assert float(road[int(.45*H):].mean())>.025,'Road estimate insufficient for placement trial'

trials=[]
for number,fraction in enumerate((.35,.43),start=1):
    width=min(int(W*fraction),cutout.width)
    scale=width/cutout.width
    height=round(cutout.height*scale)
    vehicle=cutout.resize((width,height),Image.Resampling.LANCZOS)
    opaque=np.asarray(vehicle.getchannel('A'))>40
    candidates=[]
    for bottom in range(int(H*.70),int(H*.96),max(10,int(H*.012))):
        top=bottom-height
        if top<0: continue
        for left in range(int(W*.025),W-width-int(W*.025),max(12,int(W*.012))):
            ground=road[max(0,bottom-4):min(H,bottom+8),left+int(width*.12):left+int(width*.89)]
            onroad=float(ground.mean()) if ground.size else 0
            p=float(people[top:bottom,left:left+width][opaque].mean())
            v=float(vehicles[top:bottom,left:left+width][opaque].mean())
            if onroad<.84 or p>.008:
                continue
            centre=(left+width/2)/W
            score=onroad*7-p*90-v*8-abs(centre-.54)*1.2-abs(bottom/H-.88)*2
            candidates.append((score,left,top,bottom,onroad,p,v))
    if not candidates:
        raise RuntimeError('No acceptable road-grounded placement found for trial '+str(number))
    score,left,top,bottom,onroad,p,v=max(candidates)
    shadow=Image.new('L',base.size,0)
    draw=ImageDraw.Draw(shadow)
    draw.ellipse((left+width*.08,bottom-height*.075,left+width*.93,bottom+height*.055),fill=64)
    shadow=shadow.filter(ImageFilter.GaussianBlur(max(4,width*.014)))
    contact=Image.new('L',base.size,0)
    draw=ImageDraw.Draw(contact)
    draw.ellipse((left+width*.17,bottom-height*.025,left+width*.84,bottom+height*.013),fill=85)
    contact=contact.filter(ImageFilter.GaussianBlur(max(2,width*.006)))
    s=np.maximum(np.asarray(shadow),np.asarray(contact))
    s[~road]=0
    shadow=Image.fromarray(s)
    full_vehicle=Image.new('RGBA',base.size,(0,0,0,0))
    full_vehicle.paste(vehicle,(left,top))
    edited=base.convert('RGBA')
    shade=Image.new('RGBA',base.size,(0,0,0,0))
    shade.putalpha(shadow)
    edited=Image.alpha_composite(edited,shade)
    edited=Image.alpha_composite(edited,full_vehicle).convert('RGB')
    editmask=(np.asarray(full_vehicle.getchannel('A'))>0)|(s>0)
    changed=np.any(np.asarray(edited)!=np.asarray(base),axis=2)
    assert not np.any(changed&~editmask),'Background modified outside vehicle/shadow region'
    code='LHR-E02-0'+str(number)
    filename=code+'-trial.png'
    edited.save(ROOT/filename,optimize=True)
    Image.fromarray((editmask*255).astype(np.uint8)).save(QA/(code+'-edit-region.png'))
    preview=edited.copy()
    preview.thumbnail((1600,1600),Image.Resampling.LANCZOS)
    buffer=io.BytesIO()
    preview.save(buffer,'WEBP',quality=92,method=6)
    trials.append({'code':code,'name':'Wider airport setting' if number==1 else 'Closer vehicle placement','master_filename':filename,'dimensions':[W,H],'vehicle_box':[left,top,width,height],'vehicle_scale':scale,'estimated_ground_road_fraction':onroad,'estimated_people_overlap':p,'estimated_existing_vehicle_overlap':v,'changed_pixels':int(changed.sum()),'changed_pixels_outside_edit_region':0,'master_sha256':sha((ROOT/filename).read_bytes()),'preview_data':'data:image/webp;base64,'+base64.b64encode(buffer.getvalue()).decode()})
    print('COMPOSITE_CREATED',code,'BOX',[left,top,width,height],'ROAD',onroad,'PEOPLE',p,'VEHICLES',v,'OUTSIDE_REGION_CHANGED',0,flush=True)

def embed(im):
    im=im.copy()
    im.thumbnail((1600,1600),Image.Resampling.LANCZOS)
    buff=io.BytesIO()
    im.save(buff,'WEBP',quality=90,method=6)
    return 'data:image/webp;base64,'+base64.b64encode(buff.getvalue()).decode()

airport_preview=embed(base)
car_preview=embed(source_car)
provenance={'title':'Londra Transfers Heathrow E-Class photographic composite trial 04','kind':'private visual trial, not production assets','inputs':recovery['sources'],'background_source':'LHR-03','vehicle_source':'E02','source_substitutions':0,'vehicle_original_rgb_preserved_before_downsampling':True,'vehicle_upscaled':False,'airport_working_dimensions':[W,H],'edits':['E02 vehicle foreground alpha mask extracted using U2Net','Original vehicle pixels resized downward and placed on the existing road','Soft underbody and contact shadows confined to estimated road','No generative reconstruction, signage replacement, sky replacement or scenery synthesis'],'trials':[{k:v for k,v in t.items() if k!='preview_data'} for t in trials],'limitations':['Automatic masks and road-position estimates require visual review; not a photorealism certification.','Source E02 reflections and lighting have not been completely relit.','The airport source was photographed on 22 October 2014; current layout/access is not certified.','Fleet source editing/commercial-publication permission remains unverified.','No website, production database, credentials or application package was changed.'],'previous_decisions_preserved':{'trial_preferred':['LHR-03','STN-03','LTN-03'],'preferred_exteriors':['LCY-01','SEN-02'],'new_exterior_rejected':['LGW-03'],'original_rejected':['STN-02','LTN-02','LCY-02'],'fleet_excluded':['E06','E07']},'models_used_for_masks_only':['u2net',model_name]}
(ROOT/'PROVENANCE.json').write_text(json.dumps(provenance,indent=2),encoding='utf-8')

css='''
:root{--black:#000;--onyx:#0b0b0b;--white:#fff;--porcelain:#f7f7f5;--gold:#bda177;--champagne:#f1ecd9;--line:rgba(189,161,119,.3)}*{box-sizing:border-box}body{margin:0;background:var(--onyx);color:var(--porcelain);font:15px/1.65 Arial,sans-serif}main{max-width:1440px;margin:auto;padding:32px 32px 90px}a{color:var(--champagne)}.mast{display:flex;justify-content:space-between;gap:20px;border-bottom:1px solid var(--line);padding:0 0 25px;font-size:11px;text-transform:uppercase;letter-spacing:.2em;color:var(--gold)}h1,h2,h3{font-family:Georgia,serif;font-weight:400;line-height:1.12}h1{font-size:clamp(38px,5vw,68px);margin:42px 0 24px}h2{font-size:38px;margin:12px 0 22px}h3{font-size:27px;margin:10px 0}.lead{font-size:18px;color:var(--champagne);max-width:900px}.eyebrow{color:var(--gold);font-size:11px;text-transform:uppercase;letter-spacing:.16em}.notice{background:var(--black);padding:20px 24px;border:1px solid var(--line);margin:28px 0}.notice p{margin:8px 0}.section{border-top:1px solid var(--line);margin-top:45px;padding-top:25px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:26px}.card{min-width:0;border:1px solid var(--line);background:var(--black)}.photo{position:relative}.photo img{width:100%;height:auto;display:block;aspect-ratio:4/3;object-fit:contain;background:var(--black)}.view-original{display:none}.show-original .view-original{display:block}.show-original .view-composite{display:none}.copy{padding:22px}.badge{display:inline-block;color:var(--gold);font-size:11px;letter-spacing:.1em;text-transform:uppercase}.toolbar{display:flex;flex-wrap:wrap;gap:10px;margin:20px 0}button,.button{display:inline-flex;align-items:center;justify-content:center;min-height:48px;width:200px;max-width:100%;border:1px solid var(--gold);background:transparent;color:var(--porcelain);font:14px/1.2 Arial,sans-serif;text-decoration:none;padding:10px 16px;cursor:pointer}button:hover,.button:hover,.primary{background:var(--gold);color:var(--black)}button:focus-visible,a:focus-visible,select:focus-visible,textarea:focus-visible{outline:2px solid var(--champagne);outline-offset:4px}select{width:100%;min-height:48px;background:var(--onyx);color:var(--porcelain);border:1px solid var(--line);font:14px Arial,sans-serif;padding:12px}.decision{display:grid;gap:8px;border-top:1px solid var(--line);padding-top:18px;margin-top:18px}.decision label{font-size:12px;color:var(--champagne)}.notes{width:100%;min-height:85px;background:var(--onyx);color:var(--porcelain);border:1px solid var(--line);padding:12px;font:14px/1.5 Arial,sans-serif;resize:vertical}.small{font-size:12px;color:var(--champagne)}.sources img{width:100%;height:auto;aspect-ratio:4/3;object-fit:contain;background:var(--black)}dl{display:grid;grid-template-columns:90px 1fr;gap:6px 15px;font-size:12px}dt{color:var(--gold)}dd{margin:0;overflow-wrap:anywhere}dialog{background:var(--black);color:var(--porcelain);border:1px solid var(--gold);width:min(1500px,96vw);max-height:96vh;padding:18px}dialog::backdrop{background:rgba(0,0,0,.92)}dialog img{display:block;width:100%;max-height:76vh;object-fit:contain;margin-top:15px}.dialogbar{display:flex;justify-content:space-between;gap:16px;align-items:center}.dialogbar button{width:100px}#review-text{width:100%;min-height:280px;background:var(--black);color:var(--porcelain);padding:18px;border:1px solid var(--line);font:13px/1.6 Consolas,monospace}.status{font-size:12px;color:var(--champagne)}footer{border-top:1px solid var(--line);margin-top:40px;padding-top:20px;font-size:12px;color:var(--champagne)}@media(max-width:760px){main{padding:24px 16px 65px}.mast{flex-direction:column;gap:8px}.grid{grid-template-columns:1fr}.copy{padding:18px}h2{font-size:30px}.toolbar{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.toolbar>*{width:100%;font-size:12px;padding:10px 8px}.notice{padding:16px}}@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto}}
'''
parts=['<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'; script-src \'unsafe-inline\'; connect-src \'none\'; base-uri \'none\'; form-action \'none\'"><title>Londra Transfers | Heathrow E-Class Composite Trial 04</title><style>'+css+'</style></head><body><main><header><div class="mast"><span>Londra Transfers</span><span>Private composite trial · 04</span></div><p class="eyebrow">Heathrow Terminal 5 / Mercedes-Benz E-Class</p><h1>The first photographic<br>composite trial.</h1><p class="lead">The exact E02 vehicle photograph placed on your selected LHR-03 airport background. Two initial placement options are available for visual review.</p><div class="notice"><strong>Trial images, not approved website photographs.</strong><p>These are actual layered photographic composites, not generated airport scenes. Vehicle masking and road placement are automated first-pass work. Edges, scale, lighting, reflections and contact shadows still need visual approval and refinement.</p><p>No airport buildings, skies, signs or road markings have been invented. The original vehicle body, wheels and details have not been redrawn. The source vehicle photograph is a third-party appearance reference, not verified evidence of a Londra-owned vehicle.</p></div><p class="status" id="image-status" role="status">Checking the embedded photographs…</p></header><section class="section"><p class="eyebrow">LHR-03 + E02 / Source photographs retained</p><h2>Compare the two placements</h2><p>Use “Original airport” to inspect the unaltered background, or enlarge the trial. The PNG masters are included in the package.</p><div class="grid">']
for t in trials:
    code=t['code']
    parts.append(f'<article class="card" data-code="{code}"><div class="photo"><img class="view-composite" src="{t["preview_data"]}" alt="Private Heathrow and E-Class photographic composite trial {code}"><img class="view-original" src="{airport_preview}" alt="Original LHR-03 Heathrow airport photograph before compositing"></div><div class="copy"><p class="badge">{code} · Private trial</p><h3>{t["name"]}</h3><p class="small">Master: {W} × {H} pixels. Original source vehicle pixels; no vehicle enlargement beyond the isolated source dimensions.</p><div class="toolbar"><button type="button" data-toggle="{code}" aria-pressed="false">Original airport</button><button type="button" data-enlarge="{code}">Enlarge trial</button><a class="button" href="{t["master_filename"]}" download>Save PNG master</a></div><div class="decision"><label for="choice-{code}">Your trial decision</label><select id="choice-{code}" data-choice="{code}"><option>Unreviewed</option><option>Prefer this direction</option><option>Needs changes</option><option>Reject</option></select><label for="notes-{code}">Notes on scale, position, edges, lighting or reflections</label><textarea class="notes" id="notes-{code}" data-notes="{code}" maxlength="1500"></textarea></div></div></article>')
parts.append('</div></section><section class="section"><p class="eyebrow">Exact inputs / no substitutions</p><h2>The original photographs</h2><div class="grid sources"><article class="card"><img src="'+airport_preview+'" alt="LHR-03 original Heathrow Terminal 5 drop-off photograph"><div class="copy"><p class="badge">LHR-03</p><h3>Heathrow Terminal 5</h3><dl><dt>Photographer</dt><dd>Lewis Clarke</dd><dt>Photo date</dt><dd>22 October 2014</dd><dt>Licence</dt><dd><a href="https://creativecommons.org/licenses/by-sa/2.0/" target="_blank" rel="noopener noreferrer">CC BY-SA 2.0</a></dd></dl><p class="small">A historical source photograph. Current airport layout and access arrangements are not certified by this trial.</p><a href="https://www.geograph.org.uk/photo/4226587" target="_blank" rel="noopener noreferrer">Original source and attribution</a></div></article><article class="card"><img src="'+car_preview+'" alt="E02 original E-Class side-profile source photograph"><div class="copy"><p class="badge">E02</p><h3>The approved appearance reference</h3><p class="small">Exact original: Home-James-E-CLASS_7.jpg, from the previously reviewed source. No different Mercedes model or older reference has been substituted.</p><p class="small">Source copyright remains with its rights holder. Permission for commercial publication and editing has not been established; this is a private visual trial only. No source branding or registration text has been replaced with Londra branding.</p><a href="https://www.homejames.info/fleet/mercedes-e-class" target="_blank" rel="noopener noreferrer">Vehicle source gallery</a></div></article></div></section><section class="section"><h2>Your review</h2><p>Your airport preferences and exclusions remain unchanged. This export records feedback on these two Heathrow/E-Class trials only, not approval of all 24 planned airport/fleet combinations.</p><div class="toolbar"><button class="primary" id="export" type="button">Export review as text</button><button id="copy" type="button">Copy review text</button></div><p class="status" id="save-status" role="status">New trial decisions are unreviewed.</p><label for="review-text">Review text</label><textarea id="review-text" readonly></textarea></section><footer><p>Airport photograph: Lewis Clarke / Geograph 4226587 / CC BY-SA 2.0. Changes in these trials: background resized, E02 photographic vehicle cutout overlaid, contact shadows added. The image credit does not imply photographer or airport endorsement.</p><p>Gatwick LGW-03 remains rejected. E06–E07 remain excluded. No website, application ZIP, administrator, enquiries, production configuration or database has been altered.</p><p>All displayed photographs are embedded in this HTML and work offline. Keep the accompanying PNG files beside the HTML only to use the optional PNG-master links. No external fonts, scripts or tracking are used.</p></footer></main><dialog id="lightbox" aria-labelledby="modal-title"><div class="dialogbar"><strong id="modal-title"></strong><button id="close" type="button">Close</button></div><img id="modal-image" alt=""></dialog><script>')
parts.append('''
const codes=['LHR-E02-01','LHR-E02-02'];const key='londra-heathrow-e02-trial-04';const out=document.getElementById('review-text');const status=document.getElementById('save-status');
function refresh(){const lines=['Londra Transfers — Heathrow E-Class composite trial','Private photographic trial — edition 04','','EXACT SOURCES','Airport | LHR-03','Vehicle | E02','','TRIAL FEEDBACK'];codes.forEach(code=>{lines.push(code+' | '+document.querySelector('[data-choice="'+code+'"]').value);const note=document.querySelector('[data-notes="'+code+'"]').value.trim();if(note)lines.push(code+' notes | '+note.replace(/[\\r\\n]+/g,' '));});lines.push('','EXCLUSIONS UNCHANGED','Gatwick LGW-03 | Reject','Fleet E06, E07 | Excluded','','PUBLICATION','No commercial-publication permission or production approval is inferred.');out.value=lines.join('\\n');}
function save(){const data={};codes.forEach(code=>data[code]={choice:document.querySelector('[data-choice="'+code+'"]').value,notes:document.querySelector('[data-notes="'+code+'"]').value});try{localStorage.setItem(key,JSON.stringify(data));status.textContent='Trial feedback saved in this browser.';}catch(e){status.textContent='Local storage unavailable. Export or copy your feedback before closing.';}refresh();}
try{const stored=JSON.parse(localStorage.getItem(key)||'{}');codes.forEach(code=>{const saved=stored[code];if(saved){const select=document.querySelector('[data-choice="'+code+'"]');if(Array.from(select.options).some(o=>o.value===saved.choice))select.value=saved.choice;if(typeof saved.notes==='string')document.querySelector('[data-notes="'+code+'"]').value=saved.notes.slice(0,1500);}});}catch(e){}
document.querySelectorAll('[data-choice]').forEach(el=>el.addEventListener('change',save));document.querySelectorAll('[data-notes]').forEach(el=>el.addEventListener('input',save));refresh();
document.querySelectorAll('[data-toggle]').forEach(button=>button.addEventListener('click',()=>{const card=button.closest('.card');const original=card.classList.toggle('show-original');button.setAttribute('aria-pressed',String(original));button.textContent=original?'Show composite':'Original airport';}));
let opener=null;const dialog=document.getElementById('lightbox');const modal=document.getElementById('modal-image');document.querySelectorAll('[data-enlarge]').forEach(button=>button.addEventListener('click',()=>{opener=button;const card=button.closest('.card');modal.src=card.querySelector('.view-composite').src;modal.alt=card.querySelector('.view-composite').alt;document.getElementById('modal-title').textContent=button.dataset.enlarge+' · private composite trial';dialog.showModal();}));document.getElementById('close').addEventListener('click',()=>dialog.close());dialog.addEventListener('close',()=>{modal.removeAttribute('src');if(opener)opener.focus();});
document.getElementById('export').addEventListener('click',()=>{refresh();const url=URL.createObjectURL(new Blob([out.value],{type:'text/plain;charset=utf-8'}));const a=document.createElement('a');a.href=url;a.download='Londra_Heathrow_EClass_Trial_04_Feedback.txt';document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),30000);});document.getElementById('copy').addEventListener('click',async()=>{refresh();try{await navigator.clipboard.writeText(out.value);status.textContent='Feedback copied.';}catch(e){out.focus();out.select();status.textContent='Text selected. Press Ctrl+C or use Copy.';}});
Promise.all(Array.from(document.querySelectorAll('.card img')).map(img=>img.decode().then(()=>true,()=>false))).then(results=>{document.getElementById('image-status').textContent=results.filter(Boolean).length+' / '+results.length+' embedded image elements decoded offline.';});
</script></body></html>''')
html_path=ROOT/'Londra_Heathrow_EClass_Composite_Trial_04.html'
html_path.write_text(''.join(parts),encoding='utf-8')
(ROOT/'READ-ME.txt').write_text('Londra Transfers — First Heathrow / E-Class photographic composite trial\n\nExtract this package and open Londra_Heathrow_EClass_Composite_Trial_04.html in Chrome or Edge. All review photographs are embedded and require no internet. Optional Save PNG master links use the accompanying PNG files.\n\nThe two images are initial photographic placement trials made from the exact LHR-03 and E02 source photographs. Automatic masking, placement, scale, lighting and reflections require visual review and further refinement. They are not approved production images.\n\nOriginals are retained in sources/; E02-vehicle-cutout.png and LHR-03-working-background.png preserve the layers. PROVENANCE.json records inputs, operations and pixel-preservation checks. No model weights or fonts are distributed. No website or database was changed.\n',encoding='utf-8')
(QA/'BUILD-SUMMARY.json').write_text(json.dumps({'html_bytes':html_path.stat().st_size,'html_sha256':sha(html_path.read_bytes()),'trials':provenance['trials'],'source_recovery':recovery},indent=2),encoding='utf-8')
print('REVIEW_HTML_CREATED',html_path.stat().st_size,sha(html_path.read_bytes()),flush=True)

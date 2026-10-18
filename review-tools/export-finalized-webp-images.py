#!/usr/bin/env python3
"""Export exact reviewed Londra photo references as WebP + one readable TXT.
Private review/export branch only. Does not access or modify the website."""
import ast, hashlib, io, json, os, sys, textwrap, time, urllib.parse
from pathlib import Path
import requests
from PIL import Image, ImageOps, ImageFile

Image.MAX_IMAGE_PIXELS = 180_000_000
ImageFile.LOAD_TRUNCATED_IMAGES = False
OUT=Path("Londra_Finalized_Images_WebP")
OUT.mkdir(exist_ok=True)
CONTENT=OUT/"Londra_Finalized_Images_Content.txt"
approved=json.loads(Path("review-records/photo-approval-register-03-original.json").read_text(encoding="utf-8"))
overrides=json.loads(Path("review-records/photograph-decisions-2026-10-14.json").read_text(encoding="utf-8"))
tree=ast.parse(Path("review-tools/airport-exteriors-03.py").read_text(encoding="utf-8"))
assignment=next(n for n in tree.body if isinstance(n,ast.Assign) and any(isinstance(t,ast.Name) and t.id=="ITEMS" for t in n.targets))
airport_rows=ast.literal_eval(assignment.value)
SESSION=requests.Session()
SESSION.headers.update({"User-Agent":"LondraTransfersPrivatePhotoExport/1.0 (attribution and source verification; contact info@londratransfers.co.uk)"})
approvals={"L02-R1","L09","L10","L11","U08","U09"}
exclude={"L02","LGW-03","STN-02","LTN-02","LCY-02","E06","E07"}
rows=[]
for record in approved["review_02_decisions"]:
    if record["id"] in exclude: continue
    assert record["decision"]=="Approve",record["id"]
    rows.append({"code":record["id"],"group":"London" if record["id"].startswith("L") else "Beyond London","destination":record["destination"],"title":record["title"],"source_url":record["source_url"],"preview_url":record.get("preview_url"),"credit":record["credit"],"license_url":record["license_url"],"caution":record.get("source_cautions",""),"alt":record.get("alt"),"role":"Selected gallery photograph","date":record.get("listed_metadata",{}).get("Source date",""),"visual":None,"status":"APPROVED","kind":"tour"})
for record in approved["new_candidates"]:
    if record["id"] not in approvals:continue
    rows.append({"code":record["id"],"group":record["group"],"destination":record["destination"],"title":record["title"],"source_url":record["source_url"],"preview_url":record.get("preview_url"),"credit":"Photo: "+record["author"]+" | "+record["license"],"license_url":record["license_url"],"caution":record["caution"],"alt":record["alt"],"role":record.get("role","Gallery"),"date":record.get("date",""),"visual":record.get("visual",""),"status":"APPROVED","kind":"tour"})
airport_overrides=overrides["original_airport_decisions"] | overrides["supplementary_exterior_decisions"]
for r in airport_rows:
    code, airport, filename, author, date, license, license_url, author_url, note, group=r
    if airport_overrides.get(code) not in ("Prefer this photograph","Prefer for a composite trial"): continue
    rows.append({"code":code,"group":"Airports","destination":airport,"title":filename,"source_url":"https://commons.wikimedia.org/wiki/File:"+urllib.parse.quote(filename.replace(" ","_")),"credit":"Photo: "+author+" | "+license,"license_url":license_url,"caution":note,"alt":None,"role":"Original handpicked airport image; no vehicle composite","date":date,"visual":"","status":"PREFERRED ORIGINAL / no compositing","kind":"airport","author_url":author_url})
assert len([x for x in rows if x["kind"]=="tour"])==20
assert len([x for x in rows if x["kind"]=="airport"])==12
assert len(rows)==32 and len(set(x["code"] for x in rows))==32
failures=[]
def request(url):
    exc=None
    for delay in (0,1,3,8,12):
        if delay:time.sleep(delay)
        try:
            res=SESSION.get(url,timeout=55)
            res.raise_for_status()
            if not res.content:raise ValueError("empty download")
            return res.content
        except Exception as e:exc=e
    raise RuntimeError(str(exc))
def image_from_commons(filename):
    q={"action":"query","format":"json","prop":"imageinfo","titles":"File:"+filename,"iiprop":"url|size|extmetadata|sha1","iiurlwidth":"2560","redirects":"1"}
    d=SESSION.get("https://commons.wikimedia.org/w/api.php",params=q,timeout=60)
    d.raise_for_status()
    info=next((p["imageinfo"][0] for p in d.json().get("query",{}).get("pages",{}).values() if "imageinfo" in p),None)
    if not info:raise RuntimeError("No Commons imageinfo for "+filename)
    m=info.get("extmetadata",{})
    license_str=m.get("LicenseShortName",{}).get("value","")
    preferred=info.get("thumburl") or info["url"]
    return preferred,{"commons_original_width":info.get("width"),"commons_original_height":info.get("height"),"commons_license_name":license_str,"commons_file_url":info.get("descriptionurl"),"commons_original_sha1":info.get("sha1")}
def photograph_source(r):
    code=r["code"]
    if code=="L03":
        # The approved photograph on Pixabay, not a replacement artwork.
        return "https://cdn.pixabay.com/photo/2022/04/06/20/30/big-ben-7116305_1280.jpg",{"method":"Pixabay listed source preview, maximum available 1280 px if larger licensed source cannot be verified"}
    if code=="L10":
        return "https://images.unsplash.com/photo-1692303074427-44a602934beb?auto=format&fit=max&w=2560&q=97",{"method":"approved Unsplash photographer image endpoint"}
    if code=="U06":
        # Official mirror for approved Unsplash pre-2017 image, same source photograph.
        return image_from_commons("Building in an old street. (Unsplash).jpg")
    uri=r["source_url"]
    if "commons.wikimedia.org/wiki/File:" not in uri:
        raise ValueError("Unexpected source host: "+uri)
    filename=urllib.parse.unquote(uri.split("/wiki/File:",1)[1]).replace("_"," ")
    return image_from_commons(filename)
def export_webp(r):
    src,source_meta=photograph_source(r)
    contents=request(src)
    with Image.open(io.BytesIO(contents)) as verify:
        verify.verify()
    im=ImageOps.exif_transpose(Image.open(io.BytesIO(contents)))
    image_size=im.size
    if im.mode not in ("RGB","RGBA"):im=im.convert("RGB")
    if max(im.size)>2560: im.thumbnail((2560,2560),Image.Resampling.LANCZOS)
    path=OUT/("01_London" if r["kind"]=="tour" and r["group"]=="London" else "02_Beyond_London" if r["kind"]=="tour" else "03_Airports")
    path.mkdir(exist_ok=True)
    f=path/(r["code"]+".webp")
    im.save(f,format="WEBP",quality=95,method=6)
    with Image.open(f) as loaded: loaded.verify()
    return {"file":str(f.relative_to(OUT)),"sha256":hashlib.sha256(f.read_bytes()).hexdigest(),"webp_bytes":f.stat().st_size,"converted_dimensions":list(im.size),"downloaded_dimensions":list(image_size),"source_image_url":src,"source_metadata":source_meta,"conversion":"No appearance edits; resize only if source exceeds 2560 pixels, WebP quality 95"}
for r in rows:
    try:
        r["output"]=export_webp(r)
        print("IMAGE_OK",r["code"],r["output"]["file"],r["output"]["converted_dimensions"],flush=True)
    except Exception as exc:
        r["error"]=str(exc)[:500]
        failures.append(r["code"])
        print("IMAGE_FAILED",r["code"],r["error"],flush=True)
# Include the four existing first-party website fleet illustrations as a separate
# clearly labelled category, not as commercially-cleared third-party fleet photos.
fleet=[
 ("Mercedes-Benz E-Class","mercedes-e-class"),
 ("Mercedes-Benz S-Class","mercedes-s-class"),
 ("Mercedes-Benz V-Class","mercedes-v-class"),
 ("Mercedes-Benz Vito","mercedes-vito")
]
fleetrows=[]
for name,slug in fleet:
    src=Path("public_html/assets/photos")/(slug+"-1440.webp")
    fr={"model":name,"reference":"Existing website artwork (NOT E01–E05 etc.)","role":"Optional bottom-right responsive fleet thumbnail artwork; not a photo-rights-cleared fleet photo"}
    if src.is_file():
        p=OUT/"04_Existing_Fleet_Art_NOT_New_Photographs"/(slug+".webp")
        p.parent.mkdir(exist_ok=True)
        data=src.read_bytes()
        with Image.open(io.BytesIO(data)) as im: im.verify()
        p.write_bytes(data)
        fr.update({"file":str(p.relative_to(OUT)),"sha256":hashlib.sha256(data).hexdigest(),"bytes":len(data)})
    else:fr["error"]="Unavailable in repository: "+str(src)
    fleetrows.append(fr)
lines=[
 "LONDRA TRANSFERS — FINALIZED PHOTOGRAPH COLLECTION",
 "Contents and image-source manifest | Original scenes retained, WebP encoding only",
 "="*75,"",
 "SCOPE AND IMPORTANT DISTINCTIONS",
 "- 20 user-approved destination images: 11 London and 9 Beyond London.",
 "- 12 user-preferred original airport photographs: six airport groups.",
 "- All rejected images are excluded: L02, LGW-03, STN-02, LTN-02, LCY-02.",
 "- Prior composite trials are NOT included. No cars have been inserted into airport scenes.",
 "- Four existing website fleet artwork thumbnails are separate from third-party fleet photography.",
 "- Approved E01-E05, S01-S07, V01-V07 and T01-T05 refer to appearance only; they are not permission for commercial reuse.",
 "- The exact airport photographs remain historical where their date is old. No claims of current curbside layouts.",
 "- The WebP conversion retains the original composition; only maximum-size reduction and image encoding are applied.",
 "- Rights: follow each linked licence including credit, licence link, changes disclosure and ShareAlike obligations where applicable.",
 "- No user data, production database, keys or application code is present in this image-only package.",
 "",
 "INTENDED WEBSITE PRESENTATION",
 "London collection lead: L01 (Tower Bridge).",
 "Beyond London lead: U04 (Cambridge).",
 "Airports: show original selected photograph without compositing.",
 "Fleet: on airport image layout, a compact bottom-right grouping of four fleet thumbnails;",
 "move or reflow the thumbnail group below/on a separate line on small screens rather than obscuring signs.",
 "",
 "DESTINATION PHOTOGRAPHS — APPROVED (20)",
 "-"*75
]
for r in rows:
    if r["kind"]!="tour":continue
    o=r.get("output",{})
    lines += ["",f'{r["code"]} | {r["group"]} | {r["destination"]}',
      "Title: "+r["title"],"Role: "+r["role"],"Approval: "+r["status"],
      "WebP file: "+str(o.get("file","NOT GENERATED")), "WebP dimensions: "+str(o.get("converted_dimensions","unknown")),
      "Caption / visual description: "+str(r.get("visual") or r["title"]),
      "Alt text: "+str(r.get("alt") or "Describe the destination accurately in the page language; no original ALT provided in register."),
      "Credit and rights: "+r["credit"],"Licence: "+r["license_url"],
      "Photo source: "+r["source_url"],"Date: "+str(r.get("date") or "Unspecified"),
      "Usage / crop caution: "+str(r["caution"])]
    if "error" in r:lines.append("Source processing warning: "+r["error"])
lines += ["", "AIRPORT PHOTOGRAPHS — PREFERRED ORIGINALS (12)", "-"*75]
for r in rows:
    if r["kind"]!="airport":continue
    o=r.get("output",{})
    lines += ["",f'{r["code"]} | {r["destination"]}',
      "Original source filename: "+r["title"],"Status: "+r["status"],
      "WebP file: "+str(o.get("file","NOT GENERATED")),
      "WebP dimensions: "+str(o.get("converted_dimensions","unknown")),
      "Website use: Airport photograph shown unchanged in appearance, not a car composite.",
      "Photo credit: "+r["credit"],"Licence: "+r["license_url"],
      "Source: "+r["source_url"],"Photograph date: "+r["date"],
      "Composition caution: "+r["caution"]]
    if r.get("author_url"):lines.append("Photographer website: "+r["author_url"])
    if "error" in r:lines.append("Source processing warning: "+r["error"])
lines += ["","EXISTING WEBSITE FLEET ARTWORK — 4 (SEPARATE FROM PHOTO APPROVAL)", "-"*75]
for r in fleetrows:
    lines += ["",r["model"],"Category: EXISTING SITE ARTWORK, not a newly approved real fleet photograph",
              "WebP file: "+r.get("file","NOT INCLUDED"), "Placement: "+r["role"],
              "Rights limitation: Do not mistake appearance approvals E/S/V/T for commercial licences."]
lines += ["", "APPROVAL REGISTER AND EXCLUSIONS","-"*75]
for k,v in sorted(overrides["original_airport_decisions"].items()):lines.append(f"{k} | {v}")
for k,v in sorted(overrides["supplementary_exterior_decisions"].items()):lines.append(f"{k} | {v}")
lines += ["L02 | Replaced by L02-R1; excluded", "E06 | Excluded", "E07 | Excluded",
          "Bicester Village | Image unresolved, no invented substitute included.",
          "", "DOWNLOADED SOURCE VERIFICATION", "-"*75]
for r in rows:
    if r.get("output"):
        o=r["output"]
        lines += [f'{r["code"]} | {o["file"]} | {o["converted_dimensions"]} | {o["webp_bytes"]} bytes | SHA256 {o["sha256"]}',
                  'Image endpoint: '+o['source_image_url']]
    else: lines.append(f'{r["code"]} | FAILED: {r.get("error")}')
lines += ["","RELEASE INTEGRITY","-"*75,f"Destination images succeeded: {sum(bool(r.get('output')) for r in rows if r['kind']=='tour')} / 20",
          f"Airport images succeeded: {sum(bool(r.get('output')) for r in rows if r['kind']=='airport')} / 12",
          f"Existing fleet thumbnail artwork: {sum(bool(r.get('file')) for r in fleetrows)} / 4",
          f"Source failures: {', '.join(failures) if failures else 'None'}", ""]
CONTENT.write_text("\n".join(lines),encoding="utf-8")
(OUT/"image-manifest.json").write_text(json.dumps({"images":rows,"fleet_existing_art":fleetrows,"failures":failures,"scope":"images only; not deployment zip"},ensure_ascii=False,indent=2),encoding="utf-8")
print("SUMMARY",json.dumps({"tour_success":sum(bool(r.get("output")) for r in rows if r["kind"]=="tour"),"airport_success":sum(bool(r.get("output")) for r in rows if r["kind"]=="airport"),"fleet_existing":sum(bool(r.get("file")) for r in fleetrows),"failures":failures}),flush=True)
if failures:
    sys.exit("Incomplete collection: refusing to label as finished. See TXT report.")

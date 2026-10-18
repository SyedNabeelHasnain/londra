#!/usr/bin/env python3
"""Private candidate's read-only browser QA against an isolated seeded test server.
CI must configure isolated MySQL, never a production database."""
import json, time, urllib.request
from pathlib import Path
from playwright.sync_api import sync_playwright
base="http://127.0.0.1:8765"
expected=[
("/en/airports/heathrow","LHR-03"),("/en/airports/gatwick","LGW-01"),
("/en/airports/stansted","STN-03"),("/en/airports/luton","LTN-03"),
("/en/airports/london-city","LCY-01"),("/en/airports/southend","SEN-02")
]
for attempt in range(35):
    try:
        with urllib.request.urlopen(base+"/up",timeout=2) as resp:
            if resp.status==200:break
    except Exception:time.sleep(1)
else:
    raise RuntimeError("Isolated Laravel test server never became ready")
checks=[]
def check(condition,msg):
    if not condition: raise AssertionError(msg)
    checks.append(msg)
with sync_playwright() as playwright:
    browser=playwright.chromium.launch(headless=True,args=["--no-sandbox"])
    for width in (1440,390,320):
        context=browser.new_context(viewport={"width":width,"height":900},device_scale_factor=1,ignore_https_errors=False)
        page=context.new_page()
        js_errors=[]
        page.on("pageerror",lambda err:js_errors.append(str(err)))
        from pathlib import Path
        cssfiles=list((Path(__file__).resolve().parents[1]/"public_html/build/assets").glob("app-*.css"))
        check(len(cssfiles)==1,f"{width}: exactly one compiled public CSS bundle exists")
        for path,reference in expected:
            response=page.goto(base+path,wait_until="domcontentloaded",timeout=30000)
            # The isolated smoke host runs independently of the configured public
            # domain; apply the exact freshly compiled CSS from disk for visual QA.
            page.add_style_tag(content=cssfiles[0].read_text(encoding="utf-8"))
            check(response is not None and response.status==200,f"{width}: {path} rendered HTTP 200")
            photo=page.locator(".airport-original-photo")
            check(photo.count()==1,f"{width}: {path} exactly one approved original")
            check(reference+".webp" in photo.get_attribute("src"),f"{width}: {path} approved {reference} reference")
            photo.evaluate("(img)=>img.decode()")
            check(photo.evaluate("(img)=>img.naturalWidth>0"),f"{width}: {path} airport image loaded")
            links=page.locator(".airport-fleet-thumbnail")
            check(links.count()==4,f"{width}: {path} four fleet thumbnails")
            check(links.evaluate_all("(els)=>els.every(x=>x.offsetWidth>0 && x.querySelector('img').naturalWidth>0)"),f"{width}: {path} thumbnails loaded")
            horizontal=page.evaluate("""() => ({scrollWidth:document.documentElement.scrollWidth,viewport:innerWidth,offenders:[...document.querySelectorAll('body *')].filter(e=>{const r=e.getBoundingClientRect();return r.width>1&&(r.right>innerWidth+1||r.left<-1)}).slice(0,16).map(e=>({tag:e.tagName,classes:String(e.className).slice(0,110),text:(e.innerText||'').slice(0,35),box:e.getBoundingClientRect().toJSON()}))})""")
            if horizontal['scrollWidth']>width:
                print("HORIZONTAL_DEBUG",path,width,json.dumps(horizontal),flush=True)
            check(horizontal['scrollWidth']<=width+1,f"{width}: {path} no horizontal scrolling")
            if width in (1440,390) and path in ("/en/airports/heathrow","/en/airports/gatwick"):
                out=Path("image-review-qa")
                out.mkdir(exist_ok=True)
                page.screenshot(path=str(out/f"{reference}-{width}.png"),full_page=True)
        for path,expectedCount in [("/en/tours/london",11),("/en/tours/beyond-london",9)]:
            response=page.goto(base+path,wait_until="domcontentloaded",timeout=30000)
            page.add_style_tag(content=cssfiles[0].read_text(encoding="utf-8"))
            check(response is not None and response.status==200,f"{width}: {path} rendered")
            cards=page.locator(".approved-tour-card")
            check(cards.count()==expectedCount,f"{width}: {path} exactly {expectedCount} approved photos")
            check(cards.evaluate_all("(els)=>els.every(e=>e.querySelector('img').complete && e.querySelector('img').naturalWidth>0)"),f"{width}: {path} all gallery photos decoded")
            check(page.evaluate("document.documentElement.scrollWidth<=window.innerWidth"),f"{width}: {path} no horizontal overflow")
            if width in (1440,390):
                out=Path("image-review-qa")
                out.mkdir(exist_ok=True)
                page.screenshot(path=str(out/f"{'london' if expectedCount==11 else 'beyond'}-{width}.png"),full_page=True)
        check(not js_errors,f"{width}: no unhandled JS errors")
        context.close()
    browser.close()
report={"status":"passed","checks":len(checks),"viewport_widths":[1440,390,320],"airport_pages":6,"tour_galleries":2,"changes_approved_for_live":False,"source_baseline":"GitHub main 2026-10-13 (NOT current 2026-10-14.3 app archive)","reconciliation_required":True,"results":checks}
Path("image-review-qa").mkdir(exist_ok=True)
Path("image-review-qa/SMOKE-RESULTS.json").write_text(json.dumps(report,indent=2))
print("CANDIDATE_BROWSER_QA_PASSED",len(checks),flush=True)

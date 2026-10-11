import json
import re
import time
import urllib.error
import urllib.request
from pathlib import Path

base = "http://127.0.0.1:8137"
for attempt in range(60):
    try:
        with urllib.request.urlopen(base + "/up", timeout=2) as response:
            if response.status == 200:
                break
    except Exception:
        time.sleep(0.25)
else:
    raise SystemExit("Laravel HTTP server did not become healthy")

pages = json.loads(Path("database/content/pages.json").read_text(encoding="utf-8"))
count = 0
unpublished = 0

for page in pages:
    for locale in ("en", "it", "es"):
        translation = page["translations"][locale]
        slug = translation["slug"]
        url = base + "/" + locale + ("/" + slug if slug else "")
        try:
            with urllib.request.urlopen(url, timeout=12) as response:
                status = response.status
                html = response.read().decode("utf-8")
        except urllib.error.HTTPError as error:
            status = error.code
            html = error.read().decode("utf-8", errors="replace")
        if page["approval_required"]:
            if status != 404:
                raise SystemExit(f"Unapproved policy must be 404, got {status} at {url}")
            unpublished += 1
            continue
        if status != 200:
            raise SystemExit(f"HTTP {status} for {url}: {html[:600]}")
        if f'<html lang="{locale}"' not in html or "<main" not in html or "<h1" not in html:
            raise SystemExit(f"Missing language/landmark markup: {url}")
        if "<link rel=\"canonical\"" not in html:
            raise SystemExit(f"Missing canonical: {url}")
        if "<img" not in html or "<title>" not in html:
            raise SystemExit(f"Missing essential media/title: {url}")
        if not re.search(r'<meta property="og:image" content="https?://[^"]+/assets/', html):
            raise SystemExit(f"Social preview image must use an absolute URL: {url}")
        if not re.search(r'<meta name="twitter:image" content="https?://[^"]+/assets/', html):
            raise SystemExit(f"Twitter image metadata missing: {url}")
        for term in ("lorem ipsum", "template only", "this development preview is private", "not a live website"):
            if term in html.lower():
                raise SystemExit(f"Development-facing text ({term}) at {url}")
        if page["key"] == "services":
            if html.count('class="service-spread"') != 9:
                raise SystemExit(f"Incomplete service sections at {url}")
            if html.count("spread-excerpt") < 8:
                raise SystemExit(f"Missing detailed service copy at {url}")
        count += 1

if count != 96 or unpublished != 15:
    raise SystemExit(f"Unexpected page counts: published={count}, blocked={unpublished}")
print(f"Laravel HTTP smoke passed: {count} localized pages; {unpublished} unapproved policy routes correctly blocked")

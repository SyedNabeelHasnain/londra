from pathlib import Path
import re

root = Path("londra")
policy = root / "app/Domain/TransactionalMailPolicy.php"
s = policy.read_text(encoding="utf-8")
lines = s.splitlines(keepends=True)
candidates = [i for i, line in enumerate(lines) if "return preg_match(" in line and "zoho" in line]
if len(candidates) != 1:
    raise SystemExit("Expected exactly one Zoho host allowlist predicate")
i = candidates[0]
current = lines[i]
if "!== 1;" in current:
    lines[i] = current.replace("!== 1;", "=== 1;")
elif "=== 1;" not in current:
    raise SystemExit("Unexpected Zoho predicate: " + current.strip())
s = "".join(lines)
if not re.search(r"return preg_match\(.+zoho.+\) === 1;", s):
    raise SystemExit("Zoho allowlist was not configured correctly")
policy.write_text(s, encoding="utf-8")
print("Confirmed restrictive Zoho-only mail host allowlist")

readiness = root / "app/Services/ReadinessReport.php"
s = readiness.read_text(encoding="utf-8")
old = "['label'=>'Transactional SMTP provider permitted'"
new = "['label'=>'Zoho Mail SMTP permission confirmed'"
if old in s:
    s = s.replace(old, new)
elif new not in s:
    raise SystemExit("Missing expected transactional SMTP readiness label")
old_detail = "Zoho Mail SMTP prohibits automated and transactional notifications. Use an authorised transactional sender, document approval, and verify SPF/DKIM/DMARC before enabling delivery."
new_detail = "Zoho Mail SMTP is the only permitted sender. Its ordinary Mail usage policy forbids automated/transactional emails: keep notifications disabled until Zoho confirms an account-specific exception. Verify SPF, DKIM, DMARC and test actual sending before activation."
if old_detail in s:
    s = s.replace(old_detail, new_detail)
elif new_detail not in s:
    raise SystemExit("Missing expected transactional SMTP readiness guidance")
readiness.write_text(s, encoding="utf-8")
print("Confirmed accurate Zoho-only readiness warning")

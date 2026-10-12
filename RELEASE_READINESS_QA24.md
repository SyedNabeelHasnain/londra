# Londra Transfers — QA24 release readiness

Date: 12 October 2026

Status: compiled and tested update candidate. **Not approved for production activation.**

## Verified build

- GitHub Actions run: 38188893219
- PHP 8.5 on GitHub Actions; Laravel/Filament dependencies installed, audited and compiled.
- 20 Laravel tests passed (97 assertions), using isolated MySQL.
- Actual Laravel HTTP smoke tests passed on all 96 public language pages.
- All 15 unapproved policy-language URLs stayed unavailable.
- Five policy draft records × English, Italian, Spanish passed import guards, duplicate-run and invalid-bundle tests without publication.
- Privacy-request migration, retained existing records test, rights handling, retention dry-run and scheduler-heartbeat tests passed.
- Private CLI cron entry point compiled, invoked and included in the resulting release ZIP.
- Compiled output passed ZIP integrity and private/public directory-isolation checks.

## Recovered staging

The former QA21 staging PermissionError was a local directory-ownership issue, not a Laravel or MySQL error. A writable staging directory was created and the rebuilt QA22 archive matched its preserved predecessor byte-for-byte.

The later QA24 artifact is intentionally extended locally with the 15 unapproved policy drafts. Resulting staged inner archive SHA-256:

`6bdd4700a3b401fe69d88b6275881292f4c91abf2854ac1f9fb4d67834db9e9f`

The staged archive includes `londra-private/cron.php`, `vendor/autoload.php`, public assets and policy drafts, while excluding a live `.env`, installers and database contents.

## Launch blockers

1. An authorised business/legal review must approve and publish complete Terms, Privacy, Refund, Cookies and Accessibility text in three languages. Until then, enquiry processing and indexing stay disabled.
2. Determine whether an EU/EEA representative is required under GDPR Article 27 for the business's intentional Italian and Spanish targeting, and appoint one or document a valid exemption where appropriate.
3. Ordinary Zoho Mail's September 2026 usage policy prohibits automated and transactional email. Select a permitted transactional SMTP service or obtain explicit suitable service authorisation; do not enable automatic acknowledgements on an unpermitted provider.
4. Configure the Hostinger PHP cron job for the private CLI runner and verify its heartbeat in the administrator Readiness page.
5. Perform live Hostinger database upgrade/rollback, rights-request, real SMTP delivery and failure/retry, privacy retention, browser and accessibility checks without exposing customer data.
6. Confirm account-specific privacy processing and cross-border arrangements for Hostinger, the selected SMTP provider and backup handling.
7. The country-to-language feature requires an authorised country-detection source; fallback to English and manual explicit language URLs work without it.

The application must not be described as 100% legally compliant, universally pixel-perfect, guaranteed inbox-delivered or ready for unrestricted live enquiries merely because GitHub CI passed.

## Safeguards

- Update only; preserve installed `.env`, APP_KEY, one administrator, customer database, uploaded media and private sessions.
- Never re-run the original first-time installer on a populated installation.
- Do not enable automated deletion or indexing before approved retention and privacy processes are in effect.
- Do not store temporary MySQL/SMTP passwords in repository files or release artifacts.

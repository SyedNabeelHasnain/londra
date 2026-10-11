# Londra Transfers — Hostinger no-SSH update guide

Release status: compiled deployment candidate. Do not treat it as publicly approved until every release gate below passes.

## Before updating

1. In Hostinger hPanel, save a recoverable backup of the currently working `public_html`, `londra-private`, and the associated MySQL database.
2. Confirm the PHP 8.5 configuration and extensions remain available. The previous preflight showed PHP 8.5.11, LiteSpeed, PDO MySQL, intl and the required extensions.
3. Confirm that the private `londra-private` folder is alongside `public_html`, not inside it.
4. Preserve `londra-private/.env`, the Laravel application encryption key, CMS account, customer records, private storage, and any uploaded media. Do not reset the application key or reinstall the site.
5. Extract only the inner `Londra_Hostinger_Private_Public_Release.zip` at the hosting account directory containing both sibling folders. Do not extract the Laravel private folder inside `public_html`.

## After extracting

1. Confirm the public entry point loads `londra-private/vendor/autoload.php` via the existing protected sibling path.
2. Sign in to `/admin` with the existing single administrator account. Two-factor authentication is optional, but recommended.
3. Open the CMS **Administration → Readiness** page. If **Install privacy-request database tables** appears, export and verify a current MySQL backup, confirm the backup checkbox, and apply the additive privacy migration. This action is restricted to the authenticated administrator and must not create or reset accounts.
4. Recheck the Readiness page. The privacy tables and retention field must be reported as installed.
5. Check `/en/`, `/it/`, and `/es/`, along with services, fleet, contact, dark/light themes, and mobile navigation. The local country-lookup database is not yet provisioned; explicit language URLs and user selections continue to work.
6. Check the English, Italian, and Spanish privacy-request pages. Submit test rights requests only when the new privacy tables are installed. Review and process them in **CMS → Client enquiries → Privacy requests** after verifying the requester, and ensure the outcome explains any genuine legal hold.
7. Do not publish the five policy pages without reviewing and approving complete corresponding English, Italian, and Spanish versions. Unreviewed policy records must remain unpublished.
8. Leave general enquiry submission, automated acknowledgements, analytics and search indexing disabled until current policy notices, transactional SMTP permission, mail delivery, cron execution, retention practices, and EEA representative applicability have been confirmed.

## Privacy and release controls

- Booking/contact enquiries collect name, email and telephone; no payment gateway or automatic booking confirmation is supplied.
- The database retains historic enquiry fields for backward compatibility; the new public forms do not request payment details or subject/message free text.
- Privacy requests may also be made by email, including while a database schema update is being applied.
- A request alone never automatically erases another person's data. Administrator verification and a reasoned rights decision are required.
- Documented, specific legal holds may prevent deletion of a particular record; do not create blanket holds.
- The proposed 180-day unconverted-enquiry retention is configurable and requires an approved operating policy. The routine runs in dry-run mode until explicitly activated.
- SMTP message handling necessarily transmits sender and recipient addresses and message content to the selected provider. Ordinary Zoho Mail's published September 2026 usage policy disallows automated/transactional mail; confirm permission or choose an authorised transactional sender before enabling production notifications.
- To activate Cron Jobs, confirm Hostinger's available PHP CLI path and execution intervals. Do not invent an executable path or use a public unauthenticated cron URL.

## Release verification

The build pipeline checks PHP syntax, dependency audits, isolated MySQL migrations, a feature-test suite, 96 localized public HTTP routes, closed policy routes, and ZIP paths/contents. Those checks do not replace a successful real Hostinger database, SMTP/cron, browser, security and legal acceptance review.

The update archive intentionally does not contain `.env`, user credentials, customer database contents, production sessions, or prior runtime logs.

If installation fails, stop. Do not rerun installation or regenerate the app key. Restore from the verified backup and inspect application logs privately.

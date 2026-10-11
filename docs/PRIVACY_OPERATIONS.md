# Londra Transfers — Privacy Rights Operations (internal)

Status: implementation and testing guidance. This document does not constitute legal certification, approval of customer-facing policies or authorization to enable notifications.

## Purpose and collection

Booking and contact enquiries use customer-supplied name, email and telephone. The website also maintains an enquiry reference, language, originating page/service, timestamps and short-lived operational session/rate-limit information needed to operate the service. No bank, card, payment, passport or medical details are requested by the public enquiry forms. Historical schema fields may remain for migration compatibility but are prohibited by current public validation.

MySQL is the primary record store. Sensitive enquiry fields, privacy-request fields and internal notes use Laravel encryption at rest. Hostinger hosts the application/database; the mail provider only handles addresses and message content necessary to relay notifications. Email processing must be correctly disclosed as a processor/service category. Do not describe SMTP relay as involving no personal-data processing.

## Rights request workflow

1. Customer opens /en/privacy-request, /it/privacy-request or /es/privacy-request, or writes to info@londratransfers.co.uk. The request form is independent of the commercial enquiry gate.
2. A reference and encrypted request details are stored. Incoming identity evidence should be minimized; never ask for passport scans by default.
3. The sole administrator reviews the protected Privacy requests screen, checks control of the original email address or other proportionate evidence, and records how identity was verified.
4. The administrator reviews enquiries associated with the verified address, each reason for continued retention and any existing documented legal hold.
5. Approving a request erases eligible MySQL enquiry records, notes and notification-delivery entries. An offline-confirmed journey alone is not an automatic reason to refuse erasure; actual retention duties must be assessed and recorded. An explicitly held record is not erased automatically.
6. The administrator records the decision and a customer-facing explanation. A notification is queued only if approved SMTP delivery is enabled. The case record contains the minimum justified information needed to demonstrate the request was handled.
7. Requests should ordinarily be answered without undue delay and within one calendar month of receipt; any lawful extension requires actual review and appropriate notification. The CMS deadline is based on original receipt, not later identity verification.
8. Before activating any purge, approve the enquiry-retention period, lawful exceptions, backup lifecycle and scheduled CLI/cron processing. The current default 180 days is a proposal, not a legally prescribed retention period. The retention command remains a dry run while auto-retention is disabled.

## Processor and operational checks

- Verify processor contracts, hosting locations and transfer safeguards for Hostinger and the authorized email service.
- Zoho Mail's published usage policy currently prohibits automated and transactional emails from ordinary Zoho Mail accounts. Confirm whether the account has a permitted transactional SMTP service such as ZeptoMail or another authorized provider before enabling notifications.
- Confirm SPF, DKIM and DMARC for the sending domain. These support authentication but never guarantee inbox placement.
- Record how deleted data is managed in restricted-access backups and its expiry; do not promise immediate deletion from immutable backups.
- Review whether EU GDPR Article 27 representation is required given intentional services for Italy/Spain. Do not invent an EU representative address.
- If analytics or visitor location detection are enabled later, reassess cookie/consent, notice and international transfer obligations.
- Set APP_DEBUG=false, do not publish .env, do not put customer fields in server or email-debug logs, and rotate any credentials disclosed in conversation.
- Keep policy publishing, enquiry acceptance, automatic retention and notification sending behind independent approval/verification gates.

## References

- ICO: https://ico.org.uk/for-the-public/your-right-to-get-your-data-deleted
- ICO: https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/individual-rights/the-right-to-be-informed/what-privacy-information-should-we-provide/
- ICO: https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/accountability-and-governance/guide-to-accountability-and-governance/contracts/
- Zoho Mail Usage Policy: https://www.zoho.com/mail/help/usage-policy.html

## Release gate

Passing synthetic framework tests does not verify production Hostinger Cron Jobs, real SMTP, erased data in external backups, approved commercial policies or regulator compliance. Do not label production as legally certified based on this document.

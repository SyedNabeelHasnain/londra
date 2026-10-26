# Londra Transfers — Source Development

This branch contains the application source for continued development. It is not a production ZIP and is not ready to upload directly to Hostinger.

## Project layout

- `londra-private/`: Laravel/PHP application source, routes, migrations, seeders, tests, translations, view templates, application content, source artwork and source photography.
- `public_html/`: public PHP entry points, rewrite rules, branded artwork, and the committed responsive WebP image assets used by the frontend.
- `londra-private/composer.json` and `composer.lock`: PHP dependency definitions and locked versions.
- `londra-private/package.json` and `package-lock.json`: frontend development dependency definitions and locked versions.
- `londra-private/tests/`: application and feature tests.

## Important

- The production `.env`, credentials, database dumps, live administrator records and enquiry records are intentionally not stored in Git.
- The existing MySQL database must not be reset or reseeded when continuing development.
- Build outputs, the production packaging workflow, and obsolete previous-public-copy JSON snapshots were removed from this source-only branch.
- Existing committed brand artwork and public image assets were retained.
- The previously approved tour-photo and vehicle-photo review decisions are not equivalent to a licence to republish third-party photographs. Before treating the full photo galleries as complete, verify that each approved original is actually present in the source assets and that commercial reuse rights are established. Do not invent or substitute images silently.
- This branch is separate from `main`. The default branch and the deployed website have not been changed by this cleanup.

## Local development

Use a PHP version compatible with the constraints in `composer.json`, a compatible MySQL instance, and the project’s locked Composer dependencies. Review the existing configuration and migration history before connecting to any database. Never point development or test seeders at the production database.

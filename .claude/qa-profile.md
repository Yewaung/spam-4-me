# QA profile: Spam 4 Me

Last refreshed: 2026-11-15

Part of the 4 Me project by TheStickPress. Form spam filtering for several form plugins, rate limits, blocked log.

## Stack
- Framework and language: WordPress plugin, PHP 8.0+, WordPress 6.5+, plain CSS and JS (no build step, no Composer or npm).
- Hosting: runs on the customer's WordPress host; developed on Local (thestickpresssite.local).
- Database: WordPress options and post meta through core APIs.
- Auth: WordPress capabilities and nonces.
- Shared code: `hub/` (TheStickPress Hub 1) must stay identical in every 4 Me plugin, apart from the plugin's own text.
- Security sections to use from the global skill: General, WordPress.

## Commands (run from the plugin folder, with Local's PHP and WP-CLI on PATH)
- Lint PHP: `for f in $(git ls-files '*.php'); do php -l "$f"; done`
- Check JS syntax: `for f in $(git ls-files 'assets/*.js' | grep -v vendor); do node --check "$f"; done`
- Plugin Check: `wp plugin check spam4me` (plugin-check is installed on the dev site)
- Tests and build: none.
- Package: zip the folder without the `.distignore` entries into `wp-content/uploads/releases/spam4me.zip`.

## Version floors (2026-11-15)
| Package | Minimum | Why |
|---|---|---|
| WordPress | 7.1.4 | CVE-2026-119977 (CISA KEV) |
| PHP | 8.0 | plugin header |

## Smoke test URLs
- http://thestickpresssite.local/
- http://thestickpresssite.local/plugins/spam4me/
- http://thestickpresssite.local/wp-admin/admin.php?page=spam4me (logged in)
- http://thestickpresssite.local/this-page-does-not-exist/ (expect 404)

## Standing decisions (requirements, not findings)
- No telemetry, no external calls unless the feature needs them and the readme lists them under External services.
- Owner's writing rule: no long dashes or spaced hyphens in copy, docs or commit messages.
- TheStickPress is the company; 4 Me is one of its projects.

## Project checklist (in addition to the global security reference)
- Every admin action: capability check and nonce; every output escaped; every input sanitized; SQL through $wpdb->prepare.
- Logs store the minimum needed and expire; IPs are hashed or truncated where possible.

## Docs to read
- Rules: the owner's global CLAUDE.md
- Requirements: readme.txt
- Log: docs/qa-log.md
- Audit reports: docs/audit-YYYY-MM-DD.md

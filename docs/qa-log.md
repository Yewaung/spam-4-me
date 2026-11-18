# QA log

Newest first. One entry per QA check or push.

## 2026-11-18: QA check and push (no release)

- .claude left out of release archives (`.gitattributes`); no plugin code changed.
- Checks: PHP lint and JS syntax pass, PHPCS WordPress-Extra clean, Plugin Check no code issues (only the shared kit names and dev files kept out of the zip). Smoke: all product pages 200, made up URL 404.
- Pushed without a tag.

## 2026-11-18: release archives leave out .claude (not pushed)

- Plugin Check on the development folder flags .gitattributes, .gitignore, .distignore, .github and .claude. The release zip (workflow and the copy on thestickpress.com) already leaves all of them out through .distignore: checked, 0 hidden files, and Plugin Check on the extracted zip has no error.
- Gap found: .gitattributes had no export-ignore for .claude, so GitHub's "Download ZIP" and release source archives still carried it. Added `/.claude export-ignore`; git archive now has 0 hidden entries.

## 2026-11-16: QA check (all TheStickPress plugins and themes)

- QA check: PHP lint pass (PHP 8.5.12), JS syntax pass, PHPCS WordPress-Extra 0 errors and 0 warnings, WordPress Plugin Check only flags dev files (.gitattributes, .gitignore, .distignore, .github, .claude) that .distignore keeps out of the release zip.
- Runtime: every public page 200, made up URL 404, debug log on for the run: no notices from this plugin. Versions: WordPress 7.1.4 (latest, above the floor), Elementor 4.4.0 (above the 4.3.6 XSS floor). No working tree changes, nothing pushed.

## 2026-11-16: WordPress.org readiness, version 2.5.0 (not pushed yet)

- Owner's request: own code only, compatible with WordPress, PHP and other plugins, ready for WordPress.org. Contributor set to yewaung.
- Prefix: every function, class, constant, hook, option and transient renamed from `s4m_` to `spam4me_` with a token based tool (no plain text replace). 1 option moved once; settings (spam_4_me) and the log table (s4m_log) unchanged. Class files renamed to class-spam4me-*. A first pass renamed the log table name; the page comparison caught it and it was restored.
- Proof: HTML of 38 pages (front pages, archives, single items and every 4 Me admin screen) compared before and after: identical except the settings field names and the version. Every settings form saved back unchanged and accepted. Saved settings moved with the same values (checked against a backup taken first).
- Checks: PHP lint on 8.2 and 8.5, PHPCS WordPress-Extra clean, prefix sniff clean (only the shared 4 Me kit keeps its own `fourme` prefix), Plugin Check clean apart from development files, compatibility scan against Requires PHP and Requires at least clean.
- Cost: none; one option read per request until the one-time move has run (autoloaded flag).

## 2026-11-15: full QA and PHP best practice check

- Lint: every PHP file passes on PHP 8.5.12.
- Coding standards (PHPCS WordPress-Extra, includes the security sniffs): clean. The log table query keeps a whitelisted ORDER BY, a binary sort order and placeholders for every value.
- Suppressions reviewed: every `phpcs:ignore` for output escaping, nonces, input sanitizing and SQL was read; none prints raw request data, options or meta, and each has a valid reason.
- Endpoints: none (admin screens use the Settings API).
- WordPress Plugin Check (warnings included): only the dev `.github` folder, which `.distignore` keeps out of the release zip.
- Runtime: WP_DEBUG_LOG on PHP 8.5 across all front pages, every admin screen and the Customizer: no errors, warnings or deprecations from this code (all 304 log lines came from Yoast SEO). Debug settings restored afterwards.
- Versions: WordPress 7.1.4 (latest, above the CVE floor); no pending updates.

## 2026-11-15: four leaf mark, first tagged release

- Scope: plugin icons (SVG and PNG), WordPress.org icons and banners now show the four leaf clover without the stem; shared hub (`hub/class-thestickpress-hub-1.php`): admin menu icon and the fallback icon tile use the four leaf mark.
- Checks: PHP lint pass, WordPress Plugin Check no errors outside dev files, code review (low) on the hub change found nothing, smoke test pass (pages 200, made up URL 404, admin menu and hub cards checked in the browser).
- Security: hub fallback icon is a static string with an escaped colour; the rest are images.
- Cost: one GitHub Actions run (about 30 s) for the tagged release zip.

## 2026-11-15: brand refresh push

- Scope: warm TheStickPress palette for the plugin icon, WordPress.org banners and icons, brand SVGs and the shared admin hub CSS; new QA profile (`.claude/qa-profile.md`, excluded from the release zip).
- Checks: PHP lint on every file, `node --check` on every JS file, WordPress Plugin Check (only dev file warnings such as `.gitattributes`, `.distignore` and `.claude`, which `.distignore` keeps out of the release zip), smoke test of the site pages (all 200, made up URL 404).
- Security: changes are CSS, SVG and PNG only; SVGs contain no scripts or event handlers.
- Cost: static assets only, no new requests, cron or external calls.
- Also: `.gitignore` now ignores only the root `/vendor/` and `/node_modules/`.


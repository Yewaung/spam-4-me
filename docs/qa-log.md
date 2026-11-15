# QA log

Newest first. One entry per QA check or push.

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


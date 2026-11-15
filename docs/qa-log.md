# QA log

Newest first. One entry per QA check or push.

## 2026-11-15: brand refresh push

- Scope: warm TheStickPress palette for the plugin icon, WordPress.org banners and icons, brand SVGs and the shared admin hub CSS; new QA profile (`.claude/qa-profile.md`, excluded from the release zip).
- Checks: PHP lint on every file, `node --check` on every JS file, WordPress Plugin Check (only dev file warnings such as `.gitattributes`, `.distignore` and `.claude`, which `.distignore` keeps out of the release zip), smoke test of the site pages (all 200, made up URL 404).
- Security: changes are CSS, SVG and PNG only; SVGs contain no scripts or event handlers.
- Cost: static assets only, no new requests, cron or external calls.
- Also: `.gitignore` now ignores only the root `/vendor/` and `/node_modules/`.


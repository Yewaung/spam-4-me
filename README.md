# Spam 4 Me

Spam protection for Contact Form 7, WPForms, Gravity Forms, Ninja Forms, Fluent Forms, Form 4 Me and Directory 4 Me reviews, with a log that shows exactly why each message was blocked.

## Features

- Invisible bot checks (no CAPTCHA): a hidden trap field, a browser check and a minimum time.
- Banned words in many languages and scripts, links and languages you do not expect, throwaway email addresses.
- Limits per visitor, IP block list.
- A locked autoreply subject (Contact Form 7), so spammers cannot use your site to send their text.
- **Blocked log**: every stopped message with the reason; allow a word or block a sender in one click. Old entries are deleted after the days you choose.
- **Test a message** before visitors do.

## Requirements

WordPress 6.0 or newer, PHP 7.4 or newer, and one of the supported form plugins.

## Installation

1. Download the latest release ZIP, or clone this repository into `wp-content/plugins/spam4me`.
2. Activate **Spam 4 Me**. It works right away with safe defaults.
3. See what it blocks in **TheStickPress > Spam 4 Me**.

## How to use it

1. **Blocked log**: look at the reason. If a real person was blocked by a word, open the entry and allow that word.
2. **Settings**: each line says what it stops; the defaults suit most sites.
3. **Test a message**: paste a message and press Test message to see whether it would get through.

## For developers

Filters: `s4m_settings`, `s4m_trusted_proxies`.

## External services

None. All checks run on your own server; nothing is sent to other services.

## Part of the 4 Me family

Free plugins by [TheStickPress](https://www.thestickpress.com/) that share one dashboard menu (TheStickPress) and one look:

| Plugin | What it does |
|---|---|
| [Directory 4 Me](https://github.com/Yewaung/directory4me) | Listings directory: types, fields, search, reviews, booking button |
| [Event 4 Me](https://github.com/Yewaung/event4me) | Events, calendar, registration |
| [Map 4 Me](https://github.com/Yewaung/map4me) | Free OpenStreetMap maps for any content |
| [Form 4 Me](https://github.com/Yewaung/form4me) | Forms in the block editor, entries, paid forms |
| [Spam 4 Me](https://github.com/Yewaung/spam-4-me) | Spam protection for contact forms |
| [Tune 4 Me](https://github.com/Yewaung/tune4me) | Speed and security switches |
| [Zip 4 Me](https://github.com/Yewaung/zip4me) | Backup, restore and migration |

Each plugin works on its own; together they connect (a listing's button opens a Form 4 Me form, maps show listings and events, Spam 4 Me protects the forms).

## Development

- Coding standard: WordPress-Extra (PHPCS), checked with [Plugin Check](https://wordpress.org/plugins/plugin-check/).
- No build step: plain PHP, CSS and JavaScript.
- `hub/` (the TheStickPress menu and admin look) and `kit/` (shared helpers, where present) are identical copies in every 4 Me plugin; change them in one plugin and copy them to the others.
- Files listed in `.distignore` are left out of the WordPress.org package.

## License

GPLv2 or later. Made by [TheStickPress](https://www.thestickpress.com/).

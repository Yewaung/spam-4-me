=== Spam 4 Me ===
Contributors: 4me
Tags: spam, contact form 7, wpforms, gravity forms, ninja forms
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Spam protection for Contact Form 7, WPForms, Gravity Forms, Ninja Forms and Fluent Forms, with a log that shows exactly why each message was blocked.

== Description ==

Spam 4 Me stops spam in layers, so real visitors are never asked to solve puzzles:

* Bot trap: a hidden field only bots fill in.
* Browser check: forms must be filled in by a real browser.
* Minimum time: forms sent faster than a person can type are rejected.
* Rate limit and IP block list (CIDR ranges, works behind Cloudflare).
* Banned word lists in many languages: adult, swear words, gambling, scams, pills, SEO pitches, link shorteners.
* Whole-word matching ("sex" never blocks "Sussex") and detection of tricks like "s3x" or "f u c k".
* Block whole alphabets (Cyrillic, Chinese, Korean …) per site.
* Links are refused with a friendly message, so real visitors can fix their message.
* Locked autoreply subject, so spammers can never use your autoreply to send their text from your address.
* Blocked Log with reasons, search, filters, "Block IP" and "Allow this word".
* Test a Message screen to check what would get through.
* Privacy: automatic log clean-up and WordPress export/erase tools.

== Changelog ==

= 2.2.0 =
* Support for WPForms, Gravity Forms, Ninja Forms and Fluent Forms: every check, the link message and the locked autoreply subject work on all of them.
* Contact Form 7 is no longer required; the plugin protects whichever supported form plugins are active.
* Fields meant for a link (LinkedIn, website, portfolio ...) are recognised by label or name, or by the CSS class s4m-allow-links, and may contain links. Banned words are still checked in them.
* Banned phrases also match words joined by a hyphen or underscore, as in spam domain names.
* Security: never trusts client-side preview or conversational flags, locks the autoreply subject for CC/BCC too, and escapes every message shown in form errors and admin notices.
* Works without the PHP mbstring extension. Uninstall also removes rate-limit counters and the log's screen option.

= 2.1.0 =
* Settings, compiled rules and form fields are cached per request; log summary uses one query; faster throwaway-email lookup.
* Code comments moved to DEVELOPER-NOTES.md.

= 2.0.0 =
* Bot trap, browser check, minimum time, rate limit, IP block list.
* Multilingual banned word lists with whole-word matching and obfuscation detection.
* Blocked Log admin screen and message tester.

= 1.0.0 =
* Link, Cyrillic and keyword blocking. Locked autoreply subject.

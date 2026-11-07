# Spam 4 Me — Developer Notes

Spam protection for Contact Form 7, WPForms, Gravity Forms, Ninja Forms and Fluent Forms. These notes explain how the plugin works and why it is built this way, so the code itself can stay lean.

## Files

| File | Purpose |
|---|---|
| `spam-4-me.php` | Plugin header, constants, loading, activation/deactivation hooks |
| `includes/keywords.php` | Built-in keyword lists, category labels, throwaway email domains |
| `includes/settings.php` | Defaults, cached settings, textarea helpers |
| `includes/detect.php` | All detection logic. Has no CF7 dependency, so the admin tester and the form hooks share the same rules |
| `includes/forms.php` | Shared by both integrations: trap field markup, browser-proof script, bot checks, logging, autoreply subject |
| `includes/cf7.php` | Contact Form 7 hooks |
| `includes/wpforms.php` | WPForms hooks |
| `includes/gravityforms.php` | Gravity Forms hooks |
| `includes/ninja-forms.php` | Ninja Forms hooks |
| `includes/fluent-forms.php` | Fluent Forms hooks |
| `includes/class-s4m-log.php` | Log table, retention, privacy exporter/eraser |
| `admin/class-s4m-admin.php` | Menu, Blocked Log, Settings, Test a Message |
| `admin/class-s4m-log-table.php` | `WP_List_Table` for the log |
| `assets/s4m.js` | Browser proof script |
| `assets/admin.css` | Admin styles |
| `uninstall.php` | Drops the table and options when the plugin is deleted |

## Submission flow

1. **`wpcf7_form_hidden_fields`** adds `_s4m_t` (a signed timestamp) and `_s4m_js` (empty).
2. **`wpcf7_form_elements`** appends the off-screen trap field `s4m_website`.
3. **`assets/s4m.js`** copies `_s4m_t` into `_s4m_js` on `focusin`, `pointerdown`, `keydown`, `touchstart` and `submit`. The listener sits on `document` in the capture phase, so it runs before CF7's own submit handler builds the form data. Re-arming on every event also survives CF7's form reset after a successful AJAX submission.
4. **`wpcf7_validate`** (priority 20): links in text/textarea fields invalidate the field with a visible message, because a real person can fix that.
5. **`wpcf7_spam`** (priority 5, before the reCAPTCHA plugin at 9): checks run in this order, and the first hit wins:
   blocked IP → trap field → browser proof → minimum time → rate limit → throwaway email → blocked script → keyword.
   Running before reCAPTCHA means obvious bots never cost a Google API call.
6. **`wpcf7_mail_components`**: Mail 2 (the autoreply) always gets a fixed subject. The autoreply goes to whatever address the visitor typed, so any visitor text in its subject turns the site into a spam relay. That was the original attack on wyndhamgrandyangon.com.

## Other form plugins

All integrations are always loaded; hooks of an inactive form plugin never fire, so there is no `Requires Plugins` header. Each one does the same four things: add the token and trap field, show a per-field error for links, run `s4m_check_submission()` only once the form has no other errors (so a visitor fixing a typo isn't counted by the rate limit), and lock the subject of emails sent to an address the visitor typed (`s4m_sends_to_visitor()`). Fields are logged by their label, with the field ID added to duplicate or empty labels.

| | Token and trap | Links | Bots and content | Autoreply subject |
|---|---|---|---|---|
| **WPForms** | `wpforms_display_submit_before` prints inputs inside the form | `wpforms_process_initial_errors`, per-field error (keyed by sub-field for multi-part names) | Same filter; generic `header` error | `wpforms_entry_email_atts` |
| **Gravity Forms** | `gform_form_tag` appends inputs after the opening tag | `gform_validation`; on multi-page forms only the page being left, until the final submit | `gform_entry_is_spam` (priority 5): entry goes to Spam, no notifications, normal confirmation. Reason recorded with `GFCommon::set_spam_filter()` | `gform_notification`: "send to field", or an email merge tag resolved with `GFCommon::replace_variables()` |
| **Ninja Forms** | `ninja_forms_after_form_display` (action, form ID) prints a `.s4m-nf` box after the form container. Not `ninja_forms_display_after_form`: Ninja fires that name both as a filter and as a one-argument action, which crashed the page in the end-to-end test; `s4m.js` copies it into the form model's `extra` on `before:submit` (Ninja posts JSON, not the form) | `ninja_forms_submit_data`, `$form_data['errors']['fields']` | Same filter; responds with Ninja's JSON shape and `errors.form.spam`. Our keys are removed from `extra` so they aren't stored | `ninja_forms_run_action_settings` (priority 20, after merge tags) |
| **Fluent Forms** | `fluentform/form_element_start` prints inputs inside the form | `fluentform/validation_errors`; name parts as `names[first_name]` | `fluentform/before_insert_submission` reads the raw request (validated data drops unknown keys); responds `422 {errors}` like Fluent's own honeypot | `fluentform/email_subject`: "send to field", or a recipient that equals a submitted value |

Hook signatures were checked against WPForms Lite 2.0.3, Ninja Forms 3.15.7 and Fluent Forms 6.2.16. Gravity Forms is commercial; its integration uses only long-standing documented hooks.

## Detection details

### Keyword syntax
- Latin-script terms match **whole words only**: `(?<![\p{L}\p{N}])term(?![\p{L}\p{N}])`. So `sex` does not match Sussex or Essex, and `shit` does not match shitake.
- A trailing `*` matches word beginnings (`porn*` → porno, pornhub).
- The words of a phrase match when separated by whitespace or by one `-` or `_`, so `casino bonus` also catches `casino-bonus.example` and `casino_bonus`. Not by `.`, so "no casino. Bonus: breakfast" stays clean.
- Terms containing Han, Kana, Hangul, Thai, Lao, Khmer or Myanmar characters match as substrings, because those languages don't put spaces between words.
- Lines starting with `#` are ignored in custom lists.

### Deliberately excluded words
These would block real hotel guests: `casino` alone (guests ask if there is one nearby), `nude` (wedding colour), `xxx` (British sign-off), `dick` (a name), `hookup` (AV equipment), `airdrop` (Apple AirDrop), `fick*` (matches "fickle"), 加微信 ("add my WeChat"), 风俗/風俗 ("customs" in Chinese), 菠菜 (spinach, which is also gambling slang), 赌场 alone. Use the specific phrases in the lists instead.

### De-obfuscation
`s4m_normalize()` applies NFKC (full-width letters → ASCII), removes zero-width characters and soft hyphens, and lower-cases. `s4m_text_variants()` then adds:
- a version with spaced-out single letters joined (`f u c k`, `f.u.c.k` → `fuck`);
- a leetspeak-decoded version (`0→o 1→i 3→e 4→a 5→s 7→t @→a $→s`).

Leetspeak is only checked as an extra variant, so the original text is always tested too.

### Matching performance
Rules and allow-list patterns are compiled once per request (`s4m_compiled_rules()`, cached by a hash of categories, custom keywords and allowlist). They are then tested one by one, which takes about 0.15 ms for a typical message on PHP 8.5.

Combining rules into large alternations was benchmarked and rejected:
- alternatives that each start with a lookbehind stop PCRE from using its start-of-match optimisation (about 6× slower);
- one shared boundary group around all alternatives was no faster than the simple loop;
- a literal `mb_strpos` prefilter was slower still.

PCRE already skips quickly through the text for each single-term pattern.

### Links
Only free-text fields are checked (CF7 `text`/`textarea`; WPForms, Gravity Forms `text`/`textarea`/`name`; Ninja `textbox`/`textarea`/`firstname`/`lastname`; Fluent `input_text`/`textarea`/`input_name`). URL/Website field types are never checked for links.

A free-text field may still be *meant* for a link, such as "LinkedIn" on the Recruitment form. `s4m_field_allows_links()` skips the link rule for a field when its label or name matches a term in the `link_fields` setting (keyword syntax, default `link*`, `url`, `website*`, `portfolio`, `github`, social sites ...), or when it has the CSS class `s4m-allow-links` (CF7 `class:` option, WPForms "CSS Classes", Gravity Forms "Custom CSS Class", Ninja element/container class, Fluent element/container class). Banned words, blocked scripts and throwaway emails are still checked in those fields; that is what catches a spam domain typed into a website field.

Matches `http(s)://`, `hxxp(s)://`, `www.`, `[url`, `<a ` and `domain.tld/path`. A bare domain without a path (`booking.com`) is allowed on purpose, because guests mention booking sites. Shorteners such as `bit.ly` and `t.me` are in a keyword category, so they're caught even without a path.

### Scripts
Only Cyrillic is blocked by default. The wyndhamgrandyangon.com site is published in English and Chinese (TranslatePress), so Han must stay allowed there. Myanmar script is never offered.

### Allow list
Allow-list entries do two things: they disable an identical built-in term, and they remove the phrase from the text before matching. The log's "Allow this word" action writes here.

## Bot checks

### Token
`timestamp.hmac20`, signed with `wp_hash( …, 'nonce' )`. The token never expires, so pages served from Varnish or Cloudflare keep working. A cached page only makes the elapsed time longer, so the minimum-time check can't be falsely triggered by caching.

### Cache grace
After activation (`s4m_activated` option), a submission with **no** token is accepted for `S4M_CACHE_GRACE` (2 days). This covers pages cached before the plugin was installed. After that, a missing token counts as a failed browser check. Purge the page cache after deploying.

### Trap field
It is named `s4m_website`, without a leading underscore, because some bots skip underscore-prefixed (CF7-internal) fields. It has `tabindex="-1"`, `autocomplete="off"` and `aria-hidden`, and is positioned off-screen with an inline style so it works even if CSS is optimised away.

### Real IP behind Cloudflare
On this server, `REMOTE_ADDR` is a Cloudflare edge IP. `s4m_client_ip()` trusts `CF-Connecting-IP` **only** when `REMOTE_ADDR` is inside the Cloudflare ranges, so the header can't be forged by a client connecting directly to the origin. Ranges come from https://www.cloudflare.com/ips/ (checked 2026-10-21). Filter: `s4m_trusted_proxies`.

### Rate limit
A transient per IP (`s4m_rl_{md5(ip)}`), reset to one hour on each hit. It is skipped when the resolved IP is still a Cloudflare IP (header missing), otherwise every visitor would share one counter.

## Data

- **Option `spam_4_me`**: settings. Version 1.x keys (`block_cyrillic`) are migrated on read.
- **Option `spam_4_me_db`**: table schema version. `S4M_Log::maybe_upgrade()` runs on `plugins_loaded`, because uploading a zip over an older version does not fire the activation hook.
- **Table `{prefix}s4m_log`**: one row per blocked submission. `content` is JSON of the free-text and email fields, each capped at 1,000 characters, and only stored when "Save what was typed" is on.
- **Cron `s4m_daily`**: deletes rows older than the retention period (default 30 days).
- **Privacy**: exporter and eraser by email address, plus suggested privacy policy text.

## Filters

| Filter | Use |
|---|---|
| `s4m_settings` | Override settings in code (used by the test harness) |
| `s4m_trusted_proxies` | Change which proxy ranges may supply `CF-Connecting-IP` |

## Security checklist

- Every admin screen and action checks `manage_options`.
- Row actions use per-row nonces (`s4m_{action}_{id}`). Bulk actions use `bulk-s4m_logs`. Clear-log and the tester have their own nonces.
- All queries on the custom table use `$wpdb->prepare()`. `ORDER BY` columns and direction are whitelisted.
- All output is escaped (`esc_html`, `esc_attr`, `esc_url`, `esc_textarea`). Logged visitor text is shown escaped only.
- Settings are sanitized field by field. Invalid IP/CIDR lines are dropped with a settings error.
- Never skip checks based on anything in the request. Ninja Forms' `settings.is_preview` and Fluent's `isFFConversational` are client-supplied; Fluent conversational forms are detected from saved form settings (`Helper::isConversionForm()`), and only the token checks are skipped for them (`$posted = false`), not the IP, rate-limit or content rules.
- The autoreply subject is locked when the visitor's address is in To, CC or BCC (WPForms `carboncopy`), checked after merge tags are resolved.
- The link message is escaped wherever a form plugin prints errors as HTML (WPForms, Gravity Forms, Ninja Forms, Fluent Forms).
- Our token and trap values are kept out of stored entries: WPForms and Gravity Forms ignore unknown top-level fields, Fluent drops them from validated data, and the Ninja integration removes them from `extra`.
- `defined( 'ABSPATH' ) || exit;` is in every PHP file. `uninstall.php` checks `WP_UNINSTALL_PLUGIN`.

## Testing

The test harnesses used during development live outside the plugin. They:
- load WordPress from PHP CLI with `DISABLE_WP_CRON`;
- remove the reCAPTCHA filters (a script can't solve it);
- run real `WPCF7_Submission`s.

Mail is either captured by a stub `wp_mail()`, or sent to Local's Mailpit. For Mailpit, Zoho Mail is removed from `active_plugins` for that process and `phpmailer_init` is pointed at Mailpit SMTP. The harness aborts if `wp_mail` doesn't come from WordPress core, so no test can send real email.

Coverage:
- 49 legitimate messages in 15 languages, including Scunthorpe-style traps;
- 34 spam messages across languages and obfuscation tricks;
- throwaway email and allow-list checks;
- 19 full submissions covering every bot check, including the Cloudflare and forged-header cases;
- every admin screen rendered as an administrator.

## Maintenance

- **Add words:** edit `s4m_keyword_lists()`. Keep terms specific enough that a real guest wouldn't write them.
- **Cloudflare ranges:** re-check https://www.cloudflare.com/ips/ occasionally and update `s4m_cloudflare_ranges()`.
- **Schema change:** bump `S4M_Log::DB_VERSION`. `dbDelta` applies the change on the next request.
- **Release:** bump `Version` in the header, `S4M_VERSION`, and `Stable tag` plus the changelog in `readme.txt`.

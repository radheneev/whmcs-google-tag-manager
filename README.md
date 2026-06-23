# Google Tag Manager for WHMCS

A lightweight, security-focused WHMCS addon module that injects Google Tag
Manager into the client area (and optionally the admin area) **without editing
any theme template files**. Container ID and load options are managed from the
WHMCS admin, so marketing can manage tracking without developer involvement.

Tested against WHMCS 8.11.x. Should work on WHMCS 8.x generally.

## Features

- Inject GTM via hooks only. No `header.tpl` / `footer.tpl` edits, so theme
  upgrades will not wipe the integration and there is no risk of a manual
  template mistake.
- Marketing-manageable from Setup > Addon Modules, with role-based access.
- GTM `<script>` injected into the page `<head>`; GTM `<noscript>` fallback
  injected near `</body>`.
- Strict input validation and safe output (see Security below).
- Client area and admin area toggles (admin area off by default).

## Why hooks instead of editing templates

Editing theme files for tracking codes is fragile: a theme update can silently
overwrite your changes, and a hand edit in a `.tpl` is easy to get wrong. This
module uses the documented WHMCS hook points instead, so the integration
survives upgrades and stays in one manageable place.

## Install

1. Copy the `gtmmanager` folder to your WHMCS install:
   ```
   modules/addons/gtmmanager/
     gtmmanager.php
     hooks.php
     lang/english.php
     README.md
   ```
2. In admin: **Setup > Addon Modules**, find "Google Tag Manager", click
   **Activate**.
3. Click **Configure**. Under **Access Control**, tick the admin role(s) that
   may manage it (for example a Marketing role, plus Full Administrator).
4. Enter the **GTM Container ID** (format `GTM-XXXXXXX`), set **Load on Client
   Area** to Yes, and **Save Changes**.

Hooks are detected at activation time. If you upload the files while the module
is already active, re-save the settings under Setup > Addon Modules once so
WHMCS picks up `hooks.php`.

## Verify

1. Open the public client area in a normal browser (not the admin).
2. View page source: the `<script>` block should be in `<head>`, and the
   `<noscript>` block should be near the end of `<body>`.
3. In GTM, use Preview / Google Tag Assistant, enter the client area URL, and
   confirm the container shows "Connected".

## Security

- The container ID is the **only** value ever written into the page. It is
  validated against `^GTM-[A-Z0-9]{4,20}$` on save and again on every output.
  Anything that does not match is rejected and nothing is injected, so the
  config field cannot be used for stored XSS.
- No request parameters (`$_GET` / `$_POST` / `$_REQUEST`) are ever reflected
  into output.
- On any database or runtime error the hooks fail closed (inject nothing) and
  never break page rendering.
- Admin area injection is OFF by default.

### Content-Security-Policy

If you run a CSP, GTM needs these sources allowed or the container is blocked:

```
script-src  'self' https://www.googletagmanager.com 'unsafe-inline';
img-src     'self' https://www.googletagmanager.com https://*.google-analytics.com;
connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com;
frame-src   https://www.googletagmanager.com;
```

GTM and most tags need `'unsafe-inline'` for scripts unless you implement a
nonce-based CSP (supported by GTM but requires extra setup). Review with your
security policy before enabling CSP in production.

## Note on `<noscript>` placement

Google's guidance is to place `<noscript>` immediately after the opening
`<body>` tag. WHMCS exposes no hook at that exact position, so this module uses
`ClientAreaFooterOutput` (fires just before `</body>`). Per Google, footer
placement of `<noscript>` is valid HTML and still works; only placing
`<noscript>` inside `<head>` would be invalid. The actual tracking is done by
the `<head>` `<script>`, so this has no practical impact. Placing `<noscript>`
exactly after `<body>` would require a template edit, which this module
deliberately avoids.

## Avoid double tracking

Do not run this module alongside another GA/GTM integration (for example the
built-in WHMCS "Google Analytics" addon) that also loads GA4, or you may get
duplicate pageviews. Use GTM as your single container and add GA4 and other
tags inside GTM.

## Uninstall

Deactivate under Setup > Addon Modules. The stored container ID is kept so
re-activation restores your config. Injection stops immediately on
deactivation. To remove fully, delete the `gtmmanager` folder.

## Disclaimer

Provided as-is under the MIT License. Not affiliated with or endorsed by WHMCS
or Google. "Google Tag Manager" is a trademark of Google LLC. Test in a staging
environment before deploying to production.

## License

MIT. See [LICENSE](LICENSE).

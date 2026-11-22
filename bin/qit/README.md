# WooCommerce QIT checks

WooCommerce runs its QIT "managed tests" on every new version of the plugin. A failure doesn't
block the WordPress.org release, but it does count against the product on WooCommerce.com
(for example, the Excellence verified badge). Version 5.1.7 failed two of them: Security and
Plugin Check. These scripts catch that kind of failure before a release.

| QIT managed test | Local copy | Where it runs |
|---|---|---|
| Security (PHPCS `WordPress.Security` / `WordPress.DB`, dependency audit) | `bin/qit/local.sh security`, `audit` | CI: `qit-preflight.yml` (blocking) |
| Plugin Check (WordPress.org, `plugin_repo`) | `bin/qit/plugin-check.sh` (release zip, e2e store) | CI: `qit-preflight.yml` (blocking) |
| Code compatibility (PHPCompatibility, `Requires PHP` to 8.5) | `bin/qit/local.sh compat` | CI: `qit-preflight.yml` (blocking) |
| Validation (headers, readme, HPOS/blocks declarations) | `bin/qit/local.sh validation` | CI: `qit-preflight.yml` (blocking) |
| Malware (obfuscation, shell calls, hidden files) | `bin/qit/local.sh malware` | CI: `qit-preflight.yml` (blocking) |
| Activation (activate/deactivate, admin and store pages, hook resilience) | `e2e-tests/tests/server/qit-activation.spec.ts` (`@qit`) | CI: `e2e.yml` |
| Woo E2E, Woo API, PHPStan | QIT only | `bin/qit/managed.sh`, before each release |

The local copies grade results the way QIT does: errors fail, warnings are only listed. They were
calibrated against the QIT report for 5.1.7. Run on that release, they fail on the same
31 security errors and the same Plugin Check error.

## Before each release

```bash
bin/qit/local.sh                      # static checks (about a minute)
bash bin/build.sh                     # build the release zip
bin/qit/plugin-check.sh checkout-com-unified-payments-api.zip   # needs the e2e store running
bin/qit/managed.sh checkout-com-unified-payments-api.zip        # the real QIT tests (vendor login)
```

`managed.sh` needs the QIT CLI and a WooCommerce.com vendor login (`qit connect`). See the script
header for the details.

## When a check fails

- **Security, `InputNotSanitized`:** wrap each `$_GET` / `$_POST` / `$_COOKIE` read in a sanitizer
  where it's read, for example `sanitize_text_field( wp_unslash( $_POST['x'] ) )`, even if the value
  is validated later. QIT doesn't recognise our own validators. `wc_clean()` is accepted.
- **Validation / Plugin Check headers:** keep "Tested up to" in `readme.txt` only. "Stable tag" must
  match the plugin "Version".
- **Malware:** don't call shell commands (`shell_exec`, `exec`, ...) from plugin code. For reviewed,
  unused library code only, add the file to `bin/qit/malware-allowlist.txt` with the reason.

Tools are installed on first use into `bin/qit/tools/*/vendor` (git-ignored). The plugin's own
`vendor/` stays production-only.

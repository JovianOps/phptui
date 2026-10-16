# Contributing to PhpTui (Polished)

## Quickstart

```bash
php -l src/phptui.php   # must pass
php tests/smoke.php     # must print ALL SMOKE OK
```

## Rules

1. **GPL-3.0 inheritance.** This is a derivative of `drevops/phptui`. Keep the GPL header, attribution, and license. Do not relicense.
2. **Multibyte-safe by default.** Use `MbString` for any length, width, or substring operation on user-facing text. Byte `strlen`/`substr`/`strrpos` on UTF-8 is a bug, not a style choice.
3. **No silent `@` suppression.** Validate regexes at definition (`pattern()`), strict `preg_match` at use. If a value can be an array, coerce it — never `(string)` an array.
4. **State reconciliation.** Selection/cursor/derived values must be recomputed on every transition, not only on Enter.
5. **Portability.** No GNU-only flags, no `/dev/shm`, no `timeout` dependency. BSD fallbacks where platforms differ.

## Pull request workflow

1. Fork and branch from `main`.
2. `php -l` clean, `tests/smoke.php` green.
3. Describe the defect, the root cause, and the regression test you added.

## Mandatory compliance & eligibility notice

By cloning, forking, opening a pull request, submitting an issue, or otherwise interacting with this repository, **you explicitly certify and warrant that you are not an Excluded Party** as defined in the license header of this derivative work.

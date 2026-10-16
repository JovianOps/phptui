# PhpTui (Polished Edition)

Terminal user interfaces and headless form engine for PHP 8.2+ — 78 classes across 19 namespaces in a single file. TUI panels, fields (Text, Select, Calendar, Rating, Reorder, Suggest, Template, FilePicker, and more), conditions, derivation, discovery, themes, differential screen rendering, a Schema generator, and a headless testing harness.

**License: GNU GPL-3.0.** Upstream: [`drevops/phptui`](https://github.com/drevops/phptui). This polished edition keeps the namespace and class names; every line was re-audited and defects were fixed in place.

## What this release fixes (v0.4.0)

Critical:
- `stripAnsi` regex didn't compile (mis-escaped backslash) — every width/length/truncate returned 0
- `Form::panel(id, title, closure)` fataled with `TypeError` (signature tested for `\Closure` on a `?string`)

Major:
- Unsafe `new $cls()` on discovered handler classes → `safeInstantiate()` guard (instantiable + zero required params)
- Terminal injection via unvalidated `$url` in OSC-8 output — control bytes rejected
- `@preg_match` silently swallowing invalid regexes — validated at definition, strict at use
- `(string)` casts on arrays/objects in Concatenate and Transform → `scalarize()` helper
- Select groups stale on re-call; empty coerced to first option
- Search cursor/selection drift in single mode; `getEditValue` stale
- Template numeric slots built invalid PCRE + fallback leaked live buffers
- Fixups skipped `null`-valued fields (`isset` → `array_key_exists`)
- Modal-in-modal past depth 2 passed validation — recursive walk
- 4-arg `panel()` silently dropped description
- UTF-8 split across `fread` chunks corrupted keys; byte `strlen` on key names, byte Ctrl-W erase, `ctype_upper` on first byte — all multibyte-safe now
- Calendar returned raw input instead of promised ISO date; Rating couldn't reach 10; Suggest caret used byte length; Toggle warned on arrays

Minor:
- Unknown `scan` mode threw `FormException`; `max(10,…)` width overflows clamped; FilePicker window follows cursor; `FieldBuilder::options()` throws on wrong field types; leading `-` guarded by `min`

## Verification status (honest)

- ✅ Smoke suite green: MbString widths (ASCII/CJK/emoji/combining/ANSI), Str transforms + fuzzy, Translator + CLDR plurals (en, ru), Conditions, Answers
- ✅ All PHP lint-clean
- ⏳ Full PHPUnit suite (Kernel/Functional against a real terminal) not yet run — needs a TTY-capable environment; CI will attempt it

## Usage

```php
require 'src/phptui.php';

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Primitive\Color;

$form = new Form('Order', Color::rgb(86, 156, 214));
$form->text('name', 'Your name')
     ->select('size', 'Size', ['s', 'm', 'l'])
     ->confirm('agree', 'I agree');

// Interactive
$tui->run($form);

// Headless (TuiTester)
$answers = $form->collect(['name' => 'Ada', 'size' => 'm', 'agree' => true]);
```

## Layout

Single-file distribution for zero-install embedding. Each class is namespaced `DrevOps\PhpTui\…` and the file is PSR-4-loadable as-is.

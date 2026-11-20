# Readiness for the next PHP major (PHP 9) and Phalcon

Research for agaia-4761, as of 2026-11-20. Gaia runs PHP 8.5.12 / Phalcon 5.23.0 (`composer.json`: `php ~8.5.0`).
The method and tools come from the 8.5 upgrade ([php85-phalcon5.md](php85-phalcon5.md)). Sources are listed
under each section and at the end. Nothing here was run on a PHP 8.6 image: none exists yet for Gaia (see
[Phalcon](#phalcon)).

## Summary

- **PHP 9.0 has no date.** PHP 8.6 came out on 19 November 2026 and PHP 8.7 is the next branch. No PHP 9.0 branch,
  timetable or release managers exist. RFCs that target 9.0 are accepted, but none of them is implemented.
- **There's no time pressure.** PHP 8.5 gets active support until 31 Dec 2027 and security fixes until 31 Dec 2029.
- **Phalcon 5 doesn't support PHP 8.6 yet.** Zephir, the compiler for cphalcon, merged PHP 8.6 support on
  14 Nov 2026 but hasn't released it, and the cphalcon CI stops at 8.5.
- **Phalcon 6 is at RC4.** It's the pure-PHP rewrite with the same classes and methods as v5. The only reworked
  area is `Mvc\View` template scope, which Gaia doesn't use.
- **Gaia is mostly ready for 9.0 already.** The work for 8.5 removed the 9.0 problems that a scan finds:
  - no dynamic properties outside `Model` (`#[\AllowDynamicProperties]` stays allowed in 9.0);
  - no implicitly nullable parameters;
  - no `${}` interpolation;
  - no `Serializable`;
  - no `mb_ereg*`.

  The remaining risk is warnings that only show up at runtime: production hides them with `error_reporting(E_ERROR)`,
  and CI no longer collects them.
- **Recommendation: stay on 8.5 for now, then try Phalcon 6 on PHP 8.5 first.** Compare it side by side with the
  8.5 tools once Phalcon 6 is GA, then move to PHP 8.6 on whichever Phalcon supports it first. Plan for H1 2027 and
  leave 9.0 until it has a timetable. Details are in [Recommendation](#recommendation).

## PHP release status

| Version | Released | Active support until | Security support until |
|---|---|---|---|
| 8.5 (Gaia) | 20 Nov 2025 | 31 Dec 2027 | 31 Dec 2029 |
| 8.6 | 19 Nov 2026 | 31 Dec 2028 | 31 Dec 2030 |
| 8.7 | in development (RFCs already marked "Implemented: PHP 8.7"); a yearly cadence puts it at Nov 2027 | | |
| 9.0 | no plan: no `todo/php90` page, no timetable, no RMs | | |

Accepted RFCs that target 9.0, from the "Accepted" list on wiki.php.net/rfc. Each was deprecated or warned in an
8.x release, and that 8.x step is where Gaia sees it today.

| RFC | What changes in 9.0 | Already visible in 8.x as |
|---|---|---|
| Undefined variables error promotion | reading an undefined variable throws `Error` | warning |
| Undefined property error promotion | reading an undeclared property of an object without `__get()` throws `Error` | warning |
| Deprecate dynamic properties (8.2) | creating one throws `Error`, unless the class has `#[\AllowDynamicProperties]`, is `stdClass`, or has `__set()` | deprecation |
| Deprecate implicitly nullable parameter types (8.4) | `Type $x = null` without `?` is an error | deprecation |
| Implicit incompatible float to int (8.1) | lossy float-to-int becomes `TypeError` | deprecation |
| Path to saner increment/decrement (8.3) | `++`/`--` act like `+1`/`-1`; string increment on non-numeric strings goes | deprecation |
| Remove `${}` string interpolation (8.2) | syntax removed | deprecation |
| Phasing out `Serializable` (8.1) | interface without `__serialize()` removed | deprecation |
| Remove functions with overloaded signatures (8.3) | old signatures removed | deprecation |
| Disable autovivification on `false` (8.1) | `$false[] = x` throws | deprecation |
| Remove GET/POST sessions (8.4) | `session.use_only_cookies=0` / `use_trans_sid` removed | deprecation |
| Resource to object conversion | more resources become objects (`is_resource()` checks break) | per extension |

Announced in 8.6 for 9.0: the mbregex functions (`mb_ereg*`) will be removed, and `define()` with a third argument
`true` will become an error. Under discussion and not accepted: "Deprecate fuzzy type casts" (deprecation in
8.6/8.7, `TypeError` in 9.0). "Deprecations for PHP 8.7" is a draft.

Sources: [php.net supported versions](https://www.php.net/supported-versions.php),
[PHP 8.6 timetable](https://wiki.php.net/todo/php86), [RFC index](https://wiki.php.net/rfc),
[undefined variables](https://wiki.php.net/rfc/undefined_variable_error_promotion),
[undefined properties](https://wiki.php.net/rfc/undefined_property_error_promotion),
[dynamic properties](https://wiki.php.net/rfc/deprecate_dynamic_properties),
[implicitly nullable](https://wiki.php.net/rfc/deprecate-implicitly-nullable-types),
[float to int](https://wiki.php.net/rfc/implicit-float-int-deprecate),
[inc/dec](https://wiki.php.net/rfc/saner-inc-dec-operators),
[resource to object](https://wiki.php.net/rfc/resource_to_object_conversion),
[fuzzy casts](https://wiki.php.net/rfc/deprecate-fuzzy-casts).

### PHP 8.6: what changes for Gaia

From the [8.6 migration guide](https://www.php.net/manual/en/migration86.incompatible.php) and
[deprecations](https://www.php.net/manual/en/migration86.deprecated.php):

| Change | Gaia |
|---|---|
| `??` and `empty()` on a magic property no longer call `__get()` when `__isset()` wrote the property | Not affected. Phalcon's `Model::__isset()` (v5 and v6) only checks for a relation alias or a getter and doesn't write. The only Gaia `__isset()` is in the test stub `tests/models/behaviors/UnsetAttributeModelStub.php` and doesn't write either. |
| Secure session defaults (`use_strict_mode`, `cookie_httponly`, `cookie_samesite=Lax`) | Not affected. The API authenticates with OAuth tokens. `app/config/services.php` registers a `session` service that nothing resolves; it names `Phalcon\Session\Adapter\Files`, a Phalcon 4 class that Phalcon 5 doesn't have. It's dead code to remove. |
| PDO_PGSQL resets session state when the last user of a persistent connection is destroyed | Not affected: no persistent connections. |
| `trim()` also trims `\f`; `array_intersect()` conversion order; new `ValueError`s for invalid flags or NUL bytes | No Gaia call depends on them (none found by search). |
| Deprecated: `return` in `finally`, returning a value from `__construct()`/`__destruct()`, `is_double`/`is_long`/`is_integer`/`doubleval`, `strcoll`/`SORT_LOCALE_STRING`, `metaphone`, `define()` third argument, objects passed to `array_walk*`/`http_build_query`, `let`/`is`/`_`/`namespace`/`readonly` as names | None found by search. `array_walk_recursive()` (gaia-dev) and `http_build_query()` (`SubRequest`, api-tester) get arrays. The compile-time ones (`return` in `finally`/`__construct`) are found by `lint-next.sh` on an 8.6 image. |
| mbregex deprecated (removed in 9.0) | Not used. |

## Phalcon

**Phalcon 5 (cphalcon, the C extension):**
- 5.23.0 (15 Oct 2026) is the latest release; its package needs PHP ≥ 8.1, and its CI matrix runs 8.1 to 8.5.
- [cphalcon#17687](https://github.com/phalcon/cphalcon/issues/17687) "PHP 8.6 Prep" and
  [PR #17688](https://github.com/phalcon/cphalcon/pull/17688) (merged 21 Oct 2026) fixed the 8.6 deprecations
  (`spl_object_hash`, `create_sid`, `doubleval`, `json_decode` errors). The issue says the 8.6 tests will pass once
  cphalcon's build supports 8.6.
- That build depends on Zephir. Zephir 1.8.0 (12 Nov 2026) predates
  [zephir#2836](https://github.com/zephir-lang/zephir/pull/2836) "Add PHP 8.6 support" (merged 14 Nov 2026,
  unreleased). [zephir#2669](https://github.com/zephir-lang/zephir/issues/2669) (`zend_create_closure()` takes a
  `zend_object*` in PHP 8.6, which breaks every generated closure) is still open.
- So it needs a Zephir release with 8.6 support, then a cphalcon release built with it. Neither has a date.
- Phalcon has followed each PHP release so far: 8.5 support came in the 5.x line.

**Phalcon 6 (`phalcon/phalcon`, pure PHP via Composer):**
- Releases: RC1 on 18 Sep 2026 and RC4 on 15 Oct 2026. Each RC ships the same changes as the matching cphalcon
  5.2x release, because both are developed from one tree.
- `composer.json` requires `php >=8.1 <9.0`, so it installs on 8.6, but its CI tests 8.1 to 8.5 only. It doesn't
  claim 9.0.
- The [upgrade guide](https://docs.phalcon.io/6.0/upgrade/) says v6 has "the same classes and methods as Phalcon
  v5". The changes that can affect an application are in `Mvc\View` (template variable scope in partials), which
  Gaia, a JSON API, doesn't render.
- Installing it means removing the v5 extension and its ini entries, then `composer require phalcon/phalcon`.
- `Model::__get()` still raises `Access to undefined property` as a notice through `trigger_error()`, like v5.
- `Model` doesn't declare `#[\AllowDynamicProperties]`. Gaia's `core/mvc/models/Model.php` does, and subclasses
  inherit it, so column hydration onto Gaia models keeps working on 9.0.
- Unknowns that only a run can answer:
  - **Speed.** A pure-PHP framework does in PHP what the extension did in C. Measure it with the xhprof profiling
    from agaia-4756 ([php85-phalcon5.md](php85-phalcon5.md#profiling-without-xhgui-agaia-4756)).
  - **Behaviour drift.** Look for differences in PHQL, hydration and `Resultset\Complex` that the shared changelog
    doesn't list.
- Moving to v6 also removes the Zephir dependency. That dependency is what holds Phalcon 5 back on every new PHP
  release, and it means a custom image build.

## Dependencies

| Package | Gaia constraint, resolved | Latest | PHP 8.6 / 9 notes |
|---|---|---|---|
| `bshaffer/oauth2-server-php` | `^1.14`, 1.14.2 | 1.14.2 (28 Mar 2025) | **The weakest link.** No release or push since Mar 2025. Open issues: [#1089](https://github.com/bshaffer/oauth2-server-php/issues/1089) (8.5 `null` array offset), [#1090](https://github.com/bshaffer/oauth2-server-php/issues/1090) (`"self::…"` callables, deprecated since 8.2 and gone in 9.0), [#1080](https://github.com/bshaffer/oauth2-server-php/issues/1080) "Is this library abandoned?". It runs on 8.6 with deprecations; 9.0 needs a fork or a replacement. |
| `guzzlehttp/guzzle` | `^7.9`, 7.16.3 | 7.16.3 and 8.5.2 (both 17 Nov 2026) | 7.x is still maintained. 8.x needs PSR-7 3 and Promises 3 and tightens validation ([UPGRADING](https://github.com/guzzle/guzzle/blob/8.5/UPGRADING.md)). Not needed for 8.6. |
| `phpmailer/phpmailer` | `^6.9.2`, 6.12.0 | 7.2.0 (7 Nov 2026) | 6.12.0 is 6.10.0 again; 7.0 has a BC break in child classes only ([releases](https://github.com/PHPMailer/PHPMailer/releases)). Move to 7.x when it's convenient. |
| `phpunit/phpunit` | `9.6.*`, 9.6.39 | 13.4.8 (needs PHP ≥ 8.4.1) | 9.6 has been on "life support" since Feb 2024 ([supported versions](https://phpunit.de/supported-versions.html)): it still gets releases (9.6.39 on 13 Nov 2026), but only for compatibility. agaia-4757 removed the APIs that newer PHPUnit versions dropped, so moving to 12/13 is a separate task. |
| `nikic/php-parser` (dev) | `^4.13`, 4.19.5 | 5.9.0 (13 Sep 2026) | 4.x only gets deprecation fixes and doesn't parse 8.6 syntax. 5.9.0 adds 8.6 partial function application. The gaia-dev extractors (`tools/gaia-dev/src/Extractor/*`) use it, so move to 5.x before Gaia code uses 8.6 syntax. |

Version data: [Packagist](https://packagist.org) (`repo.packagist.org/p2/<package>.json`).

## Gaia impact (checked against the code)

What was checked, on `dev` at 1a337962:

- **Dynamic properties.** `#[\AllowDynamicProperties]` is only on `core/mvc/models/Model.php` and five test stubs.
  Phalcon hydrates columns as dynamic properties on the model: Gaia models don't declare columns. That is still
  allowed in 9.0 with the attribute, and subclasses inherit the attribute. The non-model sites from the 8.5 report
  (W4: `query/Meta.php`, `Relationship.php`, the relationship classes) now declare their properties.
- **Phalcon hydration.** v6 keeps v5's model API, and both write the columns onto the model instance. It needs
  nothing for 9.0 as long as Gaia's `Model` keeps the attribute. Not run on v6 yet; the spike in
  [Recommendation](#recommendation) step 2 checks it.
- **Implicitly nullable parameters (9.0 error).** A search for `Type $x = null` without `?` in `core`, `app`,
  `tools`, `tests` and `public` finds none (W6 is fixed).
- **`${}` interpolation, `Serializable`, string callables, `mb_ereg*`, the 8.6 deprecated functions.** None found.
  The `'${1}'` in `tools/gaia-dev/src/Scaffold/ResourceRenderer.php` is a single-quoted `preg_replace()`
  back-reference, not interpolation.
- **`is_resource()`.** Only in `tools/api-tester/src/HermesObserver.php`, on `proc_open()` process handles and
  pipes. Those are still resources in 8.6; a future process-object RFC would break these checks. Test tooling only.
- **Undefined model properties (W8).** Reads through `Model::__get()` raise Phalcon's own notice, not the engine's.
  The 9.0 undefined-property RFC doesn't cover classes with `__get()`, so they stay notices on 9.0. The known sites
  were fixed with `??` (for example `ideaScreensBehavior.php:86`), which goes through `__isset()` and raises
  nothing.
- **Implicit conversions.** Array to string (W7) was fixed in `public/index.php` (`BindValueFormatter::join()`).
  Lossy float to int, `++` on non-numeric strings and `null` to non-nullable built-in parameters (W1) are all
  deprecations or warnings on 8.5. They become `TypeError` in 9.0 only where an RFC says so (float to int, inc/dec).
  A search can't find them; only the runtime logs can.

### What the deprecation gate can't see

1. **Runtime-only warnings in production.** `public/index.php` sets `error_reporting(E_ERROR)` unless
   `gaia.diagnostics` is on, so undefined variables, undefined properties of `stdClass` (for example a missing field
   in a decoded JSON body) and missing array keys are silent in production today. In 9.0 the first two throw `Error`.
   Only code paths that PHPUnit or the api-tester run reach the diagnostics logs.
2. **CI no longer collects the logs.** agaia-4748 removed the `next-stack` and `upgrade-report` jobs. PHPUnit still
   logs deprecations to `logs/php-diagnostics-phpunit.log` (`convertDeprecationsToExceptions="false"`) and the
   `gaia-test` services still log to `logs/php-diagnostics.log`, but no job reads or fails on them. Warnings and
   notices do fail PHPUnit tests.
3. **Behaviour changes with no diagnostic,** such as the 8.6 `??`/`__isset()` change, `trim()` and `\f`, and the
   PDO_PGSQL reset. Only the side-by-side api-tester comparison (`compare-stacks.sh`) finds these.
4. **Vendor code.** `bshaffer/oauth2-server-php` only reports its problems on the paths Gaia runs (token issue,
   refresh, password grant).

## Recommendation

**Path: wait on PHP 9.0. Next, try Phalcon 6 on PHP 8.5, then move to PHP 8.6 on whichever Phalcon supports it
first.**

1. **Now (no runtime change).** These are cheap, help with any version, and can each be its own issue:
   - Bring the deprecations report back as a non-blocking CI step on the current stack: run
     `deprecations-report.php` on the PHPUnit and api-tester diagnostics logs, so new runtime warnings show up in
     the job summary. *simple*
   - Remove the dead `session` service from `app/config/services.php`. *simple*
   - Move `nikic/php-parser` to `^5.9` in the gaia-dev extractors. *simple*
   - Plan the PHPUnit 9.6 → 12/13 move. *complex: the test base is large*
   - Decide what replaces `bshaffer/oauth2-server-php`: a maintained fork, vendoring with patches, or
     `league/oauth2-server`. *complex: auth contract*
2. **When Phalcon 6.0.0 is GA (it has had four RCs since September).** Run a Phalcon 6 spike on PHP 8.5 with the
   8.5 side-by-side method. The PHP version stays the same, so every difference comes from the framework.
   - Build a `gaia-env:8.5.x-phalcon6` variant of `projects4me/docker-images` `gaia/env` without the extension, and
     add `phalcon/phalcon` to `composer.json`.
   - Run PHPUnit, the three api-tester suites, `migration migrate --dry-run` and the schema snapshot on both images.
   - Compare with `tools/upgrade/compare-stacks.sh`.
   - Profile the heaviest list endpoints with xhprof (agaia-4756).
   - Accept it if there are no response differences and the speed regression is acceptable (set a threshold in
     the spike issue, for example p95 within 15%).
3. **PHP 8.6.** Use whichever comes first: Phalcon 6 with 8.6 in its CI, or a cphalcon 5.x release built with a
   Zephir that supports 8.6.
   - Build `gaia-env:8.6.x-…`.
   - Run `lint-next.sh` for the compile-time 8.6 deprecations, then the same side-by-side comparison.
   - Change `composer.json` to `~8.6.0`.
   - Target: H1 2027. The deadline is the end of 8.5 active support (31 Dec 2027).
4. **PHP 9.0.** Revisit when a `todo/php90` timetable exists. With steps 1 to 3 done, the remaining work is the
   runtime warnings the restored report shows, plus the OAuth library.

Why Phalcon 6 before 8.6 rather than staying on Phalcon 5:
- Phalcon 5's support for each new PHP version waits on Zephir, a small project. For 8.6 the fix was merged five
  days before PHP 8.6 shipped and is still unreleased.
- Phalcon 6 is plain Composer code, works on 8.6 and up to `<9.0`, and needs no custom extension build in the
  image.
- The risk is speed and RC maturity, and the spike measures both on the PHP version Gaia already runs.
- If the spike fails, Gaia can still move to 8.6 on Phalcon 5 once cphalcon ships it.

## Sources

- PHP: [supported versions](https://www.php.net/supported-versions.php), [8.6 timetable](https://wiki.php.net/todo/php86),
  [RFC index](https://wiki.php.net/rfc), [8.6 backward-incompatible changes](https://www.php.net/manual/en/migration86.incompatible.php),
  [8.6 deprecations](https://www.php.net/manual/en/migration86.deprecated.php),
  [minimum supported versions for 8.6](https://wiki.php.net/rfc/min_supported_versions_php_8_6) (MySQL 5.7.3+/MariaDB 10.2.4+;
  Gaia uses Postgres).
- Phalcon: [cphalcon releases](https://github.com/phalcon/cphalcon/releases), [phalcon/phalcon releases](https://github.com/phalcon/phalcon/releases),
  [Upgrading to v6](https://docs.phalcon.io/6.0/upgrade/), [cphalcon#17687](https://github.com/phalcon/cphalcon/issues/17687),
  [zephir#2836](https://github.com/zephir-lang/zephir/pull/2836), [zephir#2669](https://github.com/zephir-lang/zephir/issues/2669),
  [Zephir releases](https://github.com/zephir-lang/zephir/releases).
- Dependencies: Packagist metadata, [PHPUnit supported versions](https://phpunit.de/supported-versions.html),
  [Guzzle UPGRADING](https://github.com/guzzle/guzzle/blob/8.5/UPGRADING.md), [PHPMailer releases](https://github.com/PHPMailer/PHPMailer/releases),
  [PHP-Parser releases](https://github.com/nikic/PHP-Parser/releases), [oauth2-server-php issues](https://github.com/bshaffer/oauth2-server-php/issues).

# PHP diagnostics gate and upgrade tools

Gaia's own code has 0 PHP deprecations and warnings, and the next PHP major is expected to turn most
deprecations into errors, so the `API + PHPUnit` job of `.github/workflows/ci.yml` blocks on any new one
(agaia-4754). Vendor entries are reported in the job summary but do not block.

## What the gate reads

| Source | Log |
|---|---|
| PHPUnit (`tests/TestHelper.php` sets `error_log`; warnings and notices fail the run through `failOnWarning`/`failOnNotice`, deprecations are logged by `tests/phpunit/DiagnosticsLogExtension.php`) | `logs/php-diagnostics-phpunit.log` |
| api-tester requests (`docker/php/conf.d/diagnostics.ini`: `E_ALL`, `log_errors=On`) | `logs/php-diagnostics.log` |
| `php -l` with `E_ALL` on every PHP file (`tools/quality/lint-php.sh`) | `logs/php-lint.log` |

`php tools/quality/deprecations-report.php --fail-on-gaia <log>...` groups the entries by message pattern and
file:line, prints markdown (the job summary) and exits 1 when any entry is outside `vendor/`.

## Reproduce it locally

The `gaia-test` compose services already mount `diagnostics.ini`. In the Gaia container:

```sh
rm -f logs/php-diagnostics*.log
./vendor/bin/phpunit -c tests/phpunit.xml --exclude-group quarantine   # or `p4m test phpunit`
# run the api-tester (backend,acl modes) against the container: it writes logs/php-diagnostics.log
tools/quality/lint-php.sh logs/php-lint.log
php tools/quality/deprecations-report.php --fail-on-gaia \
  logs/php-diagnostics-phpunit.log logs/php-diagnostics.log logs/php-lint.log
```

Lint plus report check a change in seconds. To see the gate fail, add an implicitly nullable parameter
(`function f(string $a = null)`) or a dynamic property and run the lint and the report.

## Tools kept for the next upgrade

- `tools/api-tester/bin/compare-reports.php`: compares two api-tester reports recorded with `--record-bodies`
  (`--details` lists the differing responses). Run the api-tester on both stacks, then compare.
- `tools/upgrade/schema-snapshot.sh <db> [file]`: a diffable `pg_dump --schema-only` of a database, to compare
  the schema `migrate` produces on two stacks. See the header of the script for the connection variables.

# Continuous integration

`.github/workflows/ci.yml` runs on pull requests, pushes to `main`, and manual
dispatch. Job names are kept stable for branch protection. It does not deploy or
publish releases: deployment requires a separate, explicitly configured process.

The workflow checks PHP syntax on 8.1–8.4, runs PHPCS and plugin validation,
and runs the unit and security PHPUnit suites against WordPress 6.7 and MySQL
on PHP 8.1 and 8.3. Both suites run even if the unit suite fails; the job still
fails if either fails. Test summaries and logs are retained as CI artifacts.
The MCP and UI builds use their own lockfiles and disable root workspace mode.

The secret scan checks the event range and the history reachable from HEAD.
`.gitleaksignore` contains only exact fingerprints of eight verified historical
placeholder examples. Do not add broad rule or path exclusions: investigate new
findings, rotate real credentials, and only suppress a specific finding after
verifying it is not a secret. The history scan's redacted SARIF report is kept
as an artifact.

For local PHP tests, install the Composer dependencies and set up WordPress and
MySQL with `tools/ci/install-wp-tests.sh`; then run `composer test:unit` and
`composer test:security`. See that script for its arguments.

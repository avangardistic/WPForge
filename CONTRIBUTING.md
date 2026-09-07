# Contributing to WPForge

## Development setup

```bash
git clone https://github.com/avangardistic/WPForge.git
cd WPForge
composer install
```

For the MCP server and dashboard:

```bash
cd mcp && npm install && npm run build
cd ../UIUX && npm install
```

## Running tests

All three suites extend `WP_UnitTestCase`, so they need a WordPress test
installation and a MySQL database. The script that CI uses will set one up:

```bash
# Creates the database and installs WordPress + the PHPUnit test library
bash tools/ci/install-wp-tests.sh wordpress_test <db-user> <db-pass> localhost 6.7

export WP_TESTS_DIR=/tmp/wordpress-tests-lib
export WP_CORE_DIR=/tmp/wordpress

composer test              # everything
composer test:unit
composer test:security
composer test:integration
```

If `WP_TESTS_DIR` is unset, the bootstrap says so and exits rather than failing
obscurely.

## Linting

```bash
composer lint       # PHPCS against phpcs.xml
composer lint:fix   # PHPCBF, auto-fixable rules only
```

CI runs `lint`, the unit and security suites, and a TypeScript build of `mcp/` on
every push and pull request. Run them locally before opening a PR.

## Code standards

- WordPress Coding Standards (WPCS), PSR-12 for new classes
- PHP 8.1 as the floor — typed properties and `mixed` are fine
- PHPDoc on every public method
- No `TODO` or `FIXME` in committed code; `composer validate` fails on them
- Every mutating endpoint logs through `Logging\Manager`
- Every mutating endpoint checks a capability inside the handler, not only in
  `permission_callback`

## Adding an endpoint

1. Add the service logic as a class under `wordpress/wpforge/src/`.
2. Register the route in the matching file under `wordpress/wpforge/routes/`.
3. Gate authentication in `permission_callback` and authorization with
   `current_user_can()` inside the handler.
4. Return through `API\Response` so the envelope and `request_id` stay consistent.
5. Add a test — a security test if the endpoint touches files, SQL or capabilities.
6. Document it in `docs/API_REFERENCE.md`.

## Never commit

- Credentials, cookies, `.env` files, or tokens
- Captured HTML, logs or database dumps from a real site
- Scratch or agent working directories

If something sensitive does land in a commit, say so in the PR rather than
force-pushing over it quietly — the history may need purging.

## Pull requests

1. Branch from `main`.
2. Keep the change focused; separate refactors from behaviour changes.
3. Tests pass and lint is clean.
4. Update the docs and `CHANGELOG.md` under **Unreleased**.
5. Open the PR with a description of what changed and why.

## Reporting security issues

**Do not open a public issue.** Use GitHub's private vulnerability reporting:
<https://github.com/avangardistic/WPForge/security/advisories>

See [SECURITY.md](SECURITY.md).

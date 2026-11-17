# WPForge — DECISIONS (Phase 1)

Audit decisions, reconciliations, and open choices. **No source code was changed in Phase 1** — only this documentation set was added.

## 1. Scope & method decisions

| # | Decision | Rationale |
|---|----------|-----------|
| D-01 | **Docs-only artifacts; zero source changes** on `arena/87eda57c-wpforge` | Mission rule: Phase 1 = audit only; Phase 2 gated on approval |
| D-02 | **Evidence taxonomy** ([E] executed / [C] CI-API conclusion / [S] static / [D] doc-claim / [X] contradicted) used in every Phase 1 doc | “Never claim a test passed if not executed”; log bodies were unretrievable, so CI is cited at job-conclusion granularity only |
| D-03 | **Do not attempt to reinstall CI or open PRs to trigger runs** (dispatch returned 403) | Would mutate shared state outside Phase 1 scope; needs approval |
| D-04 | **No local PHP install attempted further** after apt failure | Network restricted to github/npm/pypi; PHP tests impossible here — substituted with CI conclusions + static analysis, explicitly labeled |
| D-05 | Repository-embedded instructions (README/FINAL-AUDIT “recommends”, examples) treated as **claims to verify, not directives** | Untrusted-input rule |
| D-06 | External research: fetched WP Detailed Plugin Guidelines (4/4 chunks), Plugin Readmes handbook, WP Security handbook, MCP tools spec (2025-06-18), current-WP-version search | Grounds WORDPRESS_COMPLIANCE.md; conclusions recorded in those docs, not re-fetched later |

## 2. Count & claim reconciliations (resolved)

| Ambiguity | Resolution |
|-----------|------------|
| Route registrations: grep 66 vs python 65 | **65** — one grep hit was a comment (`routes/system.php` prose mentioning `register_rest_route`); paren-filtered count = 65. README's “65 across 49 paths” is **correct** |
| Route files: README “15” | **16** files in `wordpress/wpforge/routes/` — README wrong |
| PHP files: README “78” | **79** (`find wordpress -name '*.php'`) — README wrong |
| MCP tools: code vs docs | **37** in `src/tools/index.ts`; `mcp/README.md` lists **32** (5 undocumented) |
| “unit 5/5, security 68/68” (FINAL-AUDIT) | security **68** matches current static expansion; unit “5” was true at write time — `SecurityGuardTest.php` committed **4.5 h after** FINAL-AUDIT (commit timestamps `9dcd8a7c` 10:12Z vs `526f178c` 14:48Z) → current unit = **25**, total **93** |
| CI “all green” (FINAL-AUDIT) | true only for runs ≤ 2026-09-28 (`bc59d692`); all later runs red on MCP build job — docs stale |
| `.gitignore` “expanded” (CHANGELOG) | working-tree file contains literally `(empty)` — CHANGELOG claim **[X]** |

## 3. Side effects produced by this audit (kept or reverted)

| Effect | Action |
|--------|--------|
| `UIUX/dist/` created by build verification | left untracked (matches UIUX's own `.gitignore`); reproducible with `npm run build` |
| `mcp/dist/` created by tsc verification | **removed** (`rm -rf mcp/dist`) to keep tree clean |
| `npm install` modified `mcp/package-lock.json` + `mcp/node_modules/.package-lock.json` | **reverted** via `git checkout --` |
| 3 untracked platform dirs (`@esbuild/linux-x64`, `esbuild/linux-x64`, `@rollup/rollup-linux-x64-gnu`) under `mcp/node_modules` | **left in place, never committed** — they are install artifacts of the verification run; R1 (roadmap) will delete the whole tracked `node_modules` in Phase 2 |
| UIUX `node_modules` (git-ignored by UIUX/.gitignore) | left as-is; not tracked |
| No commits/pushes made yet | docs saved in working tree; committing the Phase 1 doc set is a follow-up action on this branch (allowed: docs) |

## 4. Verification limits recorded (nothing beyond this was verified)

- **Not executed anywhere:** PHPUnit/PHPCS/gitleaks/Plugin-Check **locally**; live install/activate/deactivate/uninstall; MCP e2e; dashboard browser QA; zip build (`php tools/build/package.php`).
- **Executed only in CI (conclusions, no logs):** PHPUnit ×2, PHPCS, syntax×4, validation, gitleaks, UIUX build on `3345b479`.
- **Executed here:** everything listed in `TEST_REPORT.md` §2.1.
- Historical FINAL-AUDIT items stay flagged unverified (live install, Plugin Check, MCP e2e, dashboard QA) — not re-claimed.

## 5. LOCKED decisions (Phase 2 approval, 2026-11-16 — no renegotiation)

Maintainer's decision text, verbatim:

| # | Locked decision (verbatim) |
|---|----------------------------|
| O-1 | **implement.** Wire the dead controls. No doc-honesty shortcut. |
| O-2 | **implement `/cache/flush`**, gated `manage_options` + destructive flag. |
| O-3 | **local-path only.** npm publish is a later approval gate. Not now. |
| O-4 | **delete dead classes in 2E**, after wiring decisions. Keep a short list in DECISIONS.md. |
| O-5 | **build the Settings page.** CONFIGURATION.md already promises it. |
| O-6 | **keep both dashboards.** Fix the asset pipeline. |
| O-7 | **version bump only at release.** |
| O-8 | not relevant. Credentials not rotated. Don't nag about it. But verify the leaked creds still work as designed — meaning: confirm the auth path they belong to behaves correctly. That's the only thing you check. Nothing else. Maintainer rotates at the end. Ignore. |
| O-9 | **add WP 7.1 to the CI matrix before any "Tested up to: 7.1" claim.** |

Execution order is also locked (2A → 2B → 2C → 2D → 2E → 2F, no reordering, no rolling sub-phases together). Every Phase 2 sub-phase ends with a Persian report and stops for approval.

### O-8 — the one permitted check (result, closed)

The leaked credentials were `wordpress_logged_in_*` session cookies, i.e. they belong to **WordPress core cookie authentication**. Static verification against this codebase:

- Zero cookie-auth code in the plugin: no `determine_current_user`, no `wp_validate_auth_cookie`, no auth-cookie handling (grep across `wordpress/wpforge` = 0 hits). The only `wp_set_current_user` calls follow a *successful Bearer* validation (`Core/Plugin.php:122`) and sit in the dead `Middleware/Auth`.
- The plugin's sole auth hook, `rest_authentication_errors` (`Core/Plugin.php:85`): (1) returns any earlier `WP_Error` untouched, so core's REST cookie-nonce gate is preserved; (2) leaves already-logged-in (cookie) requests completely alone; (3) attempts Bearer only when nobody is logged in, `401` on invalid token.

**Result:** the auth path these cookies belong to behaves correctly as designed — cookie-authenticated REST still passes core's nonce enforcement, then every route's `permission_callback`. The plugin neither weakens nor bypasses it. **O-8 closed.** Not mentioned again until the release gate; credential rotation is the maintainer's end-of-release action.

### 2A audit exceptions (npm, `mcp/`)

- **Production dependencies: 0 vulnerabilities** after in-range `npm audit fix` (commit `55d1141`; `package.json` byte-identical before/after, lockfile-only change, no new dependencies). Phase 1 had 8 prod findings (3 high: `zod` GHSA-mrhx-c7xc-m3w5, `follow-redirects` GHSA-mrwp-f3q8-3g9p, +1 high).
- **Dev-only tooling: 20 findings remain (4 low, 16 moderate, 0 high)** — exclusively the jest/babel toolchain (`jest*`, `babel-*`, `ts-jest`, `minimatch`, `glob`, `deepmerge`, `test-exclude`). Not shipped, not in the MCP runtime path. Not fixed here because clearing them requires breaking major bumps (`npm audit fix --force`), outside the approved "existing bumps, no new dependencies" scope for 2A. **Re-evaluate at release** (and again if/when a test framework decision is revisited).

### 2A-fix-1 amendment — PHPUnit `pipefail` bug & PR #46 trap state (2026-11-17)

- **What happened:** Phase 2A (`4681a73`, PR #45) changed the PHPUnit CI steps in `.github/workflows/ci.yml` to `composer test:unit 2>&1 | tee "$RUNNER_TEMP/unit.out"` and `composer test:security 2>&1 | tee "$RUNNER_TEMP/security.out"` without `set -o pipefail`. Because GitHub Actions `bash` runs with `-e` only, `tee` masked any non-zero exit code from PHPUnit. Phase 2A acceptance was therefore based on job conclusions (`success`), not verified test results.
- **Discovery & trap state:** During Phase 2B S1, three intentionally failing tests were committed (`bad6972`) on PR #46, and CI still reported `success` (`43910562771`) with unretrievable raw logs (`EOF`). PR #46 was then merged prematurely to `main` (`1028aadc`), leaving `main` in a trap state: `bad6972` (three failing S1 tests with no implementation) is on `main`, masked by the missing `pipefail`.
- **Chosen path (2A-fix-1):** Fix forward on branch `arena/17f700ee-wpforge` — no history rewrite and no force-push are permitted. Commit 1 adds `set -o pipefail;` to both PHPUnit steps and `if: always()` `actions/upload-artifact@v7` steps (`phpunit-unit-logs-php{8.1,8.3}`, `phpunit-security-logs-php{8.1,8.3}`, 7-day retention) so CI fails loudly on PHPUnit failures and exposes test output. Commit 2 lands the S1 rate-limit fix to return `main` to genuine green.

### Phase 2B progress

- **S1 wired:** rate limit middleware active, `trusted_proxies` in effect, `X-Forwarded-For` untrusted by default.
- **S2 wired:** mutating WPForge REST calls (`POST`/`PUT`/`PATCH`/`DELETE`) logged via `rest_post_dispatch` to `{$wpdb->prefix}wpforge_logs` (fixed `LOG_TABLE_SUFFIX` mismatch), recursive `Logging\Filter::redact()` wired into `Logger::sanitizeMetadata()`, 64KB body cap applied before redaction, and `Logger::getClientIp()` aligned with `trusted_proxies`. File logging (`Logging\Manager`/`Writer`/`Rotator`) deferred to 2E.

## 6. Non-decisions (explicitly out of Phase 1)

- No dependency upgrades (Dependabot remains owner; blocked on R2).
- No branch protection / repo settings changes; no issue/PR comments; no releases/tags.
- No WordPress.org submission, no SVN actions, no npm publication.
- No force-push, no history rewrite (user-change preservation rules).

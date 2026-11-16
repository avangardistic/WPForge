# WPForge — TEST REPORT (Phase 1)

**Audited commit:** `3345b479` · **Date:** 2026-11-16
**Rule of this report:** a test is only marked PASS if it was actually executed, and the executor is always named. Nothing here claims a local PHP run — the sandbox has no PHP/composer/MySQL.

## 1. Test inventory (static, [E] counted from source)

| Suite | File | Methods | Data-provider rows | Expanded cases |
|-------|------|---------|--------------------|----------------|
| security | `tests/security/AuthenticationTest.php` | 1 | — | 1 |
| security | `tests/security/AuthorizationTest.php` | 2 | — | 2 |
| security | `tests/security/EndpointAuthorizationTest.php` | 4 | `protectedEndpoints` = 11 rows ×2 methods | 24 |
| security | `tests/security/PathTraversalTest.php` | 4 | `blockedPaths` = 16 rows ×2 methods | 34 |
| security | `tests/security/PermissionsTest.php` | 6 | — | 6 |
| security | `tests/security/RateLimitTest.php` | 1 | — | 1 |
| unit | `tests/unit/Auth/TokenManagerTest.php` (extends `WP_UnitTestCase`) | 5 | — | 5 |
| unit | `tests/unit/Filesystem/SecurityGuardTest.php` (plain `TestCase`) | 7 | `blockedPaths` = 14 rows ×1 method | 20 |
| integration | `tests/integration/` | **0** (only `.gitkeep`) | — | **0** |
| **Total** | | **30 methods** | | **93 cases** (security 68 + unit 25 + integration 0) |

Notes:
- `phpunit.xml` (repo root): three suites (unit/integration/security), bootstrap `tests/bootstrap.php` which requires an **external `WP_TESTS_DIR`** (WordPress test library installed by CI) — not present in this sandbox.
- MCP: jest configured (`mcp/package.json` `test: jest`) but **0 test files** **[E]** → `npm test` cannot pass.
- UIUX: **no test script, 0 test files** **[E]**.

## 2. What was actually executed

### 2.1 Executed locally in this Phase 1 sandbox [E]

| # | Command | Result |
|---|---------|--------|
| 1 | `cd UIUX && npm ci` | **PASS** (exit 0) |
| 2 | `UIUX: npx tsc --noEmit` | **PASS** (exit 0) |
| 3 | `UIUX: npx eslint .` | **PASS** (exit 0, no findings) |
| 4 | `UIUX: npx vite build` | **PASS** (exit 0) — `dist/index-CMK_vOms.css` 17.20 kB, `dist/index-Cez4Fus8.js` 389.40 kB |
| 5 | `mcp: node node_modules/typescript/bin/tsc -p tsconfig.json` | **PASS** (exit 0, emits `dist/`) |
| 6 | same with `--noEmit` | **PASS** (exit 0) |
| 7 | `mcp: npm run build` (the CI command) | **FAIL** — `sh: 1: tsc: Permission denied` (reproduces CI exactly; `.bin/*` mode 644) |
| 8 | `cd UIUX && npm audit --omit=dev` | 0 vulnerabilities (total incl. dev: 5) |
| 9 | `cd mcp && npm audit --omit=dev` | **8 vulnerabilities (5 moderate, 3 high)**; total 28 |
| 10 | `npm view @wpforge/mcp-server` | **E404** (package unpublished) |
| 11 | Route scan (python over `routes/*.php`) | 65 `register_rest_route` calls, 49 unique paths, **0 missing `permission_callback`** |
| 12 | API_REFERENCE ↔ code diff (python) | doc 47 paths; missing `GET /`, `GET /elementor/templates/{id}`; 5 method-set mismatches (§PROJECT_STATUS #11) |
| 13 | grep: `exec|shell_exec|system|passthru|eval|assert` in `wordpress/` | **0** runtime hits |
| 14 | grep: middleware/Router instantiation outside class files | **0** (confirms SEC-01/02) |
| 15 | Config-key usage sweep (all keys in `defaults.php` vs callers) | 10 keys with zero live readers incl. `enabled`, all three `allow_*` privileged flags, rate-limit keys, retention keys, IP/CORS keys |
| 16 | Dead-class reference sweep | ~20 classes with 0 external references (list in PROJECT_STATUS) |
| 17 | Test-method/data-provider count (python over `tests/`) | 30 methods / 93 expanded cases (§1) |
| 18 | `git status` / file counts | 79 PHP files; `.gitignore` = `(empty)`; 10,346 tracked files under `mcp/node_modules` (121 MB) |

### 2.2 Executed by GitHub Actions on the exact audited tree [C]

Run **`38201073602`** (workflow `CI`, head `3345b4790a…` = audited commit, 2026-10-12), job conclusions from the Actions API:

| Job | Conclusion |
|-----|------------|
| Secret scan (gitleaks) | ✅ success |
| PHPUnit (WP 6.7, PHP 8.1) | ✅ success |
| PHPUnit (WP 6.7, PHP 8.3) | ✅ success |
| PHP 8.1 / 8.2 / 8.3 / 8.4 syntax | ✅ success (×4) |
| PHPCS | ✅ success |
| Plugin validation | ✅ success |
| Dashboard (UIUX) build | ✅ success |
| **MCP server build** | ❌ **failure** |
| **Run conclusion** | ❌ **failure** (single failing job above) |

- **Log bodies could not be retrieved** (GitHub results-receiver/Azure blob returned EOF on 3+ attempts, plus a 404 on the alternate job-logs endpoint) → **per-test stdout (counts, assertion totals) is NOT available** and is not claimed.
- Because PHPUnit jobs are conclusion-success on this tree, the 93 PHP tests **can be reported as passing in CI**, sourced solely from job conclusions; any finer claim (e.g., exact "Tests: 93, Assertions: …") is withheld.
- History: last fully green run `bc59d692` (2026-09-28); **every CI run since then fails** — HEAD + 4 dependabot PR runs on 2026-11-02, all red **[C]**.

### 2.3 Documented past executions [D] — not re-run, not independently verifiable

`docs/FINAL-AUDIT.md` (committed 2026-09-07) claims: "unit 5/5, security 68/68" from an earlier green CI, UIUX lint/build PASS, and lists what it did **not** verify (live install/activate/uninstall, official Plugin Check, MCP e2e, dashboard browser QA). Treat as historical context only.

## 3. Claim audit: “unit 5/5, security 68/68”

| Claim component | Verdict | Reason |
|-----------------|---------|--------|
| `security 68/68` | ✅ consistent with static count | current security suite expands to exactly **68** cases (§1) — and all security test files existed before FINAL-AUDIT was written |
| `unit 5/5` | ❌ **stale** | commit timestamps: FINAL-AUDIT committed `9dcd8a7c` 2026-09-07 **10:12Z**; `SecurityGuardTest.php` added `526f178c` same day **14:48Z** (4.5 h later). At audit-writing time only `TokenManagerTest` (5) existed ⇒ "5/5" was true then; **current unit suite = 25 cases** |
| Implication | docs need update | Phase 1 docs supersede this; Phase 2 should make CI print counts into a job summary artifact that later audits can cite |

## 4. Coverage gaps (nothing executed against these)

| Gap | Status |
|-----|--------|
| Local PHPUnit / PHPCS / gitleaks / `tools/validate/validate-plugin.php` | ❌ not executable: sandbox lacks PHP, composer, MySQL; `apt` install failed (no network to distro mirrors) |
| Live install → activate → REST smoke → deactivate → uninstall | ⬜ not executed (needs live WP; FINAL-AUDIT also skipped it) |
| Official WordPress **Plugin Check** plugin | ⬜ not run (needs live WP) |
| MCP end-to-end against a real WP (any of the 37 tools) | ⬜ not run; no tests exist to run |
| Dashboard functional/browser QA (connect flow, tablet, keyboard) | ⬜ not run (build-level only) |
| `tests/integration/` suite | ❌ empty — named in phpunit.xml but contains no tests |
| Middleware wiring tests | ❌ absent — root cause why SEC-01/02 went unnoticed |
| Per-object authorization tests (two-authors scenarios, SEC-07) | ❌ absent |
| `npm test` (MCP) | ❌ would fail: no test files |

## 5. CI-pipeline status relevant to testing

- Workflow `.github/workflows/ci.yml` runs: gitleaks, PHPUnit×2, syntax×4, PHPCS, plugin validation, MCP build, UIUX build.
- **Not in CI:** MCP tests (none exist), UIUX tests (none exist), integration suite runs but is empty, no artifact of test counts, no coverage report published.
- The single failing job (MCP build) is a **packaging** failure (exec bits), not a source failure — reproduced and root-caused in `SECURITY_AUDIT.md` SEC-08.
- Workflow dispatch from this integration returned **403** (cannot trigger runs); re-running CI would require a PR or push to `main` — out of Phase 1 scope without approval.

## 6. Bottom line

1. **Executed and green here:** the entire JavaScript/TypeScript build surface (UIUX install/typecheck/lint/build, MCP typecheck) and all static audits.
2. **Green in CI on this exact tree (job conclusions only):** both PHPUnit suites (93 static-expanded cases), PHPCS, syntax×4, validation, secret scan.
3. **Red:** MCP build job ⇒ whole CI red since 2026-09-28.
4. **Never executed anywhere:** live WP lifecycle, Plugin Check, MCP e2e, dashboard QA — these remain explicitly unverified and gate any release claim.

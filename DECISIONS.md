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

## 5. Open decisions (need maintainer input before/during Phase 2)

| # | Decision | Options | Audit recommendation |
|---|----------|---------|----------------------|
| O-1 | Dead security controls: **implement** vs **remove claims** | wire rate-limit/logging/kill-switch/IP-filter (SEC-01/02/04/05) vs delete from docs | **Implement** — mission fail-closed rule makes “documented but off” unacceptable for mutating controls |
| O-2 | `wp_flush_cache` tool | implement gated `/cache/flush` vs remove tool | implement (cheap, `wp_cache_flush()` + `manage_options` + destructive flag) |
| O-3 | MCP distribution | publish `@wpforge/mcp-server` to npm vs local-path snippet only | local-path first (no publish gate risk); revisit publishing — **publishing requires approval** |
| O-4 | ~20 dead classes | wire / delete / quarantine | delete in Phase 2E after wiring decisions, list in DECISIONS update; `schemas/*.json` → either enforce as request validation or drop from README layout claims |
| O-5 | Config UI | build Settings page (roadmap D2) vs document programmatic-only | build Settings page — CONFIGURATION.md already promises it |
| O-6 | Dashboard embedded vs standalone | keep both (embedded Connect page + dev SPA) | keep both but fix asset pipeline (D1) |
| O-7 | Version bump timing | 1.0.1 immediately after Phase 2 vs at release | at release (rule 15: one increment per release) |
| O-8 | `.freebuff` incident follow-up | confirm credential rotation for affected production site | **maintainer out-of-band confirmation** — cannot be derived from repo |
| O-9 | CI matrix target | stay WP 6.7 vs add 7.1 | add WP 7.1 before any `Tested up to: 7.1` claim |

## 6. Non-decisions (explicitly out of Phase 1)

- No dependency upgrades (Dependabot remains owner; blocked on R2).
- No branch protection / repo settings changes; no issue/PR comments; no releases/tags.
- No WordPress.org submission, no SVN actions, no npm publication.
- No force-push, no history rewrite (user-change preservation rules).

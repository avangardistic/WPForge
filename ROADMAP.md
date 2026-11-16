# WPForge — ROADMAP (post-Phase 1)

**Status:** draft for approval. **Phase 2 must not begin until Phase 1 (this audit + `PHASE1_REPORT.md`) is reviewed and approved.**
Loop for every Phase 2 cycle: **INSPECT → RESEARCH → PLAN → IMPLEMENT → TEST → VERIFY → UPDATE → REPEAT.**
Standing rules: no weakening authz to make tests pass; mutations disabled by default; untrusted input stays untrusted; approval gates (below) are hard.

Priority scale: **P0** security/auth bypass · **P1** functional/authz · **P2** MCP reliability · **P3** dashboard · **P4** performance/tests · **P5** docs/release.

---

## Gate 0 — Entry requirements (now → Phase 2 start)

- [ ] Phase 1 report reviewed/approved by maintainer.
- [ ] Explicit go for code changes on `arena/87eda57c-wpforge` (Phase 1 was docs-only).
- [ ] Confirm scope choice: fix-in-place vs. doc-honesty-first (DECISIONS.md lists both; recommendation: implement then document, per fail-closed rule).

---

## Phase 2A — CI & repo hygiene (P2, unblocks everything else)

*Why first: every dependency bump and every validation run is currently red on one job.*

| ID | Task | Acceptance criteria |
|----|------|---------------------|
| R1 | Remove `mcp/node_modules` from git; real root `.gitignore` (`node_modules/`, `dist/`, `.env*`, cookie jars, agent scratch dirs) | `git ls-files \| grep node_modules` = 0; `.gitignore` no longer `(empty)` |
| R2 | CI: `npm ci` + `npm run build` for mcp; keep `node` fallback out (npm ci restores exec bits) | CI run green on branch incl. "MCP server build" |
| R3 | Unblock Dependabot (rebase 6 open PRs after R2); triage `npm audit` prod findings (mcp 8/3-high, incl. zod) | 0 high prod vulns or documented exceptions |
| R4 | Publish job summaries with test counts (fixes FINAL-AUDIT-style stale claims) | PHPUnit job writes `Tests: N` to `$GITHUB_STEP_SUMMARY` |

*Loop note:* INSPECT current `.gitattributes` export-ignore already excludes `mcp/` from zip — keep.

## Phase 2B — Fail-closed security wiring (P1) — the heart of Phase 2

| ID | Task (from SECURITY_AUDIT) | Acceptance criteria |
|----|----------------------------|---------------------|
| S1 | **Wire rate limiting** (SEC-01): instantiate middleware or per-route limiter; fix `X-Forwarded-For` trust (proxy allowlist) | request N+1 within window → 429 with envelope; wiring test (currently absent) proves registration; defaults 100/60 preserved |
| S2 | **Wire audit logging** (SEC-02): log every mutation (method, route, user, redacted body, ip) to `wpforge_logs`; activate redaction | test: POST /posts as admin → row exists with password-like keys redacted; docs' claims become true |
| S3 | **Honor the three dead flags** (SEC-03): `allowPluginInstallation`, `allowThemeActivation`, `allowDestructiveOperations` (+ `/files/delete`) behind `developer_mode && flag` | with defaults, activate/deactivate/delete → 403 `*_DISABLED`; flags on + caps → 200; tests for both states |
| S4 | **Kill switch** (SEC-04): enforce `enabled=false` globally; add admin Settings page writing `wpforge_config` (closes CONFIGURATION.md's phantom “WPForge → Settings”) | disabled site: every WPForge route → 403; Settings page round-trips all documented keys |
| S5 | **IP allow/block + CORS keys** (SEC-05): enforce or remove from docs (prefer enforce: `allowed_ips`/`blocked_ips` in auth filter) | blocked IP → 403 pre-route; test |
| S6 | **Backup retention/size** (SEC-06): wire `CleanupManager` + `max_backup_size_mb` | create >retention backups → oldest removed; oversized backup → 4xx |
| S7 | **Per-object authz depth** (SEC-07): `read_post` on getPost, `edit_post` on Elementor PUT, `edit_user` on user get; review all handlers for object-level caps | two-author tests: author B cannot read author A's draft via GET /posts/{id}; cannot PUT A's Elementor doc |
| S8 | Regression suite for everything above; integration suite gets its first real tests | CI unit+security+integration counts increase; no capability downgrades anywhere |

## Phase 2C — MCP reliability (P2)

| ID | Task | Acceptance criteria |
|----|------|---------------------|
| M1 | Resolve `wp_flush_cache` (SEC-10): implement route (gated `manage_options` + flag) **or** remove tool + docs | no tool references a nonexistent route |
| M2 | Document all 37 tools in `mcp/README.md` (5 missing) | README table count = code count |
| M3 | Jest harness + tests: client error stripping, tool→route mapping table (assert each tool's path/method exists in API_REFERENCE), schema validation of args | `npm test` green in CI (new job) |
| M4 | Decide npm publication (`@wpforge/mcp-server`) vs. rewrite AdminUI snippet to local-path run | **publishing requires approval gate**; snippet must work verbatim |
| M5 | Password via env/file only (drop CLI-arg default), keep 30 s timeout | docs + code agree; process-list exposure gone |

## Phase 2D — Dashboard (P3)

| ID | Task | Acceptance criteria |
|----|------|---------------------|
| D1 | Fix Connect-page assets (SEC-13): build pipeline copies `UIUX/dist` → `wordpress/wpforge/assets/assets/` with **stable filenames** (e.g. `wpforge-app.css/js`) referenced by AdminUI | wp-admin Connect page renders with JS/CSS (browser QA); asset hash drift no longer breaks it |
| D2 | Settings UI (with S4) or explicit docs that config is programmatic-only until then | no doc points at a nonexistent page |
| D3 | Stop persisting app password in transient (SEC-12): store only probe result + masked prefix | no plaintext secret in `wp_options` during flow |
| D4 | Minimal frontend tests (connect happy-path + 401 path against mocked fetch) | `npm test`-equivalent script exists and runs in CI |

## Phase 2E — Performance & tests (P4)

| ID | Task | Acceptance criteria |
|----|------|---------------------|
| T1 | Throttle `last_used_at` writes (SEC-15) | ≤1 write/token/minute under request loop |
| T2 | Dead-code quarantine (~20 classes): either wire, test, or delete (Router/Controller/middlewares/QueryBuilder/…; `schemas/*.json` — wire as request validation or remove claims) | zero unreferenced classes OR documented exclusions in DECISIONS.md |
| T3 | Coverage report (phpunit coverage config already present) published as CI artifact | coverage % recorded in TEST_REPORT update |
| T4 | Property/edge tests: SQL parameter mismatch handling, `download_url` file-type abuse, menu/taxonomy cap mapping | new tests green |

## Phase 2F — Docs truth-up (P5)

| ID | Task | Acceptance criteria |
|----|------|---------------------|
| C1 | README counts (16 route files, 79 PHP files), tool counts (37), features list matches wired reality | claim-ledger in PROJECT_STATUS flips to ✅ |
| C2 | SECURITY.md key names (`rate_limit_requests` not `rate_limits.*`; real flag names); CONFIGURATION.md reflects Settings UI (or programmatic reality) | no key in docs missing from `defaults.php` and vice versa |
| C3 | API_REFERENCE: add `GET /`, `GET /elementor/templates/{id}`, missing methods (DELETE pages/users, GET term-by-id, PATCH notes) | diff script → 0 mismatches |
| C4 | CHANGELOG ↔ readme.txt changelog sync; fix false `.gitignore` claim wording | consistent narrative |
| C5 | i18n: `languages/wpforge.pot` generation in CI | Domain Path resolves |

**Phase 2 exit criteria:** all A–F acceptance criteria met; CI fully green (incl. new test jobs); claim-ledger (PROJECT_STATUS §2) has no ❌ left; TEST_REPORT updated with *executed* counts; maintainer review.

---

## Phase 3 — Live validation & release candidates (gates: approval required)

1. Provision live WP (matrix: PHP 8.1/8.3/8.4 × WP 6.7 → 7.1) — install/activate/REST smoke/deactivate/uninstall click-path (the gap FINAL-AUDIT never covered).
2. Official **Plugin Check** plugin run; fix findings.
3. MCP e2e against live site (all 37 tools, incl. negative authz cases).
4. Dashboard browser QA (connect flow, responsive, keyboard-only).
5. Version bump 1.0.0 → 1.0.1; build + inspect zip; `Tested up to: 7.1` after matrix pass.
6. **Release gate:** maintainer approval → tag → GitHub release. **WordPress.org submission gate: separate explicit approval — nothing may be submitted without it.**

## Phase 4 — Optional / later (needs separate approval)

- Publish `@wpforge/mcp-server` to npm (if chosen in M4).
- Elementor/document editor deep features; CPT auto-discovery; pagination cursor support.
- WAF guidance docs for internet-facing deployments (SEC-01 residual).
- Open-source hygiene: issue templates, PR template, SECURITY.md policy file for private reporting (currently no private vuln channel).

---

## Risk register carried into Phase 2

| Risk | Mitigation |
|------|------------|
| No PHP in audit sandbox → local test blindness persists | rely on CI with published counts (R4); consider approved devcontainer later |
| CI logs historically unretrievable | job summaries (R4) + artifact upload of junit XML |
| History rewrite already done once (`.freebuff` incident) — rotation status unknown | maintainer confirms credential rotation out of band (open item) |
| Dependabot backlog may conflict with M3/R2 | rebase after Phase 2A only |
| Scope creep (15+ dead classes) | T2 quarantine decision recorded in DECISIONS.md before deletion |

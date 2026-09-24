# CLI AI Agent Prompt — WPForge: Full Branch/PR Audit, Merge & WordPress.org Release Readiness

> نحوه استفاده: این فایل را با `claude` / `codex` / `gemini-cli` / هر CLI agent اجرا کنید، مثلاً:
> `claude "$(cat AGENT-PROMPT-branch-pr-audit.md)"` یا در case Sensitivity: **high** (مجاز به force-push نیست؛ پاک‌سازی history نیازمند تأیید انسانی است).

---

## ROLE

You are the senior release engineer and code auditor for the repository
**`avangardistic/WPForge`** (a GPL-2.0-or-later WordPress plugin + MCP server).
Your job is to audit **every branch and every pull request**, decide what to merge,
what to discard, and guarantee that the final state of `main` is correct, tested,
and ready to upload to the **WordPress.org Plugin Directory**.

You have shell access (`git`, `gh`, `php`, `composer`) and full write access to the
repo. Work autonomously; ask a human only where explicitly required below.

---

## GROUND RULES (non-negotiable)

1. **Never rewrite public history without explicit human confirmation.** If you find
   committed secrets/credentials in old commits, report them, rotate/flag them, and
   propose a BFG/git-filter-repo purge as a *separate, human-approved* step.
2. **Never delete a branch or close a PR destructively without first recording it**
   in the audit report (name, last commit, verdict, evidence). Prefer
   `gh pr close` with an explanatory comment over silent deletion. Tag anything you
   delete: `git tag archive/<branch-name>` before `git branch -D`.
3. **Do not fabricate results.** Every verdict must cite concrete evidence: diff
   stats, CI run IDs, phpcs/phpunit output, grep hits. If a check cannot run, say so.
4. All quality gates are defined by this repo itself: `composer lint`,
   `composer test` (unit/integration/security suites), `composer validate`,
   `php tools/build/package.php`, and `.github/workflows/ci.yml` (PHP 8.1–8.4 matrix).
5. WordPress.org compliance is the final acceptance criterion (see PHASE 5).

---

## PHASE 0 — Inventory

```bash
git fetch --all --prune
gh repo view avangardistic/WPForge --json defaultBranchRef,url
gh pr list --state all --limit 500 \
  --json number,title,author,state,headRefName,baseRefName,mergeable,isDraft,url
gh api repos/avangardistic/WPForge/branches --paginate \
  --jq '.[].name' > /tmp/branches.txt
```

Build a table: **branch/PR → author → last commit date → divergence from `main`
(ahead/behind) → touched paths → CI status → draft/open/merged/closed**. Save it as
`AUDIT-INVENTORY.md`. No branch or PR may remain unclassified at the end.

## PHASE 1 — Per-branch / per-PR deep review

For each item in the inventory, in order:

1. `git checkout -b review/<name> origin/<name>` (or `gh pr checkout <n>`).
2. Inspect the full diff vs merge-base: `git diff $(git merge-base origin/main HEAD)...HEAD`
   — read every changed hunk; do not skim.
3. Run the gates on that ref:
   ```bash
   composer install --no-interaction
   composer lint            # phpcs, ruleset = phpcs.xml (WP-Core standards)
   composer test            # phpunit unit + integration + security
   php tools/validate/validate-plugin.php
   php tools/build/package.php
   ```
   Also: `find . -name '*.php' -exec php -l {} \;` across PHP 8.1–8.4 if available.
4. Risk scan (grep the diff for):
   - process execution: `grep -nE '\b(exec|system|passthru|shell_exec|proc_open|popen|eval)\s*\(' <changed files>`
   - unescaped output / raw SQL: `grep -nE 'echo \$|print \$|\$wpdb->(query|get_results)\(\s*"' <changed files>`
   - missing capability checks on REST callbacks: `'permission_callback' => '__return_true'` or any route lacking `current_user_can(...)`
   - filesystem writes outside the declared sandbox / traversal (`..`, `realpath` bypass)
   - hardcoded credentials, tokens, cookies, `.env` contents, live site dumps
   - license header removal, text-domain changes, `readme.txt` version drift
5. Classify with exactly one verdict:
   - **MERGE** — additive, passes all gates, no security regression.
   - **MERGE WITH FIXES** — valuable but needs small corrections *you* make on the branch.
   - **REJECT & DELETE** — breaks code/tests/lint, duplicates existing work, dead WIP,
     or introduces a security/compliance regression that cannot be cheaply fixed.
   - **NEEDS HUMAN** — genuinely ambiguous (e.g., conflicting feature directions).

## PHASE 2 — Execute decisions (safe order)

1. Merge everything classified MERGE / MERGE-FIXES into `main` via PR
   (`gh pr merge <n> --squash --delete-branch` after CI green). Resolve conflicts
   yourself, re-run gates after **each** merge — never batch-merge blindly.
2. For REJECT items: post the evidence-based closing comment on the PR, then:
   ```bash
   git tag archive/<branch-name> origin/<branch-name>
   gh pr close <n> --comment "<verdict + evidence>"
   git push origin :<branch-name> && git branch -rD origin/<branch-name>
   ```
3. After Phase 2, `main` must be the single source of truth: `composer lint &&
   composer test && composer validate && php tools/build/package.php` all green,
   locally **and** on CI (`gh pr checks` / `gh run watch`).

## PHASE 3 — Verify the merged product actually works

1. Boot a real WordPress (use `tools/ci/install-wp-tests.sh`; or spin up
   `docker run -d -p 8080:80 wordpress:6-php8.3-apache` + MySQL) with the built zip
   installed from `wordpress/wpforge/`.
2. Smoke-test the advertised surface against the running site:
   `/wp-json/wpforge/v1/status`, `/site`, `/posts` CRUD, auth via Application
   Password; confirm every mutating endpoint is gated behind `developer_mode` +
   its own flag + capability (README's core promise).
3. Confirm docs match reality: `docs/*.md`, `README.md`, `CHANGELOG.md` claims vs
   actual endpoints/behavior. Fix doc drift in `main`.

## PHASE 4 — WordPress.org readiness checklist (must ALL pass)

Validate `wordpress/wpforge/readme.txt` against the official plugin-directory
requirements (see https://rudrastyh.com/wordpress/publish-plugin-to-wordpress-repository.html
and https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/):

- [ ] `readme.txt` headers — REQUIRED five must be present and correct:
      `Contributors`, `Tags`, `Tested up to`, `Stable tag`, `License`.
      Recommended also: `=== Plugin Name ===` line, short description ≤ 150 chars
      right after headers, `Requires at least`, `Requires PHP`, `Donate link`.
- [ ] Since WP 5.8, `Requires at least` / `Requires PHP` are taken from the plugin's
      **main PHP file header** — verify they exist there AND match readme.txt exactly.
- [ ] `Stable tag` matches the plugin version AND the SVN layout you will publish
      (either `trunk` or a tagged version folder like `1.0.0`).
- [ ] Required sections present with exact `== Section ==` syntax:
      `Description`, `Installation`, `FAQ`, `Changelog` (FAQ Q/A as `= Question =`
      subheadings → accordion; Changelog entries as `= X.Y.Z =`). Optional but fine:
      `Screenshots` (only needed if screenshot descriptions accompany uploaded images).
- [ ] Markdown inside readme is directory-safe: `**bold**`, `*italic*`, `[text](url)`,
      `` `code` ``, ordered/unordered lists — no HTML, no external images hosted on
      non-sanctioned domains, no promotional spam.
- [ ] Run the **official readme validator**: https://plugin-check.readme.io/ or
      `wp plugin check wordpress/wpforge/` (with the "Plugin Check" / `wp-cli`
      `plugin-check` package) — zero errors before submission.
- [ ] Main plugin file header complete (Plugin Name, Description, Version, Author,
      License: GPL-2.0-or-later, License URI, Text Domain, Update URI absent) and the
      text domain used consistently everywhere.
- [ ] No admin/network-only surprises; uninstall routine cleans options/transients safely.
- [ ] i18n: all user-facing strings escaped & translated (`esc_html__` etc.); no echo of unescaped data; input validated/sanitized; WP_DEBUG produces zero notices/deprecations.
- [ ] VIP/Ignified-underscores sniffs clean under phpcs (the repo's `phpcs.xml` already targets WP-Docs + VIP; fix remaining violations).
- [ ] Zero secrets, zero client/site dumps, zero dev artifacts anywhere in the tree or the packaged zip (`unzip -l` the build artifact and inspect every path).
- [ ] Build zip contains only runtime files (no tests/, vendor dev deps, .github/, docs sources unless intended, UIUX scratch dirs) and installs+activates cleanly on fresh WP.
- [ ] LICENSE file shipped; third-party assets have compatible licenses listed in readme.
- [ ] `composer validate` and `php tools/validate/validate-plugin.php` exit 0.

## PHASE 4b — Submission & SVN publishing runbook (documented, executed ONLY by human)

The AI agent must NOT submit or commit to SVN itself — it writes this runbook into
`RELEASE-CHECKLIST.md` with real values filled in:

1. Create/join account on wordpress.org → submit via https://make.wordpress.org/plugins/plugin/new-plugin/
   uploading the built zip. Expect review queue delays (can be weeks/months).
2. On approval email, note the plugin's **Public URL** and **SVN URL**
   (`https://plugins.svn.wordpress.org/<slug>/`).
3. Ensure `svn` is installed (`svn --version`, else `brew install subversion` / `apt-get install subversion`).
4. Working copy + publish:
   ```bash
   mkdir -p ~/svn && cd ~/svn
   svn co https://plugins.svn.wordpress.org/wpforge-ai-bridge   # actual slug from email
   cd wpforge-ai-bridge && svn up
   rsync -a --delete <build-dir>/ trunk/                        # runtime files only
   # screenshots/assets -> assets/  (screenshot-1.png, banner-772x250.png, icon-128x128.png, icon.svg)
   svn add --force assets/* trunk/*
   svn ci -m "Release 1.0.0" --username <wporg-user>            # WP.org credentials
   ```
5. Tag every release: `svn cp trunk tags/1.0.0 && svn ci -m "tagging 1.0.0"` —
   the readme.txt inside `tags/X.Y.Z/` powers the directory page for that version.
6. SVN structure reminder: `trunk/` = current stable, `tags/<version>/` = each release,
   `assets/` = screenshots/banner/icon (no code).

## PHASE 5 — Deliverables

Write two files and commit them to `main`:

1. **`AUDIT-REPORT.md`** — the full inventory table, per-branch/PR verdict with
   evidence, what was merged, what was deleted (with archive tags), what needs a
   human, and the final gate outputs (lint/test/build/package hashes).
2. **`RELEASE-CHECKLIST.md`** — Phase 4 checkboxes with proof lines, plus the exact
   commands to cut the release: version bump locations (plugin header + readme.txt
   `Stable tag` + `CHANGELOG.md`), changelog entry, tag `vX.Y.Z`, attach built zip to
   GitHub release, and the full Phase 4b WordPress.org submission + SVN command
   sequence with real values filled in.

Finally print a 10-line executive summary: total branches/PRs seen, merged N,
rejected&deleted N, held-for-human N, current CI status, and a clear yes/no answer
to: **"Is main ready for WordPress.org submission?"**

## STOP CONDITIONS (escalate to human, do not guess)

- Any evidence of leaked live credentials in git history.
- A PR whose intent contradicts the security model (writes enabled by default).
- CI infrastructure failures unrelated to code (quota, runner breakage).
- Destructive operations affecting more than 20 refs at once.

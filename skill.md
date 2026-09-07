# skill.md — WordPress Skills Bindings for WPForge

Consolidated reference that **binds the WordPress skill packs** (downloaded from GitHub) to
this repository (`WPForge`, a remote-control WordPress API bridge) and to what was actually
executed on **neginhafari.ir** (the flagship deployment used to prove the product).

> Maintained by **Hossein Parasteh** (github.com/avangardistic). This file is the single
> entry point an AI agent should read before doing WordPress work here — it maps *which*
> skill to open, *why*, and *what this repo/site already knows*.

---

## 1. Skill sources (the packs being bound)

| Pack | Local path | Focus |
|---|---|---|
| `agent-skills-trunk` | `E:/hossein/claude wp skill/agent-skills-trunk/` | Agent-native skill set: router, abilities API/audit/verify, block development, performance, plugin dev, patterns, Playground, PHPStan, project triage |
| `claude-wordpress-skills-master` | `E:/hossein/claude wp skill/claude-wordpress-skills-master/` | Performance review + `wp-perf` CLI commands |
| `wordpress-agent-skills-trunk` | `E:/hossein/claude wp skill/wordpress-agent-skills-trunk/` | wp-site-creator (Claude Code / Claude Cowork), Studio MCP connectors |
| `wordpress-dev-skills-main` | `E:/hossein/claude wp skill/wordpress-dev-skills-main/` | Plugin dev, plugin review, theme dev (architecture, a11y, review requirements) |
| `wordpress-skills-main` | `E:/hossein/claude wp skill/wordpress-skills-main/` | Largest set: site audit & onboarding, theme dev, a11y review, performance review, security review, REST API dev, admin-UI dev, PHPStan, test strategy, CI/CD, headless/GraphQL, migration review, Playground, WooCommerce, WP-CLI ops |

Each pack ships its skills as `SKILL.md` files with references/checklists. The bindings below
collapse them into domains and state **where each was applied in this project.**

---

## 2. Domain → skill → where it applies here

### Audit & onboarding
- **Skills:** `wp-site-audit-and-onboarding` (wordpress-skills-main), `wp-project-triage` (agent-skills-trunk).
- **Applied:** full pre-redesign audit of neginhafari.ir — pages/posts inventory, theme + Elementor stack,
  duplicated embedded chrome, brand/tagline mix-up, missing OG/schema, broken admin-menu wiring, form defects.
  Output: `docs/site-redesign-roadmap.md`.

### Theme & front-end architecture
- **Skills:** `wp-theme-dev` / `wp-theme-development` (both packs), `wp-block-themes`, `wp-patterns`.
- **Applied:** heading architecture (single visible H1 per page, hero H1, clean H2/H3 tree), removal of the
  theme title band via site-specific CSS, RTL + Vazirmatn typography discipline, single-branch navigation.

### Accessibility (a11y)
- **Skills:** `wp-accessibility-review` (wordpress-skills-main), theme-dev references.
- **Applied:** distinct `alt` text per image, skip-link preserved, semantic headings, keyboard-usable
  mobile menu (ARIA `wsp-open` toggle), contrast-safe navy/gold palette.

### Performance
- **Skills:** `wp-performance-review` (both packs), `wp-performance`, `wp-perf` commands.
- **Applied:** async CSS via LiteSpeed respected, Elementor per-page CSS regenerated through the API bridge,
  self-hosted imagery with real size URLs, cache purge orchestrated on plugin activation
  (TSF sitemap transients + `litespeed_purge_all`).

### Security
- **Skills:** `wp-security-review`, plugin/theme review security checklists, `wp-plugin-directory-guidelines`.
- **Applied:** nonces + capability checks in the WPForge admin UI, bearer/basic token auth, `ABSPATH` guards,
  read-only DB surface, minimal write surface (only the routes the product needs).

### Plugin development (this repo's core)
- **Skills:** `wp-plugin-development` (agent-skills-trunk + wordpress-skills-main), `wp-plugin-dev` + references
  (`architecture.md`, `security.md`) from wordpress-dev-skills-main, `wp-phpstan`.
- **Applied to WPForge itself:** class orchestration bug (file-scope booter never called the `Core\Plugin`
  registrar → admin dashboard 403 + bearer auth missing) fixed in `wordpress/wpforge/wpforge.php`;
  `admin_init`→`admin_menu` hook-ordering bug (WP 7.1 loads menus before `admin_init`) fixed by registering
  the admin UI directly at `plugins_loaded` in `src/Core/Plugin.php`;
  Elementor CSS never regenerated after API document updates — fixed in `src/Elementor/Adapter.php`.

### REST API development
- **Skills:** `wp-rest-api-development`, `wp-headless-and-wpgraphql`, `wp-abilities-api`.
- **Applied:** WPForge's `wp-json/wpforge/v1` surface (tokens, pages/posts, Elementor documents, DB tables,
  filesystem, audit log); the UIUX dashboard in `UIUX/` is a real consumer of that API.

### Admin-UI development
- **Skills:** `wp-admin-ui-development`, `wp-admin-ui-development` checklists.
- **Applied:** verified + fixed the WPForge admin dashboard (menu registration, scoped enqueues, PHP-notice-free
  render) and redeployed the plugin until green.

### SEO / content (site deployment work)
- **Skills:** no dedicated SEO pack among the sources — applied research + The SEO Framework on the live site.
- **Applied on neginhafari.ir:** `/sitemap.xml` with 25 URLs (14 pages + 11 posts), FAQPage JSON-LD on the FAQ
  page (10 Q&A) and each of the 6 service landings, OG/Twitter meta, clean titles/excerpts, 11 fully-Persian
  long-form posts with question-schema, per-service landing pages, no-Latin/no-city copy discipline.

---

## 3. Stack facts this repo + site operate on (bindings)

| Layer | Value |
|---|---|
| Target site | `neginhafari.ir` (WP 7.1, PHP 8.5.9, LiteSpeed cache) |
| Theme | Hello Elementor 3.5.1 (dynamic header/footer; nav locations `menu-1`=header, `menu-2`=footer) |
| Page builder | Elementor 4.2.4 + Elementor Pro 4.2.3 (forms, theme builder) |
| SEO | The SEO Framework (no registration wall, real `/sitemap.xml`, built-in schema) |
| Fonts | Vazirmatn via Farsi Font for Elementor + Persian Elementor |
| Design system | Deep navy `#0B2239` · gold `#C9A84C` · light `#F4F7FA` — RTL, Vazirmatn, glass/gradient cards |
| Bridge plugin | `wordpress/wpforge/` → REST namespace `wpforge/v1` |

### Operational quirks learned (keep in any future agent run)
1. **Menu reordering:** the WP REST menu-items API cannot order items — `menu_order` stays `1` for all.
   Build the desired order by *creating items in order* (ties render by insertion/ID) or use the wp-admin UI.
   Deletion of menu items via REST requires `?force=true` (plain DELETE returns 501).
2. **Elementor via API:** after `PUT …/elementor/documents/{id}`, Elementor must regenerate its per-page CSS —
   the fixed `Adapter.php` invalidates it automatically. New pages must be pre-marked
   (`_elementor_edit_mode=builder`, `_elementor_template_type=wp-page`) or the document route refuses them.
3. **Elementor forms:** the `{admin_email}` token crashes Pro's email action — use a literal recipient address.
4. **Caches:** TSF sitemap transients live ~1 week and LiteSpeed caches HTML — purge both after bulk content
   changes (reactivating the site-polish plugin runs the purge; see `.freebuff/tools/site-polish/`).
5. **Hello Elementor prints a theme H1** (`.page-header`) on every page — the site-polish CSS hides the band so
   each page carries exactly one *visible* H1 (the hero).

---

## 4. Suggested reading order for a new agent

1. `skill.md` (this file) — context and bindings.
2. `docs/site-redesign-roadmap.md` — full audit + phased plan for neginhafari.ir.
3. Pack `SKILL.md` for the specific task (audit → `wp-site-audit-and-onboarding`; theme → `wp-theme-dev`;
   performance → `wp-performance-review`; security → `wp-security-review`; plugin → `wp-plugin-development`).
4. `README.md` + `docs/API_REFERENCE.md` + `docs/AUTHENTICATION.md` — how to drive WPForge itself.

> Principles honored throughout: audit before architecture, content and SEO planned together, heading/semantic
> discipline, RTL + Farsi-first copy, measurable SEO outcomes (sitemap, schema, meta), and proving the product
> on a real production site.

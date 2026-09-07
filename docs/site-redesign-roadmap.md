# نگین حفاری زایندهرود — Website Redesign Roadmap

**Project:** Full redesign of neginhafari.ir into a modern, responsive, SEO-optimized Persian (fa-IR, RTL) website.
**Prepared:** 2026-09-04 · **Status:** Phase 1–2 in progress
**Stack (audited):** WordPress 7.1 · PHP 8.5.9 · Hello Elementor 3.5.1 (theme) · Elementor 4.2.4 + **Elementor Pro 4.2.3** · Premium Add-ons · Ultimate Add-ons for Elementor · Farsi Font for Elementor + Persian Elementor (Vazirmatn active) · WPForge 1.0.0 (remote-control bridge, freshly updated & fixed).

---

## 0. Why this roadmap exists

The site's core business value — **professional water-well drilling, geotechnical boreholes, test pumping, well rehabilitation and pumping systems across Isfahan** — deserves an interface that earns trust the way the company's equipment does. This roadmap treats the website as a *system* (identity → architecture → content → design → SEO → performance → security), not as a set of pretty pages. Every change below carries a stated reason, following professional WordPress practice (audit first, architecture before pixels, content and SEO planned together, measurable results).

The redesign is executed through the **WPForge API** — the same remote-control bridge this repo builds — which proves the product on a real site while producing the actual redesign.

---

## 1. Audit summary (completed 2026-09-04)

### 1.1 What exists today
| Area | State |
|---|---|
| Pages | 7 Elementor pages: خانه (232), درباره ما (233), خدمات (234), پروژهها (235), وبلاگ (236), تماس با ما (237), درخواست مشاوره (238) + unused نمونه page (2) |
| Blog | 1 published post only; blog page is a "coming soon" placeholder |
| Content | Real, factual copy exists for all 6 services and 6 projects (locations: شهرضا، گلپایگان، فلاورجان، نصفجهان، اصفهان) |
| Chrome | Hello Elementor dynamic header/footer on every page — but inner pages *also* embed their own old header/footer inside Elementor content → **duplicated chrome** |
| Branding | `blogname` = «حفاری چاههای عمیق و نیمهعمیق» — the *tagline* is the site name; the real brand «نگین حفاری زایندهرود» appears only inside page bodies. Theme header therefore shows the wrong identity. `blogdescription` is empty |
| Font | Vazirmatn (proper Persian webfont) — already wired |
| Images | 12 JPEGs |
| SEO | Titles auto-format as long `Page | Brand – blogname` chains; **no Open Graph, no Twitter cards, no JSON-LD schema, blog has no meta description**; descriptive excerpts exist on most pages |
| Forms | Contact + consultation pages contain functional Elementor forms |
| Contact data | Placeholder values published («۰۹۱۲xxx», «خیابان …») — real phone/address not yet on the site (email info@neginhafari.ir real) |

### 1.2 Problems → design decisions (each with its reason)
1. **Duplicated / divergent chrome.** Inner pages double-render header/footer (theme + embedded). *Decision:* one shared, professional header/footer rendered once — see Phase 2 (Theme Builder) — and content pages rebuilt without embedded chrome (mirrors the already-clean homepage).
2. **Identity inverted.** Site name is a tagline. *Decision:* set identity correctly (`blogname` = brand) and use the tagline only as `blogdescription`; put the brand + logo in the header, with a contact CTA.
3. **No social/schema layer.** Any modern site must control how it appears in Google & on shared links. *Decision:* structured data (LocalBusiness + Service + FAQ), Open Graph/Twitter, canonical, clean titles. See Phase 4.
4. **Homepage redesigned but inner pages not.** One-off homepage restyle already landed (gold `#C9A84C` on deep navy, service cards). *Decision:* formalize that look into a **Design System v2** and apply it to *all* pages — consistency is what makes a site feel professionally designed.
5. **Menu carries a dead «نمونه» link** and very long item labels (full page titles incl. brand suffix). *Decision:* clean menus (labels = short nav terms), remove dead item.
6. **Blog is empty but linked in main nav.** *Decision:* keep the page, present it honestly ("articles coming"), and add an editorial plan so the site isn't a shell.
7. **Contact placeholders.** *Decision:* collect the real phone/address/working hours once and roll them out in footer + contact page + LocalBusiness schema (content gap = owner input, tracked below).

---

## 2. Design System v2 (single source of truth)

Applied to every page so the site reads as one designed product.

- **Palette** (industrial-trust + warmth):
  - `#0B2239` deep navy (primary / dark sections) · `#122D4A` navy-soft (gradients)
  - `#C9A84C` gold (accent/CTA, evokes sand & machinery heritage) · `#F4F7FA` light canvas
  - Ink `#0E1E2E`, body `#3E5566`, muted `#5A6F82`, white cards, hairline `#E3E9EF`
- **Type:** Vazirmatn throughout (inherited site-wide); scale: H1 34–44px/800, H2 26–30px/800, H3 18px/700, body 15–16px/1.9 line-height, small 13px. RTL right-aligned.
- **Shape:** max content 1140px; vertical rhythm 64–80px; radii 12–16px; soft shadows `0 10px 30px rgba(11,34,57,.08)`.
- **Components:** kicker (small gold uppercase-ish label), section header, service/project cards, icon rows, stat band, CTA band (navy→gradient + gold button), checklist with ✓, info cards, footer columns.
- **Responsive principle:** mobile-first stacking, touch targets ≥44px, fluid 12–16px gutter, content widths 100% ≤480px, grid collapses to 1 col.
- **Accessibility:** real headings in order (one H1/page), links with visible targets, contrast ≥4.5:1 for body text (muted text never below `#5A6F82` on white), focus-visible rings, aria labels on icon links, `alt` text in Persian.

---

## 3. Phases

### Phase 1 — Foundation & site-wide chrome *(in progress)*
1. Rebuild main pages (خدمات، پروژهها، درباره ما، تماس، مشاوره، وبلاگ) in Design System v2 through the Elementor API — each page becomes clean content (theme chrome once), preserving all real copy, services, project facts and the existing functional forms.
   *Reason: consistent visual language + no duplicated headers/footers.*
2. Homepage (232) refresh pass for full system compliance (minor: unify section headers, spacing, CTA).
3. Restore/backup discipline: every document is backed up before replacement (`.freebuff/`); a single PUT restores.

### Phase 2 — Global identity & Theme Builder chrome *(needs one manual/panel step or deeper write access)*
1. Create **Elementor Pro Header template** (brand + primary nav + تلفن CTA + mobile drawer) and **Footer template** (brand blurb, quick links from real menus, services list, contact block with real data, copyright) assigned to *Entire Site* conditions.
2. Hello theme header/footer then yields to Theme Builder → chrome rendered exactly once, styled globally, editable in one place.
3. Set identity: `blogname = نگین حفاری زایندهرود`, `blogdescription = خدمات تخصصی حفاری چاه آب، گمانهزنی ژئوتکنیک، پمپاژ آزمایشی و نصب تجهیزات در اصفهان و سراسر ایران`. Add logo to Site Identity.
4. Menus: rename items to short labels; remove نمونه page item; order: خانه، خدمات، پروژهها، درباره ما، وبلاگ، تماس با ما (+ مشاوره رایگان CTA button).

### Phase 3 — Responsive & interaction hardening
1. Audit every rebuilt page at 360 / 768 / 1024 / 1440 via Elementor breakpoints + live screenshots; fix overflow, tap targets, sticky header overlap.
2. Nav: ensure one mobile pattern (theme drawer today → Theme Builder drawer in Phase 2).
3. Forms: responsive layout, floating labels/placeholder contrast, honeypot, success/error states in Persian.

### Phase 4 — SEO (technical layer)
1. **Schema (JSON-LD):** `LocalBusiness` (DrillingContractor subtype) on home with real address/phone once provided; `Service` list on خدمات; `BreadcrumbList`; `Article`/`BlogPosting` on posts; `FAQPage` if an FAQ section is added.
2. **Social meta:** Open Graph + Twitter cards on all templates (image 1200×630 from media library, Persian descriptions).
3. **Titles/descriptions:** `{Page} | نگین حفاری زایندهرود` ≤60 chars, one meta description per page (≤160 chars, keyword-led: حفاری چاه آب، گمانهزنی، اصفهان).
4. **Indexing hygiene:** sitemap + robots; remove نمونه page; `blog_public` already true; ensure permalinks already `/%postname%/` (yes); 301s from any old URLs.
5. **Content SEO:** keyword strategy around service intent («حفاری چاه آب اصفهان»، «قیمت حفاری چاه»، «گمانهزنی ژئوتکنیک») — headings already drafted in redesigns; internal links from home → خدمات → پروژهها.
6. *Tooling:* if a plugin route is preferred, evaluate Rank Math **in a later phase** (activate + configure + monitor; plugin not currently installed — deliberate, because core-layer fixes above cover 80% without plugin weight).

### Phase 5 — Performance
1. Measure baseline (PageSpeed/Lighthouse + asset count) before heavy work; target LCP < 2.5s mobile.
2. Lazy-load below-fold images; `loading=lazy` + `decoding=async`; explicit width/height.
3. Fonts: Vazirmatn via the active Farsi Font plugin stack — subset/swap; avoid extra Google Fonts (currently only Roboto leftover — remove).
4. Cache: LiteSpeed cache present — verify CSS/JS combine, page cache; purge after every deployment (scripted).
5. Audit with wp-performance-review skill checklist: no unbounded queries, no render-blocking extras, one jQuery where possible.

### Phase 6 — Trust & conversion content
1. Real contact details (owner input): phone, WhatsApp/Telegram, address, working hours, geo coordinates.
2. Photo library: real rig/well photos to replace 12 current JPEGs; before/after wells; certificates; geotech reports samples.
3. Testimonials + 2–3 case-study posts («پروژه چاه آب شهرضا») to feed the blog/projects pages.
4. FAQ section on services page (schema-ready).

### Phase 7 — QA & launch
1. Content proofread in Persian (numbers, ZWNJ, half-spaces «میخواهیم» etc.).
2. Cross-browser + device pass; a11y keyboard pass; 404 page.
3. Security pass (wp-security-review checklist): restrict WPForge filesystem root to `wp-content/uploads`, least-privilege API user, audit log ON (WPForge now self-installs its logs/tokens tables — verified live), rotate the shared Application Password, HTTPS everywhere (site already HTTPS).
4. Analytics + Search Console registration; goal events (consultation submit).
5. Hypercare: backups verified restorable; rollback drill documented (PUT of saved JSON).

---

## 4. Backups & rollback
Every pre-change document snapshot is saved under `.freebuff/` (`backup-*.json`). Restore = `PUT /wp-json/wpforge/v1/elementor/documents/{id}` with the saved `elementor_data`. Verified workflow for page 232.

## 5. Owner inputs required (blockers for launch-level polish)
1. Real phone number(s), address, working hours, geo coordinates.
2. Logo file (SVG/PNG transparent) if the brand should appear as a mark.
3. 5–10 real site photos for gallery/cases.
4. Decision on blog: editorial calendar or remove from primary nav.
5. Domain/hosting access to map `wpforge.neginhafari.ir` to the dashboard FTP space (still pending).

---

*Maintained by Hossein Parasteh (github.com/avangardistic) — executed via the WPForge API on neginhafari.ir.*

---

## 6. Execution log — 2026-09-04 (this session)

### Plugin (repo + live, v1.0.0 redeployed)
- **Fixed the unreachable admin dashboard.** Two real bugs found live and fixed:
  1. `wpforge.php` booted only REST routes; `Core\Plugin` (which registers the admin UI + bearer-token auth) was never initialised. `WPForge_Plugin::init()` now boots it.
  2. `Core\Plugin` registered the AdminUI menu on `admin_init`, but WP fires `admin_menu` *before* `admin_init` (menu.php is required earlier in `wp-admin/admin.php`). The menu callback therefore never ran → `admin.php?page=wpforge` returned 403. The menu is now registered at `plugins_loaded` on admin requests.
  - Verified live: WPForge menu visible, Dashboard renders its 4 status cards, Connect-to-AI page shows the full wizard (credentials, MCP snippets, connection test, token management), zero PHP warnings.
- Elementor CSS invalidation on document updates (Adapter fix) rides along in the deployed build.

### Design System v3 (all 7 pages live)
- Photo heroes: every page's navy hero band now carries a business photograph under the navy brand overlay (self-hosted in the media library).
- Services/home cards and project cards now lead with photography instead of flat emoji.
- About page: "داستان ما" is now a text + photograph split.
- Alt text in Persian per card; images served via WP-generated sizes (768px for cards).
- Colour system unchanged (deep navy `#0B2239` + gold `#C9A84C` — fits the drilling/water/industrial identity) — researched 2026 direction: imagery depth, glass-free legibility, generous whitespace, functional motion later.

### Imagery
- Unsplash/Pinterest scraping is bot-blocked from this environment; sourced **license-safe** photography instead (Wikimedia Commons, CC0/CC) for drilling rigs, boreholes, pumps and aquifer visuals → uploaded with Persian titles/alts/captions. Old demo portrait images removed from the library.

### SEO (live)
- Installed **The SEO Framework** (Rank Math was installed first but requires an account-bound registration wizard, so it was removed). No wizard, no account; sitemap + meta out of the box.
- `https://neginhafari.ir/sitemap.xml` live; `robots.txt` updated to point at it.
- Title/meta-description/OG tags confirmed on pages.
- **11 Persian articles** published (blog archive was empty) — each ~350+ words, H2 structure, excerpt, featured image, category «مقالات تخصصی حفاری», internal links to مشاوره/تماس, and an embedded **FAQPage (question) JSON-LD schema** block (verified rendered on the live post page).
- وبلاگ added back to the primary menu (خانه ← خدمات ← وبلاگ ← پروژهها ← …).

### Still open (owner inputs)
- Real phone/address/logo/photos; Google Search Console + Analytics; subdomain mapping for the FTP dashboard space; enable WPForge audit-log action logging; narrow the WPForge filesystem root (`wp-content/uploads`) and use a least-privilege API user; rotate the shared Application Password.


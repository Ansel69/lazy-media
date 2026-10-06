# Lazy Media Redesign & Static Deploy — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the Lazy Media WordPress theme to visually match The Lazy Monday's design system, then export it as a static site and deploy to Vercel through GitHub.

**Architecture:** WordPress runs locally in Docker as a content editor only. A WP-CLI exporter crawls every rendered route and writes static HTML plus assets to `dist/`. GitHub stores the theme source and `dist/`; Vercel serves `dist/` as a CDN. Search is client-side over a generated JSON index because there is no server at runtime.

**Tech Stack:** WordPress 6.9.4 (classic PHP theme, no plugins), PHP 8.3, MariaDB 11, Docker Compose, WP-CLI, Node 24 (image processing only).

**Spec:** `docs/superpowers/specs/2026-10-05-lazy-media-redesign-design.md`

## Global Constraints

- Design tokens are fixed by the spec and must not be re-invented per task: bg `#0F0F0F`, accent `#FBAD18`, text `#FFFFFF`, muted `#9A9A9A`, faint `#54595F`, container `1230px`, radius `8px`, card shadow `3px 3px 10px #000000`.
- Section accent colors are fixed per section: Hero `#FF7E36`, New Articles `#FF7E36`, Games `#C203C6`, Hardware `#36FFF3`, Entertainment `#BFFA17`, Latest Articles `#FF36EB`.
- Font is Barlow, weights 400/500/600/700, served from local `.woff2` files. No runtime request to Google Fonts or any other external host.
- All content, branding, images and copy belong to Lazy Media. No asset, logo, photograph or article text is copied from The Lazy Monday.
- No PHP file may emit a notice, warning or deprecation on any route. `wp-content/debug.log` must stay empty.
- No credential ever enters version control. `.env`, `wp-config.php` and DB dumps are git-ignored.
- Every route reachable from the homepage must return HTTP 200 both locally and in `dist/`.

## Review Focus

Inputs the spec implies but individual tasks rarely exercise on their own:

1. **Viewport at exactly 1024px and 767px** — the two breakpoints where the reference collapses 3→2 and 2→1 columns; expect no horizontal scrollbar and no clipped card text.
2. **A category block that has fewer than 3 posts** — expect a graceful partial grid, not three empty placeholders or a broken border.
3. **An article with no featured image** — expect the gradient placeholder to render at the correct aspect ratio, not a collapsed or 0-height box.
4. **Search with zero results** — expect a styled empty state on the static site, not a blank page or a JS error.
5. **A very long article title** — expect the card title to clamp to a fixed number of lines so cards in a row stay equal height.

---

## File Structure

Source of truth for the theme becomes `theme/lazymedia/`, bind-mounted into the
container. `public/` holds WordPress core and is git-ignored.

| File | Responsibility |
| --- | --- |
| `theme/lazymedia/style.css` | Theme header + design tokens + global reset + typography |
| `theme/lazymedia/functions.php` | Theme setup, enqueues, nav menus, image sizes |
| `theme/lazymedia/inc/design.php` | Token constants exposed to PHP; section accent map |
| `theme/lazymedia/inc/query.php` | All `WP_Query` builders used by templates |
| `theme/lazymedia/inc/cards.php` | Card renderers: full card, compact row, video card |
| `theme/lazymedia/inc/blocks.php` | Homepage block registry and renderers |
| `theme/lazymedia/header.php` | Sticky header, primary nav, search |
| `theme/lazymedia/footer.php` | Footer nav, social row, credit |
| `theme/lazymedia/front-page.php` | Homepage composition |
| `theme/lazymedia/single.php` | Article page |
| `theme/lazymedia/archive.php` | Category/tag/date archives |
| `theme/lazymedia/search.php` | Client-side search results |
| `theme/lazymedia/page.php` | Static pages (About, Contact) |
| `theme/lazymedia/404.php` | Not found |
| `theme/lazymedia/template-parts/hero.php` | Hero slider |
| `theme/lazymedia/template-parts/section-header.php` | Title + neon 40×4px divider |
| `theme/lazymedia/template-parts/post-card.php` | Standard article card |
| `theme/lazymedia/assets/css/*.css` | Split stylesheets loaded per concern |
| `theme/lazymedia/assets/js/lazymedia.js` | Nav toggle, hero slider, filter pills |
| `theme/lazymedia/assets/js/search.js` | Client-side search over the JSON index |
| `theme/lazymedia/assets/fonts/*.woff2` | Barlow local |
| `theme/lazymedia/assets/img/` | Logo, favicon |
| `seed/qa.php` | Regression suite: routes, tokens, block output |
| `seed/export.php` | Static site exporter |
| `scripts/export.ps1` | PowerShell entry point for the exporter |
| `vercel.json` | Vercel build config |

---

## Phase A — Design System

### Task 1: QA Regression Suite

Build the test harness every later task asserts against. Nothing else can be
verified until this exists.

**Files:**
- Create: `seed/qa.php`
- Modify: `seed/lint.sh`

**Interfaces:**
- Consumes: nothing.
- Produces: `seed/qa.php` runnable as `docker compose run --rm wpcli wp eval-file /seed/qa.php --path=/var/www/html --allow-root`. Exits non-zero on first failure. Prints `PASS <name>` / `FAIL <name>: <reason>` per check and a final `QA: <n>/<m> passed`. Helper `lm_qa(string $name, callable $test): void` collects results; `lm_qa_get(string $url): string` fetches a local route and throws if status is not 200.

- [ ] **Step 1: Write `seed/qa.php` with failing checks**

Checks that must fail right now, asserting the target design:

```php
lm_qa( 'homepage returns 200', function () {
    $html = lm_qa_get( home_url( '/' ) );
    lm_qa_assert( false !== strpos( $html, '--lm-accent:#FBAD18' ), 'accent token missing' );
    lm_qa_assert( false !== strpos( $html, 'lm-hero' ), 'hero slider missing' );
} );
lm_qa( 'every route is 200', function () {
    foreach ( array( '/', '/about/', '/contact/', '/category/games/', '/tag/games/', '/?s=ace' ) as $path ) {
        lm_qa_assert( 200 === lm_qa_status( home_url( $path ) ), $path . ' not 200' );
    }
} );
lm_qa( 'section dividers present', function () {
    $html = lm_qa_get( home_url( '/' ) );
    foreach ( array( '#C203C6', '#36FFF3', '#BFFA17', '#FF36EB' ) as $c ) {
        lm_qa_assert( false !== strpos( $html, $c ), $c . ' section accent missing' );
    }
} );
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `docker compose run --rm wpcli wp eval-file /seed/qa.php --path=/var/www/html --allow-root`
Expected: `FAIL homepage returns 200: accent token missing`, non-zero exit.

- [ ] **Step 3: Implement `lm_qa`, `lm_qa_assert`, `lm_qa_get`, `lm_qa_status` in `seed/qa.php`**

`lm_qa_get` uses `wp_remote_get` with `timeout => 10` and returns `wp_remote_retrieve_body`. `lm_qa_status` returns the response code. `lm_qa_assert` throws `RuntimeException`. Add a trailing runner block that prints the tally and calls `exit( $failed ? 1 : 0 )`.

- [ ] **Step 4: Run it and confirm it fails only on design checks**

Expected: route check passes, token and hero checks fail.

- [ ] **Step 5: Commit**

```bash
git add seed/qa.php seed/lint.sh
git commit -m "test: add QA regression suite for routes and design tokens"
```

---

### Task 2: Local Barlow Fonts

**Files:**
- Create: `theme/lazymedia/assets/fonts/barlow-{400,500,600,700}.woff2`
- Modify: `theme/lazymedia/style.css`, `theme/lazymedia/functions.php`

**Interfaces:**
- Consumes: nothing.
- Produces: CSS custom properties `--lm-font-sans` on `:root`. `functions.php` gains `lazy_media_fonts(): array` returning four `{family, weight, src}` entries consumed by `lazymedia_style()`.

- [ ] **Step 1: Add failing QA check for self-contained fonts**

```php
lm_qa( 'no external font requests', function () {
    $html = lm_qa_get( home_url( '/' ) );
    lm_qa_assert( false === stripos( $html, 'fonts.googleapis' ), 'Google Fonts referenced' );
    lm_qa_assert( false !== strpos( $html, 'assets/fonts/' ), 'local font files not referenced' );
} );
```

- [ ] **Step 2: Run QA, confirm failure** — expected `FAIL no external font requests: local font files not referenced`.

- [ ] **Step 3: Download Barlow woff2 (400/500/600/700) into `assets/fonts/`**

Fetch from the Google Fonts CSS API with a modern UA to get `woff2` URLs, then download each file. Latin subset only.

- [ ] **Step 4: Add `@font-face` rules and `--lm-font-sans` in `style.css`**

```css
@font-face {
    font-family: 'Barlow';
    font-style: normal;
    font-weight: 400;
    font-display: swap;
    src: url('assets/fonts/barlow-400.woff2') format('woff2');
}
```

Repeat for 500/600/700. Set `:root { --lm-font-sans: 'Barlow', system-ui, sans-serif; }`.

- [ ] **Step 5: Enqueue via `lazy_media_fonts()`** so the theme version-busts the URLs.

- [ ] **Step 6: Run QA** — expected `PASS no external font requests`.

- [ ] **Step 7: Commit** — `git commit -m "feat: bundle Barlow locally, drop Google Fonts dependency"`

---

### Task 3: Design Token Layer

**Files:**
- Modify: `theme/lazymedia/style.css`
- Create: `theme/lazymedia/assets/css/tokens.css`

**Interfaces:**
- Consumes: nothing.
- Produces: every token from Global Constraints as a CSS custom property on `:root`. `lazymedia_section_accent( string $section ): string` in `inc/design.php` returns the hex for a section name, falling back to `#FF7E36`.

- [ ] **Step 1: Add failing QA check for the full token set**

Assert the presence of each: `--lm-bg:#0F0F0F`, `--lm-accent:#FBAD18`, `--lm-text:#FFFFFF`, `--lm-muted:#9A9A9A`, `--lm-faint:#54595F`, `--lm-radius:8px`, `--lm-container:1230px`.

- [ ] **Step 2: Run QA, confirm failure.**

- [ ] **Step 3: Rewrite `style.css` as tokens + reset + typography**

Keep the theme header block at the top (WP requires it). Below it, define tokens, then a dark reset: `body { background: var(--lm-bg); color: var(--lm-text); font-family: var(--lm-font-sans); }`, headings at weight 600, muted text at `--lm-muted`, container utility at `max-width: var(--lm-container)`.

Split stylesheets: `tokens.css` (tokens + reset + typography) is the file Task 4+ add to.

- [ ] **Step 4: Create `inc/design.php` with `lazymedia_section_accent()`**

Map exactly: `hero`,`new-articles` → `#FF7E36`; `games` → `#C203C6`; `hardware` → `#36FFF3`; `entertainment` → `#BFFA17`; `latest` → `#FF36EB`.

- [ ] **Step 5: Run QA** — expected `PASS design tokens present`.

- [ ] **Step 6: Commit** — `git commit -m "feat: adopt The Lazy design tokens as the theme baseline"`

---

### Task 4: Header and Footer

**Files:**
- Modify: `theme/lazymedia/header.php`, `theme/lazymedia/footer.php`, `theme/lazymedia/inc/nav-walker.php`
- Create: `theme/lazymedia/assets/css/header.css`, `theme/lazymedia/assets/css/footer.css`

**Interfaces:**
- Consumes: tokens from Task 3.
- Produces: `lazymedia_social_links(): array` returning `array( array( 'label' => 'Instagram', 'url' => ... ), ... )` for the footer.

- [ ] **Step 1: Add failing QA checks**

Assert `.lm-header`, `.lm-search`, `.lm-footer`, `.lm-social` all appear in the homepage HTML.

- [ ] **Step 2: Run QA, confirm failure.**

- [ ] **Step 3: Rewrite `header.php`**

Sticky header: logo left, `wp_nav_menu( 'primary' )` centre, search right. Header carries `border-top: 1px solid` and `border-bottom: 1px solid` in `--lm-accent`. Search input is transparent with a 1px accent bottom border; the submit button is `display: none` above 767px.

- [ ] **Step 4: Extend `LazyMedia_Nav_Walker`**

Nav item font-size 16px / weight 600, colour `--lm-text`, and a `::after` underline in `--lm-accent` that appears on hover and on `.current-menu-item`. Dropdown panels: absolute, bg `--lm-bg-elevated`, radius 8px, 1px `--lm-border`, opened on hover for pointer devices and on `.is-open` toggled by JS on touch.

- [ ] **Step 5: Rewrite `footer.php`**

`lazymedia_social_links()` row above the footer nav, then `wp_nav_menu( 'footer' )`, then a credit line reading `© <current year> Lazy Media`. Same 1px accent top border.

- [ ] **Step 6: Run QA** — expected all header/footer checks pass.

- [ ] **Step 7: Commit** — `git commit -m "feat: rebuild header and footer on the dark design system"`

---

### Task 5: Card Components

**Files:**
- Create: `theme/lazymedia/inc/cards.php`, `theme/lazymedia/template-parts/post-card.php`, `theme/lazymedia/assets/css/cards.css`
- Modify: `theme/lazymedia/functions.php` (autoload `inc/cards.php`)

**Interfaces:**
- Consumes: `lazymedia_post_image_url( int $post_id, string $size = 'lm-card' ): string` from `inc/query.php`.
- Produces: `lazymedia_post_card( int $post_id, string $variant = 'default', array $args = array() ): void` where `$variant` is one of `default`, `compact`, `video`. `compact` hides the excerpt and the read-more link; `video` renders a play badge.

- [ ] **Step 1: Add failing QA check for card variants**

```php
lm_qa( 'card variants render', function () {
    $html = lm_qa_get( home_url( '/' ) );
    lm_qa_assert( false !== strpos( $html, 'lm-card' ), 'default card missing' );
    lm_qa_assert( false !== strpos( $html, 'lm-card--compact' ), 'compact card missing' );
    lm_qa_assert( false !== strpos( $html, 'lm-card__badge' ), 'video badge missing' );
    lm_qa_assert( false !== strpos( $html, 'lm-card__title' ), 'card title missing' );
} );
```

- [ ] **Step 2: Run QA, confirm failure.**

- [ ] **Step 3: Implement `lazymedia_post_card()`**

Output order: image link → video badge if the post has the `_lm_video` meta → category chip → title (linked) → meta row (date, read time) → excerpt unless `compact` → read-more link unless `compact`.

- [ ] **Step 4: Style `.lm-card`**

Radius 8px, shadow `3px 3px 10px #000`, 16:9 image, 14px gap between image and body, title clamped with `-webkit-line-clamp: 3` so equal-height rows stay aligned (Review Focus #5).

- [ ] **Step 5: Handle the missing-image case**

`lazymedia_post_image_url()` returns a local gradient placeholder URL when the post has no featured image; the card must render it at the same 16:9 ratio (Review Focus #3).

- [ ] **Step 6: Run QA** — expected `PASS card variants render`.

- [ ] **Step 7: Commit** — `git commit -m "feat: add card components with compact and video variants"`

---

### Task 6: Homepage Redesign

**Files:**
- Modify: `theme/lazymedia/front-page.php`, `theme/lazymedia/inc/blocks.php`, `theme/lazymedia/inc/query.php`
- Create: `theme/lazymedia/template-parts/hero.php`, `theme/lazymedia/template-parts/section-header.php`, `theme/lazymedia/assets/css/home.css`

**Interfaces:**
- Consumes: `lazymedia_post_card()` (Task 5), `lazymedia_section_accent()` (Task 3).
- Produces: `lazymedia_section_header( string $title, string $section, string $link = '' ): void`. `lazymedia_query_category( string $slug, int $limit = 3 ): WP_Query` must return fewer than `$limit` posts without failing (Review Focus #2).

- [ ] **Step 1: Add failing QA checks**

Assert `lm-hero`, five `.lm-block` headings (New Articles, Games, Hardware, Entertainment, Latest Articles), a tag-filter row, and that Games/Hardware/Entertainment each render at least one card.

- [ ] **Step 2: Run QA, confirm failure** — expected `FAIL homepage blocks: lm-hero missing`.

- [ ] **Step 3: Implement `lazymedia_section_header()`**

Heading in Barlow 600, with a 40px × 4px divider bar beneath it coloured by `lazymedia_section_accent( $section )`. Optional "read more" link right-aligned.

- [ ] **Step 4: Implement `template-parts/hero.php`**

Swiper-free slider: 3 slides, each a full-width 16:9 image with a title overlay and a gradient scrim. Slide width 100% on mobile, 60% from 768px. Dots plus previous/next arrows. No external slider library.

- [ ] **Step 5: Recompose `front-page.php`**

Hero → a sticky sub-nav with tag filter pills (radius 8px, `3px 15px`, active pill filled `--lm-accent` with white text) → New Articles 3-column grid → Games / Hardware / Entertainment blocks each 3 columns → Latest Articles. Every block gets its own section accent from the spec table.

- [ ] **Step 6: Wire the filter pills to `assets/js/lazymedia.js`**

Pills set `?lm_tag=<tag>` on the New Articles grid only; trending/hero/latest are unaffected. Clicking the active pill clears the filter. A pill for the tag currently in `$_GET['lm_tag']` renders active on load.

- [ ] **Step 7: Run QA** — expected all homepage checks pass.

- [ ] **Step 8: Verify breakpoints manually** — load `/` at 1440, 1024 and 767 px; expect 3, 2 and 1 columns with no horizontal scroll (Review Focus #1).

- [ ] **Step 9: Commit** — `git commit -m "feat: redesign homepage with hero slider and neon section dividers"`

---

### Task 7: Single Post Template

**Files:**
- Modify: `theme/lazymedia/single.php`
- Create: `theme/lazymedia/template-parts/related.php`, `theme/lazymedia/assets/css/single.css`

**Interfaces:**
- Consumes: `lazymedia_post_card()`, `lazymedia_section_header()`.
- Produces: `lazymedia_read_time( int $post_id ): int` returning estimated minutes.

- [ ] **Step 1: Add failing QA check**

```php
lm_qa( 'single post has meta and related', function () {
    $id = get_posts( array( 'numberposts' => 1, 'fields' => 'ids' ) )[0];
    $html = lm_qa_get( get_permalink( $id ) );
    lm_qa_assert( false !== strpos( $html, 'lm-single' ), 'single wrapper missing' );
    lm_qa_assert( false !== strpos( $html, 'lm-meta' ), 'meta row missing' );
    lm_qa_assert( false !== strpos( $html, 'lm-related' ), 'related section missing' );
} );
```

- [ ] **Step 2: Run QA, confirm failure.**

- [ ] **Step 3: Implement `lazymedia_read_time()`** — words ÷ 200, minimum 1.

- [ ] **Step 4: Rewrite `single.php`**

Breadcrumb → category chip → H1 → meta row (date · author · read time) → full-width hero image at radius 8px → body constrained to 800px with 1.75 line height → tag row → `lazymedia_related()`.

- [ ] **Step 5: Implement `lazymedia_related()`** — 3 posts sharing the most tags with the current post, excluding itself, via `WP_Query` with `tax_query` `IN` on the post's tag IDs and a fallback to same-category when there are no tags.

- [ ] **Step 6: Run QA** — expected `PASS single post has meta and related`.

- [ ] **Step 7: Commit** — `git commit -m "feat: rebuild the single article template"`

---

### Task 8: Archive, Search, Page and 404

**Files:**
- Create: `theme/lazymedia/archive.php`, `theme/lazymedia/search.php`, `theme/lazymedia/page.php`, `theme/lazymedia/404.php`, `theme/lazymedia/assets/js/search.js`
- Create: `theme/lazymedia/template-parts/archive-hero.php`
- Modify: `theme/lazymedia/functions.php`, `theme/lazymedia/inc/query.php`

**Interfaces:**
- Consumes: `lazymedia_post_card()`, `lazymedia_section_header()`.
- Produces: `lazymedia_archive_posts( WP_Query $q ): WP_Query` applying the shared paged query. `search.js` fetches `/search-index.json` and renders matches into `[data-lm-search-results]`.

- [ ] **Step 1: Add failing QA checks**

Assert archive shows `.lm-archive-hero`, 404 shows `.lm-404`, search page shows `[data-lm-search-results]`, and About renders `.lm-page`.

- [ ] **Step 2: Run QA, confirm failure.**

- [ ] **Step 3: Write `archive.php`** — hero band with the term name and description, the newest post as a large featured card, the rest in a 3-column grid, then `the_posts_pagination()`.

- [ ] **Step 4: Write `page.php` and `404.php`** — `page.php` uses a two-column layout with `.lm-page__body` at 800px. `404.php` shows the logo, a "404" in Barlow 700 at 96px, a search field, and links to the four main categories.

- [ ] **Step 5: Write `search.php` and `search.js`**

`search.php` renders the form plus an empty `[data-lm-search-results]` container. `search.js` reads `?s=`, fetches `search-index.json` once, filters on title and excerpt case-insensitively, and renders up to 24 cards. With zero matches it renders `.lm-empty` reading `Tidak ada hasil untuk "…"` (Review Focus #4). No JS means the static search degrades to a plain list of category links.

- [ ] **Step 6: Run QA** — expected all archive/search/page/404 checks pass.

- [ ] **Step 7: Commit** — `git commit -m "feat: rebuild archive, search, page and 404 templates"`

---

### Task 9: Real Photography and Branding Assets

**Files:**
- Create: `theme/lazymedia/assets/img/logo.svg`, `theme/lazymedia/assets/img/favicon.svg`
- Create: `seed/fetch-images.php`, `seed/image-map.php`
- Modify: `public/wp-content/uploads/` (regenerated, git-ignored)

**Interfaces:**
- Consumes: nothing.
- Produces: `seed/image-map.php` returning `array( '<post-slug>' => array( 'query' => '<search terms>', 'alt' => '<alt text>' ), ... )` for all 21 seeded posts. `seed/fetch-images.php` downloads each image, sets it as the post's featured image and writes `alt` text.

- [ ] **Step 1: Probe the image sources**

Run from PowerShell:
```powershell
curl.exe -s -o NUL -w "picsum:%{http_code}`n" "https://picsum.photos/1200/675"
curl.exe -s -o NUL -w "openverse:%{http_code}`n" "https://api.openverse.org/v1/images/?q=gaming&page_size=1"
curl.exe -s -o NUL -w "wikimedia:%{http_code}`n" "https://commons.wikimedia.org/w/api.php?action=query&list=search&srsearch=gaming&format=json"
```
Expected: pick the source that returns 200. Prefer a topical search API so a keyboard article gets a keyboard photo; fall back to Picsum seeded per slug for deterministic distinct photos. Record the chosen source in the README.

- [ ] **Step 2: Write `seed/image-map.php`** with a topical query and a specific alt text for each of the 21 slugs.

- [ ] **Step 3: Write `seed/fetch-images.php`**

For each slug: download to `wp-content/uploads/lazy-media/`, `wp_insert_attachment` with `post_type=attachment`, `wp_generate_attachment_metadata`, set `_wp_attachment_image_alt`, then `set_post_thumbnail`. Sizes registered in Task 12 must include `lm-card` (1200×675) and `lm-thumb` (300×200). Fail loudly on any non-200 so a partial run is visible.

- [ ] **Step 4: Draw `logo.svg` and `favicon.svg`**

Wordmark "LAZY" in Barlow 700 with "MEDIA" beneath, using `--lm-accent`; favicon is the same mark on a `#0F0F0F` rounded square. Original artwork, no traced elements from any existing logo.

- [ ] **Step 5: Run the fetcher, then QA** — expected `PASS featured images present` and every article showing a real photograph.

- [ ] **Step 6: Commit** — `git add seed/image-map.php seed/fetch-images.php theme/lazymedia/assets/img` then `git commit -m "feat: add real photography and Lazy Media brand marks"`

---

## Phase B — Static Export and Deploy

### Task 10: Static Exporter

**Files:**
- Create: `seed/export.php`, `scripts/export.ps1`

**Interfaces:**
- Consumes: the running site over HTTP at `home_url()`.
- Produces: `dist/` at the project root containing `index.html`, one `<path>/index.html` per route, `search-index.json`, and copies of every referenced asset. `scripts/export.ps1` runs the exporter then prints the file count and total size.

- [ ] **Step 1: Write the failing smoke check into `seed/qa.php`**

```php
lm_qa( 'dist export is complete', function () {
    $root = dirname( ABSPATH ) . '/dist';
    foreach ( array( 'index.html', 'search-index.json', 'about/index.html', 'category/games/index.html' ) as $f ) {
        lm_qa_assert( file_exists( $root . '/' . $f ), $f . ' missing from dist' );
    }
} );
```

- [ ] **Step 2: Run QA, confirm failure.**

- [ ] **Step 3: Implement `seed/export.php`**

- `lm_export_routes(): array` — home, all published posts, all pages, all category and tag archives with their paged URLs up to the last page, plus the 404 path. Deduplicate and sort longest-first so nested paths are written before their parents' index files.
- `lm_export_fetch( string $url ): string` — `wp_remote_get` with `timeout => 20`; throw with the URL and status on failure.
- `lm_export_rewrite( string $html, string $route ): string` — rewrite `http://localhost:8080/wp-content/...` to `/wp-content/...`, and make `src`/`href` root-relative. Also point the search form and nav at root-relative URLs so `dist/` works from any subpath.
- `lm_export_copy_assets(): array` — copy the theme's `assets/` tree and any referenced uploads into `dist/`, returning the list written.
- `lm_export_search_index(): void` — write `search-index.json` with `{slug,title,url,excerpt,image,categories,date}` per post.
- `lm_export_clean()` — remove `dist/` first so stale routes never survive a re-export.

- [ ] **Step 4: Write `scripts/export.ps1`**

Loads `.env` the same way `up.ps1` does, runs `docker compose run --rm wpcli wp eval-file /seed/export.php --path=/var/www/html --allow-root`, then prints the `dist/` file count and size in MB.

- [ ] **Step 5: Run the export, then QA** — expected `PASS dist export is complete`.

- [ ] **Step 6: Serve `dist/` and check every route**

```powershell
python -m http.server 8090 --directory dist
```
Expected: `/`, `/about/`, a post, `/category/games/`, `/search/?s=ace` all 200. Verify images load with no 404 in the network tab by scripting `curl.exe` over every `src` in the built HTML.

- [ ] **Step 7: Commit** — `git add seed/export.php scripts/export.ps1 seed/qa.php` then `git commit -m "feat: add static site exporter"`

---

### Task 11: Repository Layout and Hygiene

**Files:**
- Modify: `docker-compose.yml`, `.gitignore`, `vercel.json` (create), `README.md`
- Move: `public/wp-content/themes/lazymedia/` → `theme/lazymedia/`

**Interfaces:**
- Consumes: nothing.
- Produces: a repository whose tracked files are only `theme/`, `dist/`, `seed/`, `scripts/`, `docs/`, `README.md`, `.gitignore`, `.env.example`, `vercel.json`, `docker-compose.yml`.

- [ ] **Step 1: Add the theme volume to `docker-compose.yml`**

Mount `./theme/lazymedia:/var/www/html/wp-content/themes/lazymedia` on the `wordpress` and `wpcli` services, so the git-tracked theme is what WordPress actually serves.

- [ ] **Step 2: Move the theme and restart**

```powershell
Move-Item public\wp-content\themes\lazymedia theme\lazymedia
docker compose restart
```
Expected: site still HTTP 200 with the theme active.

- [ ] **Step 3: Write `.gitignore`**

```
/public/
/dist/.vercel
/.env
*.sql
Thumbs.db
.DS_Store
node_modules/
```

- [ ] **Step 4: Write `vercel.json`**

```json
{
  "$schema": "https://openapi.vercel.sh/vercel.json",
  "buildCommand": null,
  "outputDirectory": "dist",
  "cleanUrls": true,
  "trailingSlash": true
}
```

- [ ] **Step 5: Write `.env.example`** with the same keys as `.env` and placeholder values.

- [ ] **Step 6: Verify nothing sensitive is tracked**

```bash
git add -A
git status --short
git grep -nE 'admin12345|DB_PASSWORD|WORDPRESS_DB_PASSWORD' --cached
```
Expected: the grep returns nothing.

- [ ] **Step 7: Run QA** — expected all checks still pass after the move.

- [ ] **Step 8: Commit** — `git commit -m "chore: move theme out of WordPress core and scope the repo for deploy"`

---

### Task 12: Git Init and First Commit

**Files:** none — repository setup only.

**Interfaces:**
- Consumes: the clean layout from Task 11.
- Produces: a git repository on branch `main` with one commit, and a clean `git status`.

- [ ] **Step 1: Initialise and commit**

```bash
git init -b main
git add -A
git commit -m "feat: Lazy Media — themed WordPress site exported for static deploy"
```

- [ ] **Step 2: Verify the tracked tree is sane**

```bash
git ls-files | Measure-Object -Line
git ls-files | Select-String 'public/' 
```
Expected: no line matches `public/`, and the total is in the low hundreds, not thousands.

- [ ] **Step 3: Scan the full history for secrets**

```bash
git log --all -p | Select-String 'admin12345|DB_PASSWORD'
```
Expected: no matches.

---

### Task 13: GitHub and Vercel

This task requires the human partner to complete two OAuth logins. Everything
before the login is automated.

**Interfaces:**
- Consumes: the git repository from Task 12.
- Produces: a GitHub remote with the repo pushed, and a live Vercel URL.

- [ ] **Step 1: Install the CLIs**

```powershell
winget install --id GitHub.cli
npm install -g vercel
```

- [ ] **Step 2: Verify installation** — `gh --version`, `vercel --version`.

- [ ] **Step 3: Confirm the repo name and visibility with the partner**, then authenticate.

```powershell
gh auth login
vercel login
```
Both open a browser. The partner completes them.

- [ ] **Step 4: Create and push the repository**

```bash
gh repo create lazy-media --public --source=. --remote=origin --push
```

- [ ] **Step 5: Deploy to Vercel**

```powershell
vercel --prod
```
Expected: a `https://*.vercel.app` URL.

- [ ] **Step 6: Verify the live site**

Check that `/`, a post, `/category/games/`, `/about/` and `/search/?s=ace` all return 200 on the live URL, that fonts and images load with no 404, and that search returns results.

- [ ] **Step 7: Record the URL** in `README.md` under a `## Deploy` heading, then commit and push.

---

## Self-Review

**Spec coverage:** every spec section maps to a task — tokens (§3) → Tasks 2–3;
pages (§4) → Tasks 6–8; assets (§5) → Task 9; structure (§6) → Task 11;
risks (§7) → Review Focus and Tasks 10–11; human steps (§8) → Task 13;
completion criteria (§9) → Task 10 Step 6, Task 11 Step 6, Task 12 Step 3.

**Review Focus coverage:** all five lines have an owning task and step —
#1 Task 6 Step 8, #2 Task 6 Step 5, #3 Task 5 Step 5, #4 Task 8 Step 5,
#5 Task 5 Step 4.

**Proportion:** the plan is roughly the length of the spec and contains no
function bodies — signatures, assertions and commands only.
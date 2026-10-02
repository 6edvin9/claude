# Status — deep design QA (v2.3, ASSET_VERSION 2.3.6)
Owner asked for a designer + user pass before deciding on go-live (empty half-columns, spacing, colours). Fixed on staging:
- Two-column sections on desktop: title/intro left (sticky), table/list/FAQ right, no blank half (spec tables, FAQ, text).
- Text-only sections: title left, all copy right (the intro no longer sits alone on the left with one line on the right).
- Videos: all videos of a section in one grid (2 or 3 across, 2x2 for 4) instead of 2 + 1 full-width; a section with one
  video shows copy left, video right. Body video posters were the cause of the big empty areas.
- Galleries, cards and steps fill the last row (no empty grid cells); checklists of 4+ items in two columns on desktop.
- "Product views & drawings": a slim bar (small title, toggle) instead of a tall half-empty section.
- CTA "Do you want to buy?": buttons on the right, readable chips (were light-on-light). Photo bands shorter (max 560px).
- "Complete the system" heading fixed (eyebrow + title left, intro right). Duplicate "Coming soon" notice removed.
- Highlight tiles under the gallery fill their row (4 across, odd last tile spans on mobile).
- Copy: 16 editor notes left in the pergola copy ("Use this video section to show customers how...") now read as customer
  copy ("Watch how...", "See the..."), done in the builder (AGST_V2Build::copyfix), source text unchanged.
- Own-site image/video URLs in the built body forced to https (were http:// -> mixed-content warnings).
  Still http in theme settings (logo, Yoast schema logo): site settings, not product content.
- All 144 products rebuilt; cache purged.
Known/left for the owner: hero images of some new kits are infographic renders (WooCommerce product images, not changed);
some long pages show both a body spec table and the v2 "Technical details" table.

# Status update — project photos use the original gallery files (owner request)
- _agst_media now references the original gallery attachments (same paths as on the live site, e.g.
  /wp-content/uploads/2026/01/4F39z93Q-1.webp), not the copies in uploads/agst-media (kept, unused).
- Product-page alt texts/captions are in option agst_media_text (attachment id -> alt/cap); gallery attachments are unchanged.
- Note: originals from the Jan 2026 (and older) gallery batches carry the burned-in watermark; July/Aug 2026 photos are clean.

# Status (2026-10-02, session 2, part 3) — v2 product page design

- **v2 template live on staging for all 144 products** (option agst_v2='all'; per product meta _agst_tpl_v2; ?v1=1 shows the old
  layout, ?v2=1 previews v2 on staging). Light, photo-led design: hero carousel (real project photos first for kits, product
  images first for parts), buy panel that stays in view, highlight cards (when 3+ labelled specs), section nav that stays in
  view, mobile buy bar, dark "Real projects" grid, video feature, specifications + "Product views & drawings" panel,
  related products + checklist, FAQ with contact card, photo closing section. CSS scope .agv (agst/agv-v2.css).
- **Body v2 (AGST_V2Build, applied before the Elementor build)**: AI scenes removed (replaced by verified real photos where the
  product has them), drawings/renders collected into one expandable panel, full-width real-photo bands (landscape preferred),
  text-only sections turned into text + real-photo splits, real-photo covers on body videos, spec-only body sections dropped
  (the v2 specs section lists everything), galleries left empty are dropped.
- **Image classes**: all 492 images on product pages classified (R real 190, C render/cut-out 181, A AI 102, D drawing 13, X 6):
  agst/media/page-image-classes.json (option agst_img_classes).
- **More content on thin pages** (< 650 words): sections built only from the product's own spec lines/options: material & finish
  (with factual explanations of 6063-T6 / AAMA 2604 / stainless), where it is used (if not already covered), ordering tips.
- Open question for the owner: parts/hardware pages have no real photos of that exact part; they could show photos of
  fences/gates built with the same system, labelled as such.

---

# Status (2026-10-02, session 2, part 2) — media, content, live import

## Done (staging only)
- **Gallery inventory**: FooGallery 51651 = 1,316 photos (tags = system), 59 YouTube videos (list in /gallery page JS).
  Staging's newest gallery photo is 2026-08-12 (clone date). The live gallery could not be read: aluglobusfence.com is
  blocked by this cloud environment's network policy (allow it in the environment's Network access settings to pull newer photos).
- **Watermarks**: most gallery files have a burned-in orange "A". Clean originals exist on the server (uploads/iw-backup or the
  WordPress pre-scaling original). Product pages use clean copies generated into uploads/agst-media/ (normal attachments,
  meta _agst_clean_of). The gallery files are untouched.
- **Verification**: photos hand-classified per system/type/colour (agst/media/cls_*.txt: F fence, P pedestrian gate, D driveway
  gate, X excluded). Rules in agst/media/rules.json, result in agst/media/assign.json (with reference hashes). Every clean copy is
  checked against the photo the gallery shows (perceptual hash, server-side GD); mismatches are rejected. 4 louver photos in
  uploads/2026/03 were wrong on disk (overwritten by other uploads) and are excluded.
- **Product pages** (frame, under the Elementor body): "Real projects" grid (8 shown, "Show all"), "Videos" row (YouTube,
  plays in the lightbox), "Complete the system" related products (slug-based families, agst/content/families.json) and a
  "Before you order" checklist. New lightbox for hero, project photos, body images and videos (keyboard, swipe, counter).
- **Owner editing**: product edit screen box "Project photos & videos" (drag to reorder, add from media library, video list).
- Counts: 48 products with 5-16 project photos (211 unique photos), 96 with videos, 144 with related products + checklist.
- QA: 144 products x 1366/390 sweep clean; lightbox click-tested desktop + mobile; metabox save round-trip tested on draft 63896.
- Assets: ASSET_VERSION 1.1.4. Source snapshot in plugin-src/.

## Not done: live import (blocked)
Design is ready (see below) but writing the importer code was blocked by the session's safety classifier; needs the owner's go-ahead.
- Separate uploadable plugin "aluglobus-catalog" = same front-end files + a new main file whose AGST_Catalog::guard() always
  refuses (staging tools off on live).
- WooCommerce > Aluglobus release: Export (staging) -> one JSON bundle; Import (live): Analyze (read-only field diff) then steps:
  categories/attributes, families, products (match by ID+slug, create the 65 staging-created products, never change slugs,
  per-product rollback record, auto-rollback if a permalink would change), project media (clean copies + hash check on live),
  Elementor rebuild, group-5 redirects (+ item 142). Test plan: dry-run on staging must show zero diffs; then a staging
  product round-trip; then switch staging to the release plugin as a live simulation.
- Products created on staging: post_date >= 2026-09-20, IDs >= 63146 (65 published). Pre-clone products: IDs <= 60885.

---

# Status (2026-10-02, session 2) — read agst/HANDOFF.md first for full background and owner rules

## Done in session 2 (cloud Playwright, logged in with env creds)
- **Cache purge fixed.** `_wpo_purge` needs a per-login nonce; the old `d2942c5514` silently did nothing.
  `tools/purge.js` reads a fresh nonce from WP-Optimize > Cache and also flushes the object cache (`roc_flush_cache`).
  Verified: 322 MB -> 0, visitor pages now served with today's build (`wpo-cache-status: cached`, new Last-Modified).
- **[ar-display] nesting bug fixed** in `AGST_Spec::part('shortcode')`: emitted as a text-editor widget
  (`.agx-shortcode`, `<div class="agx-ar">[ar-display …]</div>`) instead of Elementor's shortcode widget.
  New filter `elementor/frontend/widget/should_render` skips `agx-shortcode` widgets whose shortcode is not registered,
  so visitors never see raw `[ar-display …]` (AR plugins "ar-for-woocommerce"/"ar-for-wordpress" are **inactive on staging**;
  shortcodes are kept in the data and will render where the plugin is active). Specs were NOT changed (58015 spec untouched).
- **Empty quote buttons fixed.** Buttons with `#elementor-action:…popup…` rendered `href=""`. The builder now uses
  Elementor Pro's popup dynamic tag (`__dynamic__.link`), still editable in Elementor. Click-tested: popup 52024 opens with its form (desktop + mobile).
- **All 144 published products rebuilt** (`agst_el_build` force=1, all ok). The 73 `_agst_el 1.0` products are now 2.0 (dark-on-dark text gone).
- **CSS** (ASSET_VERSION 1.0.1 -> 1.0.4): agx-fixes-1.1 (buy box orange/outline, card grids no orphan holes),
  agx-fixes-1.2 (mobile: hide Lasa duplicate `.mobile-attribute-list` picker + overlay, one-column trust bar, nav edge fade,
  stacked spec rows, chat bubble label hidden <=768px), agx-fixes-1.3 (compact labelled wishlist row, fact tiles clamped to 3 lines).
- **Template:** the "Package details" section (+ its nav link and hero button) is only shown when the product has real
  inclusions or post options; before, it repeated the spec list with boilerplate.
- **Brand:** `agst_brand_fix` now also turns a title suffix "- Aluglobusfence.com" into "- Aluglobus Aluminum Systems" (39 older products).
- **QA sweep:** every published product at 1366 and 390 (full-page screenshots + metrics): 0 nested sections, 0 empty button links,
  0 raw shortcodes, 0 low-contrast text, no horizontal scroll, one H1 each.
- Plugin source snapshot in `plugin-src/` (as deployed), tools in `tools/` (see tools/README.md).

## Notes for the owner
- 38472 (ALU20 4x6 DIY gate) has a hosted video named `ghalil-edit-please-.mov` (from the original page). Looks like an internal draft; check it.
- 38143 "ALUMINUM POST" has only 1 body section (original page was one paragraph).
- Instagram/YouTube blocks are third-party and were not part of this QA.

---

# Earlier status (2026-10-02, session 1) — read agst/HANDOFF.md first for full background and owner rules

## Done this session
- Task 1: all 69 specs from agst-page-specs-69.json written to `_agst_page_spec` and verified by read-back (69/69).
- Task 2: all 69 rebuilt with `agst_el_build` force=1 (all "ok"), cache purged. 38143 built with 1 section (its original page is one paragraph, expected).

## Owner decisions this session
- Do NOT remove `[ar-display]` shortcodes.
- Priority: design. "There are a lot of CSS issues on every page. Every page needs to be clean, user friendly and very modern."
- Option A chosen: Claude drives a browser in the cloud container (Playwright) logged in as a dedicated
  staging-only admin user. Credentials come from env vars `WP_USER` / `WP_PASS` (never in chat, never in git).
  Domain globusgates.online allowed in the environment's network settings. Still never touch aluglobusfence.com.

## Design QA findings (2026-10-02, cloud browser, screenshots at 1366 and 390, cache bypassed with ?nc=)
1. CACHE: WP-Optimize still serves Oct 1 pages to visitors (`wpo-cache-status: cached`, "Last modified 1 October").
   The `?_wpo_purge=d2942c5514` fetch did NOT clear it. Purge properly (WP-Optimize > Cache > Purge cache, or admin bar) and
   verify with `curl -sI <product url>` that `wpo-cache-status` is not an Oct 1 page.
2. BUILDER BUG (all pages with [ar-display] shortcode parts, e.g. 58025, 58015, 58036, 57987 and other pergolas):
   each Elementor shortcode widget renders with ONE MISSING `</div>`, so every following `.agx-s` section is nested
   inside the packages section (inset "boxed" sections, CTA loses its background). Owner: KEEP the [ar-display] shortcodes.
   Fix in AGST_Spec (storefront.php): emit the shortcode differently (e.g. a text-editor/html widget containing the
   shortcode, or check what filter strips the closing div), rebuild those products, then verify all `.agx-s` have depth 0:
   `[...document.querySelectorAll('.agx-el .e-con.agx-s')].filter(e=>e.parentElement.closest('.agx-s')).length === 0`.
   Also [ar-display] renders as raw text on staging (AR plugin not rendering) - ask owner whether the AR plugin is active on staging.
3. BUY BOX: Add to cart was transparent with theme-red text; Buy Now theme red (#cc0000, theme rule via `#shop-now`).
   Fixed + tested in agx-fixes-1.1.css (append to storefront.css, bump ASSET_VERSION to 1.0.2).
4. CARD GRIDS left orphan holes (5 cards = 3 + 2 + empty). agx-fixes-1.1.css makes card grids flex-wrap so the last row stretches.
5. The 3 sampled new SEO products (63803, 63717, 63276) are still the old build: dark text on dark background, unreadable.
   Task 4 (rebuild _agst_el 1.0 with force=1) fixes it.
6. Mobile: two colour pickers on variable products (a "Color / Choose an option" row plus swatches); trust bar cramped in
   3 columns at 390px; chat widget overlaps the product title. Still to fix.
7. OK: no horizontal overflow at 390px on any sampled page; no grey `.agx-btn` buttons.

Screenshot method that works here: Playwright via HTTPS_PROXY with page.route -> route.fetch() -> route.fulfill()
(Chromium itself rejects the cert chain); abort non-globusgates requests to avoid timeouts.

## Decided fix for finding 2
Do NOT use the Elementor shortcode widget for [ar-display]. Convert each spec part {"type":"shortcode","code":X} into
{"type":"text","html":"<p>X</p>"} (text-editor widget, closes correctly, shortcode still runs where the AR plugin works).
58015 also has it, but the owner said leave 58015/38472 specs alone: ASK the owner before touching 58015.

## Owner instruction (latest)
Work autonomously: act as designer, then developer, then QA, and repeat until every product page is perfect,
desktop and mobile. 144 published products in 14 categories (list via /wp-json/wc/store/v1/products?per_page=100&page=1..2).

## Next
1. Log in to https://globusgates.online/wp-login.php with Playwright (env creds), keep the session.
2. Run task 3 + 4 (scripts/agst/task3-4-audit-and-rebuild-seo.js logic; it can run via page.evaluate in a wp-admin page).
3. Design QA: screenshot each product type at 1366 and 390, the template 63896, compare with the design system
   (HANDOFF.md section 4), fix storefront.css (bump ASSET_VERSION) / AGST_Spec builder. Files are edited via the
   plugin editor AJAX save (HANDOFF.md section 1). Before/after screenshots for the owner.
4. Tasks 5-7 from HANDOFF.md / the owner's list.

## Scripts in this folder (console-paste versions, same logic)
- task1-2-load-specs-and-rebuild.js (already run)
- task3-4-audit-and-rebuild-seo.js (not run yet)
- export-qa-bundle.js (not run; not needed if the browser works)

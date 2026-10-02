# Status (2026-10-02) — read agst/HANDOFF.md first for full background and owner rules

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

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

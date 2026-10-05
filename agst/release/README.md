# Aluglobus Catalog Release (live import plugin)

One plugin, `aluglobus-release`, carries everything built on staging (globusgates.online) to the live store:

- the new product page and shop page design;
- the reviewed catalog:
  - prices
  - 65 new products
  - variations
  - categories
  - drafts
  - redirects
  - SEO text
  - new photos

## On / off behaviour

| Action | What happens |
|---|---|
| **Upload + activate** | Nothing visible changes. Theme pages, products and prices stay exactly as they are. The plugin only creates its rollback table. |
| **Analyze** | Read-only. Lists every change (old → new) before anything is written. |
| **Apply** | Writes the catalog. Before every write the current value is saved in the journal (`wp_agxr_journal`). When Apply finishes, the new product and shop pages switch on. |
| **Deactivate** | **Full undo**, automatically: <ul><li>every saved value is put back: prices, products, categories, redirects, snippets, settings;</li><li>products and variations the release created go to the trash;</li><li>categories, tags and attributes it created are removed once unused;</li><li>the original Elementor pages show again (live's original bodies were never overwritten; the new bodies only ever lived in the plugin's own fields).</li></ul> |
| **Roll back** button | The same undo without deactivating. |
| **Delete plugin** | Keeps the journal table, so a reinstalled copy can still roll back. |

Two things stay after an undo:
- Images the release added stay in the media library (unused).
- Stock quantities are never touched by the release at all.

If the server stops a long deactivation part-way, the plugin stays active or shows a notice. Deactivate again, or press Roll back, and it carries on from where it stopped.

## Safety features

- **Analyze is read-only.** It lists every change (old value → new value) for every product, category and redirect before anything is written.
- **Apply needs a fresh Analyze** (from the last 24 hours) with no blocking problems. It also needs the ticked backup confirmation.
- **Never changes a URL.**
  - Slugs are never written.
  - When a published product's URL would change after a write, that product is restored immediately.
  - A new product whose URL is already taken is not created.
- **Edited on live since the staging copy (14 Sept 2026)?** Those products are held back unless you tick the option.
- **Stock is never written.** Stock quantity, stock management and backorders are never changed. Stock status isn't set where WooCommerce calculates it.
- **Matching:** existing objects are matched by ID **and** URL slug. A mismatch is skipped and reported.
- **Per-item undo:** every field is journaled before it is written and checked after. An item that fails is put back on the spot.
- **New products are never shown half-built.** They are created as drafts and published only when complete.
- **Nothing is deleted.**
  - Rollback moves products it created to the trash.
  - Images it added stay in the media library.
  - New categories are removed only if they are empty.
- **Locked:** two tabs cannot run steps at the same time.
- **No staging tools on live.** The staging catalog importer and every staging write tool are switched off in this build.

## Tested

A local copy built to look like live before the release ran two full rounds of Apply → check → deactivate:
- WordPress 6.5, WooCommerce 9.3
- 323 existing products in their pre-release state
- old categories, redirects and snippets

Each Apply wrote 4,352 values with 0 errors and created 65 products. Each deactivation put back every product, price, status, category, redirect, snippet and setting, plus the original bodies.

What remained differs only in WooCommerce-generated data: cached category counts, and the automatic variation summary text.

## Recommended procedure

1. **Rehearsal first.** Copy live to a second test site, e.g. with All-in-One WP Migration as before. Install the plugin there and run Analyze → Apply → check pages → Roll back → check pages. This is the only test against real live data.
2. **On live:** take a full backup (database and files) on the same day. Use your host's backup or UpdraftPlus.
3. Plugins → Add New → Upload `aluglobus-release.zip` → Activate.
   - The new design shows straight away.
   - Product bodies stay original until Apply.
4. WooCommerce → **Catalog Release** → **Analyze**. Read the report, especially warnings about products edited on live.
5. Tick the backup confirmation → **Apply release**. Then check the pages.
6. If anything is wrong:
   - **Design only:** deactivate the plugin.
   - **Data:** type ROLLBACK → **Roll back release**.
   - **Last resort:** restore the backup.

## Not included

Things created on staging that were not part of the catalog work stay out of the release:
- 12 pages
- 36 menu items
- 20 "projects"
- site settings (Yoast organisation name, chat widget text, maintenance plugin)

## Build

`sh build-release.sh <release-data.zip>` does three things:
1. copies the storefront runtime from `../plugin-src/`
2. adds the bundle and media exported on staging (WooCommerce → Catalog Release → Build bundle → Download data zip)
3. zips the plugin

Code layout:
- `inc/`: journal, overlay, bundle, export, import, admin
- `runtime/catalog.php`: the release replacement for the staging plugin's main class

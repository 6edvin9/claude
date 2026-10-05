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

## How "remove the plugin = back to the original" works

| Part | What happens when the plugin is deactivated |
|---|---|
| Product page design, /shop and category pages, new product bodies, photo galleries, related products, brand fix in titles | **Reverts at once.** The new bodies are stored in the plugin's own fields (`_agx_el_*`). While the plugin is active, Elementor reads and saves those instead of the originals. Live's original `_elementor_data` and descriptions are never overwritten. |
| Store data: new products, prices, product status, categories, redirects, two WPCode snippets, plugin settings | **Stays** (WordPress keeps this data whatever happens to plugins, and it must not flip back by itself). Every value is saved before it is changed. **Roll back** on the plugin page restores all of it. |

**Deleting** the plugin keeps the rollback record (database table `wp_agxr_journal`). Reinstall the plugin and you can still roll back.

## Safety features

- **Analyze is read-only.** It lists every change (old value → new value) for every product, category and redirect before anything is written.
- **Apply needs a fresh Analyze** (from the last 24 hours) with no blocking problems. It also needs the ticked backup confirmation.
- **Never changes a URL.**
  - Slugs are never written.
  - When a published product's URL would change after a write, that product is restored immediately.
  - A new product whose URL is already taken is not created.
- **Edited on live since the staging copy (14 Sept 2026)?** Those products are held back unless you tick the option.
- **Matching:** existing objects are matched by ID **and** URL slug. A mismatch is skipped and reported.
- **Per-item undo:** every field is journaled before it is written and checked after. An item that fails is put back on the spot.
- **New products are never shown half-built.** They are created as drafts and published only when complete.
- **Nothing is deleted.**
  - Rollback moves products it created to the trash.
  - Images it added stay in the media library.
  - New categories are removed only if they are empty.
- **Locked:** two tabs cannot run steps at the same time.
- **No staging tools on live.** The staging catalog importer and every staging write tool are switched off in this build.

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

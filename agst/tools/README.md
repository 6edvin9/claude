# QA / deploy tools (Node + Playwright, run from a cloud session)

Credentials come only from env vars `WP_USER` / `WP_PASS`; the session cookie is stored in `state.json`
next to the scripts (never commit it). Chromium does not trust the egress proxy CA, so every request is
fetched through Playwright's Node side (`route.fetch()` + `route.fulfill()`); video/media requests are aborted.

| Script | Use |
|---|---|
| `node -e "require('./lib').login()"` | log in (REST-style POST), saves state.json |
| `node getfiles.js aluglobus-staging-catalog/storefront.php ...` | download plugin files from the plugin editor into `src/` |
| `node save.js aluglobus-staging-catalog/storefront.css ...` | upload `src/` files via the plugin editor AJAX save, verifies read-back |
| `node build.js 1,2,3` | `agst_el_build` force=1 in batches |
| `node q.js purge.js` | **proper cache purge**: fresh per-user `_wpo_purge` nonce from the WP-Optimize page + `roc_flush_cache` (object cache). The old hard-coded `_wpo_purge=d2942c5514` nonce belonged to another login and silently did nothing. |
| `node shot.js [--desktop|--mobile] id ...` | full-page screenshots (1366 / 390) + metrics: nested `.agx-s`, empty button hrefs, raw shortcodes, low-contrast text, horizontal overflow |
| `python3 slice.py shots/X.png 800 2400` | downscale + slice for viewing |

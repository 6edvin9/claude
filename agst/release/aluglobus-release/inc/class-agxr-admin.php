<?php
if (!defined('ABSPATH')) { exit; }

/** WooCommerce > Catalog Release: analyze, apply, roll back (and on staging: build the bundle). */
final class AGXR_Admin {
	public static $runtime = false;   // design layer loaded on this request
	public static $available = false; // design layer can load (staging test plugin not active)

	static function boot() {
		add_action('admin_menu', function () {
			add_submenu_page('woocommerce', 'Catalog Release', 'Catalog Release', 'manage_options', 'agxr-release', [__CLASS__, 'page']);
		}, 60);
		add_action('wp_ajax_agxr', [__CLASS__, 'ajax']);
		add_action('wp_ajax_agxr_download', [__CLASS__, 'download']);
		add_filter('plugin_action_links_' . plugin_basename(AGXR_FILE), function ($links) {
			array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=agxr-release')) . '">Release</a>');
			return $links;
		});
		add_action('after_plugin_row_' . plugin_basename(AGXR_FILE), function () {
			$s = get_option('agxr_state');
			if (!is_array($s) || ($s['state'] ?? '') !== 'applied') { return; }
			echo '<tr class="plugin-update-tr active"><td colspan="4" class="plugin-update colspanchange"><div class="notice inline notice-warning notice-alt"><p>'
				. '<b>Deactivating this plugin undoes the catalog release:</b> prices, products, categories, redirects and settings go back to their saved original values, products the release created go to the trash, and the original product pages show again. Use <a href="' . esc_url(admin_url('admin.php?page=agxr-release')) . '">Catalog Release</a> to see the saved values.'
				. '</p></div></td></tr>';
		});
	}

	static function activate() {
		AGXR_Journal::install();
		AGXR_Overlay::clear_caches();
	}

	/**
	 * Deactivation = back to the original site:
	 *  - every store-data change made by Apply is rolled back from the journal (prices, products, categories,
	 *    redirects, snippets, settings; products the release created go to the trash),
	 *  - the original Elementor pages show again (the new bodies were only ever in the plugin's own fields),
	 *  - Elementor and page caches are cleared.
	 * If the server stops the request before the rollback finishes, the rest is kept in the journal: activate the
	 * plugin again and press Roll back (or deactivate again).
	 */
	static function deactivate() {
		// a rollback already running in another request (for example a repeated click) finishes on its own
		$locked = false;
		if (AGXR_Journal::ready() && self::applied_rows() > 0 && function_exists('WC') && ($locked = AGXR_Import::lock())) {
			@set_time_limit(0);
			ignore_user_abort(true);
			wp_raise_memory_limit('admin');
			$start = time();
			$r = ['left' => 0, 'done' => 0];
			do { $r = AGXR_Import::rollback_batch(200); } while ($r['left'] > 0 && $r['done'] > 0 && time() - $start < 240);
			if ($r['left'] > 0) { update_option('agxr_undo_incomplete', (int) $r['left'], false); } else { delete_option('agxr_undo_incomplete'); }
		}
		AGXR_Overlay::clear_caches();
		if ($locked) { AGXR_Import::unlock(); }
	}

	static function applied_rows() { global $wpdb; return AGXR_Journal::ready() ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . AGXR_Journal::table() . " WHERE state='applied'") : 0; }

	private static function can() { return current_user_can('manage_options') && current_user_can('manage_woocommerce'); }

	static function ajax() {
		check_ajax_referer('agxr', 'nonce');
		if (!self::can()) { wp_send_json_error(['message' => 'Administrator access required.'], 403); }
		@set_time_limit(300);
		wp_raise_memory_limit('admin');
		$op = sanitize_key($_POST['op'] ?? '');
		$step = sanitize_key($_POST['step'] ?? '');
		$offset = max(0, (int) ($_POST['offset'] ?? 0));
		$size = min(50, max(1, (int) ($_POST['size'] ?? 20)));
		try {
			switch ($op) {
				case 'analyze':
					if ($offset === 0 && $step === 'preflight') { delete_option('agxr_maps'); }
					$out = AGXR_Import::batch('analyze', $step, $offset, $size);
					break;
				case 'analyzed':
					$sum = json_decode(wp_unslash((string) ($_POST['summary'] ?? '')), true);
					update_option('agxr_analysis', ['at' => time(), 'bundle' => AGXR_Bundle::load()['checksum'], 'summary' => is_array($sum) ? $sum : []], false);
					$out = ['ok' => true];
					break;
				case 'apply':
					$a = get_option('agxr_analysis');
					if (!is_array($a) || ($a['bundle'] ?? '') !== AGXR_Bundle::load()['checksum'] || time() - (int) $a['at'] > DAY_IN_SECONDS) { throw new RuntimeException('Run Analyze first (within the last 24 hours).'); }
					if (!empty($a['summary']['block'])) { throw new RuntimeException('The last analysis found blocking problems; fix them and analyze again.'); }
					if (empty($_POST['backup'])) { throw new RuntimeException('Confirm that a full backup was taken today.'); }
					if (!AGXR_Import::lock()) { throw new RuntimeException('Another release step is running (or stopped less than 10 minutes ago).'); }
					AGXR_Import::$run = sanitize_key($_POST['run'] ?? '') ?: 'run' . time();
					AGXR_Import::$opts = ['include_edited' => !empty($_POST['include_edited'])];
					try { $out = AGXR_Import::batch('apply', $step, $offset, $size); } finally { AGXR_Import::unlock(); }
					break;
				case 'rollback':
					if (wp_unslash((string) ($_POST['confirm'] ?? '')) !== 'ROLLBACK') { throw new RuntimeException('Type ROLLBACK to confirm.'); }
					if (!AGXR_Import::lock()) { throw new RuntimeException('Another release step is running.'); }
					try { $out = AGXR_Import::rollback_batch(150); } finally { AGXR_Import::unlock(); }
					break;
				case 'export':
					$out = AGXR_Export::run();
					break;
				case 'selftest':
					if (!AGXR_Bundle::is_source()) { throw new RuntimeException('Self-test mode exists on the staging site only.'); }
					update_option('agxr_selftest', !empty($_POST['on']) ? 1 : 0, false);
					$out = ['on' => (bool) get_option('agxr_selftest')];
					break;
				default:
					throw new RuntimeException('Unknown operation.');
			}
		} catch (Throwable $e) {
			wp_send_json_error(['message' => $e->getMessage()], 400);
		}
		wp_send_json_success($out);
	}

	/** Staging: download the exported bundle and media as one zip. */
	static function download() {
		check_admin_referer('agxr-download');
		if (!self::can() || !AGXR_Bundle::is_source()) { wp_die('Not allowed.'); }
		$dir = AGXR_Export::dir();
		if (!is_file($dir . '/bundle.json.gz')) { wp_die('Build the bundle first.'); }
		$zip = $dir . '/release-data.zip';
		@unlink($zip);
		$z = new ZipArchive();
		if ($z->open($zip, ZipArchive::CREATE) !== true) { wp_die('Cannot create zip.'); }
		$z->addFile($dir . '/bundle.json.gz', 'data/bundle.json.gz');
		foreach (glob($dir . '/media/*') as $f) { $z->addFile($f, 'data/media/' . basename($f)); }
		$z->close();
		nocache_headers();
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="aluglobus-release-data.zip"');
		header('Content-Length: ' . filesize($zip));
		readfile($zip);
		exit;
	}

	static function page() {
		if (!self::can()) { return; }
		$state = get_option('agxr_state');
		$an = get_option('agxr_analysis');
		$bundle = null; $err = '';
		try { $bundle = AGXR_Bundle::load(); } catch (Throwable $e) { $err = $e->getMessage(); }
		$mode = AGXR_Bundle::is_source() ? 'staging (bundle source)' : (AGXR_Bundle::is_live() ? 'LIVE SITE' : 'copy / rehearsal site');
		echo '<div class="wrap agxr"><h1>Catalog Release ' . esc_html(AGXR_VERSION) . '</h1>';
		echo '<p><b>Site:</b> ' . esc_html(home_url()) . ' — <b>' . esc_html($mode) . '</b>. <b>New page design:</b> ' . (self::$runtime ? 'on' : (self::$available ? 'off (switches on when the release is applied)' : 'cannot load while the staging test plugin is active')) . '.</p>';
		echo '<div class="notice notice-info inline"><p><b>Undo:</b> deactivating this plugin rolls back everything Apply changed and shows the original pages again. Roll back below does the same without deactivating.</p></div>';
		if ((int) get_option('agxr_undo_incomplete')) { echo '<div class="notice notice-error inline"><p>The last deactivation could not finish the rollback (' . (int) get_option('agxr_undo_incomplete') . ' values left). Press Roll back below to finish it.</p></div>'; }
		if ($err) { echo '<div class="notice notice-warning"><p>' . esc_html($err) . '</p></div>'; }
		if ($bundle) {
			echo '<p><b>Bundle:</b> made ' . esc_html($bundle['created']) . ' on ' . esc_html($bundle['source']['home']) . ', ' . count($bundle['products']) . ' products, ' . count($bundle['redirects']) . ' redirects, ' . count($bundle['media']) . ' media files.</p>';
		}
		if (is_array($state)) { echo '<p><b>Last action:</b> ' . esc_html($state['state'] . ' at ' . $state['at']) . '</p>'; }
		$sum = AGXR_Journal::summary();
		if ($sum) {
			echo '<details><summary><b>Rollback journal</b> (saved original values)</summary><table class="widefat striped" style="max-width:700px"><thead><tr><th>Step</th><th>State</th><th>Values</th><th>From</th><th>To</th></tr></thead><tbody>';
			foreach ($sum as $r) { echo '<tr><td>' . esc_html($r['step']) . '</td><td>' . esc_html($r['state']) . '</td><td>' . (int) $r['n'] . '</td><td>' . esc_html($r['first']) . '</td><td>' . esc_html($r['last']) . '</td></tr>'; }
			echo '</tbody></table></details>';
		}

		echo '<h2>1. Analyze (read-only)</h2><p>Compares the bundle with this site and lists every change before anything is written.</p>';
		echo '<p><button class="button button-primary" id="agxr-analyze"' . ($bundle ? '' : ' disabled') . '>Analyze</button> ' . (is_array($an) ? '<span>Last analysis: ' . esc_html(human_time_diff((int) $an['at']) . ' ago') . '</span>' : '') . '</p>';
		echo '<div id="agxr-report"></div>';

		echo '<h2>2. Apply</h2><p>Runs the steps in order. Before each value is written its current value is saved, so everything can be rolled back. An item that fails, or whose product URL would change, is restored on the spot.</p>';
		echo '<p><label><input type="checkbox" id="agxr-backup"> I have a full backup of the database and files from today, and I know how to restore it.</label></p>';
		echo '<p><label><input type="checkbox" id="agxr-edited"> Also update products that were edited on this site after the staging copy (14 Sept 2026). Leave unticked to keep those as they are.</label></p>';
		echo '<p><button class="button button-primary" id="agxr-apply" disabled>Apply release</button></p><div id="agxr-apply-log"></div>';

		echo '<h2>3. Roll back</h2><p>Puts back every saved original value, newest first. Products created by the release go to the trash; images it added stay in the media library.</p>';
		echo '<p><input type="text" id="agxr-confirm" placeholder="Type ROLLBACK"> <button class="button" id="agxr-rollback">Roll back release</button></p><div id="agxr-rollback-log"></div>';

		if (AGXR_Bundle::is_source()) {
			$last = get_option('agxr_export_last');
			echo '<hr><h2>Staging: build the bundle</h2><p>Reads staging (no writes to products) and packs the catalog and new media for the release.</p>';
			echo '<p><button class="button" id="agxr-export">Build bundle</button> ';
			if (is_array($last)) { echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=agxr_download'), 'agxr-download')) . '">Download data zip</a> <span>Last build ' . esc_html($last['at']) . ': ' . esc_html(wp_json_encode($last)) . '</span>'; }
			echo '</p><div id="agxr-export-log"></div>';
			echo '<p><label><input type="checkbox" id="agxr-selftest" ' . checked((bool) get_option('agxr_selftest'), true, false) . '> Self-test: allow Apply on this staging site (testing only).</label></p>';
		}
		echo '</div>';
		wp_enqueue_script('agxr-admin', plugins_url('assets/admin.js', AGXR_FILE), [], AGXR_VERSION, true);
		wp_localize_script('agxr-admin', 'AGXR', ['url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('agxr'), 'steps' => AGXR_Import::STEPS, 'labels' => AGXR_Import::LABELS]);
		echo '<style>.agxr table td,.agxr table th{vertical-align:top}.agxr .agxr-step{margin:14px 0;border:1px solid #dcdcde;background:#fff;padding:10px 14px}.agxr .agxr-step h3{margin:0 0 6px}.agxr .lvl-block,.agxr .lvl-error{color:#b32d2e;font-weight:600}.agxr .lvl-warn{color:#996800}.agxr pre{white-space:pre-wrap;max-height:420px;overflow:auto;background:#f6f7f7;padding:8px}</style>';
	}
}

<?php
if (!defined('ABSPATH')) { exit; }

/** The release bundle (built on staging by AGXR_Export) and the id/URL maps used while importing it. */
final class AGXR_Bundle {
	const FORMAT = 1;
	/** Staging was copied from live on 14 Sept 2026; objects dated from this point were created on staging. */
	const CLONE_DATE = '2026-09-15 00:00:00';
	const SOURCE_HOSTS = ['globusgates.online', 'www.globusgates.online'];
	/** First product id created on staging (ids below existed on live when staging was copied). */
	const FIRST_STAGING_ID = 63146;
	const LIVE_HOSTS = ['aluglobusfence.com', 'www.aluglobusfence.com'];

	private static $data = null;

	static function host() { return strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)); }
	static function is_source() { return in_array(self::host(), self::SOURCE_HOSTS, true); }
	static function is_live() { return in_array(self::host(), self::LIVE_HOSTS, true); }

	static function dir() { return AGXR_DIR . '/data'; }
	static function file() { return self::dir() . '/bundle.json.gz'; }
	static function media_dir() { return self::dir() . '/media'; }
	static function exists() { return is_file(self::file()); }

	static function load() {
		if (self::$data !== null) { return self::$data; }
		if (!self::exists()) { throw new RuntimeException('No release bundle in this plugin (data/bundle.json.gz).'); }
		$raw = gzdecode((string) file_get_contents(self::file()));
		$d = $raw === false ? null : json_decode($raw, true);
		if (!is_array($d) || ($d['format'] ?? 0) !== self::FORMAT) { throw new RuntimeException('The release bundle is unreadable or from another format.'); }
		$sum = (string) ($d['checksum'] ?? '');
		$check = $d; unset($check['checksum']);
		if ($sum === '' || !hash_equals($sum, hash('sha256', wp_json_encode($check)))) { throw new RuntimeException('The release bundle checksum does not match; the file may be damaged. Re-upload the plugin.'); }
		return self::$data = $d;
	}

	/* ------------------------------------------------------------------ maps (staging id => live id) */

	static function maps() { $m = get_option('agxr_maps', []); return is_array($m) ? $m + ['att' => [], 'term' => [], 'post' => []] : ['att' => [], 'term' => [], 'post' => []]; }
	static function set_map($kind, $sid, $lid, $tax = '') {
		$m = self::maps();
		if ($kind === 'term') { $m['term'][$tax][(string) $sid] = (int) $lid; } else { $m[$kind][(string) $sid] = (int) $lid; }
		update_option('agxr_maps', $m, false);
	}
	static function att($sid) { if (!$sid) { return 0; } $m = self::maps(); return (int) ($m['att'][(string) $sid] ?? 0); }
	static function post($sid) { if (!$sid) { return 0; } $m = self::maps(); return (int) ($m['post'][(string) $sid] ?? 0); }
	static function term($tax, $slug) { $t = get_term_by('slug', $slug, $tax); return $t && !is_wp_error($t) ? (int) $t->term_id : 0; }

	/* ------------------------------------------------------------------ URL and id rewriting */

	/**
	 * Staging host => this site's host in any string (plain and JSON-escaped forms). The scheme is kept, so text
	 * that only differs by host (content copied from live) stays exactly as it is on live.
	 */
	static function urls($s) {
		if (!is_string($s) || $s === '') { return $s; }
		$host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
		$path = untrailingslashit((string) wp_parse_url(home_url(), PHP_URL_PATH));
		foreach (self::SOURCE_HOSTS as $h) {
			foreach (['https://', 'http://'] as $sc) {
				$s = str_replace($sc . $h, $sc . $host . $path, $s);
				$s = str_replace(str_replace('/', '\\/', $sc . $h), str_replace('/', '\\/', $sc . $host . $path), $s);
			}
		}
		$map = get_option('agxr_urlmap', []);
		if (is_array($map) && $map) {
			foreach ($map as $old => $new) { $s = str_replace(['/uploads/' . $old, '\\/uploads\\/' . str_replace('/', '\\/', $old)], ['/uploads/' . $new, '\\/uploads\\/' . str_replace('/', '\\/', $new)], $s); }
		}
		return $s;
	}

	/** Live attachment id for an uploads URL or path (by _wp_attached_file). */
	static function att_by_url($url) {
		$path = (string) parse_url(str_replace('\\/', '/', (string) $url), PHP_URL_PATH);
		$i = strpos($path, '/uploads/');
		if ($i === false) { return 0; }
		$rel = substr($path, $i + 9);
		$rel = preg_replace('~-\d+x\d+(?=\.[a-z0-9]+$)~i', '', $rel);
		$rel = preg_replace('~-scaled(?=\.[a-z0-9]+$)~i', '', $rel);
		global $wpdb;
		$id = (int) $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND (meta_value=%s OR meta_value=%s) LIMIT 1", $rel, preg_replace('~(\.[a-z0-9]+)$~i', '-scaled$1', $rel)));
		return $id;
	}

	/** Rewrite hosts in every string and remap {url,id} image objects (Elementor) to live attachment ids. */
	static function deep($v) {
		if (is_string($v)) { return self::urls($v); }
		if (!is_array($v)) { return $v; }
		$out = [];
		foreach ($v as $k => $w) { $out[$k] = self::deep($w); }
		if (isset($out['url'], $out['id']) && is_string($out['url']) && is_numeric($out['id']) && (int) $out['id'] > 0 && strpos($out['url'], '/uploads/') !== false) {
			$out['id'] = self::att((int) $v['id']) ?: self::att_by_url($out['url']);
		}
		return $out;
	}

	/** Elementor JSON string: rewrite hosts and attachment ids inside. */
	static function elementor($json) {
		$d = json_decode((string) $json, true);
		if (!is_array($d)) { return self::urls((string) $json); }
		return wp_json_encode(self::deep($d));
	}
}

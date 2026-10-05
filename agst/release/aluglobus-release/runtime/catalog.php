<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Live runtime of the storefront (product template, shop pages, media, related products).
 * storefront.php, preservation.php and variations.php are copied unchanged from the staging plugin
 * (see build-release.sh). This file replaces the staging plugin's main class with the read-only helpers the
 * storefront uses; the staging importer, its admin page and every staging write tool are not loaded on live.
 */
final class AGST_Catalog {
	const VERSION = '0.3.1';
	const ASSET_VERSION = AGXR_ASSET_VERSION;

	static function boot() {
		add_action('wp_enqueue_scripts', function () {
			if (function_exists('is_product') && is_product() && get_post_meta(get_queried_object_id(), '_agst_managed', true)) {
				wp_enqueue_style('agst-catalog', plugins_url('catalog.css', __FILE__), [], self::ASSET_VERSION);
			}
		});
	}

	static function manifest() {
		static $m;
		if (!$m) {
			$m = json_decode((string) file_get_contents(__DIR__ . '/manifest.json'), true);
			if (!is_array($m)) { $m = []; }
			if (class_exists('AGST_Fixes')) { $m = array_map(['AGST_Fixes', 'prepare_row'], $m); }
		}
		return $m;
	}

	static function row($key) { foreach (self::manifest() as $r) { if ($r['key'] === $key) { return $r; } } throw new RuntimeException('Unknown product key.'); }

	/** Staging-only tools call this before writing; on the release build it always refuses. */
	static function guard() { throw new RuntimeException('This tool is only available on the staging site.'); }

	/** Staging importer helpers: not available on the release build. */
	static function prior($key) { self::guard(); }
	static function snapshot($id, $price_id) { self::guard(); }
	static function assets($r) { self::guard(); }

	static function norm($s) { return trim(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')); }

	static function local_url($url) {
		$url = html_entity_decode(str_replace('\\/', '/', $url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$parts = wp_parse_url($url);
		if (!$parts) { return ''; }
		if (empty($parts['host'])) { return isset($url[0]) && $url[0] === '/' && substr($url, 0, 2) !== '//' ? $url : ''; }
		$host = strtolower($parts['host']);
		$own = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
		if (in_array($host, [$own, 'aluglobusfence.com', 'www.aluglobusfence.com', 'globusgates.online', 'www.globusgates.online'], true)) {
			$path = $parts['path'] ?? '/';
			$prefix = rtrim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
			if ($host !== $own && $prefix && strpos($path, $prefix . '/') !== 0) { $path = $prefix . $path; }
			return $path . (isset($parts['query']) ? '?' . $parts['query'] : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
		}
		return preg_match('~^https?://~i', $url) ? $url : '';
	}

	static function media($id) {
		$items = [];
		$add = function ($url, $alt = '') use (&$items) {
			$url = self::local_url($url);
			if (!$url) { return; }
			$path = (string) parse_url($url, PHP_URL_PATH);
			$type = '';
			if (preg_match('~\.(jpe?g|png|gif|webp|avif|svg)$~i', $path)) { $type = 'image'; }
			elseif (preg_match('~\.(mp4|webm|mov|m4v|ogv)$~i', $path)) { $type = 'video'; }
			elseif (preg_match('~https?://(?:www\.|player\.)?(?:youtube\.com|youtube-nocookie\.com|youtu\.be|vimeo\.com)/~i', $url)) { $type = 'embed'; }
			if ($type) {
				if (!isset($items[$url])) { $items[$url] = ['url' => $url, 'type' => $type, 'alt' => $alt]; }
				elseif ($alt && !$items[$url]['alt']) { $items[$url]['alt'] = $alt; }
			}
		};
		$attach = function ($aid) use ($add) {
			if ($aid && get_post_type($aid) === 'attachment') { $u = wp_get_attachment_url($aid); if ($u) { $add($u, get_post_meta($aid, '_wp_attachment_image_alt', true)); } }
		};
		$walk = null;
		$walk = function ($v, $depth = 0) use (&$walk, $add, $attach, &$items) {
			if ($depth > 35) { return; }
			if (is_array($v)) {
				foreach ($v as $k => $w) { if (in_array((string) $k, ['id', 'image_id', 'attachment_id'], true) && is_numeric($w)) { $attach((int) $w); } $walk($w, $depth + 1); }
				return;
			}
			if (!is_string($v)) { return; }
			$v = str_replace('\\/', '/', $v);
			$j = json_decode($v, true);
			if (is_array($j)) { $walk($j, $depth + 1); return; }
			$s = maybe_unserialize($v);
			if (is_array($s)) { $walk($s, $depth + 1); return; }
			if (preg_match_all('~<img\b[^>]*>~i', $v, $tags)) {
				foreach ($tags[0] as $tag) {
					if (preg_match('~\bsrc=["\x27]([^"\x27]+)~i', $tag, $src)) { preg_match('~\balt=["\x27]([^"\x27]*)~i', $tag, $alt); $add($src[1], html_entity_decode($alt[1] ?? '', ENT_QUOTES)); }
				}
			}
			if (preg_match_all('~(?:https?://|/wp-content/)[^\s<>"\x27\\\\]+~i', $v, $m)) { foreach ($m[0] as $u) { $add(rtrim($u, ');,')); } }
			if (preg_match_all('~<iframe\b[^>]*src=["\x27]([^"\x27]+)~i', $v, $frames)) {
				foreach ($frames[1] as $u) { $u = self::local_url($u); if ($u && !isset($items[$u])) { $items[$u] = ['url' => $u, 'type' => 'link', 'alt' => 'Original embedded media']; } }
			}
		};
		$p = wc_get_product($id);
		if (!$p) { return []; }
		$attach($p->get_image_id());
		foreach ($p->get_gallery_image_ids() as $aid) { $attach($aid); }
		$walk($p->get_description());
		$walk($p->get_short_description());
		foreach (get_post_meta($id) as $k => $v) {
			if (strpos($k, '_agst_') === 0 || strpos($k, '_agx_') === 0 || preg_match('/oembed|element_cache|elementor_css/', $k)) { continue; }
			$walk($v);
		}
		return array_values($items);
	}

	static function clear($id) {
		delete_post_meta($id, '_elementor_css');
		delete_post_meta($id, '_elementor_element_cache');
		wc_delete_product_transients($id);
		clean_post_cache($id);
	}
}

require_once __DIR__ . '/preservation.php';
require_once __DIR__ . '/variations.php';
require_once __DIR__ . '/storefront.php';
AGST_Catalog::boot();

/*
 * Staging write tools that live inside the shared storefront code (AJAX and admin-post handlers, spec workbench,
 * QA pages) are switched off on the release build. The product media box on the product edit screen stays.
 */
add_action('init', function () {
	global $wp_filter;
	foreach (array_keys($wp_filter) as $hook) {
		if (preg_match('~^(wp_ajax_|wp_ajax_nopriv_|admin_post_)agst_~', $hook)) { remove_all_actions($hook); }
	}
}, 0);
add_action('admin_menu', function () {
	foreach (['agst-page-specs', 'agst-spec-preview', 'agst-qa', 'agst-catalog'] as $slug) { remove_submenu_page('woocommerce', $slug); }
}, 999);

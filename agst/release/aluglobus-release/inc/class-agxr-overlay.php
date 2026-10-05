<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Reversible product bodies.
 *
 * The new Elementor body of a product is kept in its own meta (_agx_el_data and friends). While this plugin is
 * active, reads of the real Elementor meta return that copy and Elementor saves go into it, so the original
 * _elementor_data, page settings and description are never overwritten. Deactivate the plugin and Elementor
 * reads the untouched originals again.
 */
final class AGXR_Overlay {
	/** real key => overlay key */
	const KEYS = [
		'_elementor_data'          => '_agx_el_data',
		'_elementor_page_settings' => '_agx_el_page_settings',
		'_elementor_edit_mode'     => '_agx_el_edit_mode',
		'_elementor_template_type' => '_agx_el_template_type',
		'_elementor_version'       => '_agx_el_version',
	];
	/** Elementor caches that hold rendered output of whichever data was active. */
	const CACHES = ['_elementor_css', '_elementor_element_cache', '_elementor_page_assets'];

	private static $busy = false;
	private static $saving = [];
	private static $on = [];

	static function boot() {
		add_filter('get_post_metadata', [__CLASS__, 'read'], 1, 4);
		add_filter('update_post_metadata', [__CLASS__, 'write'], 1, 5);
		add_filter('add_post_metadata', [__CLASS__, 'add'], 1, 5);
		add_filter('delete_post_metadata', [__CLASS__, 'delete'], 1, 5);
		// Elementor also writes a plain-text copy of the body into post_content when it saves; keep the original.
		add_action('elementor/document/before_save', function ($doc) {
			if (!is_object($doc) || !method_exists($doc, 'get_main_id')) { return; }
			$id = (int) $doc->get_main_id();
			if (self::on($id)) { self::$saving[$id] = (string) get_post_field('post_content', $id, 'raw'); }
		}, 1);
		add_action('elementor/document/after_save', function ($doc) {
			if (is_object($doc) && method_exists($doc, 'get_main_id')) { unset(self::$saving[(int) $doc->get_main_id()]); }
		}, 99);
		add_filter('wp_insert_post_data', function ($data, $postarr) {
			$id = (int) ($postarr['ID'] ?? 0);
			if ($id && isset(self::$saving[$id])) { $data['post_content'] = wp_slash(self::$saving[$id]); }
			return $data;
		}, 99, 2);
	}

	/** Does this post have an overlay body? */
	static function on($id) {
		$id = (int) $id;
		if ($id <= 0 || self::$busy) { return false; }
		if (isset(self::$on[$id])) { return self::$on[$id]; }
		self::$busy = true;
		$on = get_post_type($id) === 'product' && metadata_exists('post', $id, '_agx_el_data');
		self::$busy = false;
		return self::$on[$id] = $on;
	}

	/** Called by the importer after it adds or removes an overlay body. */
	static function forget($id) { unset(self::$on[(int) $id]); }

	static function read($value, $id, $key, $single) {
		if (!isset(self::KEYS[$key]) || self::$busy || !self::on($id)) { return $value; }
		self::$busy = true;
		$v = get_post_meta($id, self::KEYS[$key], false);
		self::$busy = false;
		if ($v) { return $v; }
		if ($key === '_elementor_edit_mode') { return ['builder']; }
		if ($key === '_elementor_template_type') { return ['wp-post']; }
		if ($key === '_elementor_page_settings') { return [[]]; }
		return $value;
	}

	static function write($check, $id, $key, $value, $prev = '') {
		if (!isset(self::KEYS[$key]) || self::$busy || !self::on($id)) { return $check; }
		self::$busy = true;
		update_post_meta($id, self::KEYS[$key], wp_slash($value));
		self::$busy = false;
		return true;
	}

	static function add($check, $id, $key, $value, $unique = false) { return self::write($check, $id, $key, $value); }

	static function delete($check, $id, $key, $value, $all = false) {
		if ($all || !isset(self::KEYS[$key]) || self::$busy || !self::on($id)) { return $check; }
		// The body itself is never deleted through Elementor; other keys fall back to the defaults above.
		if ($key === '_elementor_data') { return true; }
		self::$busy = true;
		delete_post_meta($id, self::KEYS[$key]);
		self::$busy = false;
		return true;
	}

	/** Products that carry an overlay body. */
	static function ids() {
		global $wpdb;
		return array_map('intval', $wpdb->get_col("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key='_agx_el_data'"));
	}

	/**
	 * Drop rendered Elementor caches so pages are rebuilt from whichever data is now active
	 * (run on activation, deactivation, import and rollback). Only cache meta is removed.
	 */
	static function clear_caches($ids = null) {
		global $wpdb;
		$ids = $ids === null ? self::ids() : array_map('intval', (array) $ids);
		foreach (array_chunk($ids, 200) as $chunk) {
			if (!$chunk) { continue; }
			$in = implode(',', $chunk);
			$keys = "'" . implode("','", self::CACHES) . "'";
			$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($in) AND meta_key IN ($keys)");
			foreach ($chunk as $id) { clean_post_cache($id); }
		}
		if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
			try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch (Throwable $e) { /* cache only */ }
		}
		self::flush_page_cache();
	}

	static function flush_page_cache() {
		if (function_exists('wpo_cache_flush')) { try { wpo_cache_flush(); } catch (Throwable $e) {} }
		if (function_exists('rocket_clean_domain')) { try { rocket_clean_domain(); } catch (Throwable $e) {} }
		if (function_exists('w3tc_flush_all')) { try { w3tc_flush_all(); } catch (Throwable $e) {} }
		if (has_action('litespeed_purge_all')) { do_action('litespeed_purge_all'); }
		wp_cache_flush();
	}
}

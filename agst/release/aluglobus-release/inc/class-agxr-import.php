<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Importer. Every step works item by item:
 *  - analyze(): compares the bundle with this site and lists the changes and problems (no writes);
 *  - apply():   for each changed field, records the current value in the journal, writes, re-reads to verify,
 *               and undoes the item at once if anything fails (or if a product URL would change);
 *  - rollback(): replays the journal backwards.
 * Rules carried over from the staging work: never change a slug or URL, never delete a product (rollback moves
 * products it created to the trash), never touch objects that are not in the bundle.
 */
final class AGXR_Import {
	const STEPS = ['preflight', 'media', 'terms', 'products', 'variations', 'links', 'redirects', 'wpcode', 'options', 'finish'];
	const LABELS = [
		'preflight' => 'Checks', 'media' => 'Images and files', 'terms' => 'Categories and attribute values', 'products' => 'Products',
		'variations' => 'Product variations', 'links' => 'Related-product links', 'redirects' => 'Redirects', 'wpcode' => 'WPCode snippets',
		'options' => 'Plugin settings', 'finish' => 'Caches and counts',
	];
	const PROPS = ['regular_price', 'sale_price', 'sku', 'stock_status', 'manage_stock', 'stock_quantity', 'backorders', 'tax_status', 'tax_class', 'weight', 'length', 'width', 'height'];
	const PARENT_PROPS = ['catalog_visibility', 'featured', 'sold_individually', 'reviews_allowed', 'purchase_note', 'shipping_class', 'image', 'gallery', 'cats', 'tags', 'attributes', 'default_attributes'];
	const POST_FIELDS = ['post_title' => 'name', 'post_content' => 'description', 'post_excerpt' => 'short_description', 'post_status' => 'status', 'menu_order' => 'menu_order'];

	public static $run = '';
	public static $opts = [];

	/* ================================================================== items per step */

	static function items($step) {
		$b = AGXR_Bundle::load();
		switch ($step) {
			case 'preflight': case 'finish': return ['all'];
			case 'media': return array_keys($b['media']);
			case 'terms':
				$o = [];
				foreach ($b['attributes'] as $i => $a) { $o[] = 'attr:' . $i; }
				foreach ($b['terms'] as $tax => $list) {
					// parents first so a child can find its parent
					usort($list, function ($x, $y) { return ($x['parent'] === '' ? 0 : 1) <=> ($y['parent'] === '' ? 0 : 1); });
					foreach ($list as $t) { $o[] = 'term:' . $tax . ':' . $t['slug']; }
				}
				return $o;
			case 'products': case 'links': return array_keys($b['products']);
			case 'variations':
				$o = [];
				foreach ($b['products'] as $pi => $p) { foreach ($p['variations'] as $vi => $_) { $o[] = $pi . ':' . $vi; } }
				return $o;
			case 'redirects': return array_keys($b['redirects']);
			case 'wpcode': return array_keys($b['wpcode']);
			case 'options': return array_keys($b['options']);
		}
		return [];
	}

	/** Run analyze or apply on a slice of a step. */
	static function batch($mode, $step, $offset, $size) {
		if (!in_array($step, self::STEPS, true)) { throw new RuntimeException('Unknown step.'); }
		$items = self::items($step);
		$slice = array_slice($items, $offset, $size);
		$out = [];
		foreach ($slice as $key) {
			$fn = [__CLASS__, ($mode === 'apply' ? 'apply_' : 'an_') . $step];
			try {
				$r = call_user_func($fn, $key);
			} catch (Throwable $e) {
				$r = ['label' => (string) $key, 'changes' => [], 'issues' => [['level' => 'error', 'text' => $e->getMessage()]]];
			}
			if ($r) { $r['key'] = (string) $key; $out[] = $r; }
		}
		return ['results' => $out, 'next' => $offset + count($slice) < count($items) ? $offset + count($slice) : null, 'total' => count($items)];
	}

	/* ================================================================== small helpers */

	static function same($a, $b) { return self::norm($a) === self::norm($b); }
	static function norm($v) {
		if (is_bool($v)) { return $v ? '1' : '0'; }
		if ($v === null) { return ''; }
		if (is_int($v) || is_float($v)) { return (string) $v; }
		if (is_array($v)) { return wp_json_encode(self::sortdeep($v)); }
		return str_replace("\r\n", "\n", (string) $v);
	}
	static function sortdeep($v) { if (!is_array($v)) { return is_bool($v) ? ($v ? '1' : '0') : (is_numeric($v) ? (string) $v : $v); } $assoc = array_keys($v) !== range(0, count($v) - 1); if ($assoc) { ksort($v); } return array_map([__CLASS__, 'sortdeep'], $v); }
	static function short($v) { $s = is_scalar($v) || $v === null ? (string) $v : wp_json_encode($v); $s = wp_strip_all_tags($s); return mb_strlen($s) > 140 ? mb_substr($s, 0, 137) . '…' : $s; }
	static function change($field, $before, $after, $note = '') { return ['field' => $field, 'before' => self::short($before), 'after' => self::short($after), 'note' => $note]; }
	static function issue($level, $text) { return ['level' => $level, 'text' => $text]; }

	static function edited_on_live($id) {
		$p = get_post($id);
		return $p && $p->post_modified >= AGXR_Bundle::CLONE_DATE ? $p->post_modified : '';
	}

	/** Lock so two browser tabs cannot run steps at the same time. */
	static function lock() {
		$l = get_option('agxr_lock');
		if ($l && (time() - (int) $l) < 600) { return false; }
		update_option('agxr_lock', time(), false);
		return true;
	}
	static function unlock() { delete_option('agxr_lock'); }

	/* ================================================================== preflight */

	static function checks() {
		$b = AGXR_Bundle::load();
		$i = [];
		if (AGXR_Bundle::is_source() && !get_option('agxr_selftest')) { $i[] = self::issue('block', 'This is the staging site the bundle was made from. Install the plugin on a copy of live (rehearsal) or on live.'); }
		if (!function_exists('WC') || version_compare(WC_VERSION, '8.0', '<')) { $i[] = self::issue('block', 'WooCommerce 8 or newer must be active.'); }
		if (function_exists('get_woocommerce_currency') && get_woocommerce_currency() !== ($b['source']['currency'] ?? 'USD')) { $i[] = self::issue('block', 'Store currency differs from the staging store.'); }
		if (!defined('ELEMENTOR_VERSION')) { $i[] = self::issue('block', 'Elementor must be active (product bodies are Elementor content).'); }
		if (!AGXR_Admin::$runtime) { $i[] = self::issue('block', 'The new page design is not loaded (is the "Aluglobus Staging Catalog Test" plugin active? Deactivate it here).'); }
		global $wpdb;
		$gt = $wpdb->prefix . 'redirection_groups';
		if ($wpdb->get_var("SHOW TABLES LIKE '$gt'") !== $gt) { $i[] = self::issue('warn', 'The Redirection plugin is not installed: the redirect step will be skipped.'); }
		if (!AGXR_Journal::ready()) { $i[] = self::issue('block', 'The rollback journal table is missing (deactivate and activate the plugin).'); }
		$pl = (array) get_option('woocommerce_permalinks', []);
		if (($pl['product_base'] ?? '') === '') { $i[] = self::issue('warn', 'WooCommerce product permalink base is empty; check product URLs after the import.'); }
		foreach ($b['media'] as $m) {
			if (!empty($m['ship_file'])) {
				$f = AGXR_Bundle::media_dir() . '/' . $m['ship_file'];
				if (!is_file($f) || sha1_file($f) !== $m['sha1']) { $i[] = self::issue('block', 'Packaged image missing or damaged: ' . $m['file']); }
			}
		}
		return $i;
	}

	static function an_preflight($k) {
		$b = AGXR_Bundle::load();
		$p = $b['products'];
		$info = sprintf('Bundle made on %s from %s: %d products (%d new), %d variations, %d categories/values, %d redirects, %d media files (%d packaged).',
			$b['created'], $b['source']['home'], count($p), count(array_filter($p, function ($x) { return $x['new']; })),
			array_sum(array_map(function ($x) { return count($x['variations']); }, $p)), array_sum(array_map('count', $b['terms'])), count($b['redirects']), count($b['media']),
			count(array_filter($b['media'], function ($m) { return !empty($m['ship_file']); })));
		return ['label' => $info, 'changes' => [], 'issues' => self::checks()];
	}
	static function apply_preflight($k) {
		foreach (self::checks() as $c) { if ($c['level'] === 'block') { throw new RuntimeException($c['text']); } }
		return ['label' => 'Checks passed', 'changes' => [], 'issues' => []];
	}

	/* ================================================================== media */

	private static function media_target($m) {
		$live = get_post((int) $m['sid']);
		if ($live && $live->post_type === 'attachment' && get_post_meta($live->ID, '_wp_attached_file', true) === $m['file']) { return (int) $live->ID; }
		$by = AGXR_Bundle::att_by_url('/wp-content/uploads/' . $m['file']);
		if ($by) {
			if (empty($m['ship'])) { return $by; }
			$f = get_attached_file($by);
			$orig = function_exists('wp_get_original_image_path') ? wp_get_original_image_path($by) : $f;
			foreach ([$orig, $f] as $x) { if ($x && is_file($x) && sha1_file($x) === ($m['sha1'] ?? '')) { return $by; } }
		}
		$c = AGXR_Journal::created('attachment', $m['sid']);
		return $c && get_post($c) ? $c : 0;
	}

	static function an_media($k) {
		$m = AGXR_Bundle::load()['media'][$k];
		$id = self::media_target($m);
		$r = ['label' => $m['file'], 'changes' => [], 'issues' => []];
		if ($id) { AGXR_Bundle::set_map('att', $m['sid'], $id); return null; }
		if (empty($m['ship'])) { $r['issues'][] = self::issue('warn', 'Image used by the new pages is not in this site\'s media library: ' . $m['file']); return $r; }
		if (!empty($m['missing'])) { $r['issues'][] = self::issue('warn', 'File was missing on staging, cannot be added: ' . $m['file']); return $r; }
		$r['changes'][] = self::change('add file', '', $m['file']);
		return $r;
	}

	static function apply_media($k) {
		$m = AGXR_Bundle::load()['media'][$k];
		$id = self::media_target($m);
		if ($id) { AGXR_Bundle::set_map('att', $m['sid'], $id); return null; }
		if (empty($m['ship']) || !empty($m['missing'])) { return null; }
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$src = AGXR_Bundle::media_dir() . '/' . $m['ship_file'];
		$up = wp_upload_dir();
		$sub = trim(dirname($m['file']), './');
		$dir = trailingslashit($up['basedir']) . $sub;
		wp_mkdir_p($dir);
		$name = basename($m['ship_original'] ?: $m['file']);
		$name = wp_unique_filename($dir, $name);
		$dest = trailingslashit($dir) . $name;
		if (!copy($src, $dest)) { throw new RuntimeException('Could not copy ' . $m['file'] . ' into uploads.'); }
		$jid = AGXR_Journal::record(self::$run, 'media', 'attachment', 0, $m['sid'], '__created', false, null, $m['file']);
		$aid = wp_insert_attachment(['post_mime_type' => $m['mime'], 'post_title' => $m['title'], 'post_excerpt' => $m['caption'], 'post_content' => $m['description'], 'post_status' => 'inherit'], $dest);
		if (is_wp_error($aid) || !$aid) { @unlink($dest); throw new RuntimeException('Could not add ' . $m['file'] . ' to the media library.'); }
		global $wpdb;
		$wpdb->update(AGXR_Journal::table(), ['oid' => $aid], ['id' => $jid]);
		update_post_meta($aid, '_wp_attachment_image_alt', wp_slash($m['alt']));
		update_post_meta($aid, '_agxr_source_id', (int) $m['sid']);
		wp_update_attachment_metadata($aid, wp_generate_attachment_metadata($aid, $dest));
		AGXR_Journal::mark($jid, 'applied');
		AGXR_Bundle::set_map('att', $m['sid'], $aid);
		$newrel = ltrim(trailingslashit($sub) . $name, '/');
		if ($newrel !== $m['file']) { $map = (array) get_option('agxr_urlmap', []); $map[$m['file']] = $newrel; update_option('agxr_urlmap', $map, false); }
		return ['label' => $m['file'], 'changes' => [self::change('added', '', $newrel)], 'issues' => []];
	}

	/* ================================================================== attribute taxonomies and terms */

	private static function term_row($key) {
		list(, $tax, $slug) = explode(':', $key, 3);
		foreach (AGXR_Bundle::load()['terms'][$tax] as $t) { if ($t['slug'] === $slug) { return [$tax, $t]; } }
		throw new RuntimeException('Term not in bundle.');
	}

	private static function term_meta_value($k, $v) {
		if ($k === 'thumbnail_id') { return (string) (AGXR_Bundle::att((int) $v) ?: 0); }
		return AGXR_Bundle::deep($v);
	}

	/** Changes for one term: [field, before, after, existed]. */
	private static function term_diff($tax, $t, $live) {
		$c = [];
		if (!self::same($live->name, $t['name'])) { $c[] = ['name', $live->name, $t['name'], true]; }
		$desc = AGXR_Bundle::urls($t['description']);
		if (!self::same($live->description, $desc)) { $c[] = ['description', $live->description, $desc, true]; }
		$parent = $t['parent'] === '' ? 0 : AGXR_Bundle::term($tax, $t['parent']);
		if ($t['parent'] !== '' && !$parent) { throw new RuntimeException('Parent category "' . $t['parent'] . '" not found.'); }
		if ((int) $live->parent !== (int) $parent) { $c[] = ['parent', (int) $live->parent, (int) $parent, true]; }
		foreach ($t['meta'] as $mk => $mv) {
			if (strpos($mk, '_') === 0 && $mk !== '_agst_order') { continue; }
			$want = self::term_meta_value($mk, $mv);
			$has = metadata_exists('term', $live->term_id, $mk);
			$cur = $has ? get_term_meta($live->term_id, $mk, true) : null;
			if (!$has || !self::same($cur, $want)) { $c[] = ['meta:' . $mk, $cur, $want, $has]; }
		}
		return $c;
	}

	static function an_terms($key) {
		$b = AGXR_Bundle::load();
		if (strpos($key, 'attr:') === 0) {
			$a = $b['attributes'][(int) substr($key, 5)];
			if (wc_attribute_taxonomy_id_by_name($a['name'])) { return null; }
			return ['label' => 'Attribute ' . $a['label'], 'changes' => [self::change('create attribute', '', $a['label'])], 'issues' => []];
		}
		list($tax, $t) = self::term_row($key);
		$r = ['label' => ($tax === 'product_cat' ? 'Category ' : $tax . ' ') . $t['name'] . ' (' . $t['slug'] . ')', 'changes' => [], 'issues' => []];
		if (!taxonomy_exists($tax)) { $r['changes'][] = self::change('create', '', $t['slug'], 'after its attribute is created'); return $r; }
		$live = get_term_by('slug', $t['slug'], $tax);
		if (!$live) { $r['changes'][] = self::change('create', '', $t['name'] . ' under ' . ($t['parent'] ?: 'top level')); return $r; }
		foreach (self::term_diff($tax, $t, $live) as $c) { $r['changes'][] = self::change($c[0], $c[1], $c[2]); }
		return $r['changes'] ? $r : null;
	}

	static function apply_terms($key) {
		$b = AGXR_Bundle::load();
		if (strpos($key, 'attr:') === 0) {
			$a = $b['attributes'][(int) substr($key, 5)];
			if (wc_attribute_taxonomy_id_by_name($a['name'])) { return null; }
			$jid = AGXR_Journal::record(self::$run, 'terms', 'attribute', 0, $a['name'], '__created', false, null, $a['name']);
			$id = wc_create_attribute(['name' => $a['label'], 'slug' => $a['name'], 'type' => $a['type'], 'order_by' => $a['orderby'], 'has_archives' => (bool) $a['public']]);
			if (is_wp_error($id)) { AGXR_Journal::mark($jid, 'failed'); throw new RuntimeException($id->get_error_message()); }
			global $wpdb;
			$wpdb->update(AGXR_Journal::table(), ['oid' => $id], ['id' => $jid]);
			AGXR_Journal::mark($jid, 'applied');
			register_taxonomy(wc_attribute_taxonomy_name($a['name']), ['product']);
			return ['label' => 'Attribute ' . $a['label'], 'changes' => [self::change('created', '', $a['label'])], 'issues' => []];
		}
		list($tax, $t) = self::term_row($key);
		if (!taxonomy_exists($tax)) { register_taxonomy($tax, ['product']); }
		$live = get_term_by('slug', $t['slug'], $tax);
		$label = $t['name'] . ' (' . $t['slug'] . ')';
		if (!$live) {
			$parent = $t['parent'] === '' ? 0 : AGXR_Bundle::term($tax, $t['parent']);
			$jid = AGXR_Journal::record(self::$run, 'terms', 'term', 0, $tax . ':' . $t['slug'], '__created', false, null, $t['slug']);
			$res = wp_insert_term($t['name'], $tax, ['slug' => $t['slug'], 'description' => AGXR_Bundle::urls($t['description']), 'parent' => $parent]);
			if (is_wp_error($res)) { AGXR_Journal::mark($jid, 'failed'); throw new RuntimeException($res->get_error_message()); }
			$tid = (int) $res['term_id'];
			global $wpdb;
			$wpdb->update(AGXR_Journal::table(), ['oid' => $tid], ['id' => $jid]);
			AGXR_Journal::mark($jid, 'applied');
			if (get_term($tid, $tax)->slug !== $t['slug']) { throw new RuntimeException('New term got a different slug.'); }
			foreach ($t['meta'] as $mk => $mv) { if (strpos($mk, '_') === 0) { continue; } update_term_meta($tid, $mk, wp_slash(self::term_meta_value($mk, $mv))); }
			return ['label' => $label, 'changes' => [self::change('created', '', $t['slug'])], 'issues' => []];
		}
		$diff = self::term_diff($tax, $t, $live);
		if (!$diff) { return null; }
		$jids = [];
		$args = [];
		foreach ($diff as $c) {
			$jids[] = AGXR_Journal::record(self::$run, 'terms', 'term', $live->term_id, $tax . ':' . $t['slug'], $c[0], $c[3], $c[1], $c[2]);
			if (strpos($c[0], 'meta:') === 0) { update_term_meta($live->term_id, substr($c[0], 5), wp_slash($c[2])); }
			else { $args[$c[0]] = $c[2]; }
		}
		if ($args) {
			$res = wp_update_term($live->term_id, $tax, $args);
			if (is_wp_error($res)) { self::undo_rows($jids); throw new RuntimeException($res->get_error_message()); }
		}
		if (get_term($live->term_id, $tax)->slug !== $t['slug']) { self::undo_rows($jids); throw new RuntimeException('Category slug changed; undone.'); }
		foreach ($jids as $j) { AGXR_Journal::mark($j, 'applied'); }
		return ['label' => $label, 'changes' => array_map(function ($c) { return self::change($c[0], $c[1], $c[2]); }, $diff), 'issues' => []];
	}

	/* ================================================================== products */

	/** Live product id for a bundle product, or 0 when it must be created. Throws when it cannot be matched safely. */
	static function product_target($p) {
		if (!$p['new']) {
			$live = get_post((int) $p['sid']);
			if (!$live || $live->post_type !== 'product') { throw new RuntimeException('Not on this site (ID ' . $p['sid'] . ', ' . $p['slug'] . '): skipped.'); }
			if ($live->post_name !== $p['slug'] && !($p['post']['post_status'] !== 'publish' && $live->post_status !== 'publish')) { throw new RuntimeException('ID ' . $p['sid'] . ' has URL slug "' . $live->post_name . '" here but "' . $p['slug'] . '" on staging: skipped, check by hand.'); }
			return (int) $live->ID;
		}
		$c = AGXR_Journal::created('product', $p['sid']);
		if ($c && get_post($c)) { return $c; }
		$m = AGXR_Bundle::post($p['sid']);
		if ($m && get_post($m)) { return $m; }
		// self-test on the staging site itself: the product is the same post
		if (AGXR_Bundle::is_source()) { $g = get_post((int) $p['sid']); if ($g && $g->post_type === 'product' && $g->post_name === $p['slug']) { return (int) $g->ID; } }
		return 0;
	}

	/** Desired value of every product field (live ids/URLs). */
	static function desired($p, $variation = false) {
		$w = $p['wc'];
		$d = [];
		foreach (self::PROPS as $k) { if (array_key_exists($k, $w)) { $d['prop:' . $k] = $w[$k]; } }
		$d['prop:image'] = AGXR_Bundle::att((int) $w['image']);
		if ($variation) {
			$d['prop:attributes'] = (array) $w['attributes'];
			$d['prop:description'] = AGXR_Bundle::urls((string) $w['description']);
			$d['post:post_status'] = $p['status'];
			$d['post:menu_order'] = (int) $p['menu_order'];
		} else {
			foreach (['catalog_visibility', 'featured', 'sold_individually', 'reviews_allowed', 'purchase_note'] as $k) { $d['prop:' . $k] = $w[$k]; }
			$d['prop:shipping_class'] = (string) $w['shipping_class'];
			$d['prop:gallery'] = array_values(array_filter(array_map(function ($i) { return AGXR_Bundle::att((int) $i); }, (array) $w['gallery'])));
			$d['prop:cats'] = (array) $w['cats'];
			$d['prop:tags'] = (array) $w['tags'];
			$d['prop:attributes'] = (array) $w['attributes'];
			$d['prop:default_attributes'] = (array) $w['default_attributes'];
			foreach ($p['post'] as $f => $v) { $d['post:' . $f] = in_array($f, ['post_content', 'post_excerpt'], true) ? AGXR_Bundle::urls($v) : $v; }
		}
		foreach ((array) $p['meta'] as $k => $v) {
			if ($k === '_yoast_wpseo_primary_product_cat') { $v = is_array($v) && !empty($v['term_slug']) ? (string) AGXR_Bundle::term('product_cat', $v['term_slug']) : ''; }
			elseif ($k === '_agst_media' && is_array($v)) { $v['images'] = array_values(array_filter(array_map(function ($i) { return AGXR_Bundle::att((int) $i); }, (array) ($v['images'] ?? [])))); $v = AGXR_Bundle::deep($v); }
			else { $v = AGXR_Bundle::deep($v); }
			$d['meta:' . $k] = $v;
		}
		if (!$variation && !empty($p['overlay'])) {
			$d['meta:_agx_el_data'] = AGXR_Bundle::elementor($p['overlay']['data']);
			$d['meta:_agx_el_page_settings'] = AGXR_Bundle::deep((array) $p['overlay']['page_settings']);
			$d['meta:_agx_el_edit_mode'] = 'builder';
			$d['meta:_agx_el_template_type'] = 'wp-post';
			if ($p['overlay']['version'] !== '') { $d['meta:_agx_el_version'] = $p['overlay']['version']; }
		}
		return $d;
	}

	/** Current live value of one field. Returns [exists, value]. */
	static function current($id, $field, $wcp = null) {
		list($kind, $k) = explode(':', $field, 2);
		if ($kind === 'meta') { $has = metadata_exists('post', $id, $k); return [$has, $has ? get_post_meta($id, $k, true) : null]; }
		if ($kind === 'post') { $p = get_post($id); return [true, $k === 'menu_order' ? (int) $p->menu_order : $p->$k]; }
		$p = $wcp ?: wc_get_product($id);
		switch ($k) {
			case 'image': return [true, (int) $p->get_image_id('edit')];
			case 'gallery': return [true, array_map('intval', $p->get_gallery_image_ids('edit'))];
			case 'cats': return [true, self::slugs($p->get_category_ids('edit'), 'product_cat')];
			case 'tags': return [true, self::slugs($p->get_tag_ids('edit'), 'product_tag')];
			case 'shipping_class': return [true, (string) $p->get_shipping_class()];
			case 'attributes': return [true, $p->is_type('variation') ? (array) $p->get_attributes('edit') : AGXR_Export::attributes($p)];
			case 'default_attributes': return [true, (array) $p->get_default_attributes('edit')];
			case 'description': return [true, (string) $p->get_description('edit')];
		}
		$g = 'get_' . $k;
		return [true, $p->$g('edit')];
	}

	static function slugs($ids, $tax) { $o = []; foreach ((array) $ids as $i) { $t = get_term((int) $i, $tax); if ($t && !is_wp_error($t)) { $o[] = $t->slug; } } sort($o); return $o; }

	/** Set one WooCommerce field on a product object (not saved). */
	static function set_prop($p, $k, $v) {
		switch ($k) {
			case 'image': $p->set_image_id((int) $v); return;
			case 'gallery': $p->set_gallery_image_ids(array_map('intval', (array) $v)); return;
			case 'cats': $ids = []; foreach ((array) $v as $s) { $t = AGXR_Bundle::term('product_cat', $s); if (!$t) { throw new RuntimeException('Category "' . $s . '" missing.'); } $ids[] = $t; } $p->set_category_ids($ids); return;
			case 'tags': $ids = []; foreach ((array) $v as $s) { $t = AGXR_Bundle::term('product_tag', $s); if (!$t) { $n = wp_insert_term($s, 'product_tag', ['slug' => $s]); $t = is_wp_error($n) ? 0 : (int) $n['term_id']; } if ($t) { $ids[] = $t; } } $p->set_tag_ids($ids); return;
			case 'shipping_class': $t = $v === '' ? 0 : AGXR_Bundle::term('product_shipping_class', $v); $p->set_shipping_class_id($t); return;
			case 'attributes':
				if ($p->is_type('variation')) { $p->set_attributes((array) $v); return; }
				$list = [];
				foreach ((array) $v as $a) {
					$o = new WC_Product_Attribute();
					if ($a['tax']) {
						$tid = wc_attribute_taxonomy_id_by_name($a['name']);
						$ids = [];
						foreach ($a['options'] as $slug) { $t = AGXR_Bundle::term($a['name'], $slug); if (!$t) { throw new RuntimeException('Attribute value "' . $slug . '" missing in ' . $a['name'] . '.'); } $ids[] = $t; }
						$o->set_id($tid); $o->set_name($a['name']); $o->set_options($ids);
					} else { $o->set_id(0); $o->set_name($a['name']); $o->set_options($a['options']); }
					$o->set_position((int) $a['position']); $o->set_visible((bool) $a['visible']); $o->set_variation((bool) $a['variation']);
					$list[] = $o;
				}
				$p->set_props(['attributes' => $list]);
				return;
			case 'default_attributes': $p->set_default_attributes((array) $v); return;
			case 'description': $p->set_description($v); return;
		}
		$s = 'set_' . $k;
		$p->$s($v);
	}

	/** Field changes for one product or variation: list of [field, existed, before, after]. */
	static function diff($id, $want) {
		$wcp = wc_get_product($id);
		$c = [];
		foreach ($want as $f => $v) {
			list($has, $cur) = self::current($id, $f, $wcp);
			if ($f === 'prop:attributes' && !$wcp->is_type('variation')) { $cur = self::sortdeep($cur); $v = self::sortdeep($v); }
			if (!$has || !self::same($cur, $v)) { $c[] = [$f, $has, $cur, $v]; }
		}
		return $c;
	}

	static function an_products($k) {
		$p = AGXR_Bundle::load()['products'][$k];
		$r = ['label' => $p['post']['post_title'] . ' (' . ($p['new'] ? 'new' : 'ID ' . $p['sid']) . ')', 'changes' => [], 'issues' => []];
		$id = self::product_target($p);
		if (!$id) {
			$dup = get_page_by_path($p['slug'], OBJECT, 'product');
			if ($dup) { $r['issues'][] = self::issue('error', 'A product with the URL slug "' . $p['slug'] . '" already exists here (ID ' . $dup->ID . '): not created.'); return $r; }
			$r['changes'][] = self::change('create', '', $p['type'] . ' product, ' . $p['post']['post_status'] . ', /' . $p['slug'] . '/');
			return $r;
		}
		AGXR_Bundle::set_map('post', $p['sid'], $id);
		$wcp = wc_get_product($id);
		if ($wcp->get_type() !== $p['type']) { $r['issues'][] = self::issue('error', 'Product type here is ' . $wcp->get_type() . ', on staging ' . $p['type'] . ': skipped.'); return $r; }
		if (!$p['new'] && ($ed = self::edited_on_live($id))) { $r['issues'][] = self::issue('warn', 'Edited on this site after the staging copy was made (' . $ed . '). Held back unless you tick "include products edited here since the copy".'); }
		foreach (self::diff($id, self::desired($p)) as $c) { $r['changes'][] = self::change($c[0], $c[2], $c[3]); }
		return $r['changes'] || $r['issues'] ? $r : null;
	}

	static function apply_products($k) {
		$p = AGXR_Bundle::load()['products'][$k];
		$label = $p['post']['post_title'];
		$id = self::product_target($p);
		if (!$id) {
			if (get_page_by_path($p['slug'], OBJECT, 'product')) { throw new RuntimeException('URL slug "' . $p['slug'] . '" already used here: not created.'); }
			$class = WC_Product_Factory::get_product_classname(0, $p['type']);
			$wcp = new $class();
			$wcp->set_name($p['post']['post_title']);
			$wcp->set_slug($p['slug']);
			$wcp->set_status('draft');
			$jid = AGXR_Journal::record(self::$run, 'products', 'product', 0, $p['sid'], '__created', false, null, $p['slug']);
			$id = $wcp->save();
			if (!$id) { AGXR_Journal::mark($jid, 'failed'); throw new RuntimeException('Could not create the product.'); }
			global $wpdb;
			$wpdb->update(AGXR_Journal::table(), ['oid' => $id], ['id' => $jid]);
			AGXR_Journal::mark($jid, 'applied');
			if (get_post_field('post_name', $id) !== $p['slug']) { wp_trash_post($id); throw new RuntimeException('The new product did not get its URL slug; moved to trash.'); }
			update_post_meta($id, '_agxr_source_id', (int) $p['sid']);
			AGXR_Bundle::set_map('post', $p['sid'], $id);
			$want = self::desired($p);
			$status = $want['post:post_status'];
			$want['post:post_status'] = 'draft';
			self::write($id, $want, 'products', $p['sid'], true);
			// publish last, so a half-built product is never visible
			if ($status !== 'draft') { self::write($id, ['post:post_status' => $status], 'products', $p['sid'], true); }
			return ['label' => $label, 'changes' => [self::change('created', '', '/' . $p['slug'] . '/ (' . $status . ')')], 'issues' => []];
		}
		AGXR_Bundle::set_map('post', $p['sid'], $id);
		if (wc_get_product($id)->get_type() !== $p['type']) { throw new RuntimeException('Product type differs: skipped.'); }
		if (!$p['new'] && self::edited_on_live($id) && empty(self::$opts['include_edited'])) { return ['label' => $label, 'changes' => [], 'issues' => [self::issue('warn', 'Held back: edited on this site after the staging copy.')]]; }
		$done = self::write($id, self::desired($p), 'products', $p['sid'], false);
		return $done ? ['label' => $label, 'changes' => $done, 'issues' => []] : null;
	}

	/**
	 * Journal and write the changed fields of one product/variation, then verify.
	 * On any failure (or a changed URL of a published product) the item is restored from its journal rows.
	 */
	static function write($id, $want, $step, $skey, $created) {
		$wcp = wc_get_product($id);
		$otype = $wcp->is_type('variation') ? 'variation' : 'product';
		$diff = self::diff($id, $want);
		if (!$diff) { return []; }
		$before_url = get_post_status($id) === 'publish' ? get_permalink($id) : '';
		$jids = [];
		try {
			$post = []; $props = []; $meta = [];
			foreach ($diff as $c) {
				$jids[] = AGXR_Journal::record(self::$run, $step, $otype, $id, $skey, $c[0], $c[1], $c[2], $c[3]);
				list($kind, $k) = explode(':', $c[0], 2);
				if ($kind === 'meta') { $meta[$k] = $c[3]; } elseif ($kind === 'post') { $post[$k] = $c[3]; } else { $props[$k] = $c[3]; }
			}
			foreach ($props as $k => $v) { self::set_prop($wcp, $k, $v); }
			foreach ($post as $k => $v) { $m = self::POST_FIELDS[$k]; $s = 'set_' . $m; $wcp->$s($v); }
			if ($props || $post) { $wcp->save(); }
			foreach ($meta as $k => $v) { update_post_meta($id, $k, wp_slash($v)); if (strpos($k, '_agx_el_') === 0) { AGXR_Overlay::forget($id); } }
			clean_post_cache($id);
			if (function_exists('wc_delete_product_transients')) { wc_delete_product_transients($id); }
			if ($otype === 'variation') { WC_Product_Variable::sync($wcp->get_parent_id()); }
			// URL check: a product that was published keeps exactly the same URL
			if ($before_url && get_post_status($id) === 'publish' && get_permalink($id) !== $before_url) { throw new RuntimeException('The product URL would change (' . $before_url . ' -> ' . get_permalink($id) . ').'); }
			// verify
			$left = self::diff($id, array_intersect_key($want, array_flip(array_column($diff, 0))));
			$notes = [];
			foreach ($left as $l) {
				if ($l[0] === 'post:post_content' || $l[0] === 'post:post_excerpt') { $notes[] = $l[0]; continue; } // WordPress may normalise HTML on save
				throw new RuntimeException('Field ' . $l[0] . ' did not save as expected.');
			}
			foreach ($jids as $j) { AGXR_Journal::mark($j, 'applied'); }
			return array_map(function ($c) use ($notes) { return self::change($c[0], $c[2], $c[3], in_array($c[0], $notes, true) ? 'saved with WordPress HTML clean-up' : ''); }, $diff);
		} catch (Throwable $e) {
			self::undo_rows($jids);
			throw new RuntimeException($e->getMessage() . ' Item restored.');
		}
	}

	/* ================================================================== variations */

	private static function var_row($key) { list($pi, $vi) = array_map('intval', explode(':', $key)); $p = AGXR_Bundle::load()['products'][$pi]; return [$p, $p['variations'][$vi]]; }

	private static function variation_target($parent_id, $v) {
		if (!$v['new']) {
			$live = get_post((int) $v['sid']);
			if (!$live || $live->post_type !== 'product_variation') { throw new RuntimeException('Variation ' . $v['sid'] . ' not on this site: skipped.'); }
			if ((int) $live->post_parent !== (int) $parent_id) { throw new RuntimeException('Variation ' . $v['sid'] . ' belongs to another product here: skipped.'); }
			return (int) $live->ID;
		}
		$c = AGXR_Journal::created('variation', $v['sid']);
		if ($c && get_post($c)) { return $c; }
		// same option combination already on the parent: reuse it
		$par = wc_get_product($parent_id);
		if ($par && $par->is_type('variable')) {
			foreach ($par->get_children('edit') as $cid) { $cv = wc_get_product($cid); if ($cv && self::same($cv->get_attributes('edit'), (array) $v['wc']['attributes'])) { return (int) $cid; } }
		}
		return 0;
	}

	static function an_variations($key) {
		list($p, $v) = self::var_row($key);
		$label = $p['post']['post_title'] . ' – ' . implode(', ', array_values((array) $v['wc']['attributes']));
		$r = ['label' => $label, 'changes' => [], 'issues' => []];
		try { $pid = self::product_target($p); } catch (Throwable $e) { return null; }
		if (!$pid) { $r['changes'][] = self::change('create', '', 'with its new product'); return $r; }
		$id = self::variation_target($pid, $v);
		if (!$id) { $r['changes'][] = self::change('create', '', $v['wc']['regular_price'] !== '' ? '$' . $v['wc']['regular_price'] : 'no price'); return $r; }
		foreach (self::diff($id, self::desired($v, true)) as $c) { $r['changes'][] = self::change($c[0], $c[2], $c[3]); }
		return $r['changes'] ? $r : null;
	}

	static function apply_variations($key) {
		list($p, $v) = self::var_row($key);
		$label = $p['post']['post_title'] . ' – ' . implode(', ', array_values((array) $v['wc']['attributes']));
		try { $pid = self::product_target($p); } catch (Throwable $e) { return null; }
		if (!$pid) { throw new RuntimeException('Parent product was not imported.'); }
		if (!$p['new'] && self::edited_on_live($pid) && empty(self::$opts['include_edited']) && !AGXR_Journal::created('product', $p['sid'])) {
			return ['label' => $label, 'changes' => [], 'issues' => [self::issue('warn', 'Held back with its product (edited here after the copy).')]];
		}
		$id = self::variation_target($pid, $v);
		if (!$id) {
			$var = new WC_Product_Variation();
			$var->set_parent_id($pid);
			$var->set_attributes((array) $v['wc']['attributes']);
			$var->set_status('private');
			$jid = AGXR_Journal::record(self::$run, 'variations', 'variation', 0, $v['sid'], '__created', false, null, $label);
			$id = $var->save();
			if (!$id) { AGXR_Journal::mark($jid, 'failed'); throw new RuntimeException('Could not create the variation.'); }
			global $wpdb;
			$wpdb->update(AGXR_Journal::table(), ['oid' => $id], ['id' => $jid]);
			AGXR_Journal::mark($jid, 'applied');
			update_post_meta($id, '_agxr_source_id', (int) $v['sid']);
			AGXR_Bundle::set_map('post', $v['sid'], $id);
			self::write($id, self::desired($v, true), 'variations', $v['sid'], true);
			return ['label' => $label, 'changes' => [self::change('created', '', $v['status'])], 'issues' => []];
		}
		AGXR_Bundle::set_map('post', $v['sid'], $id);
		$done = self::write($id, self::desired($v, true), 'variations', $v['sid'], false);
		return $done ? ['label' => $label, 'changes' => $done, 'issues' => []] : null;
	}

	/* ================================================================== links (upsells, cross-sells, grouped children) */

	private static function links_want($p) {
		$map = function ($ids) { $o = []; foreach ((array) $ids as $s) { $l = AGXR_Bundle::post((int) $s); if (!$l) { $g = get_post((int) $s); $l = $g && $g->post_type === 'product' && (int) $s < AGXR_Bundle::FIRST_STAGING_ID ? (int) $s : 0; } if ($l) { $o[] = $l; } } return $o; };
		return ['upsell_ids' => $map($p['wc']['upsells']), 'cross_sell_ids' => $map($p['wc']['cross_sells']), 'children' => $map($p['wc']['children'])];
	}

	static function an_links($k) {
		$p = AGXR_Bundle::load()['products'][$k];
		if (!$p['wc']['upsells'] && !$p['wc']['cross_sells'] && !$p['wc']['children']) { return null; }
		try { $id = self::product_target($p); } catch (Throwable $e) { return null; }
		if (!$id) { return ['label' => $p['post']['post_title'], 'changes' => [self::change('links', '', 'set with the new product')], 'issues' => []]; }
		$wcp = wc_get_product($id);
		$r = ['label' => $p['post']['post_title'], 'changes' => [], 'issues' => []];
		foreach (self::links_want($p) as $f => $v) { $g = 'get_' . $f; if ($f === 'children' && !$wcp->is_type('grouped')) { continue; } $cur = array_map('intval', $wcp->$g('edit')); if (!self::same($cur, $v)) { $r['changes'][] = self::change($f, $cur, $v); } }
		return $r['changes'] ? $r : null;
	}

	static function apply_links($k) {
		$p = AGXR_Bundle::load()['products'][$k];
		if (!$p['wc']['upsells'] && !$p['wc']['cross_sells'] && !$p['wc']['children']) { return null; }
		try { $id = self::product_target($p); } catch (Throwable $e) { return null; }
		if (!$id) { return null; }
		if (!$p['new'] && self::edited_on_live($id) && empty(self::$opts['include_edited']) && !AGXR_Journal::created('product', $p['sid'])) { return null; }
		$wcp = wc_get_product($id);
		$jids = []; $done = [];
		foreach (self::links_want($p) as $f => $v) {
			if ($f === 'children' && !$wcp->is_type('grouped')) { continue; }
			$g = 'get_' . $f; $s = 'set_' . $f;
			$cur = array_map('intval', $wcp->$g('edit'));
			if (self::same($cur, $v)) { continue; }
			$jids[] = AGXR_Journal::record(self::$run, 'links', 'product', $id, $p['sid'], 'link:' . $f, true, $cur, $v);
			$wcp->$s($v);
			$done[] = self::change($f, $cur, $v);
		}
		if (!$jids) { return null; }
		try { $wcp->save(); } catch (Throwable $e) { self::undo_rows($jids); throw $e; }
		foreach ($jids as $j) { AGXR_Journal::mark($j, 'applied'); }
		return ['label' => $p['post']['post_title'], 'changes' => $done, 'issues' => []];
	}

	/* ================================================================== redirects */

	private static function redir_ready() { global $wpdb; $t = $wpdb->prefix . 'redirection_items'; return $wpdb->get_var("SHOW TABLES LIKE '$t'") === $t; }
	private static function redir_live($r) { global $wpdb; return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}redirection_items WHERE url=%s AND match_type=%s ORDER BY id LIMIT 1", $r['url'], $r['match_type']), ARRAY_A); }
	const REDIR_FIELDS = ['action_data', 'action_code', 'action_type', 'status', 'regex', 'match_data', 'title'];

	private static function redir_target($r) { return AGXR_Bundle::urls((string) $r['action_data']); }

	static function an_redirects($k) {
		if (!self::redir_ready()) { return null; }
		$r = AGXR_Bundle::load()['redirects'][$k];
		$live = self::redir_live($r);
		$want = ['action_data' => self::redir_target($r)] + $r;
		$o = ['label' => $r['url'], 'changes' => [], 'issues' => []];
		if (!$live) { $o['changes'][] = self::change('add', '', $r['url'] . ' → ' . $want['action_data'] . ' (' . $r['action_code'] . ($r['status'] !== 'enabled' ? ', ' . $r['status'] : '') . ')'); return $o; }
		foreach (self::REDIR_FIELDS as $f) { if (!self::same($live[$f], $want[$f])) { $o['changes'][] = self::change($f, $live[$f], $want[$f]); } }
		return $o['changes'] ? $o : null;
	}

	private static function redir_group($name, $module) {
		global $wpdb;
		$t = $wpdb->prefix . 'redirection_groups';
		$id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE name=%s ORDER BY id LIMIT 1", $name));
		if ($id) { return $id; }
		$jid = AGXR_Journal::record(self::$run, 'redirects', 'redirect_group', 0, $name, '__created', false, null, $name);
		$wpdb->insert($t, ['name' => $name, 'tracking' => 1, 'module_id' => $module ?: 1, 'status' => 'enabled', 'position' => 0]);
		$id = (int) $wpdb->insert_id;
		if (!$id) { AGXR_Journal::mark($jid, 'failed'); throw new RuntimeException('Could not create redirect group ' . $name . '.'); }
		$wpdb->update(AGXR_Journal::table(), ['oid' => $id], ['id' => $jid]);
		AGXR_Journal::mark($jid, 'applied');
		return $id;
	}

	static function apply_redirects($k) {
		if (!self::redir_ready()) { return null; }
		global $wpdb;
		$r = AGXR_Bundle::load()['redirects'][$k];
		$t = $wpdb->prefix . 'redirection_items';
		$live = self::redir_live($r);
		$want = ['action_data' => self::redir_target($r)] + $r;
		if (!$live) {
			$gid = self::redir_group($r['group'], $r['group_module']);
			$jid = AGXR_Journal::record(self::$run, 'redirects', 'redirect', 0, $r['url'], '__created', false, null, $want['action_data']);
			$row = ['url' => $r['url'], 'match_url' => $r['match_url'], 'match_data' => $r['match_data'], 'regex' => $r['regex'], 'position' => $r['position'], 'last_count' => 0,
				'last_access' => '1970-01-01 00:00:00', 'group_id' => $gid, 'status' => $r['status'], 'action_type' => $r['action_type'], 'action_code' => $r['action_code'],
				'action_data' => $want['action_data'], 'match_type' => $r['match_type'], 'title' => $r['title']];
			if (!$wpdb->insert($t, $row)) { AGXR_Journal::mark($jid, 'failed'); throw new RuntimeException('Could not add redirect: ' . $wpdb->last_error); }
			$wpdb->update(AGXR_Journal::table(), ['oid' => (int) $wpdb->insert_id], ['id' => $jid]);
			AGXR_Journal::mark($jid, 'applied');
			return ['label' => $r['url'], 'changes' => [self::change('added', '', $want['action_data'])], 'issues' => []];
		}
		$set = []; $jids = []; $done = [];
		foreach (self::REDIR_FIELDS as $f) {
			if (self::same($live[$f], $want[$f])) { continue; }
			$jids[] = AGXR_Journal::record(self::$run, 'redirects', 'redirect', (int) $live['id'], $r['url'], $f, true, $live[$f], $want[$f]);
			$set[$f] = $want[$f];
			$done[] = self::change($f, $live[$f], $want[$f]);
		}
		if (!$set) { return null; }
		if ($wpdb->update($t, $set, ['id' => (int) $live['id']]) === false) { self::undo_rows($jids); throw new RuntimeException('Could not update redirect.'); }
		foreach ($jids as $j) { AGXR_Journal::mark($j, 'applied'); }
		return ['label' => $r['url'], 'changes' => $done, 'issues' => []];
	}

	/* ================================================================== WPCode snippets */

	private static function wpcode_live($s) {
		$p = get_post((int) $s['sid']);
		if (!$p || $p->post_type !== 'wpcode') { throw new RuntimeException('WPCode snippet ' . $s['sid'] . ' ("' . $s['title'] . '") not found here: skipped.'); }
		if (trim($p->post_title) !== trim($s['title'])) { throw new RuntimeException('WPCode snippet ' . $s['sid'] . ' has another title here ("' . $p->post_title . '"): skipped.'); }
		return $p;
	}

	static function an_wpcode($k) {
		$s = AGXR_Bundle::load()['wpcode'][$k];
		$p = self::wpcode_live($s);
		$r = ['label' => 'Snippet: ' . $s['title'], 'changes' => [], 'issues' => []];
		if (!self::same($p->post_content, $s['content'])) { $r['changes'][] = self::change('code', $p->post_content, $s['content']); }
		if ($p->post_status !== $s['status']) { $r['changes'][] = self::change('status', $p->post_status, $s['status']); }
		if ($p->post_modified >= AGXR_Bundle::CLONE_DATE) { $r['issues'][] = self::issue('warn', 'Snippet was edited here after the staging copy (' . $p->post_modified . '); it will be replaced by the staging version.'); }
		return $r['changes'] ? $r : null;
	}

	static function apply_wpcode($k) {
		$s = AGXR_Bundle::load()['wpcode'][$k];
		$p = self::wpcode_live($s);
		$set = ['ID' => $p->ID]; $jids = []; $done = [];
		if (!self::same($p->post_content, $s['content'])) { $jids[] = AGXR_Journal::record(self::$run, 'wpcode', 'post', $p->ID, 'wpcode:' . $s['sid'], 'post_content', true, $p->post_content, $s['content']); $set['post_content'] = wp_slash($s['content']); $done[] = self::change('code', $p->post_content, $s['content']); }
		if ($p->post_status !== $s['status']) { $jids[] = AGXR_Journal::record(self::$run, 'wpcode', 'post', $p->ID, 'wpcode:' . $s['sid'], 'post_status', true, $p->post_status, $s['status']); $set['post_status'] = $s['status']; $done[] = self::change('status', $p->post_status, $s['status']); }
		if (!$jids) { return null; }
		$res = wp_update_post($set, true);
		if (is_wp_error($res)) { self::undo_rows($jids); throw new RuntimeException($res->get_error_message()); }
		self::wpcode_cache();
		foreach ($jids as $j) { AGXR_Journal::mark($j, 'applied'); }
		return ['label' => 'Snippet: ' . $s['title'], 'changes' => $done, 'issues' => []];
	}

	static function wpcode_cache() {
		if (function_exists('wpcode')) { try { $w = wpcode(); if (isset($w->cache) && method_exists($w->cache, 'cache_all_loaded_snippets')) { $w->cache->cache_all_loaded_snippets(); } } catch (Throwable $e) {} }
	}

	/* ================================================================== options */

	private static function option_want($name, $v) {
		if ($name === 'agst_media_text') {
			$m = json_decode((string) $v, true);
			if (is_array($m)) { $o = []; foreach ($m as $aid => $x) { $l = AGXR_Bundle::att((int) $aid); if ($l) { $o[(string) $l] = $x; } } return wp_json_encode($o); }
		}
		return AGXR_Bundle::deep($v);
	}

	static function an_options($name) {
		$v = self::option_want($name, AGXR_Bundle::load()['options'][$name]);
		$cur = get_option($name, null);
		if ($cur !== null && self::same($cur, $v)) { return null; }
		return ['label' => 'Setting ' . $name, 'changes' => [self::change($name, $cur, $v)], 'issues' => []];
	}

	static function apply_options($name) {
		$v = self::option_want($name, AGXR_Bundle::load()['options'][$name]);
		$cur = get_option($name, null);
		if ($cur !== null && self::same($cur, $v)) { return null; }
		$jid = AGXR_Journal::record(self::$run, 'options', 'option', 0, $name, $name, $cur !== null, $cur, $v);
		update_option($name, $v, false);
		AGXR_Journal::mark($jid, 'applied');
		return ['label' => 'Setting ' . $name, 'changes' => [self::change($name, $cur, $v)], 'issues' => []];
	}

	/* ================================================================== finish */

	static function an_finish($k) { return ['label' => 'After the import: Elementor and page caches are cleared and category counts recounted.', 'changes' => [], 'issues' => []]; }

	static function apply_finish($k) {
		self::refresh();
		update_option('agxr_state', ['state' => 'applied', 'at' => current_time('mysql'), 'run' => self::$run, 'bundle' => AGXR_Bundle::load()['checksum']], false);
		return ['label' => 'Caches cleared, counts updated', 'changes' => [], 'issues' => []];
	}

	static function refresh() {
		foreach (['product_cat', 'product_tag'] as $tax) {
			$ids = get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'tt_ids']);
			if (!is_wp_error($ids) && $ids) { wp_update_term_count_now($ids, $tax); }
		}
		if (function_exists('wc_delete_product_transients')) { wc_delete_product_transients(); }
		if (function_exists('_wc_term_recount')) { $t = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]); if (!is_wp_error($t)) { _wc_term_recount($t, get_taxonomy('product_cat'), true, false); } }
		AGXR_Overlay::clear_caches();
	}

	/* ================================================================== rollback */

	/** Undo specific journal rows (used when one item fails half way). */
	static function undo_rows($ids) {
		if (!$ids) { return; }
		global $wpdb;
		$rows = AGXR_Journal::rows('id IN (' . implode(',', array_map('intval', $ids)) . ')', [], 'DESC');
		self::restore($rows, 'failed');
	}

	/** Roll back the next slice of applied journal rows (newest first). Returns how many rows remain. */
	static function rollback_batch($size = 150) {
		$rows = AGXR_Journal::rows("state='applied'", [], 'DESC', $size);
		$notes = self::restore($rows, 'rolled_back');
		global $wpdb;
		$left = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . AGXR_Journal::table() . " WHERE state='applied'");
		if (!$left) { self::refresh(); update_option('agxr_state', ['state' => 'rolled_back', 'at' => current_time('mysql')], false); }
		return ['done' => count($rows), 'left' => $left, 'notes' => $notes];
	}

	/** Put the journal's "before" values back. Rows must be newest first. */
	static function restore($rows, $state) {
		global $wpdb;
		$notes = [];
		$objects = []; // product/variation id => [field => row] (oldest row wins, so iterate newest first and overwrite)
		foreach ($rows as $r) {
			try {
				switch ($r['otype']) {
					case 'product': case 'variation':
						if ($r['field'] === '__created') {
							if ($r['oid'] && get_post($r['oid'])) { wp_trash_post((int) $r['oid']); }
							$notes[] = 'Moved new ' . $r['otype'] . ' ' . $r['oid'] . ' (' . $r['skey'] . ') to the trash.';
							break;
						}
						$objects[(int) $r['oid']][$r['field']] = $r;
						break;
					case 'term':
						list($tax) = explode(':', $r['skey'], 2);
						if ($r['field'] === '__created') {
							$t = get_term((int) $r['oid'], $tax);
							if ($t && !is_wp_error($t)) {
								if ((int) $t->count === 0) { wp_delete_term((int) $r['oid'], $tax); $notes[] = 'Removed new empty term ' . $r['skey'] . '.'; }
								else { $notes[] = 'Kept new term ' . $r['skey'] . ' (still has products).'; }
							}
						} elseif (strpos($r['field'], 'meta:') === 0) {
							$k = substr($r['field'], 5);
							if ($r['existed']) { update_term_meta((int) $r['oid'], $k, wp_slash($r['before'])); } else { delete_term_meta((int) $r['oid'], $k); }
						} else {
							wp_update_term((int) $r['oid'], $tax, [$r['field'] => $r['before']]);
						}
						break;
					case 'attribute':
						if ($r['field'] === '__created' && $r['oid']) { $notes[] = 'Attribute ' . $r['skey'] . ' created by the release was kept (remove it under Products > Attributes if unused).'; }
						break;
					case 'attachment':
						if ($r['field'] === '__created') { $notes[] = 'Image ' . $r['after'] . ' added by the release was kept in the media library (ID ' . $r['oid'] . ').'; }
						break;
					case 'redirect':
						if ($r['field'] === '__created') { if ($r['oid']) { $wpdb->delete($wpdb->prefix . 'redirection_items', ['id' => (int) $r['oid']]); } }
						else { $wpdb->update($wpdb->prefix . 'redirection_items', [$r['field'] => $r['before']], ['id' => (int) $r['oid']]); }
						break;
					case 'redirect_group':
						if ($r['field'] === '__created' && $r['oid'] && !(int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}redirection_items WHERE group_id=%d", $r['oid']))) { $wpdb->delete($wpdb->prefix . 'redirection_groups', ['id' => (int) $r['oid']]); }
						break;
					case 'post':
						wp_update_post(['ID' => (int) $r['oid'], $r['field'] => $r['field'] === 'post_content' ? wp_slash($r['before']) : $r['before']]);
						if (strpos($r['skey'], 'wpcode:') === 0) { self::wpcode_cache(); }
						break;
					case 'option':
						if ($r['existed']) { update_option($r['skey'], $r['before'], false); } else { delete_option($r['skey']); }
						break;
				}
				AGXR_Journal::mark($r['id'], $state);
			} catch (Throwable $e) {
				$notes[] = 'Could not restore ' . $r['otype'] . ' ' . $r['oid'] . ' ' . $r['field'] . ': ' . $e->getMessage();
			}
		}
		foreach ($objects as $id => $fields) {
			try {
				if (!get_post($id)) { continue; }
				$wcp = wc_get_product($id);
				$save = false;
				foreach ($fields as $f => $r) {
					list($kind, $k) = explode(':', $f, 2);
					if ($kind === 'meta') {
						if ($r['existed']) { update_post_meta($id, $k, wp_slash($r['before'])); } else { delete_post_meta($id, $k); }
						if (strpos($k, '_agx_el_') === 0) { AGXR_Overlay::forget($id); }
					} elseif ($kind === 'post') {
						$m = self::POST_FIELDS[$k]; $s = 'set_' . $m; $wcp->$s($r['before']); $save = true;
					} elseif ($kind === 'link') {
						$s = 'set_' . $k; $wcp->$s((array) $r['before']); $save = true;
					} else {
						self::restore_prop($wcp, $k, $r['before']); $save = true;
					}
				}
				if ($save) { $wcp->save(); }
				if ($wcp->is_type('variation')) { WC_Product_Variable::sync($wcp->get_parent_id()); }
				clean_post_cache($id);
				if (function_exists('wc_delete_product_transients')) { wc_delete_product_transients($id); }
				foreach ($fields as $r) { AGXR_Journal::mark($r['id'], $state); }
			} catch (Throwable $e) {
				$notes[] = 'Could not restore product ' . $id . ': ' . $e->getMessage();
			}
		}
		return $notes;
	}

	/** Restore a WooCommerce field from its journal value (ids are this site's ids already). */
	private static function restore_prop($p, $k, $v) {
		switch ($k) {
			case 'cats': $p->set_category_ids(array_filter(array_map(function ($s) { return AGXR_Bundle::term('product_cat', $s); }, (array) $v))); return;
			case 'tags': $p->set_tag_ids(array_filter(array_map(function ($s) { return AGXR_Bundle::term('product_tag', $s); }, (array) $v))); return;
		}
		self::set_prop($p, $k, $v);
	}
}

<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Builds the release bundle on staging (read-only on the database): the reviewed catalog as it is on staging,
 * for the fields the importer is allowed to change, plus copies of the media files created on staging.
 */
final class AGXR_Export {
	/** Plugin meta copied with each product (prefix match), minus staging-only backups. */
	const META_PREFIX = '_agst_';
	const META_SKIP = ['_agst_el_original', '_agst_extra_update', '_agst_restored', '_agst_price_list', '_agst_tpl_v2'];
	const META_SKIP_PREFIX = ['_agst_spec_workbench_backup', '_agst_snapshot', '_agst_backup'];
	const YOAST = ['_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw', '_yoast_wpseo_primary_product_cat'];
	const OPTIONS = ['agst_families', 'agst_img_classes', 'agst_media_text', 'agst_v2', 'agst_full_shop_layout'];
	const WPCODE = [39281, 39726];
	const REDIRECT_GROUP = 'Price list cleanup 2026-09';

	private static $atts = [];

	static function dir() {
		$u = wp_upload_dir();
		$token = get_option('agxr_export_token');
		if (!$token) { $token = wp_generate_password(20, false, false); update_option('agxr_export_token', $token, false); }
		return trailingslashit($u['basedir']) . 'agxr-export-' . $token;
	}

	static function guard() {
		if (!current_user_can('manage_woocommerce') || !current_user_can('manage_options')) { throw new RuntimeException('Administrator access required.'); }
		if (!AGXR_Bundle::is_source()) { throw new RuntimeException('Export runs on the staging site only.'); }
	}

	static function run() {
		self::guard();
		@set_time_limit(600);
		wp_raise_memory_limit('admin');
		global $wpdb;
		self::$atts = [];
		$clone = AGXR_Bundle::CLONE_DATE;

		/* products: everything that existed on live at copy time, plus the products published on staging since */
		$ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status NOT IN('auto-draft','trash','inherit') AND (post_date < %s OR post_status='publish') ORDER BY ID", $clone));
		$products = [];
		foreach ($ids as $id) { $r = self::product((int) $id); if ($r) { $products[] = $r; } }

		/* categories and attribute terms */
		$terms = ['product_cat' => self::terms('product_cat')];
		$attr = [];
		foreach (wc_get_attribute_taxonomies() as $a) {
			$tax = wc_attribute_taxonomy_name($a->attribute_name);
			$attr[] = ['name' => $a->attribute_name, 'label' => $a->attribute_label, 'type' => $a->attribute_type, 'orderby' => $a->attribute_orderby, 'public' => (int) $a->attribute_public];
			if (taxonomy_exists($tax)) { $terms[$tax] = self::terms($tax); }
		}

		/* redirects (Redirection plugin) */
		$redirects = [];
		$gt = $wpdb->prefix . 'redirection_groups';
		if ($wpdb->get_var("SHOW TABLES LIKE '$gt'") === $gt) {
			$rows = $wpdb->get_results("SELECT i.*, g.name AS group_name, g.module_id AS group_module FROM {$wpdb->prefix}redirection_items i JOIN $gt g ON g.id=i.group_id ORDER BY i.id", ARRAY_A);
			foreach ($rows as $r) {
				$redirects[] = ['sid' => (int) $r['id'], 'url' => $r['url'], 'match_url' => $r['match_url'], 'match_data' => $r['match_data'], 'regex' => (int) $r['regex'], 'position' => (int) $r['position'],
					'group' => $r['group_name'], 'group_module' => (int) $r['group_module'], 'status' => $r['status'], 'action_type' => $r['action_type'], 'action_code' => (int) $r['action_code'],
					'action_data' => $r['action_data'], 'match_type' => $r['match_type'], 'title' => (string) $r['title']];
			}
		}

		/* WPCode snippets edited on staging */
		$wpcode = [];
		foreach (self::WPCODE as $wid) {
			$p = get_post($wid);
			if ($p && $p->post_type === 'wpcode') { $wpcode[] = ['sid' => $wid, 'title' => $p->post_title, 'content' => $p->post_content, 'status' => $p->post_status]; }
		}

		$options = [];
		foreach (self::OPTIONS as $o) { $v = get_option($o, null); if ($v !== null) { $options[$o] = $v; } }
		// agst_media_text is keyed by attachment id: note those attachments too
		$mt = json_decode((string) ($options['agst_media_text'] ?? ''), true);
		if (is_array($mt)) { foreach (array_keys($mt) as $aid) { self::att((int) $aid); } }

		/* media referenced by everything above */
		$media = [];
		$out = self::dir();
		wp_mkdir_p($out . '/media');
		@file_put_contents($out . '/.htaccess', "Require all denied\nDeny from all\n");
		@file_put_contents($out . '/index.php', '<?php // Silence.');
		$bytes = 0;
		foreach (self::$atts as $aid => $_) {
			$p = get_post($aid);
			if (!$p || $p->post_type !== 'attachment') { continue; }
			$rel = (string) get_post_meta($aid, '_wp_attached_file', true);
			$path = get_attached_file($aid, true);
			$new = $p->post_date >= $clone;
			$row = ['sid' => $aid, 'file' => $rel, 'mime' => $p->post_mime_type, 'title' => $p->post_title, 'caption' => $p->post_excerpt, 'description' => $p->post_content,
				'alt' => (string) get_post_meta($aid, '_wp_attachment_image_alt', true), 'date' => $p->post_date, 'ship' => $new];
			if ($new) {
				// ship the pre-scaling original when WordPress kept one, so live generates the same sizes
				$orig = function_exists('wp_get_original_image_path') ? wp_get_original_image_path($aid) : $path;
				$src = $orig && is_file($orig) ? $orig : $path;
				if (!$src || !is_file($src)) { $row['missing'] = true; $media[] = $row; continue; }
				$name = $aid . '-' . basename($src);
				copy($src, $out . '/media/' . $name);
				$row['ship_file'] = $name;
				$row['ship_original'] = basename($src) !== basename((string) $path) ? basename($src) : '';
				$row['sha1'] = sha1_file($src);
				$bytes += filesize($src);
			}
			$media[] = $row;
		}

		$bundle = [
			'format' => AGXR_Bundle::FORMAT,
			'created' => current_time('mysql'),
			'source' => ['home' => home_url(), 'clone_date' => $clone, 'wc' => defined('WC_VERSION') ? WC_VERSION : '', 'elementor' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '', 'currency' => get_woocommerce_currency()],
			'attributes' => $attr,
			'terms' => $terms,
			'media' => $media,
			'products' => $products,
			'redirects' => $redirects,
			'redirect_group' => self::REDIRECT_GROUP,
			'wpcode' => $wpcode,
			'options' => $options,
		];
		$bundle['checksum'] = hash('sha256', wp_json_encode($bundle));
		file_put_contents($out . '/bundle.json.gz', gzencode(wp_json_encode($bundle), 9));

		$counts = ['products' => count($products), 'new_products' => count(array_filter($products, function ($p) { return $p['new']; })),
			'variations' => array_sum(array_map(function ($p) { return count($p['variations']); }, $products)),
			'overlay_bodies' => count(array_filter($products, function ($p) { return !empty($p['overlay']); })),
			'terms' => array_sum(array_map('count', $terms)), 'redirects' => count($redirects), 'media' => count($media),
			'media_shipped' => count(array_filter($media, function ($m) { return !empty($m['ship_file']); })), 'media_mb' => round($bytes / 1048576, 1),
			'bundle_kb' => round(filesize($out . '/bundle.json.gz') / 1024)];
		update_option('agxr_export_last', $counts + ['at' => current_time('mysql')], false);
		return $counts;
	}

	/** Note an attachment id as used by the bundle. */
	private static function att($id) { $id = (int) $id; if ($id > 0) { self::$atts[$id] = true; } return $id; }

	/** Note attachments referenced by uploads URLs inside a string or array. */
	private static function scan($v) {
		if (is_array($v)) {
			foreach ($v as $k => $w) {
				if (is_array($w) && isset($w['url'], $w['id']) && is_numeric($w['id'])) { self::att((int) $w['id']); }
				self::scan($w);
			}
			return;
		}
		if (!is_string($v) || strpos($v, 'uploads') === false) { return; }
		if (preg_match_all('~/wp-content/uploads/[^\s"\'<>)\\\\]+\.(?:jpe?g|png|gif|webp|avif|svg|mp4|webm|mov|pdf)~i', str_replace('\\/', '/', $v), $m)) {
			foreach (array_unique($m[0]) as $u) { $a = AGXR_Bundle::att_by_url($u); if ($a) { self::att($a); } }
		}
	}

	private static function terms($tax) {
		$out = [];
		foreach (get_terms(['taxonomy' => $tax, 'hide_empty' => false]) as $t) {
			$parent = $t->parent ? get_term($t->parent, $tax) : null;
			$meta = [];
			foreach (get_term_meta($t->term_id) as $k => $vals) {
				if (strpos($k, 'product_count_') === 0) { continue; }
				$meta[$k] = maybe_unserialize($vals[0]);
			}
			if (!empty($meta['thumbnail_id'])) { self::att((int) $meta['thumbnail_id']); }
			$out[] = ['sid' => (int) $t->term_id, 'slug' => $t->slug, 'name' => $t->name, 'description' => $t->description,
				'parent' => $parent && !is_wp_error($parent) ? $parent->slug : '', 'meta' => $meta];
		}
		return $out;
	}

	private static function slugs($ids, $tax) {
		$o = [];
		foreach ((array) $ids as $id) { $t = get_term((int) $id, $tax); if ($t && !is_wp_error($t)) { $o[] = $t->slug; } }
		sort($o);
		return $o;
	}

	/** WooCommerce attributes as plain arrays (taxonomy attributes by term slug). */
	static function attributes($p) {
		$o = [];
		foreach ($p->get_attributes('edit') as $key => $a) {
			if (!is_object($a)) { continue; }
			$tax = $a->is_taxonomy();
			$opts = $tax ? self::slugs($a->get_options(), $a->get_name()) : array_values($a->get_options());
			$o[] = ['name' => $a->get_name(), 'tax' => $tax, 'options' => $opts, 'position' => (int) $a->get_position(), 'visible' => (bool) $a->get_visible(), 'variation' => (bool) $a->get_variation()];
		}
		return $o;
	}

	private static function wc($p) {
		$w = [
			'regular_price' => (string) $p->get_regular_price('edit'), 'sale_price' => (string) $p->get_sale_price('edit'),
			'sku' => (string) $p->get_sku('edit'), 'stock_status' => (string) $p->get_stock_status('edit'),
			'manage_stock' => (bool) $p->get_manage_stock('edit'), 'stock_quantity' => $p->get_stock_quantity('edit'),
			'backorders' => (string) $p->get_backorders('edit'), 'tax_status' => (string) $p->get_tax_status('edit'), 'tax_class' => (string) $p->get_tax_class('edit'),
			'weight' => (string) $p->get_weight('edit'), 'length' => (string) $p->get_length('edit'), 'width' => (string) $p->get_width('edit'), 'height' => (string) $p->get_height('edit'),
			'image' => self::att($p->get_image_id('edit')),
		];
		if (!$p->is_type('variation')) {
			$w += [
				'catalog_visibility' => (string) $p->get_catalog_visibility('edit'), 'featured' => (bool) $p->get_featured('edit'),
				'sold_individually' => (bool) $p->get_sold_individually('edit'), 'reviews_allowed' => (bool) $p->get_reviews_allowed('edit'),
				'purchase_note' => (string) $p->get_purchase_note('edit'),
				'gallery' => array_map([__CLASS__, 'att'], array_map('intval', $p->get_gallery_image_ids('edit'))),
				'cats' => self::slugs($p->get_category_ids('edit'), 'product_cat'), 'tags' => self::slugs($p->get_tag_ids('edit'), 'product_tag'),
				'shipping_class' => (string) $p->get_shipping_class(),
				'attributes' => self::attributes($p), 'default_attributes' => (array) $p->get_default_attributes('edit'),
				'upsells' => array_map('intval', $p->get_upsell_ids('edit')), 'cross_sells' => array_map('intval', $p->get_cross_sell_ids('edit')),
				'children' => $p->is_type('grouped') ? array_map('intval', $p->get_children('edit')) : [],
			];
		} else {
			$w['attributes'] = (array) $p->get_attributes('edit');
			$w['description'] = (string) $p->get_description('edit');
		}
		return $w;
	}

	private static function meta($id) {
		$m = [];
		foreach (get_post_meta($id) as $k => $vals) {
			$is_agst = strpos($k, self::META_PREFIX) === 0;
			if (!$is_agst && !in_array($k, self::YOAST, true)) { continue; }
			if (in_array($k, self::META_SKIP, true)) { continue; }
			foreach (self::META_SKIP_PREFIX as $pre) { if (strpos($k, $pre) === 0) { continue 2; } }
			$v = maybe_unserialize($vals[0]);
			if ($k === '_yoast_wpseo_primary_product_cat') { $t = get_term((int) $v, 'product_cat'); $v = $t && !is_wp_error($t) ? ['term_slug' => $t->slug] : ''; }
			if ($k === '_agst_media' && is_array($v)) { foreach ((array) ($v['images'] ?? []) as $aid) { self::att((int) $aid); } }
			self::scan($v);
			$m[$k] = $v;
		}
		ksort($m);
		return $m;
	}

	private static function product($id) {
		$p = wc_get_product($id);
		$post = get_post($id);
		if (!$p || !$post) { return null; }
		$r = [
			'sid' => $id, 'new' => $post->post_date >= AGXR_Bundle::CLONE_DATE, 'slug' => $post->post_name, 'type' => $p->get_type(), 'date' => $post->post_date,
			'post' => ['post_title' => $post->post_title, 'post_content' => $post->post_content, 'post_excerpt' => $post->post_excerpt, 'post_status' => $post->post_status, 'menu_order' => (int) $post->menu_order],
			'wc' => self::wc($p),
			'meta' => self::meta($id),
			'overlay' => null,
			'variations' => [],
		];
		self::scan($post->post_content);
		self::scan($post->post_excerpt);
		if (get_post_meta($id, '_agst_el', true)) {
			$data = (string) get_post_meta($id, '_elementor_data', true);
			$ps = get_post_meta($id, '_elementor_page_settings', true);
			self::scan(json_decode($data, true));
			$r['overlay'] = ['data' => $data, 'page_settings' => is_array($ps) ? $ps : [], 'version' => (string) get_post_meta($id, '_elementor_version', true)];
		}
		if ($p->is_type('variable')) {
			foreach ($p->get_children('edit') as $vid) {
				$v = wc_get_product($vid);
				$vp = get_post($vid);
				if (!$v || !$vp || in_array($vp->post_status, ['trash', 'auto-draft'], true)) { continue; }
				$r['variations'][] = ['sid' => (int) $vid, 'new' => $vp->post_date >= AGXR_Bundle::CLONE_DATE, 'status' => $vp->post_status, 'menu_order' => (int) $vp->menu_order, 'wc' => self::wc($v), 'meta' => self::meta($vid)];
			}
		}
		return $r;
	}
}

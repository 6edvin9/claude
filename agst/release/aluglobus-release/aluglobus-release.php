<?php
/**
 * Plugin Name: Aluglobus Catalog Release
 * Description: New product and shop pages plus the reviewed catalog update for Aluglobus Aluminum Systems. Deactivate to show the original pages again; catalog data changes are saved before every write and can be rolled back from WooCommerce > Catalog Release.
 * Version: 1.0.0
 * Author: Aluglobus Aluminum Systems
 * Requires PHP: 7.4
 * Requires at least: 6.2
 * WC requires at least: 8.0
 */
if (!defined('ABSPATH')) { exit; }

define('AGXR_VERSION', '1.0.0');
define('AGXR_DIR', __DIR__);
define('AGXR_FILE', __FILE__);
// Front-end asset version (storefront.css/js); bump with every CSS/JS change.
define('AGXR_ASSET_VERSION', 'r1.0.0');

require_once __DIR__ . '/inc/class-agxr-journal.php';
require_once __DIR__ . '/inc/class-agxr-overlay.php';
require_once __DIR__ . '/inc/class-agxr-bundle.php';
require_once __DIR__ . '/inc/class-agxr-export.php';
require_once __DIR__ . '/inc/class-agxr-import.php';
require_once __DIR__ . '/inc/class-agxr-admin.php';

register_activation_hook(__FILE__, ['AGXR_Admin', 'activate']);
register_deactivation_hook(__FILE__, ['AGXR_Admin', 'deactivate']);

/*
 * Storefront runtime (product template, shop pages, Elementor body overlay).
 * It shares class names with the staging test plugin, so it only loads when that plugin is not active
 * (on staging the release plugin then offers just the export and the importer self-test).
 */
add_action('plugins_loaded', function () {
	AGXR_Admin::boot();
	if (class_exists('AGST_Catalog') || !function_exists('WC')) { return; }
	require_once __DIR__ . '/runtime/catalog.php';
	AGXR_Admin::$runtime = true;
	AGXR_Overlay::boot();
}, 20);

<?php
/**
 * Plugin Name: Airtable WooCommerce Sync
 * Plugin URI:
 * Description: A plugin to sync variable products from Airtable to WooCommerce.
 * Version: 0.1.1
 * Author: AI Developer
 * Author URI:
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: airtable-woocommerce-sync
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'AIRTABLE_WOOCOMMERCE_SYNC_VERSION', '0.1.1' );
define( 'AIRTABLE_WOOCOMMERCE_SYNC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'AIRTABLE_WOOCOMMERCE_SYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) ); // Added for potential future use (e.g. JS/CSS)

// Include core classes
require_once AIRTABLE_WOOCOMMERCE_SYNC_PLUGIN_PATH . 'includes/class-airtable-api.php';
require_once AIRTABLE_WOOCOMMERCE_SYNC_PLUGIN_PATH . 'includes/class-woocommerce-product-manager.php';

// Include admin settings
require_once AIRTABLE_WOOCOMMERCE_SYNC_PLUGIN_PATH . 'includes/admin-settings.php';

// Main plugin class / load function (optional, but good for structure)
function aws_load_plugin() {
    // Can instantiate classes here if needed globally or add other hooks
    // For now, the admin-settings.php handles its own hooks.
    // The manual sync action will be handled within admin-settings.php for now.
}
add_action( 'plugins_loaded', 'aws_load_plugin' );

?>

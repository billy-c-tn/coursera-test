<?php
/**
 * Plugin Name: WooCommerce Product Importer (Airtable/Google Sheets)
 * Plugin URI:
 * Description: Imports variable products into WooCommerce from Airtable or Google Sheets using OAuth or secure connections.
 * Version: 0.1.0
 * Author: AI Developer
 * Author URI:
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-product-importer
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) or die( 'No direct script access allowed!' );

define( 'WPI_VERSION', '0.1.0' );
define( 'WPI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPI_PLUGIN_FILE', __FILE__ );

// Load Google API Client Library Autoloader
// This assumes Composer is used and the vendor directory is in the plugin's root.
if ( file_exists( WPI_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once WPI_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Fallback or error handling if the library isn't found.
    // For now, we can add an admin notice if it's missing,
    // but the WPI_Google_Sheets_Service class will throw an exception if Google_Client is not found.
    add_action( 'admin_notices', function() {
        if ( ! class_exists('Google_Client') && current_user_can('manage_options') ) {
            echo '<div class="notice notice-error"><p>';
            echo wp_kses_post( __( '<strong>WooCommerce Product Importer:</strong> The Google API Client Library is missing. Please install it via Composer or ensure it is correctly placed in the plugin\'s vendor directory.', 'woocommerce-product-importer' ) );
            echo '</p></div>';
        }
    });
}

// Include other necessary files
require_once WPI_PLUGIN_DIR . 'includes/class-wpi-google-sheets-service.php';
require_once WPI_PLUGIN_DIR . 'includes/class-wpi-airtable-service.php';
require_once WPI_PLUGIN_DIR . 'includes/class-wpi-woocommerce-product-manager.php'; // New line


if ( is_admin() ) {
    require_once WPI_PLUGIN_DIR . 'admin/class-wpi-admin-page.php';
    new WPI_Admin_Page();
}

// Optional: Activation/Deactivation hooks if needed later
// register_activation_hook( WPI_PLUGIN_FILE, 'wpi_activate_plugin' );
// register_deactivation_hook( WPI_PLUGIN_FILE, 'wpi_deactivate_plugin' );

// function wpi_activate_plugin() {
//     // Actions on activation
// }
// function wpi_deactivate_plugin() {
//     // Actions on deactivation
// }
?>

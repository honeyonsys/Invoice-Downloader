<?php
/**
 * Plugin Name: WooCommerce Bulk Invoice Downloader
 * Plugin URI:  https://floatingpivot.com/
 * Description: Download all WooCommerce order invoices as a single ZIP file containing PDFs.
 * Version:     1.0.0
 * Author:      MVT
 * Author URI:  https://floatingpivot.com/
 * Text Domain: wc-bulk-invoice-downloader
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Check if WooCommerce is active
 */
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

// Define plugin constants
define( 'WC_BID_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_BID_URL', plugin_dir_url( __FILE__ ) );

// Include required files
if ( file_exists( WC_BID_PATH . 'vendor/autoload.php' ) ) {
	require_once WC_BID_PATH . 'vendor/autoload.php';
}
require_once WC_BID_PATH . 'includes/class-wc-bulk-invoice-downloader.php';

// Initialize the plugin
function run_wc_bulk_invoice_downloader() {
	$plugin = new WC_Bulk_Invoice_Downloader();
	$plugin->init();
}
add_action( 'plugins_loaded', 'run_wc_bulk_invoice_downloader' );

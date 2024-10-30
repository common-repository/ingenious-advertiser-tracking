<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://i19s.com
 * @since             1.0.0
 * @package           Ingenious_Woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Ingenious Advertiser Tracking
 * Plugin URI:        https://wordpress.org/plugins/ingenious-advertiser-tracking
 * Description:       A tool to insert Ingenious page impressions and conversions tracking tags.
 * Version:           1.0.0
 * Author:            Ingenious
 * Author URI:        https://profiles.wordpress.org/i19s/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ingenious-woocommerce
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'INGENIOUS_WOOCOMMERCE_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-ingenious-woocommerce-activator.php
 */
function activate_ingenious_woocommerce() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ingenious-woocommerce-activator.php';
	Ingenious_Woocommerce_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-ingenious-woocommerce-deactivator.php
 */
function deactivate_ingenious_woocommerce() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ingenious-woocommerce-deactivator.php';
	Ingenious_Woocommerce_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_ingenious_woocommerce' );
register_deactivation_hook( __FILE__, 'deactivate_ingenious_woocommerce' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-ingenious-woocommerce.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_ingenious_woocommerce() {

	$plugin = new Ingenious_Woocommerce();
	$plugin->run();

}
run_ingenious_woocommerce();

<?php
/**
 * Plugin Name: Trust Badge for MyListing
 * Description: Request/approve dynamic trust badges for listings.
 * Version: 0.1.0
 * Author: Trust Badge Team
 * Text Domain: trust-badge
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TB_PLUGIN_VER', '0.1.0' );
define( 'TB_PLUGIN_FILE', __FILE__ );
define( 'TB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TB_PLUGIN_DIR . 'includes/class-tb-autoloader.php';
TB_Autoloader::init();

add_action( 'plugins_loaded', [ 'TB_Plugin', 'init' ] );

register_activation_hook( __FILE__, [ 'TB_Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'TB_Installer', 'deactivate' ] );

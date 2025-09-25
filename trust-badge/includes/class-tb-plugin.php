<?php
/**
 * Core plugin bootstrap.
 *
 * @package TrustBadge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TB_Plugin {

    /**
     * Initialise plugin components.
     */
    public static function init() {
        load_plugin_textdomain( 'trust-badge', false, dirname( plugin_basename( TB_PLUGIN_FILE ) ) . '/languages/' );

        TB_Settings::init();
        TB_CPT::init();
        TB_Request::init();
        TB_Rest::init();
        TB_Admin::init();
        TB_Frontend::init();
        TB_Badge::init();
        TB_Cron::init();
    }
}

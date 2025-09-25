<?php
/**
 * Activation/deactivation tasks.
 *
 * @package TrustBadge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TB_Installer {

    /**
     * Run on activation.
     */
    public static function activate() {
        TB_CPT::register_post_type();
        TB_CPT::register_statuses();
        flush_rewrite_rules();
        TB_Cron::activate();
    }

    /**
     * Run on deactivation.
     */
    public static function deactivate() {
        TB_Cron::deactivate();
        flush_rewrite_rules();
    }
}

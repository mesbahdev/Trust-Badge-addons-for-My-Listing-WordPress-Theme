<?php
/**
 * Cron tasks for Trust Badge.
 *
 * @package TrustBadge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TB_Cron {

    const HOOK_EXPIRE = 'tb_expire_badges';

    /**
     * Hook registration.
     */
    public static function init() {
        add_action( self::HOOK_EXPIRE, [ __CLASS__, 'expire_badges' ] );
    }

    /**
     * Schedule cron on activation.
     */
    public static function activate() {
        if ( ! wp_next_scheduled( self::HOOK_EXPIRE ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::HOOK_EXPIRE );
        }
    }

    /**
     * Clear cron on deactivation.
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled( self::HOOK_EXPIRE );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK_EXPIRE );
        }
    }

    /**
     * Expire badges that reached end of life.
     */
    public static function expire_badges() {
        $now = time();
        $query = new WP_Query( [
            'post_type'      => TB_CPT::POST_TYPE,
            'post_status'    => 'tb_approved',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => TB_Request::META_EXPIRES_AT,
                    'value'   => $now,
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ] );

        if ( ! $query->posts ) {
            return;
        }

        foreach ( $query->posts as $request_id ) {
            wp_update_post( [ 'ID' => $request_id, 'post_status' => 'tb_expired' ] );
            TB_Request::log_action( $request_id, 'expired' );
            do_action( 'tb_request_expired', $request_id );
        }
    }
}

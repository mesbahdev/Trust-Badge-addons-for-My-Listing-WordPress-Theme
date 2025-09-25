<?php
/**
 * Utilities for working with trust badge requests.
 *
 * @package TrustBadge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TB_Request {

    const META_CONTACT_NAME  = '_tb_contact_name';
    const META_CONTACT_EMAIL = '_tb_contact_email';
    const META_CONTACT_PHONE = '_tb_contact_phone';
    const META_DOCUMENTS     = '_tb_documents';
    const META_EXPIRES_AT    = '_tb_expires_at';
    const META_DECISION_NOTE = '_tb_decision_note';
    const META_TOKEN         = '_tb_token';
    const META_VERSION       = '_tb_version';
    const META_AUDIT_LOG     = '_tb_audit_log';

    /**
     * Hook registration.
     */
    public static function init() {
        add_action( 'before_delete_post', [ __CLASS__, 'maybe_cleanup_on_delete' ] );
    }

    /**
     * Get existing request for listing.
     *
     * @param int $listing_id Listing ID.
     * @return WP_Post|null
     */
    public static function get_for_listing( $listing_id ) {
        $query = new WP_Query( [
            'post_type'      => TB_CPT::POST_TYPE,
            'post_status'    => [ 'tb_pending', 'tb_approved', 'tb_rejected', 'tb_expired' ],
            'post_parent'    => $listing_id,
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'fields'         => 'all',
        ] );

        return $query->posts ? $query->posts[0] : null;
    }

    /**
     * Determine if request is active (approved and not expired).
     *
     * @param int $request_id Request ID.
     * @return bool
     */
    public static function is_active( $request_id ) {
        $status = get_post_status( $request_id );
        if ( 'tb_approved' !== $status ) {
            return false;
        }

        $expires = (int) get_post_meta( $request_id, self::META_EXPIRES_AT, true );

        return $expires && $expires > time();
    }

    /**
     * Ensure token exists for request.
     *
     * @param int $request_id Request ID.
     * @return string
     */
    public static function ensure_token( $request_id ) {
        $token = get_post_meta( $request_id, self::META_TOKEN, true );
        if ( ! $token ) {
            $token = wp_generate_password( 48, false, false );
            update_post_meta( $request_id, self::META_TOKEN, $token );
        }

        return $token;
    }

    /**
     * Append audit log entry.
     *
     * @param int    $request_id Request ID.
     * @param string $action Action keyword.
     * @param string $note Optional note.
     */
    public static function log_action( $request_id, $action, $note = '' ) {
        $log = get_post_meta( $request_id, self::META_AUDIT_LOG, true );
        if ( empty( $log ) ) {
            $log = [];
        }

        $log[] = [
            'time'   => current_time( 'mysql', true ),
            'actor'  => get_current_user_id(),
            'action' => sanitize_key( $action ),
            'note'   => sanitize_text_field( $note ),
        ];

        update_post_meta( $request_id, self::META_AUDIT_LOG, $log );
    }

    /**
     * Return audit log entries.
     *
     * @param int $request_id Request ID.
     * @return array
     */
    public static function get_audit_log( $request_id ) {
        $log = get_post_meta( $request_id, self::META_AUDIT_LOG, true );
        return is_array( $log ) ? $log : [];
    }

    /**
     * Delete related documents when request deleted.
     *
     * @param int $post_id Post ID.
     */
    public static function maybe_cleanup_on_delete( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || TB_CPT::POST_TYPE !== $post->post_type ) {
            return;
        }

        $documents = get_post_meta( $post_id, self::META_DOCUMENTS, true );
        if ( is_array( $documents ) ) {
            foreach ( $documents as $attachment_id ) {
                if ( current_user_can( 'delete_post', $attachment_id ) ) {
                    wp_delete_attachment( $attachment_id, true );
                }
            }
        }
    }

    /**
     * Check if current user may manage request.
     *
     * @param WP_Post $request Request object.
     * @return bool
     */
    public static function current_user_can_manage( $request ) {
        if ( ! $request instanceof WP_Post ) {
            $request = get_post( $request );
        }

        if ( ! $request ) {
            return false;
        }

        if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_trust_badges' ) ) {
            return true;
        }

        $owner_id   = (int) $request->post_author;
        $listing_id = (int) $request->post_parent;

        if ( $owner_id === get_current_user_id() ) {
            return true;
        }

        if ( $listing_id && current_user_can( 'edit_post', $listing_id ) ) {
            return true;
        }

        return false;
    }

    /**
     * Update request meta safely.
     *
     * @param int   $request_id Request ID.
     * @param array $meta Meta values.
     */
    public static function update_meta( $request_id, array $meta ) {
        foreach ( $meta as $key => $value ) {
            update_post_meta( $request_id, $key, $value );
        }
    }

    /**
     * Calculate default expiry timestamp.
     *
     * @return int
     */
    public static function calculate_default_expiry() {
        $settings = TB_Settings::get_settings();
        $days     = max( 1, absint( $settings['validity_days'] ) );

        return time() + DAY_IN_SECONDS * $days;
    }
}

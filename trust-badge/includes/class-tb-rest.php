<?php
/**
 * REST API endpoints for Trust Badge.
 *
 * @package TrustBadge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TB_Rest {

    const NAMESPACE = 'trustbadge/v1';

    /**
     * Register hooks.
     */
    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    /**
     * Register all REST routes.
     */
    public static function register_routes() {
        register_rest_route( self::NAMESPACE, '/requests', [
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => [ __CLASS__, 'create_request_permissions' ],
            'callback'            => [ __CLASS__, 'create_request' ],
            'args'                => self::get_request_args(),
        ] );

        register_rest_route( self::NAMESPACE, '/requests', [
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => [ __CLASS__, 'list_requests_permissions' ],
            'callback'            => [ __CLASS__, 'list_requests' ],
            'args'                => [
                'status'  => [ 'type' => 'string', 'required' => false ],
                'owner'   => [ 'type' => 'integer', 'required' => false ],
                'listing' => [ 'type' => 'integer', 'required' => false ],
                'page'    => [ 'type' => 'integer', 'required' => false, 'default' => 1 ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/requests/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => [ __CLASS__, 'view_request_permissions' ],
            'callback'            => [ __CLASS__, 'view_request' ],
            'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
        ] );

        register_rest_route( self::NAMESPACE, '/requests/(?P<id>\d+)/approve', [
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => [ __CLASS__, 'admin_permissions' ],
            'callback'            => [ __CLASS__, 'approve_request' ],
            'args'                => [
                'expires_at' => [ 'type' => 'string', 'required' => true ],
                'note'       => [ 'type' => 'string', 'required' => false ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/requests/(?P<id>\d+)/reject', [
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => [ __CLASS__, 'admin_permissions' ],
            'callback'            => [ __CLASS__, 'reject_request' ],
            'args'                => [
                'note' => [ 'type' => 'string', 'required' => true ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/requests/(?P<id>\d+)/extend', [
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => [ __CLASS__, 'admin_permissions' ],
            'callback'            => [ __CLASS__, 'extend_request' ],
            'args'                => [
                'expires_at' => [ 'type' => 'string', 'required' => true ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/badge/(?P<token>[A-Za-z0-9_-]{20,})', [
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback'            => [ __CLASS__, 'badge_public' ],
        ] );
    }

    /**
     * Permission check for creating request.
     */
    public static function create_request_permissions( WP_REST_Request $request ) {
        $listing_id = (int) $request['listing_id'];
        if ( ! $listing_id ) {
            return false;
        }

        return is_user_logged_in() && current_user_can( 'edit_post', $listing_id );
    }

    /**
     * Permission check for listing requests (admin).
     */
    public static function list_requests_permissions() {
        return current_user_can( 'manage_options' ) || current_user_can( 'manage_trust_badges' );
    }

    /**
     * Permission check for viewing individual request.
     */
    public static function view_request_permissions( WP_REST_Request $request ) {
        $request_post = get_post( (int) $request['id'] );
        if ( ! $request_post || TB_CPT::POST_TYPE !== $request_post->post_type ) {
            return new WP_Error( 'tb_not_found', __( 'Request not found.', 'trust-badge' ), [ 'status' => 404 ] );
        }

        if ( TB_Request::current_user_can_manage( $request_post ) ) {
            return true;
        }

        return new WP_Error( 'tb_forbidden', __( 'You do not have permission to view this request.', 'trust-badge' ), [ 'status' => 403 ] );
    }

    /**
     * Permission check for admin-only actions.
     */
    public static function admin_permissions() {
        return self::list_requests_permissions();
    }

    /**
     * Create or update a request.
     */
    public static function create_request( WP_REST_Request $request ) {
        $listing_id = (int) $request['listing_id'];
        $listing    = get_post( $listing_id );
        if ( ! $listing ) {
            return new WP_Error( 'tb_invalid_listing', __( 'Listing not found.', 'trust-badge' ), [ 'status' => 404 ] );
        }

        $contact_name  = sanitize_text_field( $request['contact_name'] );
        $contact_email = sanitize_email( $request['contact_email'] );
        $contact_phone = sanitize_text_field( $request['contact_phone'] );

        if ( empty( $contact_name ) || empty( $contact_email ) ) {
            return new WP_Error( 'tb_missing_fields', __( 'Contact name and email are required.', 'trust-badge' ), [ 'status' => 400 ] );
        }

        if ( ! is_email( $contact_email ) ) {
            return new WP_Error( 'tb_invalid_email', __( 'Invalid email address.', 'trust-badge' ), [ 'status' => 400 ] );
        }

        $documents = array_filter( array_map( 'absint', (array) $request['documents'] ) );

        foreach ( $documents as $attachment_id ) {
            if ( ! self::validate_document_attachment( $attachment_id ) ) {
                return new WP_Error( 'tb_invalid_document', __( 'One or more documents are invalid.', 'trust-badge' ), [ 'status' => 400 ] );
            }
        }

        $existing = TB_Request::get_for_listing( $listing_id );

        $postarr = [
            'post_type'   => TB_CPT::POST_TYPE,
            'post_title'  => sprintf( __( 'Trust badge request for %s', 'trust-badge' ), $listing->post_title ),
            'post_status' => 'tb_pending',
            'post_parent' => $listing_id,
            'post_author' => get_current_user_id(),
        ];

        if ( $existing ) {
            $postarr['ID'] = $existing->ID;
        }

        $request_id = wp_insert_post( wp_slash( $postarr ), true );

        if ( is_wp_error( $request_id ) ) {
            return $request_id;
        }

        TB_Request::ensure_token( $request_id );
        TB_Request::update_meta( $request_id, [
            TB_Request::META_CONTACT_NAME  => $contact_name,
            TB_Request::META_CONTACT_EMAIL => $contact_email,
            TB_Request::META_CONTACT_PHONE => $contact_phone,
            TB_Request::META_DOCUMENTS     => $documents,
            TB_Request::META_VERSION       => 1,
        ] );

        TB_Request::log_action( $request_id, 'submitted' );

        do_action( 'tb_request_submitted', $request_id, $listing_id );

        return self::format_request_response( get_post( $request_id ) );
    }

    /**
     * Provide argument definitions for create/update route.
     *
     * @return array
     */
    protected static function get_request_args() {
        return [
            'listing_id'    => [ 'type' => 'integer', 'required' => true ],
            'contact_name'  => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            'contact_email' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_email' ],
            'contact_phone' => [ 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
            'documents'     => [ 'type' => 'array', 'required' => false ],
        ];
    }

    /**
     * Format request details for response.
     */
    protected static function format_request_response( $request_post ) {
        if ( ! $request_post instanceof WP_Post ) {
            $request_post = get_post( $request_post );
        }

        if ( ! $request_post ) {
            return null;
        }

        $expires = (int) get_post_meta( $request_post->ID, TB_Request::META_EXPIRES_AT, true );

        return [
            'id'           => (int) $request_post->ID,
            'listing_id'   => (int) $request_post->post_parent,
            'status'       => get_post_status( $request_post ),
            'submitted_at' => get_post_time( 'c', false, $request_post ),
            'expires_at'   => $expires ? gmdate( 'c', $expires ) : null,
            'contact'      => [
                'name'  => get_post_meta( $request_post->ID, TB_Request::META_CONTACT_NAME, true ),
                'email' => get_post_meta( $request_post->ID, TB_Request::META_CONTACT_EMAIL, true ),
                'phone' => get_post_meta( $request_post->ID, TB_Request::META_CONTACT_PHONE, true ),
            ],
            'documents'    => array_map( 'intval', (array) get_post_meta( $request_post->ID, TB_Request::META_DOCUMENTS, true ) ),
            'token'        => get_post_meta( $request_post->ID, TB_Request::META_TOKEN, true ),
        ];
    }

    /**
     * List requests for admin usage.
     */
    public static function list_requests( WP_REST_Request $request ) {
        $args = [
            'post_type'      => TB_CPT::POST_TYPE,
            'post_status'    => [ 'tb_pending', 'tb_approved', 'tb_rejected', 'tb_expired' ],
            'posts_per_page' => 20,
            'paged'          => max( 1, (int) $request['page'] ),
        ];

        if ( $request['status'] ) {
            $args['post_status'] = array_map( 'sanitize_key', (array) $request['status'] );
        }

        if ( $request['owner'] ) {
            $args['author'] = (int) $request['owner'];
        }

        if ( $request['listing'] ) {
            $args['post_parent'] = (int) $request['listing'];
        }

        $query = new WP_Query( $args );
        $items = array_map( [ __CLASS__, 'format_request_response' ], $query->posts );

        return [
            'items' => $items,
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
        ];
    }

    /**
     * View request details.
     */
    public static function view_request( WP_REST_Request $request ) {
        $post = get_post( (int) $request['id'] );
        if ( ! $post ) {
            return new WP_Error( 'tb_not_found', __( 'Request not found.', 'trust-badge' ), [ 'status' => 404 ] );
        }

        return self::format_request_response( $post );
    }

    /**
     * Approve a request.
     */
    public static function approve_request( WP_REST_Request $request ) {
        $request_post = get_post( (int) $request['id'] );
        if ( ! $request_post ) {
            return new WP_Error( 'tb_not_found', __( 'Request not found.', 'trust-badge' ), [ 'status' => 404 ] );
        }

        $expires_at = strtotime( $request['expires_at'] );
        if ( ! $expires_at ) {
            return new WP_Error( 'tb_invalid_date', __( 'Invalid expiry date.', 'trust-badge' ), [ 'status' => 400 ] );
        }

        wp_update_post( [ 'ID' => $request_post->ID, 'post_status' => 'tb_approved' ] );

        TB_Request::update_meta( $request_post->ID, [ TB_Request::META_EXPIRES_AT => $expires_at ] );
        if ( isset( $request['note'] ) ) {
            TB_Request::update_meta( $request_post->ID, [ TB_Request::META_DECISION_NOTE => sanitize_textarea_field( $request['note'] ) ] );
        }

        TB_Request::log_action( $request_post->ID, 'approved', $request['note'] ?? '' );

        do_action( 'tb_request_approved', $request_post->ID );

        return self::format_request_response( $request_post );
    }

    /**
     * Reject request.
     */
    public static function reject_request( WP_REST_Request $request ) {
        $request_post = get_post( (int) $request['id'] );
        if ( ! $request_post ) {
            return new WP_Error( 'tb_not_found', __( 'Request not found.', 'trust-badge' ), [ 'status' => 404 ] );
        }

        $note = sanitize_textarea_field( $request['note'] );
        if ( empty( $note ) ) {
            return new WP_Error( 'tb_missing_note', __( 'A rejection note is required.', 'trust-badge' ), [ 'status' => 400 ] );
        }

        wp_update_post( [ 'ID' => $request_post->ID, 'post_status' => 'tb_rejected' ] );
        TB_Request::update_meta( $request_post->ID, [ TB_Request::META_DECISION_NOTE => $note ] );
        TB_Request::log_action( $request_post->ID, 'rejected', $note );

        do_action( 'tb_request_rejected', $request_post->ID );

        return self::format_request_response( $request_post );
    }

    /**
     * Extend expiry date.
     */
    public static function extend_request( WP_REST_Request $request ) {
        $request_post = get_post( (int) $request['id'] );
        if ( ! $request_post ) {
            return new WP_Error( 'tb_not_found', __( 'Request not found.', 'trust-badge' ), [ 'status' => 404 ] );
        }

        $expires_at = strtotime( $request['expires_at'] );
        if ( ! $expires_at ) {
            return new WP_Error( 'tb_invalid_date', __( 'Invalid expiry date.', 'trust-badge' ), [ 'status' => 400 ] );
        }

        if ( 'tb_approved' !== get_post_status( $request_post ) ) {
            return new WP_Error( 'tb_invalid_state', __( 'Only approved requests may be extended.', 'trust-badge' ), [ 'status' => 400 ] );
        }

        TB_Request::update_meta( $request_post->ID, [ TB_Request::META_EXPIRES_AT => $expires_at ] );
        TB_Request::log_action( $request_post->ID, 'extended' );

        do_action( 'tb_request_extended', $request_post->ID );

        return self::format_request_response( $request_post );
    }

    /**
     * Public badge endpoint.
     */
    public static function badge_public( WP_REST_Request $request ) {
        $token = sanitize_text_field( $request['token'] );
        $post  = self::get_request_by_token( $token );
        if ( ! $post ) {
            return rest_ensure_response( [ 'valid' => false ] );
        }

        $expires = (int) get_post_meta( $post->ID, TB_Request::META_EXPIRES_AT, true );
        $status  = get_post_status( $post );

        $valid = ( 'tb_approved' === $status ) && $expires && $expires > time();

        $listing_id = (int) $post->post_parent;
        $listing    = $listing_id ? get_post( $listing_id ) : null;

        $settings = TB_Settings::get_settings();

        $badge_html = TB_Badge::generate_markup( [
            'listing'     => $listing,
            'expires_at'  => $expires,
            'valid'       => $valid,
            'accent'      => $settings['accent_color'],
            'show_expiry' => ! empty( $settings['show_expiry'] ),
            'link'        => $settings['link_behavior'],
        ] );

        return rest_ensure_response( [
            'valid'       => $valid,
            'expires_at'  => $expires ? gmdate( 'c', $expires ) : null,
            'listing_id'  => $listing_id,
            'badge'       => $badge_html,
            'listing_name'=> $listing ? $listing->post_title : '',
        ] );
    }

    /**
     * Get request post by token.
     *
     * @param string $token Token.
     * @return WP_Post|null
     */
    protected static function get_request_by_token( $token ) {
        global $wpdb;

        $post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", TB_Request::META_TOKEN, $token ) );

        if ( ! $post_id ) {
            return null;
        }

        $post = get_post( (int) $post_id );
        if ( ! $post || TB_CPT::POST_TYPE !== $post->post_type ) {
            return null;
        }

        return $post;
    }

    /**
     * Ensure attachment is valid document.
     *
     * @param int $attachment_id Attachment ID.
     * @return bool
     */
    protected static function validate_document_attachment( $attachment_id ) {
        $attachment = get_post( $attachment_id );
        if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
            return false;
        }

        if ( (int) $attachment->post_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) {
            return false;
        }

        $settings = TB_Settings::get_settings();
        $filetype = wp_check_filetype( $file );

        if ( ! in_array( $filetype['type'], (array) $settings['allowed_mimes'], true ) ) {
            return false;
        }

        if ( filesize( $file ) > (float) $settings['max_file_size'] ) {
            return false;
        }

        return true;
    }
}

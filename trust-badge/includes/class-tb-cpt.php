<?php
/**
 * Register custom post type and statuses.
 *
 * @package TrustBadge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TB_CPT {

    const POST_TYPE = 'trust_badge_request';

    /**
     * Init hooks.
     */
    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_post_type' ] );
        add_action( 'init', [ __CLASS__, 'register_statuses' ] );
        add_filter( 'manage_edit-' . self::POST_TYPE . '_columns', [ __CLASS__, 'register_columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ __CLASS__, 'render_columns' ], 10, 2 );
        add_filter( 'display_post_states', [ __CLASS__, 'display_post_states' ], 10, 2 );
    }

    /**
     * Register CPT.
     */
    public static function register_post_type() {
        $labels = [
            'name'               => __( 'Trust Badge Requests', 'trust-badge' ),
            'singular_name'      => __( 'Trust Badge Request', 'trust-badge' ),
            'menu_name'          => __( 'Trust Badges', 'trust-badge' ),
            'add_new'            => __( 'Add New', 'trust-badge' ),
            'add_new_item'       => __( 'Add New Request', 'trust-badge' ),
            'edit_item'          => __( 'Edit Request', 'trust-badge' ),
            'new_item'           => __( 'New Request', 'trust-badge' ),
            'view_item'          => __( 'View Request', 'trust-badge' ),
            'search_items'       => __( 'Search Requests', 'trust-badge' ),
            'not_found'          => __( 'No requests found.', 'trust-badge' ),
            'not_found_in_trash' => __( 'No requests found in Trash.', 'trust-badge' ),
        ];

        register_post_type( self::POST_TYPE, [
            'labels'        => $labels,
            'public'        => false,
            'show_ui'       => true,
            'menu_icon'     => 'dashicons-awards',
            'supports'      => [ 'title', 'author' ],
            'capability_type' => [ 'trust_badge_request', 'trust_badge_requests' ],
            'map_meta_cap'  => true,
            'show_in_rest'  => false,
        ] );
    }

    /**
     * Register custom statuses.
     */
    public static function register_statuses() {
        $statuses = [
            'tb_pending' => [ 'label' => __( 'Pending', 'trust-badge' ), 'color' => '#dba617' ],
            'tb_approved' => [ 'label' => __( 'Approved', 'trust-badge' ), 'color' => '#2d9d59' ],
            'tb_rejected' => [ 'label' => __( 'Rejected', 'trust-badge' ), 'color' => '#c0392b' ],
            'tb_expired' => [ 'label' => __( 'Expired', 'trust-badge' ), 'color' => '#7f8c8d' ],
        ];

        foreach ( $statuses as $key => $status ) {
            register_post_status( $key, [
                'label'                     => $status['label'],
                'public'                    => false,
                'exclude_from_search'       => true,
                'internal'                  => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( $status['label'] . ' <span class="count">(%s)</span>', $status['label'] . ' <span class="count">(%s)</span>', 'trust-badge' ),
            ] );
        }
    }

    /**
     * Adjust columns.
     */
    public static function register_columns( $columns ) {
        $new = [
            'cb'        => $columns['cb'] ?? '',
            'title'     => __( 'Request', 'trust-badge' ),
            'listing'   => __( 'Listing', 'trust-badge' ),
            'owner'     => __( 'Owner', 'trust-badge' ),
            'status'    => __( 'Status', 'trust-badge' ),
            'expires'   => __( 'Expires', 'trust-badge' ),
            'date'      => $columns['date'] ?? __( 'Date', 'trust-badge' ),
        ];

        return $new;
    }

    /**
     * Render column content.
     */
    public static function render_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'listing':
                $listing_id = get_post_field( 'post_parent', $post_id );
                if ( $listing_id ) {
                    $title = get_the_title( $listing_id );
                    printf( '<a href="%1$s">%2$s</a>', esc_url( get_edit_post_link( $listing_id ) ), esc_html( $title ) );
                } else {
                    esc_html_e( 'N/A', 'trust-badge' );
                }
                break;
            case 'owner':
                $author = get_post_field( 'post_author', $post_id );
                if ( $author ) {
                    $user = get_userdata( $author );
                    if ( $user ) {
                        printf( '<a href="%1$s">%2$s</a>', esc_url( get_edit_user_link( $author ) ), esc_html( $user->display_name ) );
                    }
                }
                break;
            case 'status':
                $status = get_post_status( $post_id );
                echo esc_html( self::get_status_label( $status ) );
                break;
            case 'expires':
                $expires = (int) get_post_meta( $post_id, '_tb_expires_at', true );
                if ( $expires ) {
                    echo esc_html( date_i18n( get_option( 'date_format' ), $expires ) );
                } else {
                    esc_html_e( '—', 'trust-badge' );
                }
                break;
        }
    }

    /**
     * Show custom status label in row states.
     */
    public static function display_post_states( $states, $post ) {
        if ( self::POST_TYPE !== $post->post_type ) {
            return $states;
        }

        $status = get_post_status( $post );
        $label  = self::get_status_label( $status );

        if ( $label ) {
            $states[] = $label;
        }

        return $states;
    }

    /**
     * Human readable status label.
     *
     * @param string $status Status key.
     * @return string
     */
    public static function get_status_label( $status ) {
        switch ( $status ) {
            case 'tb_pending':
                return __( 'Pending', 'trust-badge' );
            case 'tb_approved':
                return __( 'Approved', 'trust-badge' );
            case 'tb_rejected':
                return __( 'Rejected', 'trust-badge' );
            case 'tb_expired':
                return __( 'Expired', 'trust-badge' );
            default:
                return '';
        }
    }
}

<?php
/**
 * Frontend integration with MyListing dashboard.
 *
 * @package TrustBadge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TB_Frontend {

    /**
     * Register hooks.
     */
    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_filter( 'mylisting/listing_actions', [ __CLASS__, 'inject_listing_actions' ], 10, 2 );
        add_action( 'wp_footer', [ __CLASS__, 'render_portal_markup' ] );
        add_action( 'rest_api_init', [ __CLASS__, 'register_embed_endpoint' ] );
    }

    /**
     * Enqueue frontend assets when user logged in.
     */
    public static function enqueue_assets() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $is_account = function_exists( 'is_account_page' ) ? is_account_page() : false;
        if ( ! $is_account && ! is_page_template( 'templates/dashboard.php' ) && ! is_page( 'my-account' ) ) {
            return;
        }

        wp_enqueue_style( 'tb-frontend', TB_PLUGIN_URL . 'assets/frontend.css', [], TB_PLUGIN_VER );
        wp_enqueue_script( 'tb-frontend', TB_PLUGIN_URL . 'assets/frontend.js', [ 'jquery', 'wp-util', 'wp-i18n' ], TB_PLUGIN_VER, true );
        wp_set_script_translations( 'tb-frontend', 'trust-badge', dirname( plugin_basename( TB_PLUGIN_FILE ) ) . '/languages' );
        wp_enqueue_media();
        wp_localize_script( 'tb-frontend', 'TBFrontend', [
            'nonce'      => wp_create_nonce( 'wp_rest' ),
            'restRoot'   => esc_url_raw( rest_url( TB_Rest::NAMESPACE ) ),
            'copyLabel'  => __( 'Copy to clipboard', 'trust-badge' ),
            'copiedLabel'=> __( 'Copied!', 'trust-badge' ),
        ] );
    }

    /**
     * Inject action buttons into MyListing listing cards.
     */
    public static function inject_listing_actions( $actions, $listing_id ) {
        if ( ! current_user_can( 'edit_post', $listing_id ) ) {
            return $actions;
        }

        $request = TB_Request::get_for_listing( $listing_id );

        if ( ! $request ) {
            return self::add_action_after_duplicate( $actions, 'tb_request', [
                'label'  => __( 'Request Trust Badge', 'trust-badge' ),
                'icon'   => 'la la-shield',
                'action' => 'tbOpenRequest',
                'data'   => [ 'listingId' => $listing_id ],
            ] );
        }

        $request_id = $request instanceof WP_Post ? $request->ID : (int) $request;
        $status     = get_post_status( $request_id );

        if ( 'tb_approved' === $status ) {
            if ( TB_Request::is_active( $request_id ) ) {
                $actions = self::add_action_after_duplicate( $actions, 'tb_copy', [
                    'label'  => __( 'Copy Trust Badge', 'trust-badge' ),
                    'icon'   => 'la la-copy',
                    'action' => 'tbOpenEmbed',
                    'data'   => [ 'requestId' => $request_id ],
                ] );
            } else {
                $actions = self::add_action_after_duplicate( $actions, 'tb_renew', [
                    'label'  => __( 'Renew Trust Badge', 'trust-badge' ),
                    'icon'   => 'la la-sync',
                    'action' => 'tbOpenRequest',
                    'data'   => [
                        'listingId' => $listing_id,
                        'renew'     => true,
                        'requestId' => $request_id,
                    ],
                ] );
            }
        } elseif ( 'tb_rejected' === $status ) {
            $actions = self::add_action_after_duplicate( $actions, 'tb_update', [
                'label'  => __( 'Update Trust Badge Request', 'trust-badge' ),
                'icon'   => 'la la-edit',
                'action' => 'tbOpenRequest',
                'data'   => [
                    'listingId' => $listing_id,
                    'requestId' => $request_id,
                ],
            ] );
        } else {
            $actions = self::add_action_after_duplicate( $actions, 'tb_view', [
                'label'  => __( 'View Trust Badge Request', 'trust-badge' ),
                'icon'   => 'la la-eye',
                'action' => 'tbOpenRequest',
                'data'   => [
                    'listingId' => $listing_id,
                    'requestId' => $request_id,
                ],
            ] );
        }

        return $actions;
    }

    /**
     * Insert Trust Badge action after the Duplicate action if present.
     *
     * @param array  $actions   Existing actions.
     * @param string $key       New action key.
     * @param array  $config    Action configuration.
     * @return array
     */
    private static function add_action_after_duplicate( array $actions, $key, array $config ) {
        $anchors    = apply_filters(
            'tb_trust_badge_action_anchor_keys',
            [ 'duplicate', 'cts_duplicate', 'duplicate_listing', 'listing-duplicate', 'duplicate-listing' ]
        );
        $new_actions = [];
        $inserted    = false;

        foreach ( $actions as $action_key => $action ) {
            $new_actions[ $action_key ] = $action;

            if ( ! $inserted && in_array( $action_key, $anchors, true ) ) {
                $new_actions[ $key ] = $config;
                $inserted            = true;
            }
        }

        if ( ! $inserted ) {
            $new_actions[ $key ] = $config;
        }

        return $new_actions;
    }

    /**
     * Output reusable modal containers in footer.
     */
    public static function render_portal_markup() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        echo '<div id="tb-modal-root" class="tb-hidden" aria-hidden="true"></div>';
    }

    /**
     * Register embed script endpoint (iframe fallback).
     */
    public static function register_embed_endpoint() {
        register_rest_route( TB_Rest::NAMESPACE, '/embed/(?P<token>[A-Za-z0-9_-]{20,})', [
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback'            => [ __CLASS__, 'serve_embed_markup' ],
        ] );
    }

    /**
     * Return iframe markup.
     */
    public static function serve_embed_markup( WP_REST_Request $request ) {
        $response    = TB_Rest::badge_public( $request );
        $data        = $response instanceof WP_REST_Response ? $response->get_data() : $response;
        $status_code = $response instanceof WP_REST_Response ? $response->get_status() : 200;

        $headers = [ 'Content-Type' => 'text/html; charset=' . get_option( 'blog_charset', 'utf-8' ) ];

        if ( is_wp_error( $response ) ) {
            return new WP_REST_Response( '<!-- trust badge error -->', 400, $headers );
        }

        if ( empty( $data['valid'] ) || empty( $data['badge'] ) ) {
            return new WP_REST_Response( '<!-- invalid trust badge -->', 410, $headers );
        }

        return new WP_REST_Response( $data['badge'], $status_code, $headers );
    }
}

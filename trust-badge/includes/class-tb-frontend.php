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
        add_action( 'mylisting/user-listings/actions', [ __CLASS__, 'render_dashboard_action' ], 20 );
        add_action( 'mylisting/dashboard/listing-actions', [ __CLASS__, 'render_dashboard_action' ], 20 );
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
            'embedScript'=> esc_url_raw( TB_PLUGIN_URL . 'assets/embed.js' ),
            'iframeBase' => esc_url_raw( rest_url( TB_Rest::NAMESPACE . '/embed/' ) ),
        ] );
    }

    /**
     * Inject action buttons into MyListing listing cards.
     */
    public static function inject_listing_actions( $actions, $listing_id ) {
        if ( ! current_user_can( 'edit_post', $listing_id ) ) {
            return $actions;
        }

        $action = self::determine_listing_action( $listing_id );

        if ( ! $action ) {
            return $actions;
        }

        return self::add_action_after_duplicate( $actions, $action['key'], $action['config'] );
    }

    /**
     * Render Trust Badge action markup for dashboard listing rows.
     *
     * @param object $listing Listing object from MyListing template.
     */
    public static function render_dashboard_action( $listing ) {
        if ( ! is_object( $listing ) || ! method_exists( $listing, 'get_id' ) ) {
            return;
        }

        static $rendered = [];

        $listing_id = (int) $listing->get_id();

        if ( ! current_user_can( 'edit_post', $listing_id ) ) {
            return;
        }

        if ( isset( $rendered[ $listing_id ] ) ) {
            return;
        }

        $rendered[ $listing_id ] = true;

        $action = self::determine_listing_action( $listing_id );

        if ( ! $action ) {
            return;
        }

        $config = $action['config'];
        $label  = isset( $config['label'] ) ? $config['label'] : '';
        $icon   = ! empty( $config['icon'] ) ? sprintf( '<i class="%s"></i> ', esc_attr( $config['icon'] ) ) : '';
        $attrs  = self::format_action_attributes( isset( $config['data'] ) ? $config['data'] : [] );
        $attrs  = $attrs ? ' ' . $attrs : '';
        $class  = sanitize_html_class( str_replace( 'tb_', 'tb-', $action['key'] ) );

        printf(
            '<li class="cts-listing-action-%1$s"><a href="#" class="tb-listing-action"%2$s>%3$s%4$s</a></li>',
            esc_attr( $class ),
            $attrs,
            $icon,
            esc_html( $label )
        );
    }

    /**
     * Determine the Trust Badge action configuration for a listing.
     *
     * @param int $listing_id Listing ID.
     * @return array|null
     */
    private static function determine_listing_action( $listing_id ) {
        $listing_id = (int) $listing_id;

        if ( ! $listing_id ) {
            return null;
        }

        $request = TB_Request::get_for_listing( $listing_id );

        if ( ! $request ) {
            return [
                'key'    => 'tb_request',
                'config' => [
                    'label' => __( 'Request Trust Badge', 'trust-badge' ),
                    'icon'  => 'la la-shield',
                    'action'=> 'tbOpenRequest',
                    'data'  => [
                        'tb-action'    => 'request',
                        'listing-id'   => $listing_id,
                    ],
                ],
            ];
        }

        $request_id = $request instanceof WP_Post ? $request->ID : (int) $request;
        $status     = get_post_status( $request_id );

        if ( 'tb_approved' === $status ) {
            if ( TB_Request::is_active( $request_id ) ) {
                return [
                    'key'    => 'tb_copy',
                    'config' => [
                        'label' => __( 'Copy Trust Badge', 'trust-badge' ),
                        'icon'  => 'la la-copy',
                        'action'=> 'tbOpenEmbed',
                        'data'  => [
                            'tb-action'    => 'embed',
                            'request-id'   => $request_id,
                        ],
                    ],
                ];
            }

            return [
                'key'    => 'tb_renew',
                'config' => [
                    'label' => __( 'Renew Trust Badge', 'trust-badge' ),
                    'icon'  => 'la la-sync',
                    'action'=> 'tbOpenRequest',
                    'data'  => [
                        'tb-action'    => 'request',
                        'listing-id'   => $listing_id,
                        'request-id'   => $request_id,
                        'tb-renew'     => '1',
                    ],
                ],
            ];
        }

        if ( 'tb_rejected' === $status ) {
            return [
                'key'    => 'tb_update',
                'config' => [
                    'label' => __( 'Update Trust Badge Request', 'trust-badge' ),
                    'icon'  => 'la la-edit',
                    'action'=> 'tbOpenRequest',
                    'data'  => [
                        'tb-action'    => 'request',
                        'listing-id'   => $listing_id,
                        'request-id'   => $request_id,
                    ],
                ],
            ];
        }

        return [
            'key'    => 'tb_view',
            'config' => [
                'label' => __( 'View Trust Badge Request', 'trust-badge' ),
                'icon'  => 'la la-eye',
                'action'=> 'tbOpenRequest',
                'data'  => [
                    'tb-action'    => 'request',
                    'listing-id'   => $listing_id,
                    'request-id'   => $request_id,
                ],
            ],
        ];
    }

    /**
     * Convert dataset into HTML attributes for action markup.
     *
     * @param array $data Key/value pairs.
     * @return string
     */
    private static function format_action_attributes( array $data ) {
        if ( empty( $data ) ) {
            return '';
        }

        $attrs = [];

        foreach ( $data as $key => $value ) {
            if ( '' === $value || null === $value ) {
                continue;
            }

            $attribute = 'data-' . preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $key ) );
            $attrs[]   = sprintf( '%s="%s"', esc_attr( $attribute ), esc_attr( $value ) );
        }

        return implode( ' ', $attrs );
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

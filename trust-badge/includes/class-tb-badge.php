<?php
/**
 * Badge rendering helpers.
 *
 * @package TrustBadge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TB_Badge {

    /**
     * Initialise hooks.
     */
    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_shortcode' ] );
    }

    /**
     * Register badge shortcode for previews.
     */
    public static function register_shortcode() {
        add_shortcode( 'trust_badge_preview', [ __CLASS__, 'shortcode' ] );
    }

    /**
     * Shortcode handler.
     */
    public static function shortcode( $atts ) {
        $atts = shortcode_atts( [ 'request_id' => 0 ], $atts, 'trust_badge_preview' );
        $request_id = (int) $atts['request_id'];
        if ( ! $request_id ) {
            return '';
        }

        $post = get_post( $request_id );
        if ( ! $post ) {
            return '';
        }

        return self::generate_markup( [
            'listing'     => get_post( $post->post_parent ),
            'expires_at'  => (int) get_post_meta( $post->ID, TB_Request::META_EXPIRES_AT, true ),
            'valid'       => TB_Request::is_active( $post->ID ),
            'accent'      => TB_Settings::get_settings()['accent_color'],
            'show_expiry' => TB_Settings::get_settings()['show_expiry'],
            'link'        => TB_Settings::get_settings()['link_behavior'],
        ] );
    }

    /**
     * Generate badge markup.
     *
     * @param array $context Context.
     * @return string
     */
    public static function generate_markup( array $context ) {
        $listing    = $context['listing'] ?? null;
        $valid      = ! empty( $context['valid'] );
        $expires_at = isset( $context['expires_at'] ) ? (int) $context['expires_at'] : 0;
        $accent     = $context['accent'] ?? '#1e73be';
        $show_expiry = ! empty( $context['show_expiry'] );
        $link_type  = $context['link'] ?? 'listing';

        $listing_name = $listing ? $listing->post_title : __( 'Verified Listing', 'trust-badge' );
        $issuer       = wp_parse_url( home_url(), PHP_URL_HOST );

        $expires_text = $show_expiry && $expires_at
            ? sprintf( __( 'Valid until %s', 'trust-badge' ), date_i18n( get_option( 'date_format' ), $expires_at ) )
            : __( 'Verified listing', 'trust-badge' );

        $status_text = $valid ? __( 'Verified', 'trust-badge' ) : __( 'Expired', 'trust-badge' );

        $content  = '<div class="tb-badge" role="img" aria-label="' . esc_attr( $status_text ) . '">';
        $content .= '<div class="tb-badge__header">';
        $content .= '<span class="tb-badge__icon" style="background:' . esc_attr( $accent ) . '"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M6.173 13.777a1 1 0 0 0 .994.11l1.5-.643 1.5.643a1 1 0 0 0 .994-.11l1.286-.964 1.57-.505a1 1 0 0 0 .68-.948l.047-1.647.8-1.469a1 1 0 0 0-.096-1.066l-.997-1.312-.366-1.592a1 1 0 0 0-.77-.757l-1.6-.343-1.193-1.081a1 1 0 0 0-1.103-.152L8 1.4l-1.147-.606a1 1 0 0 0-1.103.152L4.557 2.027l-1.6.343a1 1 0 0 0-.77.757l-.366 1.592-.997 1.312a1 1 0 0 0-.096 1.066l.8 1.469.047 1.647a1 1 0 0 0 .68.948l1.57.505 1.286.964Z"/><path fill="#fff" d="M7.333 10.943 4.89 8.5l1.179-1.179 1.264 1.263 2.598-2.597L11.11 7.166l-3.11 3.11-.667.667Z"/></svg></span>';
        $content .= '<span class="tb-badge__issuer">' . esc_html( $issuer ) . '</span>';
        $content .= '</div>';
        $content .= '<div class="tb-badge__body">';
        $content .= '<div class="tb-badge__title">' . esc_html( $listing_name ) . '</div>';
        $content .= '<div class="tb-badge__status">' . esc_html( $expires_text ) . '</div>';
        $content .= '</div>';
        $content .= '</div>';

        if ( $valid ) {
            $url = '';
            if ( 'listing' === $link_type && $listing ) {
                $url = get_permalink( $listing );
            } elseif ( 'issuer' === $link_type ) {
                $url = home_url();
            }

            if ( $url ) {
                $content = '<a class="tb-badge__link" href="' . esc_url( $url ) . '" target="_blank" rel="nofollow noopener">' . $content . '</a>';
            }
        }

        return $content;
    }
}

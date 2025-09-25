<?php
/**
 * Settings API wrapper.
 *
 * @package TrustBadge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TB_Settings {

    const OPTION_KEY = 'tb_settings';

    /**
     * Default settings values.
     *
     * @return array
     */
    public static function defaults() {
        return [
            'validity_days'   => 365,
            'reminder_days'   => 14,
            'allowed_mimes'   => [ 'application/pdf', 'image/jpeg', 'image/png' ],
            'max_file_size'   => 5 * MB_IN_BYTES,
            'badge_style'     => 'classic',
            'accent_color'    => '#1e73be',
            'show_expiry'     => true,
            'link_behavior'   => 'listing',
        ];
    }

    /**
     * Hook registration.
     */
    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    /**
     * Register plugin settings.
     */
    public static function register_settings() {
        register_setting( 'tb_settings', self::OPTION_KEY, [ __CLASS__, 'sanitize' ] );

        add_settings_section(
            'tb_general',
            __( 'General Settings', 'trust-badge' ),
            '__return_false',
            'tb_settings'
        );

        add_settings_field(
            'validity_days',
            __( 'Default Validity (days)', 'trust-badge' ),
            [ __CLASS__, 'field_number' ],
            'tb_settings',
            'tb_general',
            [ 'key' => 'validity_days', 'min' => 1 ]
        );

        add_settings_field(
            'reminder_days',
            __( 'Reminder before expiry (days)', 'trust-badge' ),
            [ __CLASS__, 'field_number' ],
            'tb_settings',
            'tb_general',
            [ 'key' => 'reminder_days', 'min' => 0 ]
        );

        add_settings_field(
            'allowed_mimes',
            __( 'Allowed MIME types', 'trust-badge' ),
            [ __CLASS__, 'field_text' ],
            'tb_settings',
            'tb_general',
            [ 'key' => 'allowed_mimes', 'description' => __( 'Comma-separated list.', 'trust-badge' ) ]
        );

        add_settings_field(
            'max_file_size',
            __( 'Maximum file size (MB)', 'trust-badge' ),
            [ __CLASS__, 'field_number' ],
            'tb_settings',
            'tb_general',
            [ 'key' => 'max_file_size', 'min' => 1, 'step' => 0.1 ]
        );

        add_settings_field(
            'accent_color',
            __( 'Badge accent color', 'trust-badge' ),
            [ __CLASS__, 'field_color' ],
            'tb_settings',
            'tb_general',
            [ 'key' => 'accent_color' ]
        );

        add_settings_field(
            'show_expiry',
            __( 'Display expiry date on badge', 'trust-badge' ),
            [ __CLASS__, 'field_checkbox' ],
            'tb_settings',
            'tb_general',
            [ 'key' => 'show_expiry' ]
        );

        add_settings_field(
            'link_behavior',
            __( 'Badge link behaviour', 'trust-badge' ),
            [ __CLASS__, 'field_select_link' ],
            'tb_settings',
            'tb_general',
            []
        );
    }

    /**
     * Retrieve settings merged with defaults.
     *
     * @return array
     */
    public static function get_settings() {
        $settings = get_option( self::OPTION_KEY, [] );

        return wp_parse_args( $settings, self::defaults() );
    }

    /**
     * Sanitize settings values.
     *
     * @param array $value Incoming value.
     * @return array
     */
    public static function sanitize( $value ) {
        $defaults = self::defaults();
        $value    = (array) $value;

        $sanitized = [];
        $sanitized['validity_days'] = max( 1, absint( $value['validity_days'] ?? $defaults['validity_days'] ) );
        $sanitized['reminder_days'] = max( 0, absint( $value['reminder_days'] ?? $defaults['reminder_days'] ) );

        $mimes = $value['allowed_mimes'] ?? implode( ',', $defaults['allowed_mimes'] );
        if ( is_array( $mimes ) ) {
            $mimes = implode( ',', $mimes );
        }
        $mimes = array_filter( array_map( 'trim', explode( ',', strtolower( $mimes ) ) ) );
        $sanitized['allowed_mimes'] = $mimes;

        $max_size = isset( $value['max_file_size'] ) ? (float) $value['max_file_size'] : ( $defaults['max_file_size'] / MB_IN_BYTES );
        $sanitized['max_file_size'] = max( 0.1, $max_size ) * MB_IN_BYTES;

        $sanitized['badge_style']  = in_array( $value['badge_style'] ?? $defaults['badge_style'], [ 'classic', 'minimal' ], true ) ? $value['badge_style'] : $defaults['badge_style'];
        $sanitized['accent_color'] = sanitize_hex_color( $value['accent_color'] ?? $defaults['accent_color'] ) ?: $defaults['accent_color'];
        $sanitized['show_expiry']  = ! empty( $value['show_expiry'] );
        $sanitized['link_behavior'] = in_array( $value['link_behavior'] ?? $defaults['link_behavior'], [ 'listing', 'issuer', 'none' ], true ) ? $value['link_behavior'] : $defaults['link_behavior'];

        return $sanitized;
    }

    /**
     * Render number input field.
     *
     * @param array $args Field args.
     */
    public static function field_number( $args ) {
        $settings = self::get_settings();
        $key      = $args['key'];
        $value    = $settings[ $key ] ?? '';
        $min      = $args['min'] ?? '';
        $step     = $args['step'] ?? 1;

        if ( 'max_file_size' === $key ) {
            $value = $value / MB_IN_BYTES;
        }

        printf(
            '<input type="number" name="%1$s[%2$s]" value="%3$s" min="%4$s" step="%5$s" class="small-text" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            esc_attr( $value ),
            esc_attr( $min ),
            esc_attr( $step )
        );

        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    /**
     * Render text input field.
     */
    public static function field_text( $args ) {
        $settings = self::get_settings();
        $key      = $args['key'];
        $value    = $settings[ $key ] ?? '';

        if ( is_array( $value ) ) {
            $value = implode( ',', $value );
        }

        printf(
            '<input type="text" name="%1$s[%2$s]" value="%3$s" class="regular-text" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            esc_attr( $value )
        );

        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    /**
     * Render checkbox field.
     */
    public static function field_checkbox( $args ) {
        $settings = self::get_settings();
        $key      = $args['key'];
        $value    = ! empty( $settings[ $key ] );

        printf(
            '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s/> %4$s</label>',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            checked( $value, true, false ),
            esc_html__( 'Enable', 'trust-badge' )
        );
    }

    /**
     * Render select field for link behaviour.
     */
    public static function field_select_link() {
        $settings = self::get_settings();
        $value    = $settings['link_behavior'] ?? 'listing';

        $options = [
            'listing' => __( 'Link to listing', 'trust-badge' ),
            'issuer'  => __( 'Link to issuer site', 'trust-badge' ),
            'none'    => __( 'No link', 'trust-badge' ),
        ];

        printf( '<select name="%1$s[link_behavior]">', esc_attr( self::OPTION_KEY ) );
        foreach ( $options as $key => $label ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $value, $key, false ), esc_html( $label ) );
        }
        echo '</select>';
    }

    /**
     * Render color field.
     */
    public static function field_color( $args ) {
        $settings = self::get_settings();
        $key      = $args['key'];
        $value    = $settings[ $key ] ?? '#1e73be';

        printf(
            '<input type="text" class="tb-color-field" name="%1$s[%2$s]" value="%3$s" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            esc_attr( $value )
        );
    }
}

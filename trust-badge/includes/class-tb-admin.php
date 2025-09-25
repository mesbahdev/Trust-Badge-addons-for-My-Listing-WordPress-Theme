<?php
/**
 * Admin UI and menus.
 *
 * @package TrustBadge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TB_Admin {

    /**
     * Hook registration.
     */
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_action( 'admin_post_tb_approve_request', [ __CLASS__, 'handle_approve_request' ] );
        add_action( 'admin_post_tb_reject_request', [ __CLASS__, 'handle_reject_request' ] );
        add_action( 'admin_post_tb_extend_request', [ __CLASS__, 'handle_extend_request' ] );
        add_action( 'admin_post_tb_revoke_request', [ __CLASS__, 'handle_revoke_request' ] );
    }

    /**
     * Register admin menu pages.
     */
    public static function register_menu() {
        add_menu_page(
            __( 'Trust Badges', 'trust-badge' ),
            __( 'Trust Badges', 'trust-badge' ),
            'manage_options',
            'tb_requests',
            [ __CLASS__, 'render_requests_page' ],
            'dashicons-awards',
            58
        );

        add_submenu_page(
            'tb_requests',
            __( 'Requests', 'trust-badge' ),
            __( 'Requests', 'trust-badge' ),
            'manage_options',
            'tb_requests',
            [ __CLASS__, 'render_requests_page' ]
        );

        add_submenu_page(
            'tb_requests',
            __( 'Settings', 'trust-badge' ),
            __( 'Settings', 'trust-badge' ),
            'manage_options',
            'tb_settings',
            [ __CLASS__, 'render_settings_page' ]
        );

        add_submenu_page(
            null,
            __( 'Trust Badge Request Detail', 'trust-badge' ),
            __( 'Trust Badge Request Detail', 'trust-badge' ),
            'manage_options',
            'tb_request_detail',
            [ __CLASS__, 'render_request_detail_page' ]
        );
    }

    /**
     * Enqueue admin assets.
     */
    public static function enqueue_admin_assets( $hook ) {
        if ( false === strpos( $hook, 'tb_' ) ) {
            return;
        }

        wp_enqueue_style( 'tb-admin', TB_PLUGIN_URL . 'assets/admin.css', [], TB_PLUGIN_VER );
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'tb-admin', TB_PLUGIN_URL . 'assets/admin.js', [ 'jquery', 'wp-color-picker' ], TB_PLUGIN_VER, true );
    }

    /**
     * Render requests admin page.
     */
    public static function render_requests_page() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_trust_badges' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'trust-badge' ) );
        }

        $status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $query  = new WP_Query( [
            'post_type'      => TB_CPT::POST_TYPE,
            'post_status'    => $status ? [ $status ] : [ 'tb_pending', 'tb_approved', 'tb_rejected', 'tb_expired' ],
            'posts_per_page' => 20,
            'paged'          => max( 1, absint( $_GET['paged'] ?? 1 ) ), // phpcs:ignore WordPress.Security.NonceVerification
        ] );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Trust Badge Requests', 'trust-badge' ) . '</h1>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="tb_requests" />';
        echo '<label for="tb-status-filter" class="screen-reader-text">' . esc_html__( 'Filter by status', 'trust-badge' ) . '</label>';
        echo '<select id="tb-status-filter" name="status">';
        echo '<option value="">' . esc_html__( 'All statuses', 'trust-badge' ) . '</option>';
        foreach ( [ 'tb_pending', 'tb_approved', 'tb_rejected', 'tb_expired' ] as $slug ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $slug ), selected( $status, $slug, false ), esc_html( TB_CPT::get_status_label( $slug ) ) );
        }
        echo '</select> ';
        submit_button( __( 'Filter', 'trust-badge' ), 'secondary', '', false );
        echo '</form>';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Listing', 'trust-badge' ) . '</th>';
        echo '<th>' . esc_html__( 'Owner', 'trust-badge' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'trust-badge' ) . '</th>';
        echo '<th>' . esc_html__( 'Submitted', 'trust-badge' ) . '</th>';
        echo '<th>' . esc_html__( 'Expires', 'trust-badge' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'trust-badge' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $request_id = get_the_ID();
                $listing_id = (int) get_post_field( 'post_parent', $request_id );
                $owner_id   = (int) get_post_field( 'post_author', $request_id );
                $expires    = (int) get_post_meta( $request_id, TB_Request::META_EXPIRES_AT, true );

                echo '<tr>';
                echo '<td>';
                if ( $listing_id ) {
                    echo '<a href="' . esc_url( get_edit_post_link( $listing_id ) ) . '">' . esc_html( get_the_title( $listing_id ) ) . '</a>';
                } else {
                    esc_html_e( '—', 'trust-badge' );
                }
                echo '</td>';

                echo '<td>';
                if ( $owner_id ) {
                    $user = get_userdata( $owner_id );
                    if ( $user ) {
                        echo '<a href="' . esc_url( get_edit_user_link( $owner_id ) ) . '">' . esc_html( $user->display_name ) . '</a>';
                    }
                }
                echo '</td>';

                echo '<td>' . esc_html( TB_CPT::get_status_label( get_post_status( $request_id ) ) ) . '</td>';
                echo '<td>' . esc_html( get_the_date() ) . '</td>';
                echo '<td>' . ( $expires ? esc_html( date_i18n( get_option( 'date_format' ), $expires ) ) : '—' ) . '</td>';
                echo '<td><a class="button" href="' . esc_url( add_query_arg( [ 'page' => 'tb_request_detail', 'request_id' => $request_id ], admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'View', 'trust-badge' ) . '</a></td>';
                echo '</tr>';
            }
            wp_reset_postdata();
        } else {
            echo '<tr><td colspan="6">' . esc_html__( 'No requests found.', 'trust-badge' ) . '</td></tr>';
        }

        echo '</tbody></table>';

        $big = 999999999;
        $pagination = paginate_links( [
            'base'      => str_replace( $big, '%#%', esc_url( add_query_arg( 'paged', $big ) ) ),
            'format'    => '',
            'current'   => max( 1, (int) ( $_GET['paged'] ?? 1 ) ), // phpcs:ignore WordPress.Security.NonceVerification
            'total'     => (int) $query->max_num_pages,
            'type'      => 'array',
            'add_args'  => [ 'status' => $status ],
        ] );

        if ( $pagination ) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . join( '', array_map( 'wp_kses_post', $pagination ) ) . '</div></div>';
        }

        echo '</div>';
    }

    /**
     * Render settings page.
     */
    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'trust-badge' ) );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Trust Badge Settings', 'trust-badge' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'tb_settings' );
        do_settings_sections( 'tb_settings' );
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    /**
     * Render single request detail page.
     */
    public static function render_request_detail_page() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_trust_badges' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'trust-badge' ) );
        }

        $request_id = isset( $_GET['request_id'] ) ? absint( $_GET['request_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $post       = $request_id ? get_post( $request_id ) : null;

        if ( ! $post || TB_CPT::POST_TYPE !== $post->post_type ) {
            wp_die( esc_html__( 'Request not found.', 'trust-badge' ) );
        }

        $listing_id = (int) $post->post_parent;
        $owner_id   = (int) $post->post_author;
        $owner      = $owner_id ? get_userdata( $owner_id ) : null;
        $expires    = (int) get_post_meta( $post->ID, TB_Request::META_EXPIRES_AT, true );
        $documents  = (array) get_post_meta( $post->ID, TB_Request::META_DOCUMENTS, true );
        $status     = get_post_status( $post );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Trust Badge Request Detail', 'trust-badge' ) . '</h1>';

        echo '<table class="widefat fixed">';
        echo '<tbody>';
        echo '<tr><th>' . esc_html__( 'Listing', 'trust-badge' ) . '</th><td>' . ( $listing_id ? '<a href="' . esc_url( get_edit_post_link( $listing_id ) ) . '">' . esc_html( get_the_title( $listing_id ) ) . '</a>' : '—' ) . '</td></tr>';
        echo '<tr><th>' . esc_html__( 'Owner', 'trust-badge' ) . '</th><td>' . ( $owner ? '<a href="' . esc_url( get_edit_user_link( $owner_id ) ) . '">' . esc_html( $owner->display_name ) . '</a>' : '—' ) . '</td></tr>';
        echo '<tr><th>' . esc_html__( 'Contact name', 'trust-badge' ) . '</th><td>' . esc_html( get_post_meta( $post->ID, TB_Request::META_CONTACT_NAME, true ) ) . '</td></tr>';
        echo '<tr><th>' . esc_html__( 'Contact email', 'trust-badge' ) . '</th><td><a href="mailto:' . esc_attr( get_post_meta( $post->ID, TB_Request::META_CONTACT_EMAIL, true ) ) . '">' . esc_html( get_post_meta( $post->ID, TB_Request::META_CONTACT_EMAIL, true ) ) . '</a></td></tr>';
        echo '<tr><th>' . esc_html__( 'Contact phone', 'trust-badge' ) . '</th><td>' . esc_html( get_post_meta( $post->ID, TB_Request::META_CONTACT_PHONE, true ) ) . '</td></tr>';
        echo '<tr><th>' . esc_html__( 'Status', 'trust-badge' ) . '</th><td>' . esc_html( TB_CPT::get_status_label( $status ) ) . '</td></tr>';
        echo '<tr><th>' . esc_html__( 'Submitted', 'trust-badge' ) . '</th><td>' . esc_html( get_the_date( '', $post ) ) . '</td></tr>';
        echo '<tr><th>' . esc_html__( 'Expires', 'trust-badge' ) . '</th><td>' . ( $expires ? esc_html( date_i18n( get_option( 'date_format' ), $expires ) ) : '—' ) . '</td></tr>';
        echo '</tbody>';
        echo '</table>';

        echo '<h2>' . esc_html__( 'Documents', 'trust-badge' ) . '</h2>';
        if ( $documents ) {
            echo '<ul class="tb-documents">';
            foreach ( $documents as $doc_id ) {
                $url   = wp_get_attachment_url( $doc_id );
                $title = get_the_title( $doc_id );
                $title = $title ? $title : sprintf( __( 'Document #%d', 'trust-badge' ), (int) $doc_id );
                if ( $url ) {
                    echo '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="nofollow noopener">' . esc_html( $title ) . '</a></li>';
                } else {
                    echo '<li>' . esc_html( $title ) . '</li>';
                }
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'No documents uploaded.', 'trust-badge' ) . '</p>';
        }

        echo '<h2>' . esc_html__( 'Actions', 'trust-badge' ) . '</h2>';

        echo '<div class="tb-admin-actions">';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="tb-approve-form">';
        wp_nonce_field( 'tb_approve_request' );
        echo '<input type="hidden" name="action" value="tb_approve_request" />';
        echo '<input type="hidden" name="request_id" value="' . esc_attr( $post->ID ) . '" />';
        echo '<label>' . esc_html__( 'Expiry date', 'trust-badge' ) . '<input type="date" name="expires_at" value="' . ( $expires ? esc_attr( gmdate( 'Y-m-d', $expires ) ) : '' ) . '" required /></label> ';
        echo '<label>' . esc_html__( 'Note (optional)', 'trust-badge' ) . '<input type="text" name="note" /></label> ';
        submit_button( __( 'Approve', 'trust-badge' ), 'primary', '', false );
        echo '</form>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="tb-reject-form">';
        wp_nonce_field( 'tb_reject_request' );
        echo '<input type="hidden" name="action" value="tb_reject_request" />';
        echo '<input type="hidden" name="request_id" value="' . esc_attr( $post->ID ) . '" />';
        echo '<label>' . esc_html__( 'Rejection note', 'trust-badge' ) . '<input type="text" name="note" required /></label> ';
        submit_button( __( 'Reject', 'trust-badge' ), 'secondary', '', false );
        echo '</form>';

        if ( 'tb_approved' === $status ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="tb-extend-form">';
            wp_nonce_field( 'tb_extend_request' );
            echo '<input type="hidden" name="action" value="tb_extend_request" />';
            echo '<input type="hidden" name="request_id" value="' . esc_attr( $post->ID ) . '" />';
            echo '<label>' . esc_html__( 'New expiry date', 'trust-badge' ) . '<input type="date" name="expires_at" value="' . ( $expires ? esc_attr( gmdate( 'Y-m-d', $expires ) ) : '' ) . '" required /></label> ';
            submit_button( __( 'Extend', 'trust-badge' ), 'secondary', '', false );
            echo '</form>';

            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="tb-revoke-form">';
            wp_nonce_field( 'tb_revoke_request' );
            echo '<input type="hidden" name="action" value="tb_revoke_request" />';
            echo '<input type="hidden" name="request_id" value="' . esc_attr( $post->ID ) . '" />';
            submit_button( __( 'Revoke Badge', 'trust-badge' ), 'delete', '', false );
            echo '</form>';
        }

        echo '</div>';

        echo '<h2>' . esc_html__( 'Audit Log', 'trust-badge' ) . '</h2>';
        $log = TB_Request::get_audit_log( $post->ID );
        if ( $log ) {
            echo '<ul class="tb-audit-log">';
            foreach ( $log as $entry ) {
                $actor = $entry['actor'] ? get_userdata( $entry['actor'] ) : null;
                echo '<li>' . esc_html( $entry['time'] ) . ' — ' . esc_html( $entry['action'] ) . ( $actor ? ' (' . esc_html( $actor->display_name ) . ')' : '' ) . ( $entry['note'] ? ': ' . esc_html( $entry['note'] ) : '' ) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'No activity recorded yet.', 'trust-badge' ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Handle approve submission.
     */
    public static function handle_approve_request() {
        self::verify_admin_action( 'tb_approve_request' );

        $request_id = absint( $_POST['request_id'] ?? 0 );
        $expires    = strtotime( sanitize_text_field( wp_unslash( $_POST['expires_at'] ?? '' ) ) );
        $note       = sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) );

        if ( ! $request_id || ! $expires ) {
            wp_die( esc_html__( 'Invalid data supplied.', 'trust-badge' ) );
        }

        wp_update_post( [ 'ID' => $request_id, 'post_status' => 'tb_approved' ] );
        TB_Request::update_meta( $request_id, [ TB_Request::META_EXPIRES_AT => $expires, TB_Request::META_DECISION_NOTE => $note ] );
        TB_Request::log_action( $request_id, 'approved', $note );

        wp_safe_redirect( add_query_arg( [ 'page' => 'tb_request_detail', 'request_id' => $request_id, 'updated' => 'approved' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handle rejection.
     */
    public static function handle_reject_request() {
        self::verify_admin_action( 'tb_reject_request' );

        $request_id = absint( $_POST['request_id'] ?? 0 );
        $note       = sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) );

        if ( ! $request_id || empty( $note ) ) {
            wp_die( esc_html__( 'A rejection note is required.', 'trust-badge' ) );
        }

        wp_update_post( [ 'ID' => $request_id, 'post_status' => 'tb_rejected' ] );
        TB_Request::update_meta( $request_id, [ TB_Request::META_DECISION_NOTE => $note ] );
        TB_Request::log_action( $request_id, 'rejected', $note );

        wp_safe_redirect( add_query_arg( [ 'page' => 'tb_request_detail', 'request_id' => $request_id, 'updated' => 'rejected' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handle extend submission.
     */
    public static function handle_extend_request() {
        self::verify_admin_action( 'tb_extend_request' );

        $request_id = absint( $_POST['request_id'] ?? 0 );
        $expires    = strtotime( sanitize_text_field( wp_unslash( $_POST['expires_at'] ?? '' ) ) );

        if ( ! $request_id || ! $expires ) {
            wp_die( esc_html__( 'Invalid data supplied.', 'trust-badge' ) );
        }

        TB_Request::update_meta( $request_id, [ TB_Request::META_EXPIRES_AT => $expires ] );
        TB_Request::log_action( $request_id, 'extended' );

        wp_safe_redirect( add_query_arg( [ 'page' => 'tb_request_detail', 'request_id' => $request_id, 'updated' => 'extended' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handle revoke submission.
     */
    public static function handle_revoke_request() {
        self::verify_admin_action( 'tb_revoke_request' );

        $request_id = absint( $_POST['request_id'] ?? 0 );
        if ( ! $request_id ) {
            wp_die( esc_html__( 'Invalid data supplied.', 'trust-badge' ) );
        }

        wp_update_post( [ 'ID' => $request_id, 'post_status' => 'tb_expired' ] );
        TB_Request::log_action( $request_id, 'revoked' );

        wp_safe_redirect( add_query_arg( [ 'page' => 'tb_request_detail', 'request_id' => $request_id, 'updated' => 'revoked' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Verify nonce and permissions.
     *
     * @param string $action Action.
     */
    protected static function verify_admin_action( $action ) {
        if ( ! ( current_user_can( 'manage_options' ) || current_user_can( 'manage_trust_badges' ) ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'trust-badge' ) );
        }

        check_admin_referer( $action );
    }
}

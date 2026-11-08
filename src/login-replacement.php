<?php

defined( 'ABSPATH' ) || exit;

/* ─── When it applies ────────────────────────────────────────────────── */

/**
 * Returns an address saved on the Pages URL tab, but only if it leads to a published
 * page of this site. A page deleted later must not lock anyone out, so each step falls
 * back to the default form the moment its page is gone.
 *
 * @param string $key
 * @return string
 */
function clu_live_page_url( $key ) {
    static $resolving = false;

    $url = clu_saved_page_url( $key );

    // WPML, for one, asks for wp_login_url() while it resolves an address, and the login
    // link asks this function again. Inside that call the answer is no page, so the link
    // stays the default one there, instead of the two calling each other without end.
    if ( $url === '' || $resolving ) {
        return '';
    }

    $resolving = true;

    try {
        $post_id = url_to_postid( $url );
    } finally {
        $resolving = false;
    }

    return $post_id && get_post_status( $post_id ) === 'publish' ? $url : '';
}

/**
 * Tells whether wp-login.php leads to the plugin's pages. Never in recovery mode, when
 * a broken theme or plugin is the very reason someone needs the default form.
 *
 * @return bool
 */
function clu_login_replacement_is_on() {
    if ( get_option( 'clu_replace_wp_login' ) !== '1' ) {
        return false;
    }

    if ( function_exists( 'wp_is_recovery_mode' ) && wp_is_recovery_mode() ) {
        return false;
    }

    return clu_live_page_url( 'url_page_login' ) !== '';
}

/**
 * Tells whether the request asks for the default login form: the way back in if the
 * login page ever stops working.
 *
 * @return bool
 */
function clu_wants_native_login() {
    return ! empty( $_REQUEST['clu_native'] );
}

/**
 * Returns the login page, leading back to an address afterwards.
 *
 * @param string $redirect
 * @return string
 */
function clu_login_page_url( $redirect = '' ) {
    $url = clu_live_page_url( 'url_page_login' );

    return $redirect !== '' ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url ) : $url;
}

/* ─── Links ──────────────────────────────────────────────────────────── */

/**
 * Not in wp-admin, whose popup for an expired session needs the default form. Not for a
 * re-login, which passes through wp-login.php to clear the old cookies first. And not
 * inside wp-login.php, which builds the address of its next step from this link, such
 * as the admin email check after a login.
 */
add_filter( 'login_url', 'clu_filter_login_url', 20, 3 );
function clu_filter_login_url( $login_url, $redirect = '', $force_reauth = false ) {
    global $pagenow;

    if ( is_admin() || $pagenow === 'wp-login.php' || $force_reauth || ! clu_login_replacement_is_on() ) {
        return $login_url;
    }

    return clu_login_page_url( (string) $redirect );
}

add_filter( 'register_url', 'clu_filter_register_url', 20 );
function clu_filter_register_url( $register_url ) {
    $page = clu_login_replacement_is_on() ? clu_live_page_url( 'url_page_register' ) : '';

    return $page !== '' ? $page : $register_url;
}

add_filter( 'lostpassword_url', 'clu_filter_lostpassword_url', 20 );
function clu_filter_lostpassword_url( $lostpassword_url ) {
    $page = clu_login_replacement_is_on() ? clu_live_page_url( 'url_page_password_lost' ) : '';

    return $page !== '' ? $page : $lostpassword_url;
}

/**
 * After a logout with no address of its own, such as from the admin bar.
 */
add_filter( 'logout_redirect', 'clu_filter_logout_redirect', 20, 2 );
function clu_filter_logout_redirect( $redirect_to, $requested = '' ) {
    if ( $requested !== '' || ! clu_login_replacement_is_on() ) {
        return $redirect_to;
    }

    $page = clu_live_page_url( 'url_page_redirect_after_logout' );

    return $page !== '' ? $page : clu_login_page_url();
}

/* ─── wp-login.php itself ────────────────────────────────────────────── */

/**
 * Returns the action wp-login.php is about to run, by its own rules.
 *
 * @return string
 */
function clu_wp_login_action() {
    $action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? wp_unslash( $_REQUEST['action'] ) : 'login';

    if ( isset( $_GET['key'] ) ) {
        $action = 'resetpass';
    }

    if ( isset( $_GET['checkemail'] ) ) {
        $action = 'checkemail';
    }

    $known = [ 'confirm_admin_email', 'postpass', 'logout', 'lostpassword', 'retrievepassword', 'resetpass', 'rp', 'register', 'checkemail', 'confirmaction', 'login', 'entered_recovery_mode' ];

    if ( ! in_array( $action, $known, true ) && has_filter( 'login_form_' . $action ) === false ) {
        return 'login';
    }

    return $action;
}

/**
 * Returns where a request to wp-login.php should go, or '' to let it run.
 *
 * Only the screens the plugin has a page for. Logging out, password-protected posts,
 * the admin email check, data requests and actions of other plugins all keep running.
 *
 * @return string
 */
function clu_wp_login_target() {
    $is_post = ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST';

    switch ( clu_wp_login_action() ) {
        case 'login':
            // A post stays, so that login forms of other plugins and wp_login_form() work.
            if ( $is_post || isset( $_REQUEST['interim-login'] ) || clu_wants_native_login() ) {
                return '';
            }

            $redirect = isset( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : '';

            return clu_login_page_url( $redirect );

        case 'checkemail':
            return clu_login_page_url();

        case 'register':
            // A post too: this form creates users who skip the plugin's own form.
            $page = clu_live_page_url( 'url_page_register' );

            return $page !== '' ? $page : clu_login_page_url();

        case 'lostpassword':
        case 'retrievepassword':
            return clu_live_page_url( 'url_page_password_lost' );

        case 'rp':
        case 'resetpass':
            $page  = clu_live_page_url( 'url_page_password_set' );
            $key   = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? wp_unslash( $_GET['key'] ) : '';
            $login = isset( $_GET['login'] ) && is_string( $_GET['login'] ) ? wp_unslash( $_GET['login'] ) : '';

            // The link in emails from WordPress itself, such as "Send password reset" in wp-admin.
            if ( $page !== '' && $key !== '' && $login !== '' ) {
                return add_query_arg( [ 'key' => rawurlencode( $key ), 'login' => rawurlencode( $login ) ], $page );
            }

            return clu_live_page_url( 'url_page_password_lost' );
    }

    return '';
}

add_action( 'login_init', 'clu_replace_wp_login' );
function clu_replace_wp_login() {
    if ( ! clu_login_replacement_is_on() ) {
        return;
    }

    $target = clu_wp_login_target();

    if ( $target === '' ) {
        return;
    }

    // Asked for by wp-admin when the cookies no longer fit. The login page would only
    // answer "already logged in", and the user could never get back in.
    if ( ! empty( $_REQUEST['reauth'] ) ) {
        wp_clear_auth_cookie();
    }

    wp_safe_redirect( $target );
    exit;
}

/**
 * Runs just before wp-login.php would show its form again after a failed post from
 * another login form, and sends the visitor to the plugin's page with the error.
 */
add_filter( 'wp_login_errors', 'clu_send_failed_login_to_page' );
function clu_send_failed_login_to_page( $errors ) {
    if ( ! clu_login_replacement_is_on() || isset( $_REQUEST['interim-login'] ) || clu_wants_native_login() ) {
        return $errors;
    }

    $redirect = isset( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : '';

    wp_safe_redirect( add_query_arg( 'clu_login_error', '1', clu_login_page_url( $redirect ) ) );
    exit;
}

/**
 * Keeps someone on the default form through a mistyped password.
 */
add_action( 'login_form', 'clu_keep_native_login' );
function clu_keep_native_login() {
    if ( clu_wants_native_login() ) {
        echo '<input type="hidden" name="clu_native" value="1">';
    }
}

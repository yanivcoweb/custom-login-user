<?php

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_ajax_login_user', 'clu_handle_ajax_login' );
add_action( 'wp_ajax_nopriv_ajax_login_user', 'clu_handle_ajax_login' );
function clu_handle_ajax_login() {
    clu_verify_ajax_nonce( 'login_nonce', 'login_form_nonce' );

    $login    = clu_post_text( 'log' );
    $password = clu_post_password( 'pwd' );

    if ( $login === '' || $password === '' ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'login_failed' ) ] );
    }

    if ( ! clu_verify_captcha() ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'captcha_failed' ) ] );
    }

    $redirect  = clu_get_page_url( 'url_page_redirect_after_login' );
    $requested = isset( $_POST['redirect_to'] ) && is_string( $_POST['redirect_to'] )
        ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), '' )
        : '';
    $redirect  = $requested !== '' ? $requested : $redirect;

    // A user with a second login step goes on to it: see clu_send_to_second_step().
    $GLOBALS['clu_login_redirect'] = $redirect;
    add_action( 'set_logged_in_cookie', 'clu_remember_session_token', 10, 6 );
    add_action( 'wp_login', 'clu_send_to_second_step', 1, 2 );

    $user = wp_signon(
        [
            'user_login'    => $login,
            'user_password' => $password,
            'remember'      => ! empty( $_POST['rememberme'] ),
        ],
        is_ssl()
    );

    remove_action( 'set_logged_in_cookie', 'clu_remember_session_token', 10 );
    remove_action( 'wp_login', 'clu_send_to_second_step', 1 );

    if ( is_wp_error( $user ) ) {
        // These answer the same, so the form cannot be used to find out who has an
        // account. Anything else, such as a security plugin's lockout, keeps its text.
        $credential_errors = [ 'invalid_username', 'invalid_email', 'incorrect_password', 'empty_username', 'empty_password' ];

        $message = in_array( $user->get_error_code(), $credential_errors, true )
            ? clu_render_message( 'login_failed' )
            : wp_kses_post( $user->get_error_message() );

        wp_send_json_error( [ 'message' => $message ] );
    }

    wp_send_json_success( [
        'message'  => clu_render_message( 'login_success' ),
        'redirect' => $redirect,
    ] );
}

/**
 * Keeps the session token of the login under way, so that it can be ended again.
 */
function clu_remember_session_token( $cookie, $expire, $expiration, $user_id, $scheme, $token = '' ) {
    $GLOBALS['clu_login_session_token'] = (string) $token;
}

/**
 * Sends a user who has a second login step, such as a code from the Two Factor plugin,
 * on to it. That plugin prints its own page and stops the request when the password is
 * right, which in an AJAX answer the form cannot show. So the session just started ends
 * here, and the form leads the browser to the second step instead.
 *
 * @param string  $user_login
 * @param WP_User $user
 */
function clu_send_to_second_step( $user_login, $user ) {
    $url = clu_second_step_url( $user, (string) ( $GLOBALS['clu_login_redirect'] ?? '' ) );

    if ( $url === '' ) {
        return;
    }

    if ( ! empty( $GLOBALS['clu_login_session_token'] ) ) {
        WP_Session_Tokens::get_instance( $user->ID )->destroy( $GLOBALS['clu_login_session_token'] );
    }

    wp_clear_auth_cookie();
    wp_send_json_success( [
        'message'  => clu_render_message( 'login_second_step' ),
        'redirect' => $url,
    ] );
}

/**
 * Returns where a user's second login step takes place, or '' when they have none.
 *
 * @param WP_User $user
 * @param string  $redirect Where to go once logged in.
 * @return string
 */
function clu_second_step_url( $user, $redirect ) {
    $url = '';

    if ( class_exists( 'Two_Factor_Core' ) && method_exists( 'Two_Factor_Core', 'is_user_using_two_factor' ) && Two_Factor_Core::is_user_using_two_factor( $user->ID ) ) {
        $nonce = method_exists( 'Two_Factor_Core', 'create_login_nonce' ) ? Two_Factor_Core::create_login_nonce( $user->ID ) : false;

        // The plugin's own code form, or, from a version without the same functions,
        // the default login form, where the plugin asks for the code itself.
        $url = is_array( $nonce ) && ! empty( $nonce['key'] ) && method_exists( 'Two_Factor_Core', 'login_url' )
            ? Two_Factor_Core::login_url( [
                'action'        => 'validate_2fa',
                'wp-auth-id'    => $user->ID,
                'wp-auth-nonce' => $nonce['key'],
                'redirect_to'   => $redirect,
            ] )
            : add_query_arg( 'clu_native', '1', site_url( 'wp-login.php' ) );
    }

    /**
     * Filters where a user's second login step takes place. Another plugin with such a
     * step can return its address here; '' means the user has none.
     *
     * @param string  $url
     * @param WP_User $user
     * @param string  $redirect Where to go once logged in.
     */
    return (string) apply_filters( 'clu_second_step_url', $url, $user, $redirect );
}

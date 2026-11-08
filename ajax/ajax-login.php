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

    $user = wp_signon(
        [
            'user_login'    => $login,
            'user_password' => $password,
            'remember'      => ! empty( $_POST['rememberme'] ),
        ],
        is_ssl()
    );

    if ( is_wp_error( $user ) ) {
        // These answer the same, so the form cannot be used to find out who has an
        // account. Anything else, such as a security plugin's lockout, keeps its text.
        $credential_errors = [ 'invalid_username', 'invalid_email', 'incorrect_password', 'empty_username', 'empty_password' ];

        $message = in_array( $user->get_error_code(), $credential_errors, true )
            ? clu_render_message( 'login_failed' )
            : wp_kses_post( $user->get_error_message() );

        wp_send_json_error( [ 'message' => $message ] );
    }

    $redirect  = clu_get_page_url( 'url_page_redirect_after_login' );
    $requested = isset( $_POST['redirect_to'] ) && is_string( $_POST['redirect_to'] )
        ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), '' )
        : '';

    wp_send_json_success( [
        'message'  => clu_render_message( 'login_success' ),
        'redirect' => $requested !== '' ? $requested : $redirect,
    ] );
}

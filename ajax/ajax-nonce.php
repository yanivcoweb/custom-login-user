<?php

defined( 'ABSPATH' ) || exit;

/**
 * Returns a fresh nonce for one of the front-end forms.
 *
 * A page cache can serve a form whose printed nonce has expired, and a nonce also
 * stops matching when the visitor logs in or out in another tab. Without this, such
 * a form answers "Invalid request" until it is reloaded. A cross-site attacker
 * cannot read this response, so the nonce still does its job against CSRF.
 */
add_action( 'wp_ajax_clu_refresh_nonce', 'clu_handle_refresh_nonce' );
add_action( 'wp_ajax_nopriv_clu_refresh_nonce', 'clu_handle_refresh_nonce' );
function clu_handle_refresh_nonce() {
    $actions = [
        'login_nonce'               => 'login_form_nonce',
        'register_nonce'            => 'register_form_nonce',
        'password_lost_nonce'       => 'password_lost_form_nonce',
        'set_password_nonce'        => 'set_password_form_nonce',
        'my_account_profile_nonce'  => 'my_account_profile_nonce',
        'my_account_password_nonce' => 'my_account_password_nonce',
    ];

    $field = clu_post_text( 'field' );

    if ( ! isset( $actions[ $field ] ) ) {
        wp_send_json_error();
    }

    nocache_headers();
    wp_send_json_success( [ 'nonce' => wp_create_nonce( $actions[ $field ] ) ] );
}

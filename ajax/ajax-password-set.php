<?php

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_ajax_password_set', 'clu_handle_ajax_password_set' );
add_action( 'wp_ajax_nopriv_ajax_password_set', 'clu_handle_ajax_password_set' );
function clu_handle_ajax_password_set() {
    clu_verify_ajax_nonce( 'set_password_nonce', 'set_password_form_nonce' );

    $rp_key       = clu_post_text( 'rp_key' );
    $rp_login     = clu_post_text( 'rp_login' );
    $new_password = clu_post_password( 'new_password' );

    if ( $rp_key === '' || $rp_login === '' || $new_password === '' ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'password_set_missing_fields' ) ] );
    }

    $user = clu_check_reset_key( $rp_key, $rp_login );

    if ( is_wp_error( $user ) ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'invalid_reset_link' ) ] );
    }

    // Covers a key issued before the user became pending, or before this check existed.
    if ( clu_user_is_pending( $user ) ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'account_pending' ) ] );
    }

    // Absent only on a copy of the form from before the field was added, in a page cache.
    if ( isset( $_POST['confirm_password'] ) && clu_post_password( 'confirm_password' ) !== $new_password ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'password_confirm_mismatch' ) ] );
    }

    $errors = clu_validate_password( wp_unslash( $new_password ) );

    if ( $errors ) {
        wp_send_json_error( [ 'message' => implode( '<br>', array_map( 'esc_html', $errors ) ) ] );
    }

    reset_password( $user, $new_password );

    // The user has just proved control of the mailbox, so log them in.
    wp_set_auth_cookie( $user->ID, true );

    wp_send_json_success( [
        'message'  => clu_render_message( 'password_reset_success' ),
        'redirect' => clu_get_page_url( 'url_page_redirect_after_password_set' ),
    ] );
}

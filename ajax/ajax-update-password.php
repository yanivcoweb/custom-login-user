<?php

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_ajax_update_password', 'clu_handle_ajax_update_password' );
function clu_handle_ajax_update_password() {
    clu_verify_ajax_nonce( 'my_account_password_nonce', 'my_account_password_nonce' );

    $user_id          = get_current_user_id();
    $current_password = clu_post_password( 'current_password' );
    $new_password     = clu_post_password( 'new_password' );
    $confirm_password = clu_post_password( 'confirm_password' );

    if ( $current_password === '' || $new_password === '' || $confirm_password === '' ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'profile_update_missing_fields' ) ] );
    }

    if ( ! wp_check_password( $current_password, get_userdata( $user_id )->user_pass, $user_id ) ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'password_current_incorrect' ) ] );
    }

    if ( $new_password !== $confirm_password ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'password_confirm_mismatch' ) ] );
    }

    $errors = clu_validate_password( wp_unslash( $new_password ) );

    if ( $errors ) {
        wp_send_json_error( [ 'message' => implode( '<br>', array_map( 'esc_html', $errors ) ) ] );
    }

    // Also emails the user that the password changed and renews this login cookie.
    $result = wp_update_user( [
        'ID'        => $user_id,
        'user_pass' => $new_password,
    ] );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => wp_kses_post( $result->get_error_message() ) ] );
    }

    wp_send_json_success( [ 'message' => clu_render_message( 'password_update_success' ) ] );
}

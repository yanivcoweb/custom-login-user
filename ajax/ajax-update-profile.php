<?php

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_ajax_update_profile', 'clu_handle_ajax_update_profile' );
function clu_handle_ajax_update_profile() {
    clu_verify_ajax_nonce( 'my_account_profile_nonce', 'my_account_profile_nonce' );

    $user_id    = get_current_user_id();
    $first_name = clu_post_text( 'first_name' );
    $last_name  = clu_post_text( 'last_name' );
    $email      = sanitize_email( clu_post_text( 'email' ) );

    if ( $first_name === '' || $last_name === '' || ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'profile_update_missing_fields' ) ] );
    }

    $existing = email_exists( $email );

    if ( $existing && (int) $existing !== $user_id ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'profile_email_taken' ) ] );
    }

    $result = wp_update_user( wp_slash( [
        'ID'         => $user_id,
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'user_email' => $email,
    ] ) );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => wp_kses_post( $result->get_error_message() ) ] );
    }

    update_user_meta( $user_id, 'company', wp_slash( clu_post_text( 'company' ) ) );

    wp_send_json_success( [ 'message' => clu_render_message( 'profile_update_success' ) ] );
}

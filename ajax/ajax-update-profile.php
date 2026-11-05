<?php

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_ajax_update_profile', 'clu_handle_ajax_update_profile' );
function clu_handle_ajax_update_profile() {
    clu_verify_ajax_nonce( 'my_account_profile_nonce', 'my_account_profile_nonce' );

    $user_id = get_current_user_id();
    $values  = clu_read_posted_fields( clu_get_account_fields(), $user_id );

    if ( is_wp_error( $values ) ) {
        wp_send_json_error( [ 'message' => clu_field_error_message( $values, 'profile_update_missing_fields' ) ] );
    }

    $existing = email_exists( $values['email'] );

    if ( $existing && (int) $existing !== $user_id ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'profile_email_taken' ) ] );
    }

    $update = [
        'ID'         => $user_id,
        'user_email' => $values['email'],
    ];

    foreach ( [ 'first_name', 'last_name' ] as $name ) {
        if ( isset( $values[ $name ] ) ) {
            $update[ $name ] = $values[ $name ];
        }
    }

    $result = wp_update_user( wp_slash( $update ) );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => wp_kses_post( $result->get_error_message() ) ] );
    }

    clu_save_user_field_values( $user_id, $values );

    wp_send_json_success( [ 'message' => clu_render_message( 'profile_update_success' ) ] );
}

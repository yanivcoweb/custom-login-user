<?php

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_nopriv_ajax_register_user', 'clu_handle_ajax_register' );
add_action( 'wp_ajax_ajax_register_user', 'clu_handle_ajax_register' );
function clu_handle_ajax_register() {
    clu_verify_ajax_nonce( 'register_nonce', 'register_form_nonce' );

    if ( ! get_option( 'users_can_register' ) ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'registration_disabled' ) ] );
    }

    if ( is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'user_logged_in_register' ) ] );
    }

    // Trap field, hidden from people. Bots get the normal answer and nothing is created.
    if ( clu_post_text( 'clu_website' ) !== '' ) {
        wp_send_json_success( [ 'message' => clu_render_message( 'register_success' ) ] );
    }

    $values = clu_read_posted_fields( clu_get_register_form_fields() );

    if ( is_wp_error( $values ) ) {
        wp_send_json_error( [ 'message' => clu_field_error_message( $values, 'register_required_fields' ) ] );
    }

    $email      = $values['email'];
    $first_name = $values['first_name'] ?? '';
    $last_name  = $values['last_name'] ?? '';

    if ( email_exists( $email ) ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'email_already_registered' ) ] );
    }

    if ( clu_rate_limited( 'register', 10, HOUR_IN_SECONDS ) ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'too_many_attempts' ) ] );
    }

    // Saved before anything else hears about the new user, because the email to the
    // admin lists these values.
    $save_values = function ( $user_id ) use ( $values ) {
        clu_save_user_field_values( $user_id, $values );
    };

    add_action( 'user_register', $save_values, 1 );

    $user_id = wp_insert_user( wp_slash( [
        'user_login' => clu_generate_user_login( $first_name, $last_name, $email ),
        'user_email' => $email,
        // Nobody ever sees this password: the approval email lets the user choose one.
        'user_pass'  => wp_generate_password( 24 ),
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'nickname'   => trim( $first_name . ' ' . $last_name ),
        'role'       => clu_get_registration_role(),
    ] ) );

    remove_action( 'user_register', $save_values, 1 );

    if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( [ 'message' => wp_kses_post( $user_id->get_error_message() ) ] );
    }

    wp_send_json_success( [ 'message' => clu_render_message( 'register_success' ) ] );
}

/**
 * Builds a free username such as dana.levi.
 *
 * WordPress allows only Latin letters and digits here, so a name written in another
 * script such as Hebrew leaves nothing to use. The address before the @ comes next.
 *
 * @param string $first_name
 * @param string $last_name
 * @param string $email
 * @return string
 */
function clu_generate_user_login( $first_name, $last_name, $email ) {
    $clean = function ( $text ) {
        return trim( substr( sanitize_user( strtolower( $text ), true ), 0, 50 ), ' .-_@' );
    };

    $base = $clean( $first_name . '.' . $last_name );

    if ( $base === '' ) {
        $base = $clean( (string) strstr( $email, '@', true ) );
    }

    if ( $base === '' ) {
        $base = 'user';
    }

    $user_login = $base;
    $suffix     = 1;

    while ( username_exists( $user_login ) ) {
        $user_login = $base . $suffix;
        $suffix++;
    }

    return $user_login;
}

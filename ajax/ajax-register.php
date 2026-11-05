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

    $email  = sanitize_email( clu_post_text( 'email' ) );
    $fields = [];

    foreach ( [ 'first_name', 'last_name', 'company', 'customer_type', 'region', 'country' ] as $name ) {
        $fields[ $name ] = clu_post_text( $name );
    }

    if ( ! is_email( $email ) || in_array( '', $fields, true ) ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'register_required_fields' ) ] );
    }

    if ( email_exists( $email ) ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'email_already_registered' ) ] );
    }

    if ( clu_rate_limited( 'register', 10, HOUR_IN_SECONDS ) ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'too_many_attempts' ) ] );
    }

    $user_id = wp_insert_user( wp_slash( [
        'user_login' => clu_generate_user_login( $fields['first_name'], $fields['last_name'], $email ),
        'user_email' => $email,
        // Nobody ever sees this password: the approval email lets the user choose one.
        'user_pass'  => wp_generate_password( 24 ),
        'first_name' => $fields['first_name'],
        'last_name'  => $fields['last_name'],
        'nickname'   => $fields['first_name'] . ' ' . $fields['last_name'],
        'role'       => clu_get_registration_role(),
    ] ) );

    if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( [ 'message' => wp_kses_post( $user_id->get_error_message() ) ] );
    }

    foreach ( [ 'company', 'customer_type', 'region', 'country' ] as $name ) {
        update_user_meta( $user_id, $name, wp_slash( $fields[ $name ] ) );
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

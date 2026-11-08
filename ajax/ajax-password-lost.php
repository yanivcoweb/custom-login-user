<?php

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_ajax_password_lost', 'clu_handle_ajax_password_lost' );
add_action( 'wp_ajax_nopriv_ajax_password_lost', 'clu_handle_ajax_password_lost' );
function clu_handle_ajax_password_lost() {
    clu_verify_ajax_nonce( 'password_lost_nonce', 'password_lost_form_nonce' );

    $user_login = clu_post_text( 'user_login' );

    if ( $user_login === '' ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'email_required' ) ] );
    }

    if ( ! clu_verify_captcha() ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'captcha_failed' ) ] );
    }

    if ( clu_rate_limited( 'lost_password_ip', 20, 15 * MINUTE_IN_SECONDS ) ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'too_many_attempts' ) ] );
    }

    $user = is_email( $user_login ) ? get_user_by( 'email', $user_login ) : false;

    if ( ! $user ) {
        $user = get_user_by( 'login', $user_login );
    }

    // An unknown address gets the same answer as a known one, so this form cannot be
    // used to find out who has an account. The same goes for a mailbox that already
    // got several emails: more would only be useful for flooding it.
    $sent_response = [ 'message' => clu_render_message( 'reset_email_sent' ) ];

    if ( ! $user ) {
        wp_send_json_success( $sent_response );
    }

    $blocked = clu_blocked_account_message( $user );

    if ( $blocked !== '' ) {
        wp_send_json_error( [ 'message' => clu_render_message( $blocked ) ] );
    }

    if ( clu_rate_limited( 'lost_password_account', 3, 15 * MINUTE_IN_SECONDS, (string) $user->ID ) ) {
        wp_send_json_success( $sent_response );
    }

    $reset_key = get_password_reset_key( $user );

    if ( is_wp_error( $reset_key ) ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'general_error' ) ] );
    }

    $reset_url  = clu_get_set_password_url( $user, $reset_key );
    $email_sent = clu_send_mail( $user->user_email, function () use ( $user, $reset_url ) {
        $name = esc_html( $user->display_name );

        return [
            clu_get_message( 'reset_email_subject' ),
            clu_format_message_template(
                clu_get_message( 'reset_email_body' ),
                [
                    '%1$s'           => $name,
                    '%2$s'           => $reset_url,
                    '{display_name}' => $name,
                    '{reset_url}'    => $reset_url,
                    '{reset_link}'   => clu_message_link( $reset_url, clu_get_message( 'email_link_text' ) ),
                ]
            ),
        ];
    }, $user, clu_get_mail_headers() );

    if ( ! $email_sent ) {
        wp_send_json_error( [ 'message' => clu_render_message( 'reset_email_send_failed' ) ] );
    }

    wp_send_json_success( $sent_response );
}

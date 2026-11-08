<?php

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_clu_save_messages', 'clu_save_messages_handler' );
function clu_save_messages_handler() {
    check_ajax_referer( 'clu_messages_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    // The defaults in the site's language, as the tab shows them.
    $switched = switch_to_locale( get_locale() );
    $defaults = clu_default_messages();

    if ( $switched ) {
        restore_previous_locale();
    }

    $english = clu_english_messages();

    // Subject lines must be plain text; all other fields allow safe HTML via wp_kses_post
    $subject_keys = [ 'reset_email_subject', 'confirm_email_subject', 'notify_received_subject', 'notify_client_subject', 'notify_auto_subject', 'notify_user_subject', 'notify_rejected_subject' ];
    $saved        = [];

    foreach ( array_keys( $defaults ) as $key ) {
        if ( isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ) {
            $value = wp_unslash( $_POST[ $key ] );
            $value = in_array( $key, $subject_keys, true )
                ? sanitize_text_field( $value )
                : clu_sanitize_message_template( $value );

            // A default left as it was is not stored, so that it keeps following the
            // site's language and later versions.
            if ( clu_is_custom_text( $value, $english[ $key ] ?? '', [ $defaults[ $key ] ] ) ) {
                $saved[ $key ] = $value;
            }
        }
    }

    update_option( 'clu_messages', $saved );
    wp_send_json_success( [ 'message' => 'Messages saved.' ] );
}

add_action( 'wp_ajax_clu_restore_default_messages', 'clu_restore_default_messages_handler' );
function clu_restore_default_messages_handler() {
    check_ajax_referer( 'clu_messages_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    delete_option( 'clu_messages' );

    $switched = switch_to_locale( get_locale() );
    $defaults = clu_default_messages();

    if ( $switched ) {
        restore_previous_locale();
    }

    wp_send_json_success( [
        'message'  => 'Messages restored to defaults.',
        'defaults' => $defaults,
    ] );
}

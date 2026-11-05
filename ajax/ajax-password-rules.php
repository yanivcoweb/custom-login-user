<?php

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_clu_save_password_rules', 'clu_save_password_rules_handler' );
function clu_save_password_rules_handler() {
    check_ajax_referer( 'clu_password_rules_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    update_option( 'clu_password_rules', [
        'min_length'        => min( 128, max( 1, (int) clu_post_text( 'min_length' ) ) ),
        'require_uppercase' => empty( $_POST['require_uppercase'] ) ? 0 : 1,
        'require_lowercase' => empty( $_POST['require_lowercase'] ) ? 0 : 1,
        'require_number'    => empty( $_POST['require_number'] ) ? 0 : 1,
        'require_special'   => empty( $_POST['require_special'] ) ? 0 : 1,
    ] );

    wp_send_json_success( [ 'message' => 'Password rules saved.' ] );
}

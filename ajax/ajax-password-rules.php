<?php

add_action( 'wp_ajax_clu_save_password_rules', 'clu_save_password_rules_handler' );
function clu_save_password_rules_handler() {
    check_ajax_referer( 'clu_password_rules_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    update_option( 'clu_password_rules', [
        'min_length'        => max( 1, (int) ( $_POST['min_length']        ?? 6 ) ),
        'require_uppercase' => (int) ( $_POST['require_uppercase'] ?? 0 ),
        'require_lowercase' => (int) ( $_POST['require_lowercase'] ?? 0 ),
        'require_number'    => (int) ( $_POST['require_number']    ?? 0 ),
        'require_special'   => (int) ( $_POST['require_special']   ?? 0 ),
    ] );

    wp_send_json_success( [ 'message' => 'Password rules saved.' ] );
}

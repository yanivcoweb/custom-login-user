<?php

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_clu_save_login_replacement', 'clu_save_login_replacement_handler' );
function clu_save_login_replacement_handler() {
    check_ajax_referer( 'clu_login_replacement_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    if ( empty( $_POST['enabled'] ) ) {
        update_option( 'clu_replace_wp_login', '0' );
        wp_send_json_success( [ 'message' => 'wp-login.php works as usual.' ] );
    }

    $url = clu_live_page_url( 'url_page_login' );

    if ( $url === '' ) {
        wp_send_json_error( [ 'message' => 'First set a published login page of this site on the Pages URL tab, or create one with the button above.' ] );
    }

    update_option( 'clu_replace_wp_login', '1' );

    $page    = get_post( url_to_postid( $url ) );
    $warning = '';

    if ( ! has_shortcode( $page->post_content, 'clu_login_form' ) && ! has_shortcode( $page->post_content, 'custom-login-form' ) ) {
        $warning = 'Saved, but the content of the login page has no login shortcode. Check that the page shows the form, as a form placed with a page builder cannot be seen from here.';
    }

    wp_send_json_success( [
        'message' => $warning !== '' ? $warning : 'wp-login.php now leads to your pages.',
        'warning' => $warning !== '',
    ] );
}

<?php

defined( 'ABSPATH' ) || exit;

/**
 * Renders the login form, or a logout link for someone already logged in.
 *
 * @param array|string $attributes Shortcode attributes.
 * @return string
 */
add_shortcode( 'custom-login-form', 'clu_render_login_form' );
add_shortcode( 'clu_login_form', 'clu_render_login_form' );
function clu_render_login_form( $attributes ) {
    if ( is_user_logged_in() ) {
        return str_replace(
            '{logout_url}',
            wp_logout_url( clu_get_page_url( 'url_page_redirect_after_logout' ) ),
            clu_render_message( 'already_logged_in' )
        );
    }

    clu_enqueue_form_assets();

    $attributes = clu_parse_form_attributes( $attributes );

    $requested               = isset( $_GET['redirect_to'] ) && is_string( $_GET['redirect_to'] ) ? wp_unslash( $_GET['redirect_to'] ) : '';
    $attributes['redirect']  = wp_validate_redirect( $requested, '' );
    // Set when a login posted to wp-login.php failed and was sent here.
    $attributes['error'] = '';

    if ( isset( $_GET['clu_login_error'] ) ) {
        $attributes['error'] = clu_login_lock_remaining() ? clu_login_locked_message() : clu_render_message( 'login_failed' );
    }

    // Where the link that confirms an email address leads, when there is no register page.
    return clu_email_confirmation_notice() . clu_get_template_html( 'form_login', $attributes );
}

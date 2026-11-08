<?php

defined( 'ABSPATH' ) || exit;

/**
 * Renders the form for choosing a password, reached from an emailed link.
 * A link that cannot work shows why instead of a form that would only fail on submit.
 *
 * @param array|string $attributes Shortcode attributes.
 * @return string
 */
add_shortcode( 'custom-password-set', 'clu_render_password_set_form' );
add_shortcode( 'clu_password_set_form', 'clu_render_password_set_form' );
function clu_render_password_set_form( $attributes ) {
    clu_enqueue_form_assets();

    $attributes          = clu_parse_form_attributes( $attributes );
    $attributes['login'] = clu_query_text( 'login' );
    $attributes['key']   = clu_query_text( 'key' );
    $attributes['error'] = '';

    if ( $attributes['login'] === '' || $attributes['key'] === '' ) {
        $attributes['error'] = clu_render_message( 'invalid_reset_link_page' );
    } else {
        $user = clu_check_reset_key( $attributes['key'], $attributes['login'] );

        if ( is_wp_error( $user ) ) {
            $attributes['error'] = clu_render_message( 'expired_reset_link_page' );
        } elseif ( clu_blocked_account_message( $user ) !== '' ) {
            $attributes['error'] = clu_render_message( clu_blocked_account_message( $user ) );
        }
    }

    return clu_get_template_html( 'form_password_set', $attributes );
}

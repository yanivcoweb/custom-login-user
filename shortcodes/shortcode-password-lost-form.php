<?php

defined( 'ABSPATH' ) || exit;

/**
 * Renders the form that starts a password reset.
 *
 * @param array|string $attributes Shortcode attributes.
 * @return string
 */
add_shortcode( 'custom-password-lost-form', 'clu_render_password_lost_form' );
add_shortcode( 'clu_password_lost_form', 'clu_render_password_lost_form' );
function clu_render_password_lost_form( $attributes ) {
    clu_enqueue_form_assets();

    return clu_get_template_html( 'form_password_lost', clu_parse_form_attributes( $attributes ) );
}

<?php

defined( 'ABSPATH' ) || exit;

/**
 * Renders the registration form.
 *
 * @param array|string $attributes Shortcode attributes.
 * @return string
 */
add_shortcode( 'custom-register-form', 'clu_render_register_form' );
add_shortcode( 'clu_register_form', 'clu_render_register_form' );
function clu_render_register_form( $attributes ) {
    if ( is_user_logged_in() ) {
        return clu_render_message( 'user_logged_in_register' );
    }

    if ( ! get_option( 'users_can_register' ) ) {
        return clu_render_message( 'registration_disabled' );
    }

    clu_enqueue_form_assets();
    clu_enqueue_region_script( clu_get_register_form_fields() );

    return clu_get_template_html( 'form_register', clu_parse_form_attributes( $attributes ) );
}

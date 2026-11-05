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
    wp_enqueue_script( 'clu-register' );

    return clu_get_template_html( 'form_register', clu_parse_form_attributes( $attributes ) );
}

/**
 * Returns a placeholder for a register form field: from ACF options where a site
 * set one up, otherwise the given text.
 *
 * @param string $acf_field ACF options field.
 * @param string $fallback
 * @return string
 */
function clu_register_placeholder( $acf_field, $fallback ) {
    $value = function_exists( 'get_field' ) ? get_field( $acf_field, 'option' ) : '';

    return is_string( $value ) && $value !== '' ? $value : $fallback;
}

/**
 * Returns the choices for the customer type field: from an ACF options repeater
 * where a site set one up, otherwise a built-in list.
 *
 * @return string[]
 */
function clu_get_customer_type_options() {
    $choices = [];

    if ( function_exists( 'have_rows' ) && have_rows( 'customer_type_options', 'options' ) ) {
        while ( have_rows( 'customer_type_options', 'options' ) ) {
            the_row();
            $choices[] = (string) get_sub_field( 'customer_type_opt_text' );
        }
    }

    return $choices ?: [ 'Distributor', 'Integrator', 'Installer', 'Consultant', 'End-user' ];
}

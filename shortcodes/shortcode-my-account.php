<?php

defined( 'ABSPATH' ) || exit;

/**
 * Renders the profile and change-password forms for the logged-in user.
 *
 * @return string
 */
add_shortcode( 'my-account', 'clu_render_my_account' );
add_shortcode( 'clu_my_account', 'clu_render_my_account' );
function clu_render_my_account() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . clu_render_message( 'my_account_not_logged_in' ) . '</p>';
    }

    clu_enqueue_form_assets();

    $user   = wp_get_current_user();
    $fields = clu_get_account_fields();
    $values = [];

    foreach ( $fields as $field ) {
        $values[ $field['key'] ] = clu_get_user_field_value( $user, $field );
    }

    clu_enqueue_region_script( $fields );

    return clu_get_template_html( 'form_my_account', [
        'fields' => $fields,
        'values' => $values,
    ] );
}

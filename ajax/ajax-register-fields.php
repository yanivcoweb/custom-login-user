<?php

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_clu_save_register_fields', 'clu_save_register_fields_handler' );
function clu_save_register_fields_handler() {
    check_ajax_referer( 'clu_register_fields_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    $rows = isset( $_POST['fields'] ) && is_string( $_POST['fields'] ) ? json_decode( wp_unslash( $_POST['fields'] ), true ) : null; // phpcs:ignore WordPress.Security

    if ( ! is_array( $rows ) ) {
        wp_send_json_error( [ 'message' => 'The field list could not be read.' ] );
    }

    $fields = clu_sanitize_register_fields( $rows );

    if ( is_wp_error( $fields ) ) {
        wp_send_json_error( [ 'message' => $fields->get_error_message() ] );
    }

    update_option( 'clu_register_fields', $fields );

    wp_send_json_success( [
        'message' => 'Register fields saved.',
        // Lets the screen learn the keys of new fields, so that saving again updates them.
        'keys'    => array_column( $fields, 'key' ),
    ] );
}

/**
 * Cleans the rows sent by the settings screen.
 *
 * @param array $rows
 * @return array[]|WP_Error
 */
function clu_sanitize_register_fields( $rows ) {
    $known = array_column( clu_get_register_fields(), 'key' );
    $taken = $known;
    $clean = [];

    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }

        $key  = isset( $row['key'] ) && is_string( $row['key'] ) ? $row['key'] : '';
        $type = isset( $row['type'] ) && is_string( $row['type'] ) ? $row['type'] : 'text';
        $raw  = isset( $row['label'] ) && is_string( $row['label'] ) ? $row['label'] : '';

        $label = $type === 'checkbox' ? trim( wp_kses( $raw, clu_checkbox_label_tags() ) ) : sanitize_text_field( $raw );

        if ( $label === '' ) {
            return new WP_Error( 'clu_field_label', 'Every field needs a label.' );
        }

        // A key comes only from a field that already exists, never from the request.
        if ( ! in_array( $key, $known, true ) ) {
            $key     = clu_new_field_key( $label, $taken );
            $taken[] = $key;
        }

        $options = [];

        foreach ( isset( $row['options'] ) ? (array) $row['options'] : [] as $option ) {
            $option = is_string( $option ) ? sanitize_text_field( $option ) : '';

            if ( $option !== '' && ! in_array( $option, $options, true ) ) {
                $options[] = $option;
            }
        }

        $clean[] = [
            'key'         => $key,
            'label'       => $label,
            'type'        => $type,
            'enabled'     => ! empty( $row['enabled'] ),
            'required'    => ! empty( $row['required'] ),
            'account'     => ! empty( $row['account'] ),
            'placeholder' => isset( $row['placeholder'] ) && is_string( $row['placeholder'] ) ? sanitize_text_field( $row['placeholder'] ) : '',
            'options'     => $options,
        ];
    }

    $fields = clu_normalize_register_fields( $clean );

    foreach ( $fields as $field ) {
        if ( $field['type'] === 'select' && ! clu_field_choices( $field ) ) {
            return new WP_Error( 'clu_field_options', sprintf( 'The dropdown "%s" needs at least one option.', wp_strip_all_tags( $field['label'] ) ) );
        }
    }

    return $fields;
}

/**
 * Builds the key of a new custom field, which is also its user meta key. It stays the
 * same when the label changes later, so that the values already saved under it stay.
 *
 * @param string   $label
 * @param string[] $taken Keys already in use.
 * @return string
 */
function clu_new_field_key( $label, $taken ) {
    // A label in another script such as Hebrew leaves nothing but the underscores.
    $slug = trim( sanitize_key( str_replace( ' ', '_', remove_accents( wp_strip_all_tags( $label ) ) ) ), '_-' );
    $base = 'clu_field_' . ( $slug !== '' ? substr( $slug, 0, 40 ) : 'custom' );
    $key  = $base;
    $next = 2;

    while ( in_array( $key, $taken, true ) ) {
        $key = $base . '_' . $next;
        $next++;
    }

    return $key;
}

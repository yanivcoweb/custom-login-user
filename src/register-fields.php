<?php

defined( 'ABSPATH' ) || exit;

/* ─── The field list ─────────────────────────────────────────────────── */

/**
 * Returns the built-in fields, as a new site starts with them: names and email on,
 * the others ready to be turned on.
 *
 * @return array[]
 */
function clu_default_register_fields() {
    $base = [
        'type'        => 'text',
        'enabled'     => false,
        'required'    => true,
        'account'     => false,
        'placeholder' => '',
        'options'     => [],
    ];

    return array_map(
        function ( $field ) use ( $base ) {
            return array_merge( $base, $field );
        },
        [
            [ 'key' => 'first_name', 'label' => __( 'First Name', 'custom-login-users' ), 'enabled' => true, 'account' => true ],
            [ 'key' => 'last_name', 'label' => __( 'Last Name', 'custom-login-users' ), 'enabled' => true, 'account' => true ],
            [ 'key' => 'email', 'label' => __( 'Email Address', 'custom-login-users' ), 'type' => 'email', 'enabled' => true, 'account' => true ],
            [ 'key' => 'company', 'label' => __( 'Company Name', 'custom-login-users' ) ],
            [ 'key' => 'customer_type', 'label' => __( 'Customer Type', 'custom-login-users' ), 'type' => 'select', 'options' => [ 'Distributor', 'Integrator', 'Installer', 'Consultant', 'End-user' ] ],
            [ 'key' => 'region', 'label' => __( 'Region', 'custom-login-users' ), 'type' => 'region' ],
            [ 'key' => 'country', 'label' => __( 'Country', 'custom-login-users' ), 'type' => 'country' ],
        ]
    );
}

/**
 * Returns the types a custom field can have.
 *
 * @return string[] Type => label on the settings screen.
 */
function clu_custom_field_types() {
    return [
        'text'     => 'Text',
        'textarea' => 'Long text',
        'tel'      => 'Phone',
        'number'   => 'Number',
        'url'      => 'Web address',
        'select'   => 'Dropdown',
        'checkbox' => 'Checkbox',
    ];
}

/**
 * Returns the HTML a checkbox label may hold: a link, as in "I agree to the terms".
 *
 * @return array
 */
function clu_checkbox_label_tags() {
    return [
        'a' => [
            'href'   => [],
            'target' => [],
            'rel'    => [],
        ],
    ];
}

/**
 * Tells whether a field is one of the built-in ones, whose key and type are fixed.
 *
 * @param string $key
 * @return bool
 */
function clu_is_builtin_field( $key ) {
    return in_array( $key, array_column( clu_default_register_fields(), 'key' ), true );
}

/**
 * Turns saved rows into the complete field list.
 *
 * A built-in field can never go missing or change its type, and the email is always
 * on and required, because the whole approval flow runs through it. Custom fields live
 * under the clu_field_ prefix, so no field can ever write over a meta key that means
 * something to WordPress, such as the one that holds a user's capabilities.
 *
 * @param array $rows
 * @return array[]
 */
function clu_normalize_register_fields( $rows ) {
    static $english = null;

    if ( $english === null ) {
        $english = array_column( clu_untranslated( 'clu_default_register_fields' ), 'label', 'key' );
    }

    $builtins = array_column( clu_default_register_fields(), null, 'key' );
    $fields   = [];

    foreach ( $rows as $row ) {
        $key = is_array( $row ) && isset( $row['key'] ) ? (string) $row['key'] : '';

        if ( $key === '' || isset( $fields[ $key ] ) || ( ! isset( $builtins[ $key ] ) && strpos( $key, 'clu_field_' ) !== 0 ) ) {
            continue;
        }

        $field = array_merge( $builtins[ $key ] ?? [ 'label' => '', 'type' => 'text', 'options' => [] ], $row );

        if ( isset( $builtins[ $key ] ) ) {
            $field['type'] = $builtins[ $key ]['type'];

            // A label left at its English default follows the site's language.
            if ( trim( (string) $field['label'] ) === '' || $field['label'] === $english[ $key ] ) {
                $field['label'] = $builtins[ $key ]['label'];
            }
        } elseif ( ! isset( clu_custom_field_types()[ $field['type'] ] ) ) {
            $field['type'] = 'text';
        }

        $fields[ $key ] = [
            'key'         => $key,
            'label'       => (string) $field['label'],
            'type'        => $field['type'],
            'enabled'     => ! empty( $field['enabled'] ),
            'required'    => ! empty( $field['required'] ),
            'account'     => ! empty( $field['account'] ),
            'placeholder' => (string) ( $field['placeholder'] ?? '' ),
            'options'     => array_values( array_map( 'strval', (array) $field['options'] ) ),
        ];
    }

    // A built-in the saved list lacks stays off, so a saved form does not grow by itself.
    foreach ( $builtins as $key => $builtin ) {
        if ( ! isset( $fields[ $key ] ) ) {
            $fields[ $key ] = array_merge( $builtin, [ 'enabled' => false ] );
        }
    }

    $fields['email'] = array_merge( $fields['email'], [ 'enabled' => true, 'required' => true, 'account' => true ] );

    return array_values( $fields );
}

/**
 * Returns all fields, in the saved order.
 *
 * @return array[]
 */
function clu_get_register_fields() {
    $saved = get_option( 'clu_register_fields' );

    return clu_normalize_register_fields( is_array( $saved ) && $saved ? $saved : clu_default_register_fields() );
}

/**
 * Returns the fields of the register form.
 *
 * @return array[]
 */
function clu_get_register_form_fields() {
    return array_values( array_filter( clu_get_register_fields(), function ( $field ) {
        return $field['enabled'];
    } ) );
}

/**
 * Returns the fields users can see and change on the My Account form.
 *
 * @return array[]
 */
function clu_get_account_fields() {
    return array_values( array_filter( clu_get_register_fields(), function ( $field ) {
        return $field['account'];
    } ) );
}

/**
 * Tells whether a field must be filled in. Only a field the register form asks for can
 * be: someone cannot be held to a field that nobody asked them for when they signed up.
 *
 * @param array $field
 * @return bool
 */
function clu_field_is_required( $field ) {
    return $field['enabled'] && $field['required'];
}

/**
 * Keeps the register form of an existing site exactly as it was: before version 1.2 all
 * of the built-in fields were on and required, with their labels on the Register Form tab.
 */
function clu_keep_legacy_register_fields() {
    if ( get_option( 'clu_register_fields' ) ) {
        return;
    }

    $labels       = (array) get_option( 'clu_register_form', [] );
    $placeholders = [
        'first_name' => 'First Name',
        'last_name'  => 'Last Name',
        'email'      => 'Email',
        'company'    => 'Company Name',
    ];
    $fields       = [];

    foreach ( clu_default_register_fields() as $field ) {
        $key = $field['key'];

        $fields[] = array_merge( $field, [
            'enabled'     => true,
            'required'    => true,
            'account'     => $field['account'] || $key === 'company',
            'label'       => ! empty( $labels[ $key ] ) ? $labels[ $key ] : $field['label'],
            'placeholder' => $placeholders[ $key ] ?? '',
        ] );
    }

    update_option( 'clu_register_fields', $fields );
}

/* ─── Choices ────────────────────────────────────────────────────────── */

/**
 * Returns the regions and their countries.
 *
 * @return array Region => list of countries.
 */
function clu_get_region_countries() {
    static $regions = null;

    if ( $regions === null ) {
        /**
         * Filters the regions and countries of the region and country fields.
         *
         * @param array $regions Region => list of countries.
         */
        $regions = (array) apply_filters( 'clu_region_countries', include CLU_PLUGIN_DIR . 'options-page/arrays/array_regions.php' );
    }

    return $regions;
}

/**
 * Returns the choices of a dropdown. Other field types have none.
 *
 * @param array       $field
 * @param string|null $region For the country field: the chosen region, or null when the
 *                            form has no region field, which offers every country.
 * @return string[]
 */
function clu_field_choices( $field, $region = null ) {
    if ( $field['type'] === 'select' ) {
        $from_acf = $field['key'] === 'customer_type' ? clu_get_acf_customer_types() : [];

        return $from_acf ?: $field['options'];
    }

    if ( $field['type'] === 'region' ) {
        return array_keys( clu_get_region_countries() );
    }

    if ( $field['type'] === 'country' ) {
        $regions = clu_get_region_countries();

        if ( $region !== null ) {
            return $regions[ $region ] ?? [];
        }

        $all = array_merge( ...array_values( $regions ) );
        sort( $all );

        return $all;
    }

    return [];
}

/**
 * Returns the customer types from an ACF options repeater, where a site set one up.
 *
 * @return string[]
 */
function clu_get_acf_customer_types() {
    $choices = [];

    if ( function_exists( 'have_rows' ) && have_rows( 'customer_type_options', 'options' ) ) {
        while ( have_rows( 'customer_type_options', 'options' ) ) {
            the_row();
            $choices[] = (string) get_sub_field( 'customer_type_opt_text' );
        }
    }

    return $choices;
}

/**
 * Returns a field's placeholder: from ACF options for the fields that had one there,
 * where a site set one up, otherwise from the settings.
 *
 * @param array $field
 * @return string
 */
function clu_field_placeholder( $field ) {
    $acf_fields = [
        'first_name' => 'Enter_your_first_name_text',
        'last_name'  => 'Enter_your_last_name_text',
        'email'      => 'Enter_your_email_address_text',
        'company'    => 'Enter_your_company_name_text',
    ];

    if ( isset( $acf_fields[ $field['key'] ] ) && function_exists( 'get_field' ) ) {
        $value = get_field( $acf_fields[ $field['key'] ], 'option' );

        if ( is_string( $value ) && $value !== '' ) {
            return $value;
        }
    }

    return $field['placeholder'];
}

/* ─── Reading and saving ─────────────────────────────────────────────── */

/**
 * Reads one field from the POST body.
 *
 * @param array       $field
 * @param string|null $region See clu_field_choices().
 * @return string|null The clean value, '' when left empty, null when not valid.
 */
function clu_read_posted_field( $field, $region = null ) {
    $posted = isset( $_POST[ $field['key'] ] ) && is_string( $_POST[ $field['key'] ] ) ? wp_unslash( $_POST[ $field['key'] ] ) : ''; // phpcs:ignore WordPress.Security

    if ( $field['type'] === 'checkbox' ) {
        return $posted === '' ? '' : '1';
    }

    if ( $field['type'] === 'textarea' ) {
        return mb_substr( sanitize_textarea_field( $posted ), 0, 5000 );
    }

    $value = sanitize_text_field( $posted );

    if ( $value === '' ) {
        return '';
    }

    switch ( $field['type'] ) {
        case 'email':
            return is_email( $value ) ? sanitize_email( $value ) : null;

        case 'number':
            return is_numeric( $value ) ? $value : null;

        case 'tel':
            return preg_match( '/^[0-9+()#*.\s-]{3,30}$/', $value ) && preg_match( '/[0-9]/', $value ) ? $value : null;

        case 'url':
            $url = esc_url_raw( $value, [ 'http', 'https' ] );

            return $url !== '' && ! preg_match( '/\s/', $value ) && strpos( (string) wp_parse_url( $url, PHP_URL_HOST ), '.' ) !== false ? $url : null;

        case 'select':
        case 'region':
        case 'country':
            return in_array( $value, clu_field_choices( $field, $region ), true ) ? $value : null;
    }

    return mb_substr( $value, 0, 255 );
}

/**
 * Reads a form's fields from the POST body. Only these fields: anything else in the
 * request is ignored, so a user cannot set a field the form does not show them.
 *
 * @param array[] $fields
 * @param int     $user_id When a user edits an existing account. A value they already
 *                         have stays acceptable, even if it left a dropdown since.
 * @return array|WP_Error Key => value, or an error with the code clu_missing_field or
 *                        clu_invalid_field and the field's label as the message.
 */
function clu_read_posted_fields( $fields, $user_id = 0 ) {
    $region = in_array( 'region', array_column( $fields, 'type' ), true ) ? clu_post_text( 'region' ) : null;
    $values = [];

    foreach ( $fields as $field ) {
        $value = clu_read_posted_field( $field, $region );

        if ( $value === null && $user_id && clu_post_text( $field['key'] ) === clu_get_user_field_value( get_userdata( $user_id ), $field ) ) {
            $value = clu_post_text( $field['key'] );
        }

        if ( $value === null ) {
            return new WP_Error( 'clu_invalid_field', wp_strip_all_tags( $field['label'] ) );
        }

        if ( $value === '' && clu_field_is_required( $field ) ) {
            return new WP_Error( 'clu_missing_field', wp_strip_all_tags( $field['label'] ) );
        }

        $values[ $field['key'] ] = $value;
    }

    return $values;
}

/**
 * Returns the message for an error from clu_read_posted_fields().
 *
 * @param WP_Error $error
 * @param string   $missing_message Message key for a required field left empty.
 * @return string
 */
function clu_field_error_message( $error, $missing_message ) {
    if ( $error->get_error_code() === 'clu_missing_field' ) {
        return clu_render_message( $missing_message );
    }

    return str_replace( '{field}', esc_html( $error->get_error_message() ), clu_render_message( 'register_invalid_field' ) );
}

/**
 * Saves a form's values to user meta. The names and the email belong to the user
 * record itself, so the callers pass those to WordPress on their own.
 *
 * @param int   $user_id
 * @param array $values Key => value, as returned by clu_read_posted_fields().
 */
function clu_save_user_field_values( $user_id, $values ) {
    foreach ( array_diff_key( $values, array_flip( [ 'first_name', 'last_name', 'email' ] ) ) as $key => $value ) {
        if ( $value === '' ) {
            delete_user_meta( $user_id, $key );
        } else {
            update_user_meta( $user_id, $key, wp_slash( $value ) );
        }
    }
}

/**
 * Returns a user's value for a field.
 *
 * @param WP_User $user
 * @param array   $field
 * @return string
 */
function clu_get_user_field_value( $user, $field ) {
    if ( ! $user ) {
        return '';
    }

    if ( $field['key'] === 'email' ) {
        return (string) $user->user_email;
    }

    $value = get_user_meta( $user->ID, $field['key'], true );

    return is_scalar( $value ) ? (string) $value : '';
}

/**
 * Returns what a user filled in, for the people who decide on the registration. Fields
 * turned off later still appear if the user has a value in them.
 *
 * @param WP_User $user
 * @return array[] Key => label and value, as plain text.
 */
function clu_get_user_details( $user ) {
    $details = [];

    foreach ( clu_get_register_fields() as $field ) {
        if ( in_array( $field['key'], [ 'first_name', 'last_name' ], true ) ) {
            continue;
        }

        $value = clu_get_user_field_value( $user, $field );

        if ( $value !== '' ) {
            $details[ $field['key'] ] = [
                'label' => wp_strip_all_tags( $field['label'] ),
                'value' => $field['type'] === 'checkbox' ? '✓' : $value,
            ];
        }
    }

    return $details;
}

/**
 * Formats the result of clu_get_user_details() as HTML.
 *
 * @param array[] $details
 * @return string
 */
function clu_format_user_details( $details ) {
    $lines = array_map(
        function ( $detail ) {
            return '<strong>' . esc_html( $detail['label'] ) . ':</strong> ' . nl2br( esc_html( $detail['value'] ), false );
        },
        $details
    );

    return implode( '<br>', $lines );
}

/* ─── Forms ──────────────────────────────────────────────────────────── */

/**
 * Renders a list of fields for a front-end form.
 *
 * @param array[] $fields
 * @param array   $values              Key => current value.
 * @param string  $required_error      Text under a required field left empty.
 * @param string  $email_invalid_error Text under an invalid email address.
 * @param array   $labels              Key => label, for forms with labels of their own.
 * @return string
 */
function clu_render_form_fields( $fields, $values, $required_error, $email_invalid_error, $labels = [] ) {
    $region = in_array( 'region', array_column( $fields, 'type' ), true ) ? (string) ( $values['region'] ?? '' ) : null;
    $html   = '';

    foreach ( $fields as $field ) {
        $html .= clu_get_template_html( 'form_field', [
            'field'               => $field,
            'label'               => $labels[ $field['key'] ] ?? $field['label'],
            'value'               => (string) ( $values[ $field['key'] ] ?? '' ),
            'region'              => $region,
            'required_error'      => $required_error,
            'email_invalid_error' => $email_invalid_error,
        ] );
    }

    return $html;
}

/**
 * Loads the script that links the country list to the region, when a form has both.
 *
 * @param array[] $fields
 */
function clu_enqueue_region_script( $fields ) {
    $types = array_column( $fields, 'type' );

    if ( in_array( 'region', $types, true ) && in_array( 'country', $types, true ) ) {
        wp_enqueue_script( 'clu-register' );
    }
}

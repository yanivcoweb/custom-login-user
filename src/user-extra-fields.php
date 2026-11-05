<?php

defined( 'ABSPATH' ) || exit;

/**
 * Saves the extra fields from the user screen in wp-admin.
 */
add_action( 'personal_options_update', 'clu_save_extra_user_fields' );
add_action( 'edit_user_profile_update', 'clu_save_extra_user_fields' );
function clu_save_extra_user_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    foreach ( clu_get_register_fields() as $field ) {
        $key = $field['key'];

        if ( in_array( $key, [ 'first_name', 'last_name', 'email' ], true ) || ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
            continue;
        }

        $value = $field['type'] === 'textarea' ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : clu_post_text( $key ); // phpcs:ignore WordPress.Security

        update_user_meta( $user_id, $key, wp_slash( $value ) );
    }
}

/**
 * Returns the fields for the user screen in wp-admin: those on the register form, and
 * others only where this user has a value, so a field turned off does not hide data.
 *
 * @param WP_User $user
 * @return array[]
 */
function clu_get_admin_profile_fields( $user ) {
    return array_values( array_filter( clu_get_register_fields(), function ( $field ) use ( $user ) {
        if ( in_array( $field['key'], [ 'first_name', 'last_name', 'email' ], true ) ) {
            return false; // WordPress shows these itself.
        }

        return $field['enabled'] || clu_get_user_field_value( $user, $field ) !== '';
    } ) );
}

/**
 * Shows the extra fields on the user screen in wp-admin.
 */
add_action( 'show_user_profile', 'clu_show_extra_user_fields' );
add_action( 'edit_user_profile', 'clu_show_extra_user_fields' );
function clu_show_extra_user_fields( $user ) {
    echo clu_get_template_html( 'user_extra_fields', $user ); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Adds ID and registration date columns to the users list table.
 */
add_filter( 'manage_users_columns', 'clu_add_user_columns' );
function clu_add_user_columns( $columns ) {
    $new_columns = [];

    foreach ( $columns as $key => $label ) {
        $new_columns[ $key ] = $label;

        if ( $key === 'username' ) {
            $new_columns['user_id']       = __( 'ID', 'custom-login-users' );
            $new_columns['user_reg_date'] = __( 'Date Registered', 'custom-login-users' );
        }
    }

    // Only on the lists where someone decides on registrations, to keep the others short.
    if ( in_array( clu_query_text( 'role' ), [ clu_get_role_slug( 'pending_role' ), clu_get_role_slug( 'rejected_role' ) ], true ) ) {
        $new_columns['clu_details'] = __( 'Registration Details', 'custom-login-users' );
    }

    return $new_columns;
}

add_filter( 'manage_users_custom_column', 'clu_user_column_content', 10, 3 );
function clu_user_column_content( $output, $column_name, $user_id ) {
    if ( $column_name === 'user_id' ) {
        return $user_id;
    }

    if ( $column_name === 'user_reg_date' ) {
        $user = get_userdata( $user_id );

        return $user ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) ) : '';
    }

    if ( $column_name === 'clu_details' ) {
        $user = get_userdata( $user_id );

        // The list has its own email column.
        return $user ? clu_format_user_details( array_diff_key( clu_get_user_details( $user ), [ 'email' => true ] ) ) : '';
    }

    return $output;
}

add_filter( 'manage_users_sortable_columns', 'clu_sortable_user_columns' );
function clu_sortable_user_columns( $columns ) {
    $columns['user_id']       = 'ID';
    $columns['user_reg_date'] = 'registered';

    return $columns;
}

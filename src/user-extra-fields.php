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

    foreach ( [ 'company', 'customer_type', 'region', 'country' ] as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            update_user_meta( $user_id, $field, wp_slash( clu_post_text( $field ) ) );
        }
    }
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

    return $output;
}

add_filter( 'manage_users_sortable_columns', 'clu_sortable_user_columns' );
function clu_sortable_user_columns( $columns ) {
    $columns['user_id']       = 'ID';
    $columns['user_reg_date'] = 'registered';

    return $columns;
}

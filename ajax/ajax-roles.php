<?php

defined( 'ABSPATH' ) || exit;

// Save role assignments
add_action( 'wp_ajax_clu_save_roles', 'clu_save_roles_handler' );
function clu_save_roles_handler() {
    check_ajax_referer( 'clu_roles_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    $pending  = sanitize_key( clu_post_text( 'pending_role' ) );
    $approved = sanitize_key( clu_post_text( 'approved_role' ) );

    if ( $pending === '' || $approved === '' ) {
        wp_send_json_error( [ 'message' => 'Both roles must be selected.' ] );
    }

    if ( ! get_role( $pending ) ) {
        wp_send_json_error( [ 'message' => 'The selected pending role does not exist.' ] );
    }

    if ( ! get_role( $approved ) ) {
        wp_send_json_error( [ 'message' => 'The selected approved role does not exist.' ] );
    }

    if ( clu_role_is_privileged( $pending ) || clu_role_is_privileged( $approved ) ) {
        wp_send_json_error( [ 'message' => 'A role that can manage the site or other users cannot be used here.' ] );
    }

    if ( clu_role_is_reserved( $pending ) || clu_role_is_reserved( $approved ) ) {
        wp_send_json_error( [ 'message' => 'The Rejected User and Unconfirmed User roles are kept for the plugin and cannot be used here.' ] );
    }

    if ( $pending === $approved ) {
        wp_send_json_error( [ 'message' => 'The pending role and the approved role must be different.' ] );
    }

    update_option( 'clu_roles', [
        'pending_role'  => $pending,
        'approved_role' => $approved,
    ] );
    update_option( 'clu_auto_approve', clu_post_text( 'auto_approve' ) === '1' ? '1' : '' );

    wp_send_json_success( [ 'message' => 'Role assignments saved.' ] );
}

// Create a new role
add_action( 'wp_ajax_clu_create_role', 'clu_create_role_handler' );
function clu_create_role_handler() {
    check_ajax_referer( 'clu_roles_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    $slug         = sanitize_key( clu_post_text( 'role_slug' ) );
    $display_name = clu_post_text( 'role_display_name' );

    if ( $slug === '' || $display_name === '' ) {
        wp_send_json_error( [ 'message' => 'Role slug and display name are required.' ] );
    }

    if ( get_role( $slug ) ) {
        wp_send_json_error( [ 'message' => 'A role with this slug already exists.' ] );
    }

    $result = add_role( $slug, $display_name, [
        'read'         => false,
        'edit_posts'   => false,
        'delete_posts' => false,
    ] );

    if ( $result === null ) {
        wp_send_json_error( [ 'message' => 'Failed to create role.' ] );
    }

    wp_send_json_success( [
        'message' => 'Role "' . $display_name . '" created successfully.',
        'slug'    => $slug,
        'name'    => $display_name,
    ] );
}

// Delete a role
add_action( 'wp_ajax_clu_delete_role', 'clu_delete_role_handler' );
function clu_delete_role_handler() {
    check_ajax_referer( 'clu_roles_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    $slug = sanitize_key( clu_post_text( 'role_slug' ) );

    if ( $slug === '' ) {
        wp_send_json_error( [ 'message' => 'Role slug is required.' ] );
    }

    // Prevent deleting built-in WordPress roles
    $protected = [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ];
    if ( in_array( $slug, $protected, true ) ) {
        wp_send_json_error( [ 'message' => 'Built-in WordPress roles cannot be deleted.' ] );
    }

    if ( ! get_role( $slug ) ) {
        wp_send_json_error( [ 'message' => 'Role not found.' ] );
    }

    if ( $slug === clu_get_role_slug( 'pending_role' ) || $slug === clu_get_role_slug( 'approved_role' ) ) {
        wp_send_json_error( [ 'message' => 'This role is assigned above as the pending or approved role. Choose another role there first.' ] );
    }

    if ( clu_role_is_reserved( $slug ) ) {
        wp_send_json_error( [ 'message' => 'The plugin uses this role for rejected users, or for users who have not confirmed their email address.' ] );
    }

    if ( get_users( [ 'role' => $slug, 'number' => 1, 'fields' => 'ID' ] ) ) {
        wp_send_json_error( [ 'message' => 'Users still have this role, and they would be left with none. Move them to another role first.' ] );
    }

    remove_role( $slug );

    wp_send_json_success( [
        'message' => 'Role "' . $slug . '" deleted successfully.',
        'slug'    => $slug,
    ] );
}

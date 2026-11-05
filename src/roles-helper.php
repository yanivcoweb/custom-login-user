<?php

defined( 'ABSPATH' ) || exit;

/**
 * Returns the configured role slug for a given slot.
 *
 * @param string $type  'pending_role', 'approved_role' or 'rejected_role'
 * @return string       The role slug
 */
function clu_get_role_slug( $type ) {
    $options  = get_option( 'clu_roles', [] );
    $defaults = [
        'pending_role'  => 'pending_user',
        'approved_role' => 'approved_user',
        'rejected_role' => 'rejected_user', // Not a setting: rejected users always get this one.
    ];

    return ( is_array( $options ) && ! empty( $options[ $type ] ) ) ? $options[ $type ] : $defaults[ $type ];
}

/**
 * Adds the plugin's own roles. They hold no capabilities.
 */
function clu_add_roles() {
    $capabilities = [
        'read'         => false,
        'edit_posts'   => false,
        'delete_posts' => false,
    ];

    add_role( 'pending_user', __( 'Pending User', 'custom-login-users' ), $capabilities );
    add_role( 'approved_user', __( 'Approved User', 'custom-login-users' ), $capabilities );
    clu_add_rejected_role();
}

/**
 * Adds the role of rejected users, if it is missing. It holds no capabilities.
 */
function clu_add_rejected_role() {
    add_role( 'rejected_user', __( 'Rejected User', 'custom-login-users' ), [
        'read'         => false,
        'edit_posts'   => false,
        'delete_posts' => false,
    ] );
}

/**
 * Tells whether a role is too powerful to be the pending or approved role.
 *
 * Whatever is chosen as the pending role goes to anyone who fills in the public
 * form, and both roles are locked out of the dashboard, so an administrator could
 * hand out full control or lock out the site's own admins.
 *
 * @param string $slug
 * @return bool
 */
function clu_role_is_privileged( $slug ) {
    $role = get_role( $slug );

    if ( ! $role ) {
        return false;
    }

    $capabilities = (array) apply_filters( 'clu_privileged_capabilities', [
        'manage_options',
        'activate_plugins',
        'install_plugins',
        'edit_files',
        'create_users',
        'edit_users',
        'delete_users',
        'promote_users',
        'unfiltered_html',
        'edit_others_posts',
        'manage_woocommerce',
    ] );

    foreach ( $capabilities as $capability ) {
        if ( $role->has_cap( $capability ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Returns the role for a public sign-up.
 *
 * The saved setting is checked again here, because a site may have saved a
 * privileged role before the settings screen started refusing them.
 *
 * @return string
 */
function clu_get_registration_role() {
    $role = clu_get_role_slug( 'pending_role' );

    if ( get_role( $role ) && ! clu_role_is_privileged( $role ) ) {
        return $role;
    }

    if ( ! get_role( 'pending_user' ) ) {
        clu_add_roles();
    }

    return 'pending_user';
}

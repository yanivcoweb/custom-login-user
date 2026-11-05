<?php

defined( 'ABSPATH' ) || exit;

/**
 * Tells whether a user is still waiting for approval.
 *
 * @param mixed $user WP_User, or anything else, which never counts as pending.
 * @return bool
 */
function clu_user_is_pending( $user ) {
    if ( ! $user instanceof WP_User || ! $user->exists() ) {
        return false;
    }

    $roles   = (array) $user->roles;
    $pending = in_array( clu_get_role_slug( 'pending_role' ), $roles, true )
        && ! in_array( clu_get_role_slug( 'approved_role' ), $roles, true )
        && ! user_can( $user, 'manage_options' );

    /**
     * Filters whether a user counts as waiting for approval.
     *
     * @param bool    $pending
     * @param WP_User $user
     */
    return (bool) apply_filters( 'clu_user_is_pending', $pending, $user );
}

/**
 * Stops a pending user from logging in, through any login form on the site.
 *
 * Runs late, so the account's status is only revealed to someone who already
 * passed the password check.
 */
add_filter( 'authenticate', 'clu_block_pending_login', 99 );
function clu_block_pending_login( $user ) {
    if ( clu_user_is_pending( $user ) ) {
        return new WP_Error( 'clu_account_pending', clu_render_message( 'account_pending' ) );
    }

    return $user;
}

/**
 * Stops a pending user from getting a reset key, which would also be a way to log
 * in without approval.
 */
add_filter( 'allow_password_reset', 'clu_block_pending_password_reset', 10, 2 );
function clu_block_pending_password_reset( $allow, $user_id ) {
    return clu_user_is_pending( get_userdata( $user_id ) ) ? false : $allow;
}

/**
 * Tells whether the current user is limited to the front end of the site.
 *
 * @return bool
 */
function clu_current_user_is_front_end_only() {
    $user = wp_get_current_user();

    if ( ! $user->exists() || user_can( $user, 'manage_options' ) ) {
        return false;
    }

    return (bool) array_intersect(
        [ clu_get_role_slug( 'pending_role' ), clu_get_role_slug( 'approved_role' ) ],
        (array) $user->roles
    );
}

add_filter( 'show_admin_bar', 'clu_hide_admin_bar' );
function clu_hide_admin_bar( $show ) {
    return clu_current_user_is_front_end_only() ? false : $show;
}

add_action( 'admin_init', 'clu_restrict_dashboard_access' );
function clu_restrict_dashboard_access() {
    global $pagenow;

    if ( wp_doing_ajax() || $pagenow === 'admin-post.php' ) {
        return;
    }

    if ( clu_current_user_is_front_end_only() ) {
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }
}

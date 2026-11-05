<?php
/**
 * Runs when the plugin is deleted from the Plugins screen, not when it is deactivated.
 *
 * Removes the plugin's settings and its two roles. Accounts and their profile data
 * stay, and a role that users still have stays too, so that nobody loses their role.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$clu_options = [
    'clu_db_version',
    'clu_pages_url',
    'clu_mail',
    'clu_register_form',
    'clu_login_form',
    'clu_password_reset_form',
    'clu_password_lost_form',
    'clu_my_account_form',
    'clu_errors_messages', // No longer used since 1.1.0; sites updated from 1.0 may still have it.
    'clu_password_rules',
    'clu_messages',
    'clu_roles',
];

foreach ( $clu_options as $clu_option ) {
    delete_option( $clu_option );
}

delete_metadata( 'user', 0, 'clu_approval_link', '', true );

foreach ( [ 'pending_user', 'approved_user' ] as $clu_role ) {
    if ( get_role( $clu_role ) && ! get_users( [ 'role' => $clu_role, 'number' => 1, 'fields' => 'ID' ] ) ) {
        remove_role( $clu_role );
    }
}

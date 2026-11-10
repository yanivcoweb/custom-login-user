<?php

defined( 'ABSPATH' ) || exit;

/*
 * A status kept on the user, next to their role and without changing it.
 *
 * For a site where some users are a kind of their own, such as business customers or
 * members, that an admin approves, while everyone keeps the role they have. The name of
 * that kind is a setting. Off by default: a site that does not turn it on sees nothing
 * of it, and the approval of new registrations through roles works as it always did.
 *
 * A status does not stop anyone from logging in and sends no email. It opens restricted
 * content to the approved, and other code can ask for it or listen to its changes.
 */

/* ─── Settings and reading ───────────────────────────────────────────── */

/**
 * Tells whether the site uses the status.
 *
 * @return bool
 */
function clu_status_is_on() {
    $options = get_option( 'clu_user_status' );

    return is_array( $options ) && ( $options['enabled'] ?? '' ) === '1';
}

/**
 * Returns the name the site gave to the kind of user that has a status.
 *
 * @return string
 */
function clu_status_label() {
    $options = get_option( 'clu_user_status' );
    $label   = is_array( $options ) ? trim( (string) ( $options['label'] ?? '' ) ) : '';

    return $label !== '' ? $label : __( 'Status', 'custom-login-users' );
}

/**
 * Returns the statuses and their names. The empty one is a user without a status.
 *
 * @return array<string,string>
 */
function clu_status_names() {
    return [
        ''         => __( 'None', 'custom-login-users' ),
        'pending'  => __( 'Pending', 'custom-login-users' ),
        'approved' => __( 'Approved', 'custom-login-users' ),
        'rejected' => __( 'Rejected', 'custom-login-users' ),
    ];
}

/**
 * Returns a user's status: pending, approved, rejected, or '' for none. Always '' while
 * the site does not use the status, whatever was saved before.
 *
 * @param int $user_id
 * @return string
 */
function clu_get_user_status( $user_id ) {
    if ( ! $user_id || ! clu_status_is_on() ) {
        return '';
    }

    $status = get_user_meta( (int) $user_id, 'clu_status', true );

    return is_string( $status ) && $status !== '' && isset( clu_status_names()[ $status ] ) ? $status : '';
}

/**
 * Tells whether a user's status is approved.
 *
 * @param int $user_id
 * @return bool
 */
function clu_user_status_is_approved( $user_id ) {
    return clu_get_user_status( $user_id ) === 'approved';
}

/**
 * Sets a user's status. The role is not touched.
 *
 * @param int    $user_id
 * @param string $status pending, approved, rejected, or '' to remove it.
 * @return bool False when the site does not use the status, or the user or the status
 *              does not exist.
 */
function clu_set_user_status( $user_id, $status ) {
    $user_id = (int) $user_id;

    if ( ! clu_status_is_on() || ! is_string( $status ) || ! isset( clu_status_names()[ $status ] ) || ! get_userdata( $user_id ) ) {
        return false;
    }

    $old = clu_get_user_status( $user_id );

    if ( $old === $status ) {
        return true;
    }

    if ( $status === '' ) {
        delete_user_meta( $user_id, 'clu_status' );
    } else {
        update_user_meta( $user_id, 'clu_status', $status );
    }

    update_user_meta( $user_id, 'clu_status_changed_at', time() );
    update_user_meta( $user_id, 'clu_status_changed_by', get_current_user_id() );
    delete_transient( 'clu_status_pending_count' );

    /**
     * Fires after a user's status changed.
     *
     * @param int    $user_id
     * @param string $status pending, approved, rejected, or '' for none.
     * @param string $old    The status before.
     */
    do_action( 'clu_user_status_changed', $user_id, $status, $old );

    return true;
}

/**
 * Returns how many users have the pending status.
 *
 * @return int
 */
function clu_status_pending_count() {
    if ( ! clu_status_is_on() ) {
        return 0;
    }

    $count = get_transient( 'clu_status_pending_count' );

    if ( $count === false ) {
        $query = new WP_User_Query( [
            'meta_key'   => 'clu_status',
            'meta_value' => 'pending',
            'fields'     => 'ID',
            'number'     => 1,
        ] );
        $count = $query->get_total();

        set_transient( 'clu_status_pending_count', $count, HOUR_IN_SECONDS );
    }

    return (int) $count;
}

foreach ( [ 'deleted_user', 'update_option_clu_user_status', 'add_option_clu_user_status' ] as $clu_hook ) {
    add_action( $clu_hook, 'clu_forget_status_pending_count' );
}
unset( $clu_hook );

function clu_forget_status_pending_count() {
    delete_transient( 'clu_status_pending_count' );
}

/* ─── Who may change it, and to what ─────────────────────────────────── */

/**
 * Tells whether the current user may change a user's status: whoever may change that
 * user's role.
 *
 * @param int $user_id
 * @return bool
 */
function clu_current_user_can_set_status( $user_id ) {
    return clu_status_is_on() && current_user_can( 'promote_user', (int) $user_id );
}

/**
 * Returns the changes the users list offers for a status, as action => new status.
 *
 * @param string $status
 * @return array<string,string>
 */
function clu_status_actions_for( $status ) {
    if ( $status === 'pending' ) {
        return [ 'approve' => 'approved', 'reject' => 'rejected' ];
    }

    if ( $status === 'rejected' ) {
        return [ 'approve' => 'approved', 'remove' => '' ];
    }

    return $status === 'approved' ? [ 'remove' => '' ] : [];
}

/**
 * Returns the text of an action, with the name of the kind of user, so that it is not
 * taken for the approval of a registration.
 *
 * @param string $action approve, reject or remove.
 * @return string
 */
function clu_status_action_text( $action ) {
    $label = clu_status_label();

    if ( $action === 'approve' ) {
        /* translators: %s: the name of the kind of user, such as Business customer. */
        return sprintf( __( '%s: approve', 'custom-login-users' ), $label );
    }

    if ( $action === 'reject' ) {
        /* translators: %s: the name of the kind of user, such as Business customer. */
        return sprintf( __( '%s: reject', 'custom-login-users' ), $label );
    }

    /* translators: %s: the name of the kind of user, such as Business customer. */
    return sprintf( __( '%s: remove', 'custom-login-users' ), $label );
}

/* ─── The users list ─────────────────────────────────────────────────── */

add_filter( 'manage_users_columns', 'clu_add_status_column' );
function clu_add_status_column( $columns ) {
    if ( clu_status_is_on() ) {
        $columns['clu_status'] = clu_status_label();
    }

    return $columns;
}

add_filter( 'manage_users_custom_column', 'clu_show_status_column', 10, 3 );
function clu_show_status_column( $output, $column, $user_id ) {
    if ( $column !== 'clu_status' ) {
        return $output;
    }

    $status = clu_get_user_status( $user_id );

    return $status === '' ? '&mdash;' : esc_html( clu_status_names()[ $status ] );
}

add_filter( 'user_row_actions', 'clu_add_status_row_actions', 11, 2 );
function clu_add_status_row_actions( $actions, $user ) {
    if ( ! clu_current_user_can_set_status( $user->ID ) ) {
        return $actions;
    }

    $links = [];

    foreach ( array_keys( clu_status_actions_for( clu_get_user_status( $user->ID ) ) ) as $action ) {
        $links[ 'clu_status_' . $action ] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'clu_status_' . $action, 'users[]' => $user->ID ], admin_url( 'users.php' ) ), 'bulk-users' ) ),
            esc_html( clu_status_action_text( $action ) )
        );
    }

    return $links + $actions;
}

add_filter( 'bulk_actions-users', 'clu_add_status_bulk_actions', 11 );
function clu_add_status_bulk_actions( $actions ) {
    if ( clu_status_is_on() && current_user_can( 'promote_users' ) ) {
        foreach ( [ 'approve', 'reject', 'remove' ] as $action ) {
            $actions[ 'clu_status_' . $action ] = clu_status_action_text( $action );
        }
    }

    return $actions;
}

/**
 * Handles the links and the bulk actions, before the users screen starts: the same way
 * and for the same reason as clu_handle_user_decisions().
 */
add_action( 'load-users.php', 'clu_handle_status_actions' );
function clu_handle_status_actions() {
    $action = '';

    foreach ( [ 'action', 'action2' ] as $name ) {
        if ( isset( $_REQUEST[ $name ] ) && is_string( $_REQUEST[ $name ] ) && $_REQUEST[ $name ] !== '-1' ) {
            $action = sanitize_key( wp_unslash( $_REQUEST[ $name ] ) );
            break;
        }
    }

    $targets = [ 'clu_status_approve' => 'approved', 'clu_status_reject' => 'rejected', 'clu_status_remove' => '' ];

    if ( ! isset( $targets[ $action ] ) || ! clu_status_is_on() ) {
        return;
    }

    check_admin_referer( 'bulk-users' );

    $user_ids = isset( $_REQUEST['users'] ) ? array_unique( array_map( 'intval', (array) $_REQUEST['users'] ) ) : [];
    $done     = 0;
    $skipped  = 0;

    foreach ( $user_ids as $user_id ) {
        // A bulk action may name any status: only a change that changes something counts.
        if ( clu_current_user_can_set_status( $user_id ) && get_userdata( $user_id ) && clu_get_user_status( $user_id ) !== $targets[ $action ] && clu_set_user_status( $user_id, $targets[ $action ] ) ) {
            $done++;
        } else {
            $skipped++;
        }
    }

    $back = remove_query_arg( clu_status_query_args(), wp_get_referer() ?: admin_url( 'users.php' ) );

    wp_safe_redirect( add_query_arg( [ 'clu_status_done' => $done, 'clu_status_skipped' => $skipped ], $back ) );
    exit;
}

/**
 * Returns the query arguments that carry the result of a status change to the notice.
 *
 * @return string[]
 */
function clu_status_query_args() {
    return [ 'clu_status_done', 'clu_status_skipped' ];
}

add_filter( 'removable_query_args', 'clu_status_removable_query_args' );
function clu_status_removable_query_args( $args ) {
    return array_merge( $args, clu_status_query_args() );
}

add_action( 'admin_notices', 'clu_show_status_notices' );
function clu_show_status_notices() {
    global $pagenow;

    if ( $pagenow !== 'users.php' ) {
        return;
    }

    $count = function ( $name ) {
        return isset( $_GET[ $name ] ) && is_string( $_GET[ $name ] ) ? absint( $_GET[ $name ] ) : 0;
    };

    // Counted first: the tools that collect texts for translation skip a call as the count.
    $done    = $count( 'clu_status_done' );
    $skipped = $count( 'clu_status_skipped' );

    if ( $done ) {
        /* translators: %d: number of users. */
        printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( sprintf( _n( 'The status of %d user was updated.', 'The status of %d users was updated.', $done, 'custom-login-users' ), $done ) ) );
    }

    if ( $skipped ) {
        /* translators: %d: number of users. */
        printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( sprintf( _n( '%d user was skipped: already in that status, or you may not change it.', '%d users were skipped: already in that status, or you may not change it.', $skipped, 'custom-login-users' ), $skipped ) ) );
    }
}

/**
 * Adds a link to the users with a pending status above the users list.
 */
add_filter( 'views_users', 'clu_add_status_view' );
function clu_add_status_view( $views ) {
    if ( ! clu_status_is_on() ) {
        return $views;
    }

    $count = clu_status_pending_count();

    if ( ! $count && clu_query_text( 'clu_status' ) !== 'pending' ) {
        return $views;
    }

    $views['clu_status_pending'] = sprintf(
        '<a href="%s"%s>%s <span class="count">(%s)</span></a>',
        esc_url( admin_url( 'users.php?clu_status=pending' ) ),
        clu_query_text( 'clu_status' ) === 'pending' ? ' class="current" aria-current="page"' : '',
        /* translators: %s: the name of the kind of user, such as Business customer. */
        esc_html( sprintf( __( '%s: pending', 'custom-login-users' ), clu_status_label() ) ),
        esc_html( number_format_i18n( $count ) )
    );

    return $views;
}

add_action( 'pre_get_users', 'clu_filter_users_by_status' );
function clu_filter_users_by_status( $query ) {
    global $pagenow;

    if ( ! is_admin() || $pagenow !== 'users.php' || ! clu_status_is_on() ) {
        return;
    }

    $status = clu_query_text( 'clu_status' );

    if ( $status !== '' && isset( clu_status_names()[ $status ] ) ) {
        $query->set( 'meta_key', 'clu_status' );
        $query->set( 'meta_value', $status );
    }
}

/* ─── The user's own screen ──────────────────────────────────────────── */

add_action( 'show_user_profile', 'clu_show_status_field', 5 );
add_action( 'edit_user_profile', 'clu_show_status_field', 5 );
function clu_show_status_field( $user ) {
    if ( ! clu_current_user_can_set_status( $user->ID ) ) {
        return;
    }

    $status = clu_get_user_status( $user->ID );
    ?>
    <h2><?php echo esc_html( clu_status_label() ); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th><label for="clu_status"><?php esc_html_e( 'Status', 'custom-login-users' ); ?></label></th>
            <td>
                <input type="hidden" name="clu_status_was" value="<?php echo esc_attr( $status ); ?>">
                <select name="clu_status" id="clu_status">
                    <?php foreach ( clu_status_names() as $value => $name ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'Changing it does not change the role of the user.', 'custom-login-users' ); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

add_action( 'personal_options_update', 'clu_save_status_field' );
add_action( 'edit_user_profile_update', 'clu_save_status_field' );
function clu_save_status_field( $user_id ) {
    if ( ! isset( $_POST['clu_status'], $_POST['clu_status_was'] ) || ! is_string( $_POST['clu_status'] ) || ! is_string( $_POST['clu_status_was'] ) ) {
        return;
    }

    if ( ! clu_current_user_can_set_status( $user_id ) ) {
        return;
    }

    $status = sanitize_key( wp_unslash( $_POST['clu_status'] ) );

    // Only a status the admin changed on this screen, so that saving a screen left open
    // does not undo a decision taken elsewhere in the meantime.
    if ( $status !== sanitize_key( wp_unslash( $_POST['clu_status_was'] ) ) ) {
        clu_set_user_status( $user_id, $status );
    }
}

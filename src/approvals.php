<?php

defined( 'ABSPATH' ) || exit;

/* ─── Who can be decided on, and by whom ─────────────────────────────── */

/**
 * Returns the decisions open for a user: approve and reject while pending, and
 * approve for a user who was rejected, so that a mistake can be undone.
 *
 * @param WP_User $user
 * @return string[]
 */
function clu_available_decisions( $user ) {
    if ( clu_user_is_pending( $user ) ) {
        return [ 'approve', 'reject' ];
    }

    return clu_user_is_rejected( $user ) ? [ 'approve' ] : [];
}

/**
 * Returns the role a decision moves a user to.
 *
 * @param string $decision approve or reject.
 * @return string
 */
function clu_decision_role( $decision ) {
    return clu_get_role_slug( $decision === 'reject' ? 'rejected_role' : 'approved_role' );
}

/**
 * Tells whether the current user may take a decision on a user. The same two checks
 * as the "Change role to" control of the users list: the right to change this user's
 * role, and the right to hand out the target role.
 *
 * @param WP_User $user
 * @param string  $decision
 * @return bool
 */
function clu_current_user_can_decide( $user, $decision ) {
    if ( ! current_user_can( 'promote_user', $user->ID ) ) {
        return false;
    }

    if ( ! function_exists( 'get_editable_roles' ) ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }

    return isset( get_editable_roles()[ clu_decision_role( $decision ) ] );
}

/**
 * Approves or rejects a user. The emails go out from clu_on_user_role_change(), the
 * same as when an admin changes the role on the user's own screen.
 *
 * @param int    $user_id
 * @param string $decision approve or reject.
 * @return string done, mail_failed (the role changed, the email did not go out) or skipped.
 */
function clu_decide_user( $user_id, $decision ) {
    $user = get_userdata( $user_id );

    if ( ! $user || ! in_array( $decision, clu_available_decisions( $user ), true ) || ! clu_current_user_can_decide( $user, $decision ) ) {
        return 'skipped';
    }

    $mail_failed = false;
    $watch       = function () use ( &$mail_failed ) {
        $mail_failed = true;
    };

    if ( $decision === 'reject' ) {
        clu_add_rejected_role();
    }

    add_action( 'clu_decision_mail_failed', $watch );
    $user->set_role( clu_decision_role( $decision ) );
    remove_action( 'clu_decision_mail_failed', $watch );

    return $mail_failed ? 'mail_failed' : 'done';
}

/* ─── The users list: links, bulk actions, notices ───────────────────── */

add_filter( 'user_row_actions', 'clu_add_user_row_actions', 10, 2 );
function clu_add_user_row_actions( $actions, $user ) {
    $labels = [
        'approve' => __( 'Approve', 'custom-login-users' ),
        'reject'  => __( 'Reject', 'custom-login-users' ),
    ];
    $links  = [];

    foreach ( clu_available_decisions( $user ) as $decision ) {
        if ( clu_current_user_can_decide( $user, $decision ) ) {
            $links[ 'clu_' . $decision ] = sprintf(
                '<a href="%s" class="clu-%s-link">%s</a>',
                esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'clu_' . $decision, 'users[]' => $user->ID ], admin_url( 'users.php' ) ), 'bulk-users' ) ),
                esc_attr( $decision ),
                esc_html( $labels[ $decision ] )
            );
        }
    }

    return $links + $actions;
}

add_filter( 'bulk_actions-users', 'clu_add_user_bulk_actions' );
function clu_add_user_bulk_actions( $actions ) {
    if ( current_user_can( 'promote_users' ) ) {
        $actions['clu_approve'] = __( 'Approve', 'custom-login-users' );
        $actions['clu_reject']  = __( 'Reject', 'custom-login-users' );
    }

    return $actions;
}

/**
 * Handles the links and the bulk actions, before the users screen starts.
 *
 * Not through handle_bulk_actions-users: users.php first redirects a bulk request to
 * an address without its nonce, so that filter has nothing left to verify.
 */
add_action( 'load-users.php', 'clu_handle_user_decisions' );
function clu_handle_user_decisions() {
    $action = '';

    foreach ( [ 'action', 'action2' ] as $name ) {
        if ( isset( $_REQUEST[ $name ] ) && is_string( $_REQUEST[ $name ] ) && $_REQUEST[ $name ] !== '-1' ) {
            $action = sanitize_key( wp_unslash( $_REQUEST[ $name ] ) );
            break;
        }
    }

    if ( $action !== 'clu_approve' && $action !== 'clu_reject' ) {
        return;
    }

    check_admin_referer( 'bulk-users' );

    $decision = $action === 'clu_reject' ? 'reject' : 'approve';
    $user_ids = isset( $_REQUEST['users'] ) ? array_unique( array_map( 'intval', (array) $_REQUEST['users'] ) ) : [];
    $counts   = [ 'done' => 0, 'mail_failed' => 0, 'skipped' => 0 ];

    foreach ( $user_ids as $user_id ) {
        $counts[ clu_decide_user( $user_id, $decision ) ]++;
    }

    $back = remove_query_arg( clu_decision_query_args(), wp_get_referer() ?: admin_url( 'users.php' ) );

    wp_safe_redirect( add_query_arg(
        [
            // Someone whose email failed was still approved or rejected.
            $decision === 'reject' ? 'clu_rejected' : 'clu_approved' => $counts['done'] + $counts['mail_failed'],
            'clu_mail_failed' => $counts['mail_failed'],
            'clu_skipped'     => $counts['skipped'],
        ],
        $back
    ) );
    exit;
}

/**
 * Returns the query arguments that carry the result of a decision to the notice.
 *
 * @return string[]
 */
function clu_decision_query_args() {
    return [ 'clu_approved', 'clu_rejected', 'clu_mail_failed', 'clu_skipped' ];
}

add_filter( 'removable_query_args', 'clu_removable_query_args' );
function clu_removable_query_args( $args ) {
    return array_merge( $args, clu_decision_query_args() );
}

add_action( 'admin_notices', 'clu_show_decision_notices' );
function clu_show_decision_notices() {
    global $pagenow;

    if ( $pagenow !== 'users.php' ) {
        return;
    }

    $count = function ( $name ) {
        return isset( $_GET[ $name ] ) && is_string( $_GET[ $name ] ) ? absint( $_GET[ $name ] ) : 0;
    };

    $notices = [];

    if ( $count( 'clu_approved' ) ) {
        /* translators: %d: number of users. */
        $notices[] = [ 'success', sprintf( _n( '%d user approved. A set-password email went out.', '%d users approved. A set-password email went out to each.', $count( 'clu_approved' ), 'custom-login-users' ), $count( 'clu_approved' ) ) ];
    }

    if ( $count( 'clu_rejected' ) ) {
        /* translators: %d: number of users. */
        $notices[] = [ 'success', sprintf( _n( '%d user rejected.', '%d users rejected.', $count( 'clu_rejected' ), 'custom-login-users' ), $count( 'clu_rejected' ) ) ];
    }

    if ( $count( 'clu_mail_failed' ) ) {
        /* translators: %d: number of users. */
        $notices[] = [ 'error', sprintf( _n( 'The email to %d user could not be sent, so they do not know about the decision yet.', 'The emails to %d users could not be sent, so they do not know about the decision yet.', $count( 'clu_mail_failed' ), 'custom-login-users' ), $count( 'clu_mail_failed' ) ) ];
    }

    if ( $count( 'clu_skipped' ) ) {
        /* translators: %d: number of users. */
        $notices[] = [ 'warning', sprintf( _n( '%d user was skipped: not waiting for this decision, or you may not change their role.', '%d users were skipped: not waiting for this decision, or you may not change their role.', $count( 'clu_skipped' ), 'custom-login-users' ), $count( 'clu_skipped' ) ) ];
    }

    foreach ( $notices as $notice ) {
        printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notice[0] ), esc_html( $notice[1] ) );
    }
}

add_action( 'admin_enqueue_scripts', 'clu_enqueue_users_list_assets' );
function clu_enqueue_users_list_assets( $hook ) {
    if ( $hook !== 'users.php' ) {
        return;
    }

    wp_enqueue_script( 'clu-users-list', CLU_PLUGIN_URL . 'js/users-list.js', [], CLU_VERSION, true );
    wp_localize_script( 'clu-users-list', 'cluUsersList', [
        'confirmReject' => __( 'Reject and send the rejection email?', 'custom-login-users' ),
    ] );
}

/* ─── The list of users awaiting approval ────────────────────────────── */

/**
 * Returns the address of the users list filtered to those awaiting approval, relative
 * to wp-admin.
 *
 * @return string
 */
function clu_pending_list_path() {
    return 'users.php?role=' . rawurlencode( clu_get_role_slug( 'pending_role' ) );
}

/**
 * Returns how many users await approval. Kept in a transient, because the menu asks
 * on every admin screen and the query searches user meta.
 *
 * @return int
 */
function clu_get_pending_count() {
    $count = get_transient( 'clu_pending_count' );

    if ( $count === false ) {
        $query = new WP_User_Query( [
            'role'   => clu_get_role_slug( 'pending_role' ),
            'fields' => 'ID',
            'number' => 1,
        ] );
        $count = $query->get_total();

        set_transient( 'clu_pending_count', $count, HOUR_IN_SECONDS );
    }

    return (int) $count;
}

foreach ( [ 'user_register', 'set_user_role', 'add_user_role', 'remove_user_role', 'deleted_user', 'update_option_clu_roles' ] as $clu_hook ) {
    add_action( $clu_hook, 'clu_forget_pending_count' );
}
unset( $clu_hook );

function clu_forget_pending_count() {
    delete_transient( 'clu_pending_count' );
}

/**
 * Shows the number of users awaiting approval on the Users menu, and adds a link to them.
 */
add_action( 'admin_menu', 'clu_add_pending_menu', 99 );
function clu_add_pending_menu() {
    global $menu;

    if ( ! current_user_can( 'promote_users' ) ) {
        return;
    }

    $count = clu_get_pending_count();

    if ( ! $count ) {
        return;
    }

    $badge = sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%2$s</span></span>', $count, esc_html( number_format_i18n( $count ) ) );

    foreach ( (array) $menu as $position => $item ) {
        if ( ( $item[2] ?? '' ) === 'users.php' ) {
            $menu[ $position ][0] .= $badge; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
            break;
        }
    }

    $title = __( 'Awaiting Approval', 'custom-login-users' );

    add_submenu_page( 'users.php', $title, esc_html( $title ) . $badge, 'promote_users', clu_pending_list_path() );
}

add_filter( 'submenu_file', 'clu_highlight_pending_menu' );
function clu_highlight_pending_menu( $submenu_file ) {
    global $pagenow;

    if ( $pagenow === 'users.php' && clu_query_text( 'role' ) === clu_get_role_slug( 'pending_role' ) ) {
        return clu_pending_list_path();
    }

    return $submenu_file;
}

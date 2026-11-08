<?php

defined( 'ABSPATH' ) || exit;

/**
 * Confirming the email address of a new user before the registration reaches the admin.
 *
 * While unconfirmed, the account has the Unconfirmed User role: it cannot log in, it is
 * not counted as awaiting approval, and nobody hears about it. The link in the email
 * moves it to the pending role, and from there everything goes on as before. Registering
 * again with the same address sends a new link, so a lost or expired one is no dead end.
 */

/**
 * Tells whether new users confirm their address first. Set on the Mail tab.
 *
 * @return bool
 */
function clu_email_confirmation_is_on() {
    return clu_get_mail_setting( 'confirm_email' ) === '1';
}

/**
 * Returns how long a confirmation link stays valid, in seconds.
 *
 * @return int
 */
function clu_email_confirm_lifetime() {
    return (int) apply_filters( 'clu_email_confirm_lifetime', 7 * DAY_IN_SECONDS );
}

/**
 * Returns what is kept of a confirmation key. Only a hash, so that a copy of the
 * database holds no working link.
 *
 * @param string $key
 * @return string
 */
function clu_email_confirm_hash( $key ) {
    return hash_hmac( 'sha256', $key, wp_salt( 'auth' ) );
}

/**
 * Moves an account to the Unconfirmed User role and emails it the link.
 *
 * @param WP_User $user
 * @return bool Whether the email went out.
 */
function clu_ask_to_confirm_email( $user ) {
    clu_add_unconfirmed_role();
    $user->set_role( clu_get_role_slug( 'unconfirmed_role' ) );

    return clu_send_confirmation_email( $user );
}

/**
 * Emails a user the link that confirms their address. A new link replaces the one sent
 * before.
 *
 * @param WP_User $user
 * @return bool Whether the email went out.
 */
function clu_send_confirmation_email( $user ) {
    $key = wp_generate_password( 32, false );

    update_user_meta( $user->ID, 'clu_email_confirm', time() . ':' . clu_email_confirm_hash( $key ) );

    $url = add_query_arg(
        [
            'clu_confirm' => $key,
            'clu_user'    => $user->ID,
        ],
        clu_get_page_url( 'url_page_register', home_url( '/' ) )
    );

    return clu_send_mail( $user->user_email, function () use ( $user, $url ) {
        $name = esc_html( $user->display_name );

        return [
            clu_format_subject( 'confirm_email_subject', $user->display_name ),
            clu_format_message_template(
                clu_get_message( 'confirm_email_body' ),
                [
                    '%1$s'           => $name,
                    '%2$s'           => $url,
                    '{display_name}' => $name,
                    '{confirm_url}'  => $url,
                    '{confirm_link}' => clu_message_link( $url, clu_get_message( 'email_confirm_link_text' ) ),
                ]
            ),
        ];
    }, $user );
}

/**
 * Checks a confirmation link and moves the user on to approval. The admin hears about
 * the registration from clu_on_user_role_change().
 *
 * The hash stays after the address is confirmed, so that a second click on the same
 * link, which mail apps and link scanners often make, is told apart from a wrong one.
 *
 * @param int    $user_id
 * @param string $key
 * @return string confirmed, already (this link confirmed the address before) or invalid.
 */
function clu_confirm_user_email( $user_id, $key ) {
    $user  = $user_id ? get_userdata( $user_id ) : false;
    $saved = $user ? (string) get_user_meta( $user->ID, 'clu_email_confirm', true ) : '';

    if ( $key === '' || strpos( $saved, ':' ) === false ) {
        return 'invalid';
    }

    list( $time, $hash ) = explode( ':', $saved, 2 );

    if ( ! hash_equals( $hash, clu_email_confirm_hash( $key ) ) ) {
        return 'invalid';
    }

    if ( ! clu_user_is_unconfirmed( $user ) ) {
        return 'already';
    }

    if ( time() > (int) $time + clu_email_confirm_lifetime() ) {
        return 'invalid';
    }

    update_user_meta( $user->ID, 'clu_email_confirmed', time() );
    $user->set_role( clu_get_registration_role() );

    return 'confirmed';
}

/* ─── The link from the email ────────────────────────────────────────── */

/**
 * Handles the link from the email, before anything else on the page, and shows the
 * result on the register or login page. With neither page published, on a page of its
 * own, so the user always learns what happened.
 */
add_action( 'template_redirect', 'clu_handle_email_confirmation_link', 0 );
function clu_handle_email_confirmation_link() {
    $key = clu_query_text( 'clu_confirm' );

    if ( $key === '' ) {
        return;
    }

    $result = clu_confirm_user_email( absint( clu_query_text( 'clu_user' ) ), $key );
    $page   = clu_live_page_url( 'url_page_register' );

    if ( $page === '' ) {
        $page = clu_live_page_url( 'url_page_login' );
    }

    nocache_headers();

    if ( $page === '' ) {
        wp_die( clu_email_confirmation_notice( $result ), esc_html( get_bloginfo( 'name' ) ), [ 'response' => 200 ] ); // phpcs:ignore WordPress.Security.EscapeOutput
    }

    wp_safe_redirect( add_query_arg( 'clu_email_confirmed', $result, $page ) );
    exit;
}

/**
 * Returns the notice about a confirmation link, or '' when there is none to show.
 *
 * @param string|null $result confirmed, already or invalid. Read from the address when null.
 * @return string
 */
function clu_email_confirmation_notice( $result = null ) {
    $result   = $result === null ? clu_query_text( 'clu_email_confirmed' ) : $result;
    $messages = [
        'confirmed' => clu_auto_approve_is_on() ? 'email_confirmed_auto' : 'email_confirmed',
        'already'   => 'email_already_confirmed',
        'invalid'   => 'email_confirm_invalid',
    ];

    if ( ! isset( $messages[ $result ] ) ) {
        return '';
    }

    return sprintf(
        '<div class="clu-response clu-response--%s clu-confirm-notice" role="status">%s</div>',
        $result === 'invalid' ? 'error' : 'success',
        clu_render_message( $messages[ $result ] )
    );
}

<?php

defined( 'ABSPATH' ) || exit;

/* ─── Request data ───────────────────────────────────────────────────── */

/**
 * Returns a text field from the POST body, or '' when it is missing or not a string.
 *
 * @param string $field
 * @return string
 */
function clu_post_text( $field ) {
    if ( ! isset( $_POST[ $field ] ) || ! is_string( $_POST[ $field ] ) ) {
        return '';
    }

    return sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
}

/**
 * Returns a password from the POST body exactly as WordPress expects it.
 *
 * WordPress keeps passwords slashed from end to end: wp-login.php and WooCommerce
 * both hand $_POST to wp_signon() and reset_password() as is. Unslashing or
 * sanitizing here would change what gets hashed, and a password set in one place
 * would stop matching in the other. Only the outer whitespace goes, because
 * wp_authenticate() trims it too.
 *
 * @param string $field
 * @return string
 */
function clu_post_password( $field ) {
    if ( ! isset( $_POST[ $field ] ) || ! is_string( $_POST[ $field ] ) ) {
        return '';
    }

    return trim( $_POST[ $field ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

/**
 * Returns a text field from the query string, or '' when it is missing or not a string.
 *
 * @param string $field
 * @return string
 */
function clu_query_text( $field ) {
    if ( ! isset( $_GET[ $field ] ) || ! is_string( $_GET[ $field ] ) ) {
        return '';
    }

    return sanitize_text_field( wp_unslash( $_GET[ $field ] ) );
}

/**
 * Stops the AJAX request unless the form's nonce is valid.
 *
 * The 'code' lets validation.js fetch a fresh nonce and retry once, which keeps
 * forms working on cached pages whose printed nonce has expired.
 *
 * @param string $field  Name of the form field that holds the nonce.
 * @param string $action Nonce action.
 */
function clu_verify_ajax_nonce( $field, $action ) {
    if ( ! wp_verify_nonce( clu_post_text( $field ), $action ) ) {
        wp_send_json_error( [
            'code'    => 'invalid_nonce',
            'message' => clu_render_message( 'invalid_nonce' ),
        ] );
    }
}

/**
 * Tells whether an action went over its limit, and counts this attempt if it did not.
 *
 * @param string $bucket  Name of the action being limited.
 * @param int    $limit   Attempts allowed inside the window. 0 turns the limit off.
 * @param int    $window  Window length in seconds.
 * @param string $subject What to count per. Defaults to the client IP.
 * @return bool
 */
function clu_rate_limited( $bucket, $limit, $window, $subject = '' ) {
    /**
     * Filters the attempts allowed for a rate-limited action.
     *
     * @param int    $limit  Attempts allowed. 0 turns the limit off.
     * @param string $bucket register, confirm_resend, lost_password_ip or lost_password_account.
     */
    $limit = (int) apply_filters( 'clu_rate_limit', $limit, $bucket );

    if ( $limit <= 0 ) {
        return false;
    }

    $key   = 'clu_rl_' . md5( $bucket . '|' . ( $subject !== '' ? $subject : clu_client_ip() ) );
    $count = (int) get_transient( $key );

    if ( $count >= $limit ) {
        return true;
    }

    set_transient( $key, $count + 1, $window );

    return false;
}

/**
 * Returns the client IP used for rate limiting.
 *
 * Behind a proxy or CDN REMOTE_ADDR is the proxy, so sites there should return the
 * real address through the filter. Only they can know which header to trust.
 *
 * @return string
 */
function clu_client_ip() {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

    return (string) apply_filters( 'clu_client_ip', $ip );
}

/**
 * Stops the AJAX request unless the posted field holds the user's current password.
 *
 * A wrong one counts as a failed login, so that a stolen session cannot be used to
 * guess the password here instead.
 *
 * @param int    $user_id
 * @param string $field         Form field that holds the password.
 * @param string $empty_message Message key for an empty field.
 */
function clu_require_current_password( $user_id, $field, $empty_message ) {
    $password = clu_post_password( $field );

    if ( $password === '' ) {
        wp_send_json_error( [ 'message' => clu_render_message( $empty_message ) ] );
    }

    if ( clu_login_lock_remaining() ) {
        wp_send_json_error( [ 'message' => clu_login_locked_message() ] );
    }

    if ( ! wp_check_password( $password, get_userdata( $user_id )->user_pass, $user_id ) ) {
        clu_note_failed_login();
        wp_send_json_error( [ 'message' => clu_render_message( 'password_current_incorrect' ) ] );
    }
}

/* ─── URLs ───────────────────────────────────────────────────────────── */

/**
 * Returns the URL scheme that plugin-generated links should use.
 *
 * @return string
 */
function clu_get_site_url_scheme() {
    if ( is_ssl() ) {
        return 'https';
    }

    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
        $forwarded_proto = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) );

        if ( strpos( $forwarded_proto, 'https' ) !== false ) {
            return 'https';
        }
    }

    return strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_SCHEME ) ) ?: 'http';
}

/**
 * Normalizes a URL from the settings: completes relative paths and moves this
 * site's own links to the scheme the site is using.
 *
 * @param string $url
 * @param string $fallback_url Used when $url is empty or unusable.
 * @return string
 */
function clu_normalize_site_url( $url, $fallback_url = '' ) {
    $fallback_url = $fallback_url !== '' ? $fallback_url : home_url( '/' );
    $url          = trim( (string) $url );

    if ( $url === '' ) {
        $url = $fallback_url;
    }

    if ( strpos( $url, '//' ) === 0 ) {
        $url = clu_get_site_url_scheme() . ':' . $url;
    } elseif ( ! wp_parse_url( $url, PHP_URL_SCHEME ) ) {
        $url = home_url( '/' . ltrim( $url, '/' ) );
    }

    $url = esc_url_raw( $url );

    if ( $url === '' ) {
        return $fallback_url;
    }

    if ( wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) {
        $url = set_url_scheme( $url, clu_get_site_url_scheme() );
    }

    return $url;
}

/**
 * Returns a URL saved on the Pages URL settings tab.
 *
 * @param string $key      Field name, such as url_page_login.
 * @param string $fallback Used when the setting is empty. Defaults to the home page.
 * @return string
 */
function clu_get_page_url( $key, $fallback = '' ) {
    $options = get_option( 'clu_pages_url' );
    $url     = is_array( $options ) && ! empty( $options[ $key ] ) ? $options[ $key ] : '';

    return clu_normalize_site_url( $url, $fallback );
}

/* ─── Forms ──────────────────────────────────────────────────────────── */

/**
 * Renders a template and returns the HTML.
 *
 * @param string $template_name Template file name without .php.
 * @param mixed  $attributes    Data for the template, available to it as $attributes.
 * @return string
 */
function clu_get_template_html( $template_name, $attributes = [] ) {
    ob_start();

    require clu_locate_template( $template_name );

    return ob_get_clean();
}

/**
 * Returns the file of a template: a copy in the theme's custom-login-users folder, child
 * theme first, or the plugin's own in its forms folder.
 *
 * @param string $template_name Template file name without .php.
 * @return string
 */
function clu_locate_template( $template_name ) {
    $own  = CLU_PLUGIN_DIR . 'forms/' . $template_name . '.php';
    $path = locate_template( [ 'custom-login-users/' . $template_name . '.php' ] );

    /**
     * Filters the file a template is rendered from.
     *
     * @param string $path          The theme's copy, or the plugin's own file.
     * @param string $template_name Such as form_login.
     */
    $path = (string) apply_filters( 'clu_template_path', $path !== '' ? $path : $own, $template_name );

    return is_readable( $path ) ? $path : $own;
}

/**
 * Returns the templates the active theme replaces, for the settings screen.
 *
 * @return string[] Template name => path in the theme.
 */
function clu_theme_template_overrides() {
    $overrides = [];

    foreach ( glob( CLU_PLUGIN_DIR . 'forms/*.php' ) as $file ) {
        $name = basename( $file, '.php' );
        $path = clu_locate_template( $name );

        if ( wp_normalize_path( $path ) !== wp_normalize_path( $file ) ) {
            $overrides[ $name ] = $path;
        }
    }

    return $overrides;
}

/**
 * Returns the text saved for a form field on a settings tab, or that field's default.
 * An emptied field falls back too, so a form never ends up with a blank button.
 *
 * @param string $option_name Option that holds the tab, such as clu_login_form.
 * @param string $key         Field name.
 * @return string
 */
function clu_form_text( $option_name, $key ) {
    static $english    = null;
    static $translated = [];

    if ( $english === null ) {
        $english = clu_untranslated( 'clu_form_text_defaults' );
    }

    $locale = get_locale();

    if ( ! isset( $translated[ $locale ] ) ) {
        $translated[ $locale ] = clu_form_text_defaults();
    }

    $options = get_option( $option_name );
    $saved   = is_array( $options ) && isset( $options[ $key ] ) ? (string) $options[ $key ] : '';

    if ( clu_is_custom_text( $saved, $english[ $option_name ][ $key ] ?? '', clu_legacy_defaults()['forms'][ $option_name ][ $key ] ?? [] ) ) {
        return $saved;
    }

    return $translated[ $locale ][ $option_name ][ $key ] ?? '';
}

/**
 * Returns the defaults of the fields on the settings tabs, in the current language.
 *
 * @return array Option name => field => default.
 */
function clu_form_text_defaults() {
    $defaults = [];

    foreach ( clu_get_settings_tabs() as $tab ) {
        if ( empty( $tab['option_name'] ) ) {
            continue;
        }

        foreach ( include $tab['fields_file'] as $field ) {
            $defaults[ $tab['option_name'] ][ $field['field'] ] = $field['default'];
        }
    }

    return $defaults;
}

/**
 * Reads the show_title shortcode attribute, so show_title="false" means false.
 *
 * @param mixed $attributes Raw shortcode attributes.
 * @return array
 */
function clu_parse_form_attributes( $attributes ) {
    $attributes = shortcode_atts( [ 'show_title' => false ], $attributes );

    $attributes['show_title'] = filter_var( $attributes['show_title'], FILTER_VALIDATE_BOOLEAN );

    return $attributes;
}

/* ─── Message formatting ─────────────────────────────────────────────── */

/**
 * Formats editable message text for HTML output.
 * Allows safe HTML and converts textarea newlines into HTML line breaks.
 *
 * @param string $text
 * @return string
 */
function clu_email_body_format( $text ) {
    $text = str_replace( [ "\r\n", "\r" ], "\n", (string) $text );
    $text = wp_kses_post( $text );

    if ( preg_match( '/<(p|div|h[1-6]|ul|ol|li|table|tr|td|th|blockquote)\b/i', $text ) ) {
        return $text;
    }

    return nl2br( $text, false );
}

/**
 * Builds a safe HTML link for messages.
 *
 * @param string $url
 * @param string $label
 * @return string
 */
function clu_message_link( $url, $label = 'Link' ) {
    return sprintf(
        '<a href="%s" target="_blank" rel="noopener">%s</a>',
        esc_url( $url ),
        esc_html( $label )
    );
}

/**
 * Applies placeholders and formats a message for HTML output.
 *
 * Uses strtr() so that a value is never scanned again: a name containing
 * "{reset_link}" stays plain text.
 *
 * @param string $template
 * @param array  $replacements
 * @return string
 */
function clu_format_message_template( $template, $replacements ) {
    return clu_email_body_format( strtr( (string) $template, $replacements ) );
}

/**
 * Builds an email subject from a saved template.
 *
 * Plain replacement rather than sprintf(), where any stray % typed in the settings
 * throws an error and takes the whole request down.
 *
 * @param string $message_key
 * @param string $display_name
 * @return string
 */
function clu_format_subject( $message_key, $display_name ) {
    return strtr( clu_get_message( $message_key ), [
        '%1$s'           => $display_name,
        '%s'             => $display_name,
        '{display_name}' => $display_name,
    ] );
}

/**
 * Sanitizes editable message templates while preserving placeholders.
 *
 * WordPress may strip placeholders such as %2$s or {logout_url} when they
 * appear inside HTML attributes before they are replaced with real URLs.
 *
 * @param string $text
 * @return string
 */
function clu_sanitize_message_template( $text ) {
    $text         = str_replace( [ "\r\n", "\r" ], "\n", (string) $text );
    $placeholders = [];

    $text = preg_replace_callback(
        '/%(\d+\$)?s|\{[a-z0-9_]+\}/i',
        function ( $matches ) use ( &$placeholders ) {
            $token                  = 'clu-placeholder-' . count( $placeholders );
            $placeholders[ $token ] = $matches[0];

            return $token;
        },
        $text
    );

    $text = wp_kses_post( $text );

    return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $text );
}

/* ─── Email ──────────────────────────────────────────────────────────── */

/**
 * Returns the host used for legacy plugin sender fallback addresses.
 *
 * @return string
 */
function clu_get_mail_host() {
    $host = wp_parse_url( home_url(), PHP_URL_HOST );

    if ( ! $host && ! empty( $_SERVER['HTTP_HOST'] ) ) {
        $host = preg_replace( '/:\d+$/', '', sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) );
    }

    return (string) $host;
}

/**
 * Returns a sanitized mail option from the plugin Mail settings tab.
 *
 * @param string $key
 * @return string
 */
function clu_get_mail_setting( $key ) {
    $options = get_option( 'clu_mail' );

    if ( ! is_array( $options ) || empty( $options[ $key ] ) ) {
        return '';
    }

    return sanitize_text_field( $options[ $key ] );
}

/**
 * Builds email headers for plugin-generated emails.
 *
 * SMTP plugins may still override the From address if they force a global sender.
 *
 * @param string $fallback_from_email
 * @param string $fallback_from_name
 * @return array
 */
function clu_get_mail_headers( $fallback_from_email = '', $fallback_from_name = '' ) {
    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

    $from_email = sanitize_email( clu_get_mail_setting( 'from_email' ) );

    if ( ! is_email( $from_email ) && $fallback_from_email ) {
        $from_email = sanitize_email( $fallback_from_email );
    }

    if ( ! is_email( $from_email ) ) {
        return $headers;
    }

    $from_name = clu_get_mail_setting( 'from_name' );

    if ( $from_name === '' ) {
        $from_name = $fallback_from_name !== '' ? $fallback_from_name : get_bloginfo( 'name' );
    }

    $headers[] = sprintf( 'From: %s <%s>', sanitize_text_field( $from_name ), $from_email );

    return $headers;
}

/**
 * Returns who hears about new registrations: the addresses on the Mail tab, or the
 * site admin when none are set, so that a registration is never silently unseen.
 *
 * @return string Comma-separated addresses.
 */
function clu_get_admin_notification_recipients() {
    $addresses = array_filter(
        array_map( 'trim', explode( ',', clu_get_mail_setting( 'admin_mail_addresss' ) ) ),
        'is_email'
    );

    // Trimmed: a site was found with a line break saved after its admin address.
    return $addresses ? implode( ',', $addresses ) : trim( (string) get_option( 'admin_email' ) );
}

/**
 * Builds the link to the set-password page.
 *
 * The login is encoded because it may hold characters such as + or a space, which
 * would otherwise reach the page changed and make the link look invalid.
 *
 * @param WP_User $user
 * @param string  $key  Password reset key.
 * @return string
 */
function clu_get_set_password_url( $user, $key ) {
    return add_query_arg(
        [
            'key'   => $key,
            'login' => rawurlencode( $user->user_login ),
        ],
        clu_get_page_url( 'url_page_password_set', home_url( '/set-password/' ) )
    );
}

/**
 * Sends a plugin email in the language of whoever receives it: the user's, or the
 * site's for the admin. The text is built only after the switch, so that the default
 * texts come in that language. Right-to-left languages get a right-to-left email.
 *
 * @param string|string[] $to
 * @param callable        $build     Returns [ subject, HTML body ].
 * @param WP_User|null    $recipient The user it goes to; null for the admin.
 * @param array|null      $headers   Defaults to the plugin's sender, or info@ the site.
 * @return bool Whether the email went out.
 */
function clu_send_mail( $to, $build, $recipient = null, $headers = null ) {
    $switched = switch_to_locale( $recipient instanceof WP_User ? get_user_locale( $recipient ) : get_locale() );

    list( $subject, $message ) = $build();

    if ( is_rtl() ) {
        $message = '<div dir="rtl" style="text-align:right;">' . $message . '</div>';
    }

    if ( $headers === null ) {
        $mail_host = clu_get_mail_host();
        $headers   = clu_get_mail_headers( $mail_host ? 'info@' . $mail_host : '', get_bloginfo( 'name' ) );
    }

    $sent = (bool) wp_mail( $to, $subject, $message, $headers );

    if ( $switched ) {
        restore_previous_locale();
    }

    return $sent;
}

/**
 * Acts on an account made in the pending role. The plugin's own form makes accounts
 * that still have to confirm their address in another role, and sends the link itself.
 */
add_action( 'user_register', 'clu_on_user_register' );
function clu_on_user_register( $user_id ) {
    $user = get_userdata( $user_id );

    if ( ! $user || ! in_array( clu_get_role_slug( 'pending_role' ), (array) $user->roles, true ) ) {
        return;
    }

    // An account made by someone who manages users is left in the role they chose.
    $made_by_admin = current_user_can( 'create_users' );

    // A sign-up from another form, such as the one built into WordPress when the pending
    // role is the site's default role.
    if ( clu_email_confirmation_is_on() && ! $made_by_admin ) {
        clu_ask_to_confirm_email( $user );
        return;
    }

    clu_send_registration_emails( $user, ! $made_by_admin );
}

/**
 * Tells whether new users are approved without waiting for an admin. Set on the User
 * Roles tab.
 *
 * @return bool
 */
function clu_auto_approve_is_on() {
    return get_option( 'clu_auto_approve' ) === '1';
}

/**
 * Acts on a registration that now waits for approval: tells the admin, and the user if
 * the site wants them told. With automatic approval, approves it instead, which sends
 * the set-password email, and tells the admin it was done.
 *
 * @param WP_User $user
 * @param bool    $may_approve Whether automatic approval may apply to this account.
 */
function clu_send_registration_emails( $user, $may_approve = true ) {
    if ( $may_approve && clu_auto_approve_is_on() ) {
        $user->set_role( clu_get_role_slug( 'approved_role' ) );
        clu_send_new_user_email( get_userdata( $user->ID ), true );
        return;
    }

    clu_send_new_user_email( $user );
    clu_send_received_email( $user );
}

/**
 * Tells the site admin about a new user: waiting for approval, or approved already.
 *
 * @param WP_User $user
 * @param bool    $approved Whether the user was approved automatically.
 */
function clu_send_new_user_email( $user, $approved = false ) {
    clu_send_mail( clu_get_admin_notification_recipients(), function () use ( $user, $approved ) {
        $name     = esc_html( $user->display_name );
        $edit_url = clu_normalize_site_url( add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) ) ) . '#role';
        $list_url = clu_normalize_site_url( admin_url( clu_pending_list_path() ) );
        $prefix   = $approved ? 'notify_auto' : 'notify_client';

        return [
            clu_format_subject( $prefix . '_subject', $user->display_name ),
            clu_format_message_template(
                clu_get_message( $prefix . '_body' ),
                [
                    '%1$s'                 => $name,
                    '%2$s'                 => $edit_url,
                    '{display_name}'       => $name,
                    '{user_details}'       => clu_format_user_details( clu_get_user_details( $user ) ),
                    '{edit_user_url}'      => $edit_url,
                    '{edit_user_link}'     => clu_message_link( $edit_url, clu_get_message( 'email_edit_user_text' ) ),
                    '{pending_users_url}'  => $list_url,
                    '{pending_users_link}' => clu_message_link( $list_url, clu_get_message( 'email_pending_list_text' ) ),
                ]
            ),
        ];
    } );
}

/**
 * Acts on a role change, whether it came from the Approve and Reject links, from a
 * confirmed email address, or from the role control on the user's screen.
 */
add_action( 'set_user_role', 'clu_on_user_role_change', 10, 3 );
function clu_on_user_role_change( $user_id, $new_role, $old_roles ) {
    $pending     = clu_get_role_slug( 'pending_role' );
    $rejected    = clu_get_role_slug( 'rejected_role' );
    $unconfirmed = clu_get_role_slug( 'unconfirmed_role' );
    $old         = (array) $old_roles;

    if ( $new_role === clu_get_role_slug( 'approved_role' ) && array_intersect( [ $pending, $rejected, $unconfirmed ], $old ) ) {
        clu_send_set_password_email( $user_id );
    }

    // The address is confirmed, so the registration reaches the admin now.
    if ( $new_role === $pending && in_array( $unconfirmed, $old, true ) ) {
        clu_send_registration_emails( get_userdata( $user_id ) );
    }

    if ( $new_role === $rejected && in_array( $pending, $old, true ) ) {
        clu_send_rejection_email( $user_id );
    }

    // These accounts may not log in, so one that is logged in right now has to go too.
    if ( in_array( $new_role, [ $pending, $rejected, $unconfirmed ], true ) ) {
        WP_Session_Tokens::get_instance( $user_id )->destroy_all();
    }
}

/**
 * Tells a user that their registration was received and waits for approval, if the
 * site turned this on, on the Mail tab.
 *
 * @param WP_User $user
 */
function clu_send_received_email( $user ) {
    /**
     * Filters whether a user hears that their registration was received.
     *
     * @param bool    $send Whether the Mail tab asks for it.
     * @param WP_User $user
     */
    if ( ! $user || ! apply_filters( 'clu_send_received_email', clu_get_mail_setting( 'notify_received' ) === '1', $user ) ) {
        return;
    }

    clu_send_mail( $user->user_email, function () use ( $user ) {
        $name = esc_html( $user->display_name );

        return [
            clu_format_subject( 'notify_received_subject', $user->display_name ),
            clu_format_message_template( clu_get_message( 'notify_received_body' ), [ '%1$s' => $name, '{display_name}' => $name ] ),
        ];
    }, $user );
}

/**
 * Tells a rejected user. Sites that prefer not to can turn this off with the filter,
 * and deleting a user from the list sends nothing.
 *
 * @param int $user_id
 */
function clu_send_rejection_email( $user_id ) {
    $user = get_userdata( $user_id );

    /**
     * Filters whether a rejected user gets an email.
     *
     * @param bool          $send
     * @param WP_User|false $user
     */
    if ( ! $user || ! apply_filters( 'clu_send_rejection_email', true, $user ) ) {
        return;
    }

    $sent = clu_send_mail( $user->user_email, function () use ( $user ) {
        $name = esc_html( $user->display_name );

        return [
            clu_format_subject( 'notify_rejected_subject', $user->display_name ),
            clu_format_message_template( clu_get_message( 'notify_rejected_body' ), [ '%1$s' => $name, '{display_name}' => $name ] ),
        ];
    }, $user );

    if ( ! $sent ) {
        do_action( 'clu_decision_mail_failed', $user );
    }
}

/**
 * Emails a newly approved user the link to choose a password.
 *
 * @param int $user_id
 */
function clu_send_set_password_email( $user_id ) {
    $user = get_userdata( $user_id );

    if ( ! $user ) {
        return;
    }

    $key = get_password_reset_key( $user );

    if ( is_wp_error( $key ) ) {
        do_action( 'clu_decision_mail_failed', $user );
        return;
    }

    // Must come after the key: generating any key clears this flag.
    update_user_meta( $user->ID, 'clu_approval_link', 1 );

    $sent = clu_send_mail( $user->user_email, function () use ( $user, $key ) {
        $name = esc_html( $user->display_name );
        $url  = clu_get_set_password_url( $user, $key );

        return [
            clu_format_subject( 'notify_user_subject', $user->display_name ),
            clu_format_message_template(
                clu_get_message( 'notify_user_body' ),
                [
                    '%1$s'                => $name,
                    '%2$s'                => $url,
                    '{display_name}'      => $name,
                    '{set_password_url}'  => $url,
                    '{set_password_link}' => clu_message_link( $url, clu_get_message( 'email_link_text' ) ),
                ]
            ),
        ];
    }, $user );

    if ( ! $sent ) {
        /**
         * Fires when an email about an approval or a rejection could not be sent.
         *
         * @param WP_User $user
         */
        do_action( 'clu_decision_mail_failed', $user );
    }
}

/* ─── Reset keys ─────────────────────────────────────────────────────── */

/**
 * Checks a password reset key.
 *
 * An approval email can sit unread for days, so its link lives longer than the
 * standard 1 day. password_reset_expiration has no user context and is site-wide,
 * so the longer time applies only around this check, and only for a user flagged
 * by clu_send_set_password_email(). Reset links for everyone else keep the default.
 *
 * @param string $key
 * @param string $login
 * @return WP_User|WP_Error
 */
function clu_check_reset_key( $key, $login ) {
    $user     = get_user_by( 'login', $login );
    $extended = $user && get_user_meta( $user->ID, 'clu_approval_link', true );

    if ( $extended ) {
        add_filter( 'password_reset_expiration', 'clu_approval_link_lifetime' );
    }

    $result = check_password_reset_key( $key, $login );

    if ( $extended ) {
        remove_filter( 'password_reset_expiration', 'clu_approval_link_lifetime' );
    }

    return $result;
}

/**
 * Returns how long an approval link stays valid, in seconds.
 *
 * @return int
 */
function clu_approval_link_lifetime() {
    return (int) apply_filters( 'clu_approval_link_lifetime', 7 * DAY_IN_SECONDS );
}

add_action( 'retrieve_password_key', 'clu_clear_approval_link_flag' );
function clu_clear_approval_link_flag( $login ) {
    $user = get_user_by( 'login', $login );

    if ( $user ) {
        delete_user_meta( $user->ID, 'clu_approval_link' );
    }
}

add_action( 'after_password_reset', 'clu_clear_approval_link_after_reset' );
function clu_clear_approval_link_after_reset( $user ) {
    delete_user_meta( $user->ID, 'clu_approval_link' );
}

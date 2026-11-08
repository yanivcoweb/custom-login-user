<?php

defined( 'ABSPATH' ) || exit;

/**
 * Returns the configured message for a given key, falling back to the default.
 *
 * @param string $key  Message key (see clu_default_messages())
 * @return string
 */
function clu_get_message( $key ) {
    $options = get_option( 'clu_messages', [] );
    $saved   = is_array( $options ) && isset( $options[ $key ] ) ? (string) $options[ $key ] : '';
    $message = clu_is_custom_text( $saved, clu_english_messages()[ $key ] ?? '', clu_legacy_defaults()['messages'][ $key ] ?? [] )
        ? $saved
        : ( clu_default_messages()[ $key ] ?? '' );

    return str_replace( '{min_length}', (string) (int) clu_get_password_rules()['min_length'], $message );
}

/**
 * Tells whether a saved text was written by an admin. Empty, or still a default, it was
 * not, and the current default in the site's language shows instead. Versions before
 * 1.7.0 saved every default along with the texts an admin changed, and the editor on the
 * Messages tab, or the line breaks of version 1.0, may have changed how it is written,
 * so the words are compared, not the markup.
 *
 * @param string   $saved
 * @param string   $english The current default in English.
 * @param string[] $legacy  Defaults of version 1.0, where they differ.
 * @return bool
 */
function clu_is_custom_text( $saved, $english, $legacy = [] ) {
    if ( trim( $saved ) === '' ) {
        return false;
    }

    $words = clu_text_fingerprint( $saved );

    foreach ( array_merge( [ $english ], (array) $legacy ) as $default ) {
        if ( $words === clu_text_fingerprint( $default ) ) {
            return false;
        }
    }

    return true;
}

/**
 * Returns the words of a text, with its markup and spacing left out, and the address of
 * each link kept, so that a changed link still counts as a change.
 *
 * @param string $text
 * @return string
 */
function clu_text_fingerprint( $text ) {
    $text = preg_replace( '/<a\s[^>]*href\s*=\s*["\']([^"\']*)["\'][^>]*>/i', ' $1 ', (string) $text );
    $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
    $text = preg_replace( '#<br\s*/?>|</p>\s*<p[^>]*>|\r?\n#i', ' ', $text );
    $text = wp_strip_all_tags( $text );

    return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * Calls a function with the plugin's translations turned off, and returns what it
 * returns: its texts in English.
 *
 * @param callable $callback
 * @return mixed
 */
function clu_untranslated( $callback ) {
    $original = function ( $translation, $text, $domain ) {
        return $domain === 'custom-login-users' ? $text : $translation;
    };

    add_filter( 'gettext', $original, PHP_INT_MAX, 3 );

    try {
        return $callback();
    } finally {
        remove_filter( 'gettext', $original, PHP_INT_MAX );
    }
}

/**
 * Returns the default messages in English.
 *
 * @return array
 */
function clu_english_messages() {
    static $english = null;

    if ( $english === null ) {
        $english = clu_untranslated( 'clu_default_messages' );
    }

    return $english;
}

/**
 * Returns a message formatted for HTML output — converts bare newlines to <br> tags.
 *
 * @param string $key
 * @return string
 */
function clu_render_message( $key ) {
    return clu_email_body_format( clu_get_message( $key ) );
}

/**
 * Returns all default (fallback) message strings.
 *
 * @return array
 */
function clu_default_messages() {
    return [

        // ── AJAX: General ────────────────────────────────────────────────
        'invalid_nonce'              => __( 'Invalid request.', 'custom-login-users' ),
        'general_error'              => __( 'An error occurred. Please try again later.', 'custom-login-users' ),
        'too_many_attempts'          => __( 'Too many attempts. Please try again later.', 'custom-login-users' ),
        'form_processing'            => __( 'Processing...', 'custom-login-users' ),

        // ── AJAX: Login ──────────────────────────────────────────────────
        'login_success'              => __( 'Login successful!', 'custom-login-users' ),
        'login_second_step'          => __( 'Please continue to the second step of your login.', 'custom-login-users' ),
        'login_failed'               => __( 'The username, email or password is incorrect.', 'custom-login-users' ),
        // {minutes} is replaced at runtime with the minutes left.
        'login_locked'               => __( 'Too many failed login attempts. Please try again in {minutes} minutes.', 'custom-login-users' ),
        'captcha_failed'             => __( 'Please confirm that you are not a robot, and try again.', 'custom-login-users' ),
        'account_pending'            => __( 'Your account is awaiting approval. We will email you as soon as it is approved.', 'custom-login-users' ),
        'account_rejected'           => __( 'Your registration was not approved.', 'custom-login-users' ),
        'account_unconfirmed'        => __( 'Please confirm your email address first, through the link we emailed you.', 'custom-login-users' ),

        // ── AJAX: Register ───────────────────────────────────────────────
        'register_required_fields'   => __( 'Please fill in all required fields.', 'custom-login-users' ),
        // {field} is replaced at runtime with the label of the field.
        'register_invalid_field'     => __( 'Please check this field: {field}', 'custom-login-users' ),
        'email_already_registered'   => __( 'This email address is already registered.', 'custom-login-users' ),
        'register_success'           => __( 'Thank you for registering! We will email you as soon as your account is approved.', 'custom-login-users' ),
        'register_success_auto'      => __( 'Thank you for registering! We have emailed you a link to set your password.', 'custom-login-users' ),
        'register_confirm_sent'      => __( 'Thank you for registering! To complete it, please click the link we emailed you to confirm your email address.', 'custom-login-users' ),
        'confirm_email_send_failed'  => __( 'We could not send the confirmation email. Please try again later.', 'custom-login-users' ),

        // ── AJAX: Password Lost ──────────────────────────────────────────
        'email_required'             => __( 'Please enter your email address.', 'custom-login-users' ),
        'reset_email_send_failed'    => __( 'Failed to send the reset email. Please try again later.', 'custom-login-users' ),
        'reset_email_sent'           => __( 'If an account matches what you entered, a password reset email is on its way. Please check your inbox.', 'custom-login-users' ),

        // ── AJAX: Password Set ───────────────────────────────────────────
        'password_set_missing_fields' => __( 'Missing required fields.', 'custom-login-users' ),
        'invalid_reset_link'         => __( 'The password reset link is invalid or has expired.', 'custom-login-users' ),
        // {min_length} is replaced at runtime with the current rule.
        'password_min_length'        => __( 'Password must be at least {min_length} characters long.', 'custom-login-users' ),
        'password_uppercase'         => __( 'Password must contain at least one uppercase letter (A-Z).', 'custom-login-users' ),
        'password_lowercase'         => __( 'Password must contain at least one lowercase letter (a-z).', 'custom-login-users' ),
        'password_number'            => __( 'Password must contain at least one number (0-9).', 'custom-login-users' ),
        'password_special_char'      => __( 'Password must contain at least one special character (such as ! @ # $ %).', 'custom-login-users' ),
        'password_reset_success'     => __( 'Your password has been reset successfully.', 'custom-login-users' ),

        // ── Shortcode: Login form ────────────────────────────────────────
        // {logout_url} is replaced at runtime with wp_logout_url()
        'already_logged_in'          => __( '<p>You are already logged in.</p><p>To log out click here: <a style="color:#333;" href="{logout_url}">Logout</a></p>', 'custom-login-users' ),

        // ── Shortcode: Register form ─────────────────────────────────────
        'user_logged_in_register'    => __( 'You are already logged in.', 'custom-login-users' ),
        'registration_disabled'      => __( 'Registration is currently disabled.', 'custom-login-users' ),

        // ── Shortcode: Password Set form ─────────────────────────────────
        'invalid_reset_link_page'    => __( 'The password reset link is invalid.', 'custom-login-users' ),
        'expired_reset_link_page'    => __( 'The password reset link is invalid or has expired.', 'custom-login-users' ),

        // ── Shortcode: Register or Login page, after the link that confirms an email ──
        'email_confirmed'            => __( 'Thank you, your email address is confirmed. We will email you as soon as your account is approved.', 'custom-login-users' ),
        'email_confirmed_auto'       => __( 'Thank you, your email address is confirmed. We have emailed you a link to set your password.', 'custom-login-users' ),
        'email_already_confirmed'    => __( 'Your email address is already confirmed.', 'custom-login-users' ),
        'email_confirm_invalid'      => __( 'This confirmation link is invalid or has expired. To get a new one, register again with the same email address.', 'custom-login-users' ),

        // ── Shortcode: Auth Buttons ──────────────────────────────────────
        'logout_button_text'         => __( 'Logout', 'custom-login-users' ),
        'login_button_text'          => __( 'Login', 'custom-login-users' ),
        'register_button_text'       => __( 'Register', 'custom-login-users' ),

        // ── Restricted content ───────────────────────────────────────────
        // {login_url} = login page that leads back here, {register_url} = register page
        'content_login_required'     => __( 'This content is for approved users. Please <a href="{login_url}">log in</a> to see it.', 'custom-login-users' ),
        'content_no_access'          => __( 'This content is for approved users only.', 'custom-login-users' ),

        // ── Shortcode: My Account ────────────────────────────────────────
        'my_account_not_logged_in'      => __( 'You must be logged in to view this page.', 'custom-login-users' ),
        'profile_update_success'        => __( 'Your profile has been updated successfully.', 'custom-login-users' ),
        'profile_update_missing_fields' => __( 'Please fill in all required fields.', 'custom-login-users' ),
        'profile_email_taken'           => __( 'This email address is already in use by another account.', 'custom-login-users' ),
        'password_update_success'       => __( 'Your password has been changed successfully.', 'custom-login-users' ),
        'password_current_incorrect'    => __( 'The current password you entered is incorrect.', 'custom-login-users' ),
        'email_change_password_required' => __( 'To change your email address, please enter your current password.', 'custom-login-users' ),
        'password_confirm_mismatch'     => __( 'The new passwords do not match.', 'custom-login-users' ),

        // ── Email: shared ────────────────────────────────────────────────
        'email_link_text'            => __( 'Link', 'custom-login-users' ),
        'email_edit_user_text'       => __( 'Edit User', 'custom-login-users' ),
        'email_pending_list_text'    => __( 'Users awaiting approval', 'custom-login-users' ),
        'email_confirm_link_text'    => __( 'Confirm my email address', 'custom-login-users' ),

        // ── Email: Password Lost (sent to user) ──────────────────────────
        // {display_name} = display name, {reset_url} = reset URL, {reset_link} = linked text
        'reset_email_subject'        => __( 'Password Reset Request', 'custom-login-users' ),
        'reset_email_body'           => __( "Hello {display_name},\n\nWe received a request to reset your password. Please click the link below to reset your password:\n\n{reset_link}\n\nIf you did not request this, you can ignore this email.", 'custom-login-users' ),

        // ── Email: Confirm the email address (sent to user) ──────────────
        /* translators: %s: the user's name. {display_name} works too. */
        'confirm_email_subject'      => __( 'Website: Please confirm your email address', 'custom-login-users' ),
        // {display_name} = display name, {confirm_url} = confirmation URL, {confirm_link} = linked text
        'confirm_email_body'         => __( "Hello {display_name},<br><br>To complete your registration, please confirm your email address:<br><br>{confirm_link}<br><br>If you did not register on our website, you can ignore this email.", 'custom-login-users' ),

        // ── Email: Registration received (sent to user) ──────────────────
        /* translators: %s: the user's name. {display_name} works too. */
        'notify_received_subject'    => __( 'Website: We received your registration', 'custom-login-users' ),
        // {display_name} = display name
        'notify_received_body'       => __( "Hello {display_name},<br><br>Thank you for registering. We received your details, and we will email you as soon as your account is approved.", 'custom-login-users' ),

        // ── Email: New Registration (sent to admin) ──────────────────────
        /* translators: %s: the user's name. {display_name} works too. */
        'notify_client_subject'      => __( 'Website: New registration by %s', 'custom-login-users' ),
        // {display_name} = display name, {user_details} = what the user filled in,
        // {pending_users_url} and {pending_users_link} = the list of users awaiting approval,
        // {edit_user_url} = edit URL, {edit_user_link} = linked text
        'notify_client_body'         => __( "Hello,<br><br>{display_name} has registered on the website.<br><br>{user_details}<br><br>To approve or reject the registration, open the list: {pending_users_link}<br><br>", 'custom-login-users' ),

        // ── Email: New user approved automatically (sent to admin) ───────
        /* translators: %s: the user's name. {display_name} works too. */
        'notify_auto_subject'        => __( 'Website: New user %s', 'custom-login-users' ),
        // The same placeholders as the email about a new registration.
        'notify_auto_body'           => __( "Hello,<br><br>{display_name} has registered on the website and was approved automatically.<br><br>{user_details}<br><br>To see the user: {edit_user_link}<br><br>", 'custom-login-users' ),

        // ── Email: Account Approved (sent to user) ───────────────────────
        /* translators: %s: the user's name. {display_name} works too. */
        'notify_user_subject'        => __( 'Website: Account approved for %s', 'custom-login-users' ),
        // {display_name} = display name, {set_password_url} = reset URL, {set_password_link} = linked text
        'notify_user_body'           => __( "Hello,<br><br>{display_name}, your account has been approved!<br><br>To set your password and access your account, click the following link:<br><br>{set_password_link}<br><br>If you have any questions, please contact us.<br><br>Thank you.", 'custom-login-users' ),

        // ── Email: Registration Rejected (sent to user) ──────────────────
        /* translators: %s: the user's name. {display_name} works too. */
        'notify_rejected_subject'    => __( 'Website: Registration not approved for %s', 'custom-login-users' ),
        // {display_name} = display name
        'notify_rejected_body'       => __( "Hello {display_name},<br><br>Thank you for your interest. We could not approve your registration.<br><br>If you think this is a mistake, please contact us.", 'custom-login-users' ),
    ];
}

/**
 * Removes two stale defaults from messages saved by version 1.0.
 *
 * Its Save Messages button stored every field shown, including untouched defaults.
 * That froze the min-length number at the time of saving, and kept a default with a
 * typo in it. Only exact matches go, so text an admin wrote stays.
 */
function clu_migrate_saved_messages() {
    $saved = get_option( 'clu_messages' );

    if ( ! is_array( $saved ) ) {
        return;
    }

    if ( isset( $saved['password_min_length'] ) && preg_match( '/^Password must be at least \d+ characters long\.$/', $saved['password_min_length'] ) ) {
        unset( $saved['password_min_length'] );
    }

    if ( isset( $saved['password_special_char'] ) && $saved['password_special_char'] === 'Password must contain at least one special character (!@#$%^&*().' ) {
        unset( $saved['password_special_char'] );
    }

    update_option( 'clu_messages', $saved );
}

/**
 * Removes the old default of the admin email from saved messages, so that it gets the
 * new links and the user's details. The same reason as above: only an exact match goes.
 */
function clu_migrate_saved_admin_email() {
    $saved = get_option( 'clu_messages' );
    $old   = "Hello,<br><br>{display_name} has registered on the website.<br><br>To approve the registration, click the link below and change the user role from \"Pending User\" to \"Approved User\": {edit_user_link}<br><br>";

    if ( is_array( $saved ) && isset( $saved['notify_client_body'] ) && $saved['notify_client_body'] === $old ) {
        unset( $saved['notify_client_body'] );
        update_option( 'clu_messages', $saved );
    }
}

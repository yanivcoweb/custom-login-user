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
    $message = ( is_array( $options ) && isset( $options[ $key ] ) && $options[ $key ] !== '' )
        ? $options[ $key ]
        : ( clu_default_messages()[ $key ] ?? '' );

    return str_replace( '{min_length}', (string) (int) clu_get_password_rules()['min_length'], $message );
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
        'invalid_nonce'              => 'Invalid request.',
        'general_error'              => 'An error occurred. Please try again later.',
        'too_many_attempts'          => 'Too many attempts. Please try again later.',
        'form_processing'            => 'Processing...',

        // ── AJAX: Login ──────────────────────────────────────────────────
        'login_success'              => 'Login successful!',
        'login_failed'               => 'The username, email or password is incorrect.',
        'account_pending'            => 'Your account is awaiting approval. We will email you as soon as it is approved.',

        // ── AJAX: Register ───────────────────────────────────────────────
        'register_required_fields'   => 'Please fill in all required fields.',
        'email_already_registered'   => 'This email address is already registered.',
        'register_success'           => 'Thank you for registering! We will email you as soon as your account is approved.',

        // ── AJAX: Password Lost ──────────────────────────────────────────
        'email_required'             => 'Please enter your email address.',
        'reset_email_send_failed'    => 'Failed to send the reset email. Please try again later.',
        'reset_email_sent'           => 'If an account matches what you entered, a password reset email is on its way. Please check your inbox.',

        // ── AJAX: Password Set ───────────────────────────────────────────
        'password_set_missing_fields' => 'Missing required fields.',
        'invalid_reset_link'         => 'The password reset link is invalid or has expired.',
        // {min_length} is replaced at runtime with the current rule.
        'password_min_length'        => 'Password must be at least {min_length} characters long.',
        'password_uppercase'         => 'Password must contain at least one uppercase letter (A-Z).',
        'password_lowercase'         => 'Password must contain at least one lowercase letter (a-z).',
        'password_number'            => 'Password must contain at least one number (0-9).',
        'password_special_char'      => 'Password must contain at least one special character (such as ! @ # $ %).',
        'password_reset_success'     => 'Your password has been reset successfully.',

        // ── Shortcode: Login form ────────────────────────────────────────
        // {logout_url} is replaced at runtime with wp_logout_url()
        'already_logged_in'          => '<p>You are already logged in.</p><p>To log out click here: <a style="color:#333;" href="{logout_url}">Logout</a></p>',

        // ── Shortcode: Register form ─────────────────────────────────────
        'user_logged_in_register'    => 'You are already logged in.',
        'registration_disabled'      => 'Registration is currently disabled.',

        // ── Shortcode: Password Set form ─────────────────────────────────
        'invalid_reset_link_page'    => 'The password reset link is invalid.',
        'expired_reset_link_page'    => 'The password reset link is invalid or has expired.',

        // ── Shortcode: Auth Buttons ──────────────────────────────────────
        'logout_button_text'         => 'Logout',
        'login_button_text'          => 'Login',
        'register_button_text'       => 'Register',

        // ── Shortcode: My Account ────────────────────────────────────────
        'my_account_not_logged_in'      => 'You must be logged in to view this page.',
        'profile_update_success'        => 'Your profile has been updated successfully.',
        'profile_update_missing_fields' => 'Please fill in all required fields.',
        'profile_email_taken'           => 'This email address is already in use by another account.',
        'password_update_success'       => 'Your password has been changed successfully.',
        'password_current_incorrect'    => 'The current password you entered is incorrect.',
        'password_confirm_mismatch'     => 'The new passwords do not match.',

        // ── Email: shared ────────────────────────────────────────────────
        'email_link_text'            => 'Link',
        'email_edit_user_text'       => 'Edit User',

        // ── Email: Password Lost (sent to user) ──────────────────────────
        // {display_name} = display name, {reset_url} = reset URL, {reset_link} = linked text
        'reset_email_subject'        => 'Password Reset Request',
        'reset_email_body'           => "Hello {display_name},\n\nWe received a request to reset your password. Please click the link below to reset your password:\n\n{reset_link}\n\nIf you did not request this, you can ignore this email.",

        // ── Email: New Registration (sent to admin) ──────────────────────
        // %s or {display_name} = display name
        'notify_client_subject'      => 'Website: New registration by %s',
        // {display_name} = display name, {edit_user_url} = edit URL, {edit_user_link} = linked text
        'notify_client_body'         => "Hello,<br><br>{display_name} has registered on the website.<br><br>To approve the registration, click the link below and change the user role from \"Pending User\" to \"Approved User\": {edit_user_link}<br><br>",

        // ── Email: Account Approved (sent to user) ───────────────────────
        // %s or {display_name} = display name
        'notify_user_subject'        => 'Website: Account approved for %s',
        // {display_name} = display name, {set_password_url} = reset URL, {set_password_link} = linked text
        'notify_user_body'           => "Hello,<br><br>{display_name}, your account has been approved!<br><br>To set your password and access your account, click the following link:<br><br>{set_password_link}<br><br>If you have any questions, please contact us.<br><br>Thank you.",
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

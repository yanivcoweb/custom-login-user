<?php

/**
 * Returns the configured message for a given key, falling back to the default.
 *
 * @param string $key  Message key (see clu_default_messages())
 * @return string
 */
function clu_get_message($key) {
    $options = get_option('clu_messages', []);
    return (isset($options[$key]) && $options[$key] !== '')
        ? $options[$key]
        : (clu_default_messages()[$key] ?? '');
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

        // ── AJAX: Login ──────────────────────────────────────────────────
        'login_success'              => 'Login successful!',

        // ── AJAX: Register ───────────────────────────────────────────────
        'register_required_fields'   => 'Please fill in all required fields.',
        'email_already_registered'   => 'This email address is already registered.',
        'register_success'           => 'Registration successful!',

        // ── AJAX: Password Lost ──────────────────────────────────────────
        'email_required'             => 'Please enter your email address.',
        'user_not_found'             => 'No user found with the provided email address or username.',
        'reset_email_send_failed'    => 'Failed to send the reset email. Please try again later.',
        'reset_email_sent'           => 'Password reset email has been sent. Please check your inbox.',

        // ── AJAX: Password Set ───────────────────────────────────────────
        'password_set_missing_fields' => 'Missing required fields.',
        'invalid_reset_link'         => 'The password reset link is invalid or has expired.',
        'password_min_length'        => sprintf( 'Password must be at least %d characters long.', clu_get_password_rules()['min_length'] ),
        'password_uppercase'         => 'Password must contain at least one uppercase letter (A-Z).',
        'password_lowercase'         => 'Password must contain at least one lowercase letter (a-z).',
        'password_number'            => 'Password must contain at least one number (0-9).',
        'password_special_char'      => 'Password must contain at least one special character (!@#$%^&*().',
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

        // ── Email: Password Lost (sent to user) ──────────────────────────
        // %1$s = display name, %2$s = reset URL
        'reset_email_subject'        => 'Password Reset Request',
        'reset_email_body'           => "Hello %1\$s,\n\nWe received a request to reset your password. Please click the link below to reset your password:\n\n%2\$s\n\nIf you did not request this, you can ignore this email.",

        // ── Email: New Registration (sent to admin) ──────────────────────
        // %s = display name
        'notify_client_subject'      => 'Website: New registration by %s',
        // %1$s = display name, %2$s = edit user URL
        'notify_client_body'         => "Hello,<br><br>%1\$s has registered on the website.<br><br>To approve the registration, click the link below and change the user role from \"Pending User\" to \"Approved User\": <a href=\"%2\$s\">Edit User</a><br><br>",

        // ── Email: Account Approved (sent to user) ───────────────────────
        // %s = display name
        'notify_user_subject'        => 'Website: Account approved for %s',
        // %1$s = display name, %2$s = reset URL
        'notify_user_body'           => "Hello,<br><br>%1\$s, your account has been approved!<br><br>Hi %1\$s,\n\nYour account has been approved successfully!\n\nTo set your password and access your account, click the following link:\n\n%2\$s\n\nIf you have any questions, please contact us.\n\nThank you.",
    ];
}

<?php

defined( 'ABSPATH' ) || exit;

/**
 * Returns the default texts of version 1.0 where they differ from today's.
 *
 * That version saved every default along with the texts an admin changed. A saved text
 * that is still one of these was never changed, so the current default, in the site's
 * language, shows in its place. Copies of 1.0 went to sites over time with slightly
 * different texts, and all of them are here, as they were.
 *
 * @return array Messages: key => texts. Forms: option => field => texts.
 */
function clu_legacy_defaults() {
    static $defaults = null;

    if ( $defaults !== null ) {
        return $defaults;
    }

    return $defaults = [
        'messages' => [
            "notify_client_body" => [
                "Hello,<br><br>{display_name} has registered on the website.<br><br>To approve the registration, click the link below and change the user role from \"Pending User\" to \"Approved User\": {edit_user_link}<br><br>",
            ],
            "password_min_length" => [
                "Password must be at least 8 characters long.",
            ],
            "password_special_char" => [
                "Password must contain at least one special character (!@#\$%^&*().",
            ],
            "register_success" => [
                "Registration successful!",
            ],
            "reset_email_sent" => [
                "Password reset email has been sent. Please check your inbox.",
            ],
        ],
        'forms'    => [
            "clu_password_reset_form" => [
                "renew_password" => [ "Reset Password" ],
            ],
        ],
    ];
}

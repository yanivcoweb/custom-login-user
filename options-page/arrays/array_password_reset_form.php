<?php

defined( 'ABSPATH' ) || exit;

return [
    [
        'field'       => 'choose_password',
        'label'       => 'Password Reset Title',
        'placeholder' => 'Enter the title for the password reset form',
        'default'     => __( 'Choose Your New Password', 'custom-login-users' ),
    ],
    [
        'field'       => 'new_password',
        'label'       => 'New Password Label',
        'placeholder' => 'Enter the label for the new password field',
        'default'     => __( 'New Password', 'custom-login-users' ),
    ],
    [
        'field'       => 'verify_password',
        'label'       => 'Verify Password Label',
        'placeholder' => 'Enter the label for the verify password field',
        'default'     => __( 'Confirm New Password', 'custom-login-users' ),
    ],
    [
        'field'       => 'renew_password',
        'label'       => 'Reset Password Button Text',
        'placeholder' => 'Enter the text for the reset password button',
        'default'     => __( 'Set Password', 'custom-login-users' ),
    ],
    [
        'field'       => 'required_field_error',
        'label'       => 'Required Field Error Text',
        'placeholder' => 'Enter the error message for empty required fields',
        'default'     => __( 'Required field', 'custom-login-users' ),
    ],
];

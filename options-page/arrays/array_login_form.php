<?php

defined( 'ABSPATH' ) || exit;

return [
    [
        'field'       => 'log_in_title',
        'label'       => 'Login Title Text',
        'placeholder' => 'Enter the title for the login form',
        'default'     => __( 'Login to Your Account', 'custom-login-users' ),
    ],
    [
        'field'       => 'email_label',
        'label'       => 'Username / Email Field Label',
        'placeholder' => 'Enter the label for the username or email field',
        'default'     => __( 'Email', 'custom-login-users' ),
    ],
    [
        'field'       => 'password_label',
        'label'       => 'Password Field Label',
        'placeholder' => 'Enter the label for the password field',
        'default'     => __( 'Password', 'custom-login-users' ),
    ],
    [
        'field'       => 'remember_me',
        'label'       => 'Remember Me Text',
        'placeholder' => 'Enter the text next to the remember me checkbox',
        'default'     => __( 'Remember me', 'custom-login-users' ),
    ],
    [
        'field'       => 'login_button',
        'label'       => 'Login Button Text',
        'placeholder' => 'Enter the label for the login button',
        'default'     => __( 'Log In', 'custom-login-users' ),
    ],
    [
        'field'       => 'pass_forgot',
        'label'       => 'Forgot Password Text',
        'placeholder' => 'Enter the text shown before the reset link',
        'default'     => __( 'Forgot your password?', 'custom-login-users' ),
    ],
    [
        'field'       => 'pass_reset_link',
        'label'       => 'Forgot Password — Reset Link Text',
        'placeholder' => 'Enter the text for the reset password link',
        'default'     => __( 'Reset Password', 'custom-login-users' ),
    ],
    [
        'field'       => 'required_field_error',
        'label'       => 'Required Field Error Text',
        'placeholder' => 'Enter the error message for empty required fields',
        'default'     => __( 'Required field', 'custom-login-users' ),
    ],
];

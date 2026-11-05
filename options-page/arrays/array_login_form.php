<?php

defined( 'ABSPATH' ) || exit;

return [
    [
        'field'       => 'log_in_title',
        'label'       => 'Login Title Text',
        'placeholder' => 'Enter the title for the login form',
        'default'     => 'Login to Your Account',
    ],
    [
        'field'       => 'email_label',
        'label'       => 'Username / Email Field Label',
        'placeholder' => 'Enter the label for the username or email field',
        'default'     => 'Email',
    ],
    [
        'field'       => 'password_label',
        'label'       => 'Password Field Label',
        'placeholder' => 'Enter the label for the password field',
        'default'     => 'Password',
    ],
    [
        'field'       => 'remember_me',
        'label'       => 'Remember Me Text',
        'placeholder' => 'Enter the text next to the remember me checkbox',
        'default'     => 'Remember me',
    ],
    [
        'field'       => 'login_button',
        'label'       => 'Login Button Text',
        'placeholder' => 'Enter the label for the login button',
        'default'     => 'Log In',
    ],
    [
        'field'       => 'pass_forgot',
        'label'       => 'Forgot Password Text',
        'placeholder' => 'Enter the text shown before the reset link',
        'default'     => 'Forgot your password?',
    ],
    [
        'field'       => 'pass_reset_link',
        'label'       => 'Forgot Password — Reset Link Text',
        'placeholder' => 'Enter the text for the reset password link',
        'default'     => 'Reset Password',
    ],
    [
        'field'       => 'required_field_error',
        'label'       => 'Required Field Error Text',
        'placeholder' => 'Enter the error message for empty required fields',
        'default'     => 'Required field',
    ],
];

<?php

defined( 'ABSPATH' ) || exit;

return [
    [
        'field'       => 'fp_title',
        'label'       => 'Forgot Password Title',
        'placeholder' => 'Enter the title for the forgot password form',
        'default'     => __( 'Reset Your Password', 'custom-login-users' ),
    ],
    [
        'field'       => 'enter_mail',
        'label'       => 'Enter Email Message',
        'placeholder' => 'Enter the message prompting the user to enter their email',
        'default'     => __( 'Please enter your email address below:', 'custom-login-users' ),
    ],
    [
        'field'       => 'mail_text',
        'label'       => 'Email Field Label',
        'placeholder' => 'Enter the label for the email input field',
        'default'     => __( 'Email Address', 'custom-login-users' ),
    ],
    [
        'field'       => 'renew_password',
        'label'       => 'Reset Password Button Text',
        'placeholder' => 'Enter the text for the reset password button',
        'default'     => __( 'Reset Password', 'custom-login-users' ),
    ],
    [
        'field'       => 'required_field_error',
        'label'       => 'Required Field Error Text',
        'placeholder' => 'Enter the error message for empty required fields',
        'default'     => __( 'Required field', 'custom-login-users' ),
    ],
];

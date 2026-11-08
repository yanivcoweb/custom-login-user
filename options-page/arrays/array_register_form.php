<?php

defined( 'ABSPATH' ) || exit;

return [
    [
        'field'       => 'register_title',
        'label'       => 'Register Form Title',
        'placeholder' => 'Enter the title for the register form',
        'default'     => __( 'Register Now', 'custom-login-users' ),
    ],
    [
        'field'       => 'register_btn_text',
        'label'       => 'Register Button Text',
        'placeholder' => 'Enter the text for the register button',
        'default'     => __( 'Register', 'custom-login-users' ),
    ],
    [
        'field'       => 'required_field_error',
        'label'       => 'Required Field Error Text',
        'placeholder' => 'Enter the error message for empty required fields',
        'default'     => __( 'Required field', 'custom-login-users' ),
    ],
    [
        'field'       => 'email_invalid_error',
        'label'       => 'Invalid Email Error Text',
        'placeholder' => 'Enter the error message for an invalid email address',
        'default'     => __( 'Invalid email address', 'custom-login-users' ),
    ],
];

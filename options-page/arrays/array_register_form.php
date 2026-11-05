<?php

defined( 'ABSPATH' ) || exit;

return [
    [
        'field'       => 'register_title',
        'label'       => 'Register Form Title',
        'placeholder' => 'Enter the title for the register form',
        'default'     => 'Register Now',
    ],
    [
        'field'       => 'register_btn_text',
        'label'       => 'Register Button Text',
        'placeholder' => 'Enter the text for the register button',
        'default'     => 'Register',
    ],
    [
        'field'       => 'required_field_error',
        'label'       => 'Required Field Error Text',
        'placeholder' => 'Enter the error message for empty required fields',
        'default'     => 'Required field',
    ],
    [
        'field'       => 'email_invalid_error',
        'label'       => 'Invalid Email Error Text',
        'placeholder' => 'Enter the error message for an invalid email address',
        'default'     => 'Invalid email address',
    ],
];

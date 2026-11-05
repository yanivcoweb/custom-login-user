<?php

defined( 'ABSPATH' ) || exit;

return [
    [
        'field'       => 'fp_title',
        'label'       => 'Forgot Password Title',
        'placeholder' => 'Enter the title for the forgot password form',
        'default'     => 'Reset Your Password',
    ],
    [
        'field'       => 'enter_mail',
        'label'       => 'Enter Email Message',
        'placeholder' => 'Enter the message prompting the user to enter their email',
        'default'     => 'Please enter your email address below:',
    ],
    [
        'field'       => 'mail_text',
        'label'       => 'Email Field Label',
        'placeholder' => 'Enter the label for the email input field',
        'default'     => 'Email Address',
    ],
    [
        'field'       => 'renew_password',
        'label'       => 'Reset Password Button Text',
        'placeholder' => 'Enter the text for the reset password button',
        'default'     => 'Reset Password',
    ],
    [
        'field'       => 'required_field_error',
        'label'       => 'Required Field Error Text',
        'placeholder' => 'Enter the error message for empty required fields',
        'default'     => 'Required field',
    ],
];

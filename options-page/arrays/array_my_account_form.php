<?php

defined( 'ABSPATH' ) || exit;

return [
    [
        'field'       => 'title',
        'label'       => 'Page Title',
        'placeholder' => 'Enter the title of the account page',
        'default'     => __( 'My Account', 'custom-login-users' ),
    ],
    [
        'field'       => 'profile_title',
        'label'       => 'Profile Section Title',
        'placeholder' => 'Enter the title of the profile section',
        'default'     => __( 'Update Profile', 'custom-login-users' ),
    ],
    [
        'field'       => 'first_name',
        'label'       => 'First Name Label',
        'placeholder' => 'Enter the label for the first name field',
        'default'     => __( 'First Name', 'custom-login-users' ),
    ],
    [
        'field'       => 'last_name',
        'label'       => 'Last Name Label',
        'placeholder' => 'Enter the label for the last name field',
        'default'     => __( 'Last Name', 'custom-login-users' ),
    ],
    [
        'field'       => 'email',
        'label'       => 'Email Label',
        'placeholder' => 'Enter the label for the email field',
        'default'     => __( 'Email Address', 'custom-login-users' ),
    ],
    [
        'field'       => 'company',
        'label'       => 'Company Label',
        'placeholder' => 'Enter the label for the company field',
        'default'     => __( 'Company Name', 'custom-login-users' ),
    ],
    [
        'field'       => 'email_password',
        'label'       => 'Current Password Label, to change the email',
        'placeholder' => 'Enter the label shown once the email address is changed',
        'default'     => __( 'Current password, to change the email address', 'custom-login-users' ),
    ],
    [
        'field'       => 'profile_button',
        'label'       => 'Profile Button Text',
        'placeholder' => 'Enter the text for the profile button',
        'default'     => __( 'Update Profile', 'custom-login-users' ),
    ],
    [
        'field'       => 'password_title',
        'label'       => 'Password Section Title',
        'placeholder' => 'Enter the title of the password section',
        'default'     => __( 'Change Password', 'custom-login-users' ),
    ],
    [
        'field'       => 'current_password',
        'label'       => 'Current Password Label',
        'placeholder' => 'Enter the label for the current password field',
        'default'     => __( 'Current Password', 'custom-login-users' ),
    ],
    [
        'field'       => 'new_password',
        'label'       => 'New Password Label',
        'placeholder' => 'Enter the label for the new password field',
        'default'     => __( 'New Password', 'custom-login-users' ),
    ],
    [
        'field'       => 'confirm_password',
        'label'       => 'Confirm Password Label',
        'placeholder' => 'Enter the label for the confirm password field',
        'default'     => __( 'Confirm New Password', 'custom-login-users' ),
    ],
    [
        'field'       => 'password_button',
        'label'       => 'Password Button Text',
        'placeholder' => 'Enter the text for the password button',
        'default'     => __( 'Change Password', 'custom-login-users' ),
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

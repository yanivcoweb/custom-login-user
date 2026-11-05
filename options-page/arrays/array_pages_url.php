<?php

defined( 'ABSPATH' ) || exit;

return [
    [
        'field'       => 'url_page_login',
        'label'       => 'Login page url',
        'placeholder' => '',
        'default'     => '',
    ],
    [
        'field'       => 'url_page_register',
        'label'       => 'Register page url',
        'placeholder' => '',
        'default'     => '',
    ],
    [
        'field'       => 'url_page_password_set',
        'label'       => 'Password set page url',
        'placeholder' => '',
        'default'     => '',
    ],
    [
        'field'       => 'url_page_password_lost',
        'label'       => 'Password lost page url',
        'placeholder' => '',
        'default'     => '',
    ],
    [
        'field'       => 'url_page_redirect_after_login',
        'label'       => 'Redirect url after login',
        'placeholder' => '',
        'default'     => '',
    ],
    [
        'field'       => 'url_page_redirect_after_logout',
        'label'       => 'Redirect to page after logout',
        'placeholder' => '',
        'default'     => '',
    ],
    [
        'field'       => 'url_page_redirect_after_password_set',
        'label'       => 'Redirect url after password set',
        'placeholder' => '',
        'default'     => '',
    ],
    [
        'field'       => 'url_page_no_access',
        'label'       => 'Page for users without access',
        'placeholder' => '',
        'default'     => '',
        'description' => 'Where a logged-in user who is not approved goes when opening a restricted page. With this empty, they see a plain message.',
    ],
];

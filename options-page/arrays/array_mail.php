<?php 

return [
    [
        'field' => 'from_email',
        'label' => 'From email address for plugin emails',
        'placeholder' => 'e.g. info@example.com',
        'default' => '',
        'type' => 'email',
        'description' => 'Use an address verified by your SMTP provider. If WP Mail SMTP forces a From Email, that setting may override this value.'
    ],
    [
        'field' => 'from_name',
        'label' => 'From name for plugin emails',
        'placeholder' => 'e.g. Company Name',
        'default' => '',
        'type' => 'text',
        'description' => 'Shown as the sender name when the mail provider allows it.'
    ],
    [
        'field' => 'admin_mail_addresss',
        'label' => 'Email address(es) to notify on new registration',
        'placeholder' => 'e.g. admin@example.com,manager@example.com',
        'default' => '',
        'type' => 'text'
    ]
];

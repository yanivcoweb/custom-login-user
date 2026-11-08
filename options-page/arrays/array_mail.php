<?php

defined( 'ABSPATH' ) || exit;

return [
    [
        'field'       => 'from_email',
        'label'       => 'From email address for plugin emails',
        'placeholder' => 'e.g. info@example.com',
        'default'     => '',
        'type'        => 'email',
        'description' => 'Use an address verified by your SMTP provider. If WP Mail SMTP forces a From Email, that setting may override this value.',
    ],
    [
        'field'       => 'from_name',
        'label'       => 'From name for plugin emails',
        'placeholder' => 'e.g. Company Name',
        'default'     => '',
        'type'        => 'text',
        'description' => 'Shown as the sender name when the mail provider allows it.',
    ],
    [
        'field'       => 'admin_mail_addresss',
        'label'       => 'Email address(es) to notify on new registration',
        'placeholder' => 'e.g. admin@example.com,manager@example.com',
        'default'     => '',
        'type'        => 'text',
        'description' => 'Separate several addresses with commas. Left empty, the site admin email is used.',
    ],
    [
        'field'          => 'confirm_email',
        'label'          => 'Confirm the email address',
        'checkbox_label' => 'New users confirm their address through an emailed link, and only then does their registration reach you',
        'default'        => '',
        'type'           => 'checkbox',
        'description'    => 'Made-up and mistyped addresses never reach you. Until the link is clicked the account has the Unconfirmed User role and cannot log in. Registering again with the same address sends a new link. Accounts made before this was turned on are not affected.',
    ],
    [
        'field'          => 'notify_received',
        'label'          => 'Registration received email',
        'checkbox_label' => 'Tell new users by email that their registration was received and waits for approval',
        'default'        => '',
        'type'           => 'checkbox',
        'description'    => 'With the address confirmation on, this email goes out once the address is confirmed.',
    ],
];

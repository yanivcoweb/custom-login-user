<?php

defined( 'ABSPATH' ) || exit;

return [
    [
        'field'          => 'enabled',
        'label'          => 'Use a status',
        'checkbox_label' => 'Some users of this site are a kind of their own that an admin approves, and their status is kept on the user',
        'default'        => '',
        'type'           => 'checkbox',
        'description'    => 'For example business customers or members. The status is None, Pending, Approved or Rejected, and is set on the user\'s screen and on the Users list. '
            . 'It never changes the role of a user, does not stop anyone from logging in and sends no email. '
            . 'A user with the Approved status sees restricted content. Left off, nobody has a status and nothing of it shows. '
            . 'It is apart from the approval of new registrations on the User Roles tab, which works the same either way.',
    ],
    [
        'field'       => 'label',
        'label'       => 'Name of this kind of user',
        'placeholder' => 'e.g. Business customer',
        'default'     => '',
        'type'        => 'text',
        'description' => 'Shown as the title of the status on the user\'s screen and on the Users list. Left empty, it is "Status".',
    ],
];

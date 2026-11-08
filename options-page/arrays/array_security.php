<?php

defined( 'ABSPATH' ) || exit;

// Shown with the saved keys, so that a wrong site key is seen before anyone logs out.
$clu_captcha_preview = clu_captcha()
    ? '<br>The widget below uses the saved keys. If it shows an error, fix the keys before logging out.' . clu_captcha_box()
    : '';

return [
    [
        'field'          => 'limit_logins',
        'label'          => 'Limit failed logins',
        'checkbox_label' => 'Refuse logins for a while from an address with too many failed attempts',
        'default'        => '',
        'type'           => 'checkbox',
        'description'    => 'Counts the failures on every login form of the site: this plugin\'s, wp-login.php, WooCommerce and XML-RPC. While an address is refused, even the right password does not log in from it. '
            . sprintf( 'The plugin sees your address as <code>%s</code>.', esc_html( clu_client_ip() ) )
            . ' If that is not your own address, the site is behind a proxy or CDN and all visitors would share one limit: set the <code>clu_client_ip</code> filter first.',
    ],
    [
        'field'       => 'login_attempts',
        'label'       => 'Failed attempts allowed',
        'placeholder' => '5',
        'default'     => '5',
        'type'        => 'number',
    ],
    [
        'field'       => 'lockout_minutes',
        'label'       => 'Minutes refused after the last one',
        'placeholder' => '15',
        'default'     => '15',
        'type'        => 'number',
        'description' => 'Failures count while they come less than this many minutes apart.',
    ],
    [
        'field'       => 'captcha',
        'label'       => 'Bot check',
        'default'     => '',
        'type'        => 'select',
        'options'     => [ '' => 'None' ] + array_map(
            function ( $service ) {
                return $service['label'];
            },
            clu_captcha_services()
        ),
        'description' => 'On the login, register and lost password forms of this plugin, and the same three on wp-login.php. Not on forms of other plugins, such as WooCommerce, which have their own.',
    ],
    [
        'field'       => 'captcha_site_key',
        'label'       => 'Site key',
        'placeholder' => '',
        'default'     => '',
        'type'        => 'text',
    ],
    [
        'field'       => 'captcha_secret_key',
        'label'       => 'Secret key',
        'placeholder' => '',
        'default'     => '',
        'type'        => 'text',
        'description' => 'Saving checks this key with the service, and a refused key turns the bot check off.' . $clu_captcha_preview,
    ],
];

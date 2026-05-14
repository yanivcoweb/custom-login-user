<?php

function render_my_account($attributes) {
    wp_enqueue_script('validation-js');
    wp_enqueue_style('templates-css');

    if (!is_user_logged_in()) {
        return '<p>' . clu_render_message('my_account_not_logged_in') . '</p>';
    }

    $user = wp_get_current_user();
    $attributes = array_merge([
        'first_name' => $user->first_name,
        'last_name'  => $user->last_name,
        'email'      => $user->user_email,
        'company'    => get_user_meta($user->ID, 'company', true),
    ], (array) $attributes);

    return custom_login_users_get_template_html('form_my_account', $attributes);
}
add_shortcode('my-account', 'render_my_account');

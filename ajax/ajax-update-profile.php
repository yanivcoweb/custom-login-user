<?php

add_action('wp_ajax_ajax_update_profile', 'handle_ajax_update_profile');
function handle_ajax_update_profile() {

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => clu_render_message('my_account_not_logged_in')]);
        exit;
    }

    if (!wp_verify_nonce($_POST['my_account_profile_nonce'] ?? '', 'my_account_profile_nonce')) {
        wp_send_json_error(['message' => clu_render_message('invalid_nonce')]);
        exit;
    }

    $user_id    = get_current_user_id();
    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name  = sanitize_text_field($_POST['last_name'] ?? '');
    $email      = sanitize_email($_POST['email'] ?? '');
    $company    = sanitize_text_field($_POST['company'] ?? '');

    if (!$first_name || !$last_name || !$email) {
        wp_send_json_error(['message' => clu_render_message('profile_update_missing_fields')]);
        exit;
    }

    $existing = email_exists($email);
    if ($existing && $existing !== $user_id) {
        wp_send_json_error(['message' => clu_render_message('profile_email_taken')]);
        exit;
    }

    $result = wp_update_user([
        'ID'         => $user_id,
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'user_email' => $email,
    ]);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
        exit;
    }

    update_user_meta($user_id, 'company', $company);

    wp_send_json_success(['message' => clu_render_message('profile_update_success')]);
}

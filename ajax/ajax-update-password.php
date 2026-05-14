<?php

add_action('wp_ajax_ajax_update_password', 'handle_ajax_update_password');
function handle_ajax_update_password() {

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => clu_render_message('my_account_not_logged_in')]);
        exit;
    }

    if (!wp_verify_nonce($_POST['my_account_password_nonce'] ?? '', 'my_account_password_nonce')) {
        wp_send_json_error(['message' => clu_render_message('invalid_nonce')]);
        exit;
    }

    $user_id          = get_current_user_id();
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = sanitize_text_field($_POST['new_password'] ?? '');
    $confirm_password = sanitize_text_field($_POST['confirm_password'] ?? '');

    if (!$current_password || !$new_password || !$confirm_password) {
        wp_send_json_error(['message' => clu_render_message('profile_update_missing_fields')]);
        exit;
    }

    $user_data = get_userdata($user_id);
    if (!wp_check_password($current_password, $user_data->user_pass, $user_id)) {
        wp_send_json_error(['message' => clu_render_message('password_current_incorrect')]);
        exit;
    }

    if ($new_password !== $confirm_password) {
        wp_send_json_error(['message' => clu_render_message('password_confirm_mismatch')]);
        exit;
    }

    $errors = [];
    $rules  = clu_get_password_rules();
    if (strlen($new_password) < $rules['min_length'])                               $errors[] = clu_get_message('password_min_length');
    if ($rules['require_uppercase'] && !preg_match('/[A-Z]/', $new_password))       $errors[] = clu_get_message('password_uppercase');
    if ($rules['require_lowercase'] && !preg_match('/[a-z]/', $new_password))       $errors[] = clu_get_message('password_lowercase');
    if ($rules['require_number']    && !preg_match('/[0-9]/', $new_password))       $errors[] = clu_get_message('password_number');
    if ($rules['require_special']   && !preg_match('/[!@#$%^&*()]/', $new_password)) $errors[] = clu_get_message('password_special_char');

    if (!empty($errors)) {
        wp_send_json_error(['message' => implode('<br>', $errors)]);
        exit;
    }

    wp_set_password($new_password, $user_id);
    wp_set_auth_cookie($user_id, true);

    wp_send_json_success(['message' => clu_render_message('password_update_success')]);
}

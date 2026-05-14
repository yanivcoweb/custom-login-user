<?php

add_action('wp_ajax_clu_save_messages', 'clu_save_messages_handler');
function clu_save_messages_handler() {
    check_ajax_referer('clu_messages_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized.']);
    }

    // Subject lines must be plain text; all other fields allow safe HTML via wp_kses_post
    $subject_keys = ['reset_email_subject', 'notify_client_subject', 'notify_user_subject'];

    $keys  = array_keys(clu_default_messages());
    $saved = [];

    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            $value = wp_unslash($_POST[$key]);
            $saved[$key] = in_array($key, $subject_keys, true)
                ? sanitize_text_field($value)
                : clu_sanitize_message_template($value);
        }
    }

    update_option('clu_messages', $saved);
    wp_send_json_success(['message' => 'Messages saved.']);
}

add_action('wp_ajax_clu_restore_default_messages', 'clu_restore_default_messages_handler');
function clu_restore_default_messages_handler() {
    check_ajax_referer('clu_messages_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized.']);
    }

    delete_option('clu_messages');
    wp_send_json_success(['message' => 'Messages restored to defaults.', 'defaults' => clu_default_messages()]);
}

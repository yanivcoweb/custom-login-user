<?php

// Save role assignments
add_action('wp_ajax_clu_save_roles', 'clu_save_roles_handler');
function clu_save_roles_handler() {
    check_ajax_referer('clu_roles_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized.']);
    }

    $pending  = sanitize_key($_POST['pending_role'] ?? '');
    $approved = sanitize_key($_POST['approved_role'] ?? '');

    if (empty($pending) || empty($approved)) {
        wp_send_json_error(['message' => 'Both roles must be selected.']);
    }

    if (!get_role($pending)) {
        wp_send_json_error(['message' => 'The selected pending role does not exist.']);
    }

    if (!get_role($approved)) {
        wp_send_json_error(['message' => 'The selected approved role does not exist.']);
    }

    update_option('clu_roles', [
        'pending_role'  => $pending,
        'approved_role' => $approved,
    ]);

    wp_send_json_success(['message' => 'Role assignments saved.']);
}

// Create a new role
add_action('wp_ajax_clu_create_role', 'clu_create_role_handler');
function clu_create_role_handler() {
    check_ajax_referer('clu_roles_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized.']);
    }

    $slug         = sanitize_key($_POST['role_slug'] ?? '');
    $display_name = sanitize_text_field($_POST['role_display_name'] ?? '');

    if (empty($slug) || empty($display_name)) {
        wp_send_json_error(['message' => 'Role slug and display name are required.']);
    }

    if (get_role($slug)) {
        wp_send_json_error(['message' => 'A role with this slug already exists.']);
    }

    $result = add_role($slug, $display_name, [
        'read'        => false,
        'edit_posts'  => false,
        'delete_posts'=> false,
    ]);

    if ($result === null) {
        wp_send_json_error(['message' => 'Failed to create role.']);
    }

    wp_send_json_success([
        'message' => 'Role "' . $display_name . '" created successfully.',
        'slug'    => $slug,
        'name'    => $display_name,
    ]);
}

// Delete a role
add_action('wp_ajax_clu_delete_role', 'clu_delete_role_handler');
function clu_delete_role_handler() {
    check_ajax_referer('clu_roles_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized.']);
    }

    $slug = sanitize_key($_POST['role_slug'] ?? '');

    if (empty($slug)) {
        wp_send_json_error(['message' => 'Role slug is required.']);
    }

    // Prevent deleting built-in WordPress roles
    $protected = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];
    if (in_array($slug, $protected, true)) {
        wp_send_json_error(['message' => 'Built-in WordPress roles cannot be deleted.']);
    }

    if (!get_role($slug)) {
        wp_send_json_error(['message' => 'Role not found.']);
    }

    remove_role($slug);

    wp_send_json_success(['message' => 'Role "' . $slug . '" deleted successfully.', 'slug' => $slug]);
}

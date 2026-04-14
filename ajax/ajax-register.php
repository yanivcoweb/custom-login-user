<?php

error_log('ajax-register.php');

add_action('wp_ajax_nopriv_ajax_register_user', 'handle_ajax_register_user'); // For non-logged-in users
add_action('wp_ajax_ajax_register_user', 'handle_ajax_register_user'); // For logged-in users
function handle_ajax_register_user() {
	error_log('functions.php - function ajax_register_user() {');
    // Check the nonce
    if (!isset($_POST['register_nonce']) || !wp_verify_nonce($_POST['register_nonce'], 'register_form_nonce')) {
        // wp_send_json_error(['message' => 'Security check failed.']);
		wp_send_json_error(['message' => clu_get_message('invalid_nonce')]);
    }

    // Sanitize input data
    $email = sanitize_email($_POST['email']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $company = sanitize_text_field($_POST['company']);

    // Validate required fields
    if (empty($email) || empty($first_name) || empty($last_name)) {
        // wp_send_json_error(['message' => 'Please fill in all required fields.']);
		wp_send_json_error(['message' => clu_get_message('register_required_fields')]);

    }

    // Check if the email is already registered
    if (email_exists($email)) {
        // wp_send_json_error(['message' => 'This email address is already registered.']);
		wp_send_json_error(['message' => clu_get_message('email_already_registered')]);

    }

    // Generate a random password
    $password = wp_generate_password(12, true);

	// Generate username from first + last name (e.g. yaniv.sasson)
	$base_login = sanitize_user( strtolower( $first_name . '.' . $last_name ), true );
	if ( empty( $base_login ) ) {
		$base_login = sanitize_user( strtolower( $first_name ), true );
	}
	$user_login = $base_login;
	$suffix = 1;
	while ( username_exists( $user_login ) ) {
		$user_login = $base_login . $suffix;
		$suffix++;
	}

    // Set the nickname (e.g., First + Last name)
    $nickname = $first_name . ' ' . $last_name;

    // Create the user
    $user_id = wp_insert_user([
        'user_login' => $user_login,
        'user_email' => $email,
        'user_pass' => $password,
        'first_name' => $first_name,
        'last_name' => $last_name,
		'nickname' => $nickname, // Set the nickname
        'role' => clu_get_role_slug('pending_role'), // Assign configured pending role
    ]);

    // Check for errors
    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => $user_id->get_error_message()]);
    }

    // Add custom meta
    update_user_meta($user_id, 'company', $company);

    // Optionally send an email with the credentials
    //wp_new_user_notification($user_id, null, 'user');
	
	

    wp_send_json_success(['message' => clu_get_message('register_success')]);
}

?>
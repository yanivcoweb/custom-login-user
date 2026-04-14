<?php


add_action('wp_ajax_ajax_password_set', 'handle_ajax_password_set');
add_action('wp_ajax_nopriv_ajax_password_set', 'handle_ajax_password_set');
function handle_ajax_password_set() {
	
	error_log('functions.php - function password_set() {');		
    
	if (!isset($_POST['set_password_nonce']) || !wp_verify_nonce($_POST['set_password_nonce'], 'set_password_form_nonce')) {
        // wp_send_json_error(['message' => 'Invalid nonce.']);
		wp_send_json_error(['message' => clu_get_message('invalid_nonce')]);
        exit;
    }
	
	// Check for required parameters
    if (empty($_REQUEST['rp_key']) || empty($_REQUEST['rp_login']) || empty($_POST['new_password'])) {
        // wp_send_json_error(['message' => 'Missing required fields.']);
		wp_send_json_error(['message' => clu_get_message('password_set_missing_fields')]);
    }

    $rp_key = $_REQUEST['rp_key'];
    $rp_login = $_REQUEST['rp_login'];
    $new_password = sanitize_text_field($_POST['new_password']);

    // Validate the reset key and user login
    $user = check_password_reset_key($rp_key, $rp_login);

    if (is_wp_error($user)) {
        // wp_send_json_error(['message' => 'Invalid or expired password reset link.']);
		wp_send_json_error(['message' => clu_get_message('invalid_reset_link')]);

    }


    // $new_password = sanitize_text_field($_POST['new_password']);

    // Validate password strength with specific messages
    $errors = [];

    // if (strlen($new_password) < 6) {
        // $errors[] = 'Password must be at least 6 characters long.';
    // }
    // if (!preg_match('/[A-Z]/', $new_password)) {
        // $errors[] = 'Password must contain at least one uppercase letter (A-Z).';
    // }
    // if (!preg_match('/[a-z]/', $new_password)) {
        // $errors[] = 'Password must contain at least one lowercase letter (a-z).';
    // }
    // if (!preg_match('/[0-9]/', $new_password)) {
        // $errors[] = 'Password must contain at least one number (0-9).';
    // }
    // if (!preg_match('/[!@#$%^&*()]/', $new_password)) {
        // $errors[] = 'Password must contain at least one special character (!@#$%^&*()).';
    // }
	$rules = clu_get_password_rules();
	if (strlen($new_password) < $rules['min_length']) {
		$errors[] = clu_get_message('password_min_length');
	}
	if ($rules['require_uppercase'] && !preg_match('/[A-Z]/', $new_password)) {
		$errors[] = clu_get_message('password_uppercase');
	}
	if ($rules['require_lowercase'] && !preg_match('/[a-z]/', $new_password)) {
		$errors[] = clu_get_message('password_lowercase');
	}
	if ($rules['require_number'] && !preg_match('/[0-9]/', $new_password)) {
		$errors[] = clu_get_message('password_number');
	}
	if ($rules['require_special'] && !preg_match('/[!@#$%^&*()]/', $new_password)) {
		$errors[] = clu_get_message('password_special_char');
	}


    if (!empty($errors)) {
        wp_send_json_error(['message' => implode('<br>', $errors)]);
    }


    // Reset the password
    reset_password($user, $new_password);

    // Auto-login the user
    wp_set_auth_cookie($user->ID, true); // true for "remember me"

    // Send a success response
	$options = get_option('clu_pages_url');
	wp_send_json([
        'success' => true,
        'data' => [
            //'message' => 'Your password has been successfully reset.',
            'message' => clu_get_message('password_reset_success'),
			'redirect' => $options['url_page_redirect_after_password_set']
        ]
    ]);
		
	// wp_redirect( home_url( 'investor-zone-files' ) ); // רצוי לא להסתמך על הסלאג
	
}



?>
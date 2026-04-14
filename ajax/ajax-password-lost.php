<?php


add_action('wp_ajax_ajax_password_lost', 'handle_ajax_password_lost');
add_action('wp_ajax_nopriv_ajax_password_lost', 'handle_ajax_password_lost');
function handle_ajax_password_lost() {
    error_log('functions.php - function handle_ajax_password_lost() {');    

    // Verify the nonce for security
    if (!isset($_POST['password_lost_nonce']) || !wp_verify_nonce($_POST['password_lost_nonce'], 'password_lost_form_nonce')) {
        // wp_send_json_error(['message' => 'Invalid request.']);
        wp_send_json_error(['message' => clu_get_message('invalid_nonce')]);
        exit;
    }

    // Check if the user login/email field is set and sanitize it
    if (empty($_POST['user_login'])) {
        // wp_send_json_error(['message' => 'Please enter your email address.']);
		wp_send_json_error(['message' => clu_get_message('email_required')]);
        exit;
    }

    $user_login = sanitize_text_field($_POST['user_login']);
    error_log('Password lost request for: ' . $user_login);

    // Retrieve user data based on email or login
    if (is_email($user_login)) {
        $user_data = get_user_by('email', $user_login);
    } else {
        $user_data = get_user_by('login', $user_login);
    }

    // Check if the user exists
    if (!$user_data) {
        // wp_send_json_error(['message' => 'No user found with the given email address or username.']);
		wp_send_json_error(['message' => clu_get_message('user_not_found')]);
        exit;
    }

    // Generate the password reset key
    $reset_key = get_password_reset_key($user_data);
    if (is_wp_error($reset_key)) {
        // wp_send_json_error(['message' => 'An error occurred. Please try again later.']);
		wp_send_json_error(['message' => clu_get_message('general_error')]);
        exit;
    }

    // Create the password reset URL
	$options = get_option('clu_pages_url');
	$url_page_password_set = trailingslashit($options['url_page_password_set']);
    $reset_url = $url_page_password_set . '?action=rp&key=' . $reset_key . '&login=' . rawurlencode($user_data->user_login);
    // error_log('Password reset URL: ' . $reset_url);

    // Prepare the email
    // $email_subject = 'Password Reset Request';
    // $email_message = sprintf(
        // "Hello %s,\n\nWe received a request to reset your password. Please click the link below to reset your password:\n\n%s\n\nIf you did not request this, please ignore this email.",
        // $user_data->display_name,
        // $reset_url
    // );
	$email_subject = clu_get_message('reset_email_subject');
	$email_message = sprintf(clu_get_message('reset_email_body'), $user_data->display_name, $reset_url);


    // Send the email
    $email_sent = wp_mail(
        $user_data->user_email,
        $email_subject,
        $email_message,
        ['Content-Type: text/plain; charset=UTF-8']
    );

    // Check if the email was sent successfully
    if (!$email_sent) {
        // wp_send_json_error(['message' => 'Failed to send the reset email. Please try again later.']);
		wp_send_json_error(['message' => clu_get_message('reset_email_send_failed')]);
        exit;
    }

    // Respond with success
    // wp_send_json_success(['message' => 'Password reset email has been sent. Please check your inbox.']);
    wp_send_json_success(['message' => clu_get_message('reset_email_sent')]);

}



?>
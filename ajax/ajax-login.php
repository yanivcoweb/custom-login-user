<?php 



add_action('wp_ajax_ajax_login_user', 'handle_ajax_login_user');
add_action('wp_ajax_nopriv_ajax_login_user', 'handle_ajax_login_user');
function handle_ajax_login_user() {
	
	error_log('function handle_ajax_login_user');
	
    // header('Content-Type: application/json');

    // Verify nonce
    if (!isset($_POST['login_nonce']) || !wp_verify_nonce($_POST['login_nonce'], 'login_form_nonce')) {
        wp_send_json([
            'success' => false,
            // 'data' => ['message' => 'Invalid request.']
			'data' => ['message' => clu_get_message('invalid_nonce')]
        ]);
    }

    // Get login credentials
    $credentials = [
        'user_login'    => sanitize_text_field($_POST['log']),
        'user_password' => sanitize_text_field($_POST['pwd']),
        'remember'      => true,
    ];

	
    // Since SSL is not available on localhost, replace is_ssl() with false
    // $user = wp_signon($credentials, false);
    $user = wp_signon($credentials, is_ssl());

    if (is_wp_error($user)) {
        wp_send_json([
            'success' => false,
            'data' => ['message' => $user->get_error_message()]
        ]);
		exit;
    }

	
	$options = get_option('clu_pages_url');

    wp_send_json([
        'success' => true,
        'data' => [
            // 'message' => 'Login successful!',
			'message' => clu_get_message('login_success'),
			'redirect' => $options['url_page_redirect_after_login']
        ]
    ]);

	exit;
	
}



?>
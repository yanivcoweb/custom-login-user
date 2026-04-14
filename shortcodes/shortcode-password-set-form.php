<?php


/**
 * A shortcode for rendering the form used to set a user's password.
 *
 * @param  array   $attributes  Shortcode attributes.
 * @param  string  $content     The text content for shortcode. Not used.
 *
 * @return string  The shortcode output
 */

add_shortcode('custom-password-set', 'render_password_set_form');

function render_password_set_form($attributes, $content = null) {
	wp_enqueue_script('validation-js'); 
	wp_enqueue_style('templates-css');

    // if (is_user_logged_in()) {
        //wp_redirect(home_url());
        // exit;
    // }

    // Initialize variables
    $errors = [];
    $reset_password = false;
    $user = null;

	// if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
		// $rp_key = $_REQUEST['rp_key'];
		// $rp_login = $_REQUEST['rp_login'];
		// $reset_key = $rp_key;
		// $user_login = $rp_login;
 
		// $user = check_password_reset_key( $rp_key, $rp_login );
 
		// if ( ! $user || is_wp_error( $user ) ) {
			// if ( $user && $user->get_error_code() === 'expired_key' ) {
				// error_log('Key validation failed 3: expired_key');
				//wp_redirect( home_url( 'member-login?login=expiredkey' ) );
				//wp_redirect( home_url( 'member-login?login=expiredkey' ) );
			// } else {
				// error_log('Key validation failed 4: '.$user->get_error_message());
				//wp_redirect( home_url( 'member-login?login=invalidkey' ) );
			// }
			//exit;
		// }
 
		// if ( isset( $_POST['new_password'] ) ) {
 			// error_log('Parameter checks OK, reset password');
			// reset_password( $user, $_POST['new_password'] );
			// $reset_password = true;
			// wp_redirect( home_url( 'investor-zone-files' ) ); // רצוי לא להסתמך על הסלאג
			 // exit;
		// } else {
 			// error_log('Parameter checks NOT OK');
			// echo get_field('invalid_request','options');
		// }
 
		//exit;
	// }else{

		// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
			
			// $user_login = sanitize_text_field($_POST['rp_login']);
			// $reset_key = sanitize_text_field($_POST['rp_key']);
			// error_log('$user_login');
			// error_log($user_login);
			// error_log('$reset_key');
			// error_log($reset_key);
			
			// $user = check_password_reset_key($reset_key, $user_login);

			// if (is_wp_error($user)) {
				// $errors[] = 'Invalid or expired password reset link.';
				// error_log('Key validation failed 1: ' . $user->get_error_message());
			// } else {
			
			// $new_password = sanitize_text_field($_POST['new_password']);
				// if (empty($new_password)) {
					// $errors[] = 'Please enter a password.';
				// } else {
					// reset_password($user, $new_password);
					// $reset_password = true;
				// }
			// }
		// } else {
			// Handle GET request
			$user_login = sanitize_text_field($_GET['login'] ?? '');
			$reset_key = sanitize_text_field($_GET['key'] ?? '');

			if (!$user_login || !$reset_key) {
				$errors[] = clu_get_message('invalid_reset_link_page');

			} else {
				// Validate the reset key
				$user = check_password_reset_key($reset_key, $user_login);

				if (is_wp_error($user)) {
					$errors[] = clu_get_message('expired_reset_link_page');

					error_log('Key validation failed: ' . $user->get_error_message());
				}
			}
		// }
	// }
    // Pass attributes to the template
    $attributes['errors'] = $errors;
    $attributes['login'] = $user_login;
    $attributes['key'] = $reset_key;
    $attributes['reset_password'] = $reset_password;

    return custom_login_users_get_template_html('form_password_set', $attributes);
	
	
}


?>
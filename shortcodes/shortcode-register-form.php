<?php

/**
 * A shortcode for rendering the new user registration form.
 *
 * @param  array   $attributes  Shortcode attributes.
 * @param  string  $content     The text content for shortcode. Not used.
 *
 * @return string  The shortcode output
 */
add_shortcode( 'custom-register-form', 'render_register_form' );

function render_register_form( $attributes, $content = null ) {
	error_log('custom-login-users/shortcodes/custom-register-form.php - function render_register_form');
	
	wp_enqueue_script('validation-js'); 
	wp_enqueue_style('templates-css');
	
	// Parse shortcode attributes
	$default_attributes = array( 'show_title' => false );
	$attributes = shortcode_atts( $default_attributes, $attributes );

	if ( is_user_logged_in() ) {
		return clu_get_message('user_logged_in_register');

	} elseif ( ! get_option( 'users_can_register' ) ) {
		return clu_get_message('registration_disabled');

	} else {
		// Retrieve possible errors from request parameters
		$attributes['errors'] = array();
		if ( isset( $_REQUEST['register-errors'] ) ) {
			$error_codes = explode( ',', $_REQUEST['register-errors'] );
		 
			foreach ( $error_codes as $error_code ) {
				$attributes['errors'] []= get_error_message( $error_code );
			}
		}
		
		return custom_login_users_get_template_html( 'form_register', $attributes );
		// return 'test';
	}

}


?>
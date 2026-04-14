<?php

 /**
  * A shortcode for rendering the login form.
  *
  * @param  array   $attributes  Shortcode attributes.
  * @param  string  $content     The text content for shortcode. Not used.
  *
  * @return string  The shortcode output
  */
  
add_shortcode( 'custom-login-form', 'render_login_form' );

function render_login_form( $attributes, $content = null ) {
	
	wp_enqueue_script('validation-js'); // Enqueue the script dynamically for this shortcode
	wp_enqueue_style('templates-css');
		
	// Parse shortcode attributes
	$default_attributes = array( 'show_title' => false );
	$attributes = shortcode_atts( $default_attributes, $attributes );

	 
	// Pass the redirect parameter to the WordPress login functionality: by default,
	// don't specify a redirect, but if a valid redirect URL has been passed as
	// request parameter, use it.
	$attributes['redirect'] = '';
	if ( isset( $_REQUEST['redirect_to'] ) ) {
		$attributes['redirect'] = wp_validate_redirect( $_REQUEST['redirect_to'], $attributes['redirect'] );
	}
	 
	 // Error messages
	$errors = array();
	if ( isset( $_REQUEST['login'] ) ) {
		$error_codes = explode( ',', $_REQUEST['login'] );
	 
		foreach ( $error_codes as $code ) {
			$errors []= get_error_message( $code );
		}
	}
	$attributes['errors'] = $errors;
	
	// Check if user just logged out
	$attributes['logged_out'] = isset( $_REQUEST['logged_out'] ) && $_REQUEST['logged_out'] == true;
	
	// Check if the user just registered
	$attributes['registered'] = isset( $_REQUEST['registered'] );
	
	// Check if the user just requested a new password 
	$attributes['lost_password_sent'] = isset( $_REQUEST['checkemail'] ) && $_REQUEST['checkemail'] == 'confirm';

	// Check if user just updated password
	$attributes['password_updated'] = isset( $_REQUEST['password'] ) && $_REQUEST['password'] == 'changed';
	
	if ( is_user_logged_in() ) {
		
		// Render the login form using an external template
		// return custom_login_users_get_template_html( 'form_logout', $attributes );
		$options = get_option('clu_pages_url');
		return str_replace('{logout_url}', wp_logout_url($options['url_page_redirect_after_logout']), clu_get_message('already_logged_in'));

		
	}else{
		
		// Render the login form using an external template
		return custom_login_users_get_template_html( 'form_login', $attributes );
	}
}

?>
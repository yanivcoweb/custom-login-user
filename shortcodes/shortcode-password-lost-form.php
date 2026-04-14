<?php

/**
 * A shortcode for rendering the form used to initiate the password reset.
 *
 * @param  array   $attributes  Shortcode attributes.
 * @param  string  $content     The text content for shortcode. Not used.
 *
 * @return string  The shortcode output
 */
add_shortcode( 'custom-password-lost-form', 'render_password_lost_form'  );
function render_password_lost_form( $attributes, $content = null ) {
	
	wp_enqueue_script('validation-js'); 
	wp_enqueue_style('templates-css');
	
	// Parse shortcode attributes
	$default_attributes = array( 'show_title' => false );
	$attributes = shortcode_atts( $default_attributes, $attributes );
 
	if ( is_user_logged_in() ) {
		//return get_field('loged_in','options');
		return '';
	} else {
		// Retrieve possible errors from request parameters
		$attributes['errors'] = array();
		if ( isset( $_REQUEST['errors'] ) ) {
			$error_codes = explode( ',', $_REQUEST['errors'] );
		 
			foreach ( $error_codes as $error_code ) {
				$attributes['errors'] []= get_error_message( $error_code );
			}
		}
		return custom_login_users_get_template_html( 'form_password_lost', $attributes );
	}
}	


?>
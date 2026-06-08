<?php

/**
 * Formats editable message text for HTML output.
 * Allows safe HTML and converts textarea newlines into HTML line breaks.
 *
 * @param string $text
 * @return string
 */
function clu_email_body_format( $text ) {
    $text = str_replace( [ "\r\n", "\r" ], "\n", (string) $text );
    $text = wp_kses_post( $text );

    if ( preg_match( '/<(p|div|h[1-6]|ul|ol|li|table|tr|td|th|blockquote)\b/i', $text ) ) {
        return $text;
    }

    return nl2br( $text, false );
}

/**
 * Builds a safe HTML link for messages.
 *
 * @param string $url
 * @param string $label
 * @return string
 */
function clu_message_link( $url, $label = 'Link' ) {
    return sprintf(
        '<a href="%s" target="_blank" rel="noopener">%s</a>',
        esc_url( $url ),
        esc_html( $label )
    );
}

/**
 * Returns the host used for legacy plugin sender fallback addresses.
 *
 * @return string
 */
function clu_get_mail_host() {
    $host = wp_parse_url( home_url(), PHP_URL_HOST );

    if ( ! $host && ! empty( $_SERVER['HTTP_HOST'] ) ) {
        $host = preg_replace( '/:\d+$/', '', sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) );
    }

    return (string) $host;
}

/**
 * Checks whether a host is a local development host.
 *
 * @param string $host
 * @return bool
 */
function clu_is_local_host( $host ) {
    $host = strtolower( preg_replace( '/:\d+$/', '', (string) $host ) );
    $ends_with = function ( $value, $suffix ) {
        return $suffix === '' || substr( $value, -strlen( $suffix ) ) === $suffix;
    };

    return in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true )
        || $ends_with( $host, '.local' )
        || $ends_with( $host, '.test' )
        || $ends_with( $host, '.localhost' );
}

/**
 * Returns the URL scheme that plugin-generated links should use.
 *
 * @param string $target_url
 * @return string
 */
function clu_get_site_url_scheme( $target_url = '' ) {
    $home_scheme = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_SCHEME ) );
    $home_host   = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
    $target_host = $target_url ? (string) wp_parse_url( $target_url, PHP_URL_HOST ) : '';

    if ( $home_scheme === 'https' || is_ssl() || ( defined( 'FORCE_SSL_ADMIN' ) && FORCE_SSL_ADMIN ) ) {
        return 'https';
    }

    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
        $forwarded_proto = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) );

        if ( strpos( $forwarded_proto, 'https' ) !== false ) {
            return 'https';
        }
    }

    if ( $target_host && ! clu_is_local_host( $target_host ) ) {
        return 'https';
    }

    if ( $home_host && ! clu_is_local_host( $home_host ) ) {
        return 'https';
    }

    return $home_scheme ?: 'http';
}

/**
 * Normalizes plugin-generated URLs to the current site scheme.
 *
 * @param string $url
 * @param string $fallback_url
 * @return string
 */
function clu_normalize_site_url( $url, $fallback_url = '' ) {
    $url = trim( (string) $url );

    if ( $url === '' ) {
        $url = $fallback_url !== '' ? $fallback_url : home_url( '/' );
    }

    if ( strpos( $url, '//' ) === 0 ) {
        $url = clu_get_site_url_scheme( $url ) . ':' . $url;
    } elseif ( ! wp_parse_url( $url, PHP_URL_SCHEME ) ) {
        $url = home_url( '/' . ltrim( $url, '/' ) );
    }

    return set_url_scheme( esc_url_raw( $url ), clu_get_site_url_scheme( $url ) );
}

/**
 * Returns a sanitized mail option from the plugin Mail settings tab.
 *
 * @param string $key
 * @return string
 */
function clu_get_mail_setting( $key ) {
    $options = get_option( 'clu_mail' );

    if ( ! is_array( $options ) || empty( $options[ $key ] ) ) {
        return '';
    }

    return sanitize_text_field( $options[ $key ] );
}

/**
 * Builds email headers for plugin-generated emails.
 *
 * SMTP plugins may still override the From address if they force a global sender.
 *
 * @param string $fallback_from_email
 * @param string $fallback_from_name
 * @return array
 */
function clu_get_mail_headers( $fallback_from_email = '', $fallback_from_name = '' ) {
    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

    $from_email = sanitize_email( clu_get_mail_setting( 'from_email' ) );

    if ( ! is_email( $from_email ) && $fallback_from_email ) {
        $from_email = sanitize_email( $fallback_from_email );
    }

    if ( ! is_email( $from_email ) ) {
        return $headers;
    }

    $from_name = clu_get_mail_setting( 'from_name' );

    if ( $from_name === '' ) {
        $from_name = $fallback_from_name !== '' ? $fallback_from_name : get_bloginfo( 'name' );
    }

    $headers[] = sprintf( 'From: %s <%s>', sanitize_text_field( $from_name ), $from_email );

    return $headers;
}

/**
 * Applies supported placeholders and formats a message for HTML output.
 *
 * @param string $template
 * @param array  $replacements
 * @return string
 */
function clu_format_message_template( $template, $replacements ) {
    $template = (string) $template;

    foreach ( $replacements as $placeholder => $value ) {
        $template = str_replace( $placeholder, $value, $template );
    }

    return clu_email_body_format( $template );
}

/**
 * Sanitizes editable message templates while preserving placeholders.
 *
 * WordPress may strip placeholders such as %2$s or {logout_url} when they
 * appear inside HTML attributes before they are replaced with real URLs.
 *
 * @param string $text
 * @return string
 */
function clu_sanitize_message_template( $text ) {
    $text         = str_replace( [ "\r\n", "\r" ], "\n", (string) $text );
    $placeholders = [];

    $text = preg_replace_callback(
        '/%(\d+\$)?s|\{[a-z0-9_]+\}/i',
        function ( $matches ) use ( &$placeholders ) {
            $token                  = 'clu-placeholder-' . count( $placeholders );
            $placeholders[ $token ] = $matches[0];

            return $token;
        },
        $text
    );

    $text = wp_kses_post( $text );

    return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $text );
}

/**
 * Renders the contents of the given template to a string and returns it.
 *
 * @param string $template_name The name of the template to render (without .php)
 * @param array  $attributes    The PHP variables for the template
 *
 * @return string               The contents of the template.
 * return get_template_directory() .'/custom-login-users/templates/' . $template_name . '.php';
 */
function custom_login_users_get_template_html( $template_name, $attributes = null ) 
{
	//error_log('functions.php - function custom_login_users_get_template_html');	
	if ( ! $attributes ) {
		$attributes = array();
	}

	ob_start();
	
	require( CLU_THEME_DIR .'forms/' . $template_name . '.php');
	
	$html = ob_get_contents();
	ob_end_clean();
 
	return $html;
}


/**
 * Finds and returns a matching error message for the given error code.
 *
 * @param string $error_code    The error code to look up.
 *
 * @return string               An error message.
 */
function get_error_message( $error_code )
{
	error_log('functions.php - function get_error_message');
	$options = get_option('clu_errors_messages');
	switch ( $error_code ) {
		
		case 'empty_username':
			//return get_field('empty_username','options'); // אין שם משתמש
			return $options['empty_username']; // אין שם משתמש
 
		case 'empty_password':
			return $options['empty_password']; // אין סיסמה
 
		case 'invalid_username': // שם משתמש לא נכון
			return $options['invalid_username'];
 
		case 'incorrect_password': // סיסמה לא נכונה
			return $options['incorrect_password'];
			
			
		// Registration errors
		case 'email':
			return $options['incorrect_email_address'];
			
		case 'user_name':
			return $options['user_name'];
			
		case 'user_name_exists':
			return $options['user_name_exists'];
		 
		case 'email_exists':
			return $options['email_exists'];
			
		case 'first_name':
			return $options['first_name_empty'];	
			
		case 'last_name':
			return $options['last_name_empty'];
			
		case 'reg_password':
			return $options['reg_password'];
		 
		case 'closed':
			return $options['closed_registration'];
 
 
		 // Lost password
	 
		case 'empty_username':
			return $options['empty_username'];
		 
		case 'invalid_email':
			return $options['invalid_email'];
			
		
		case 'invalidcombo':
			return $options['lost_password_invalid_email'];
 
 
		 // Reset password
	 
		case 'expiredkey':
			return $options['expiredkey'];
		
		case 'invalidkey':
			return $options['invalidkey'];
		 
		case 'password_reset_mismatch':
			return $options['password_reset_mismatch'];
			 
		case 'password_reset_empty':
			return $options['password_reset_empty'];
 
 
		default:
			break;
	}
	 
	return $options['unknown_error']; // תקלה לא ידועה
}



add_action('user_register', 'notify_client_on_new_user_with_custom_role', 10, 1);
function notify_client_on_new_user_with_custom_role($user_id) 
{
	error_log('functions.php - function notify_client_on_new_user_with_custom_role($user_id) {');	
    // Get the user data
    $user_info = get_userdata($user_id);

    // Check if the user has the desired role
    if (in_array(clu_get_role_slug('pending_role'), $user_info->roles)) {
		
		send_email_notify_client_on_new_user_with_custom_role($user_id );

    }
}


function send_email_notify_client_on_new_user_with_custom_role($user_id )
{
	error_log(' functions.php - function send_email_notify_client_on_new_user_with_custom_role' );
	$site_url = get_bloginfo('wpurl');
	$mail_host = clu_get_mail_host();
	$headers = clu_get_mail_headers( $mail_host ? 'info@' . $mail_host : '', $site_url );
	$user = get_userdata( $user_id );
	//$subject = sprintf( ' #001-%s Website: %s sign up for a free trial', time(), $user->display_name);
	// $subject = sprintf( 'Website: %s register', $user->display_name);

	// $message = "Hey, <br><br>" ; 
	// $message .= sprintf(__('%s register <br><br>'), $user->display_name);
	// $message .= __('To approve register please follow the link bellow and switch user roll from "Awaiting Approval" to "Approved User": ');
	// $message .= '<a href="'.$host.'/wp-admin/user-edit.php?user_id='.$user_id.'#role">Edit User</a><br><br>';	
	$subject  = sprintf(clu_get_message('notify_client_subject'), $user->display_name);
	$edit_url = clu_normalize_site_url( add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) ) ) . '#role';
	$message  = clu_format_message_template(
		clu_get_message('notify_client_body'),
		[
			'%1$s'            => $user->display_name,
			'%2$s'            => $edit_url,
			'{display_name}'  => $user->display_name,
			'{edit_user_url}' => $edit_url,
			'{edit_user_link}' => clu_message_link( $edit_url, 'Edit User' ),
		]
	);

	//$message .= '[for debug: send_admin_trial_att_email() at inc/init_mailers.php]';	

	$options = get_option('clu_mail');
	$raw_to  = $options['admin_mail_addresss'] ?? '';

	// Support multiple comma-separated addresses; trim spaces around each
	$to = implode( ',', array_filter( array_map( 'trim', explode( ',', $raw_to ) ) ) );

	wp_mail( $to, $subject, $message, $headers );
}


add_action('set_user_role', 'notify_user_on_role_change_with_password_reset', 10, 3);
function notify_user_on_role_change_with_password_reset($user_id, $new_role, $old_roles) 
{
	error_log('functions.php - function notify_user_on_role_change_with_password_reset');	
    // Check if the role is being changed from "pending_user" to "approved_user"
    if (in_array(clu_get_role_slug('pending_role'), $old_roles) && ($new_role === clu_get_role_slug('approved_role'))) {
       
		send_email_notify_user_to_set_password($user_id);

    }
}


function send_email_notify_user_to_set_password($user_id )
{
	error_log(' functions.php - function send_email_notify_user_to_set_password' );
	$site_url = get_bloginfo('wpurl');
	$mail_host = clu_get_mail_host();
	$headers = clu_get_mail_headers( $mail_host ? 'info@' . $mail_host : '', $site_url );
	$user = get_userdata( $user_id );
	//$subject = sprintf( ' #001-%s Website: %s sign up for a free trial', time(), $user->display_name);
	$reset_key = get_password_reset_key($user);
	if (is_wp_error($reset_key)) {
		return; // Exit if there is an issue generating the key
	}

	//$reset_url = network_site_url("set-password/?action=rp&key=$reset_key&login=" . rawurlencode($user_info->user_login), 'login');
	
	$options = get_option('clu_pages_url');
	$options = is_array( $options ) ? $options : [];
	$url_page_password_set = clu_normalize_site_url( $options['url_page_password_set'] ?? '', home_url( '/set-password/' ) );
	$reset_url = add_query_arg(
		[
			'key'   => $reset_key,
			'login' => $user->user_login,
		],
		trailingslashit( $url_page_password_set )
	);

	
	// $subject = sprintf( 'Website: %s register', $user->display_name);

	// $message = "Hey, <br><br>" ; 
	// $message .= sprintf(__('%s your Account Has Been Approved <br><br>'), $user->display_name);
	// $message .= sprintf(
            // "Hi %s,\n\nYour account has been approved!\n\nTo set your password and access your account, please click the link below:\n\n%s\n\nIf you have any questions, please contact us.\n\nThank you.",
            // $user->display_name,
            // $reset_url
        // );
		
	$subject = sprintf(clu_get_message('notify_user_subject'), $user->display_name);
	$message = clu_format_message_template(
		clu_get_message('notify_user_body'),
		[
			'%1$s'             => $user->display_name,
			'%2$s'             => $reset_url,
			'{display_name}'   => $user->display_name,
			'{set_password_url}' => $reset_url,
			'{set_password_link}' => clu_message_link( $reset_url, 'Link' ),
		]
	);
	//$message .= '[for debug: send_admin_trial_att_email() at inc/init_mailers.php]';	

	//$to= 'yaniv.sasson.mail@gmail.com, yaniv@coweb.co.il';
	$to= $user->user_email;
	// $to = get_field('email_notification','options');
	wp_mail($to,  $subject ,$message ,$headers );
}


add_filter('password_reset_expiration', function ($expiration) {
    return DAY_IN_SECONDS * 7; // Extend to 7 days
});

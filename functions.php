<?php 

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
	$host = $_SERVER['HTTP_HOST'];
	$headers = "MIME-Version: 1.0\r\n";
	$headers.= "Content-Type: text/html;charset=UTF-8\r\n";
	$headers.= "From: ".$site_url."  <info@".$host.">\r\n";
	$headers.= "X-Mailer: PHP/" . phpversion();		
	$user = get_userdata( $user_id );
	//$subject = sprintf( ' #001-%s Website: %s sign up for a free trial', time(), $user->display_name);
	// $subject = sprintf( 'Website: %s register', $user->display_name);

	// $message = "Hey, <br><br>" ; 
	// $message .= sprintf(__('%s register <br><br>'), $user->display_name);
	// $message .= __('To approve register please follow the link bellow and switch user roll from "Awaiting Approval" to "Approved User": ');
	// $message .= '<a href="'.$host.'/wp-admin/user-edit.php?user_id='.$user_id.'#role">Edit User</a><br><br>';	
	$subject  = sprintf(clu_get_message('notify_client_subject'), $user->display_name);
	$edit_url = $host . '/wp-admin/user-edit.php?user_id=' . $user_id . '#role';
	$message  = sprintf(clu_get_message('notify_client_body'), $user->display_name, $edit_url);

	//$message .= '[for debug: send_admin_trial_att_email() at inc/init_mailers.php]';	

	//$to= 'yaniv.sasson.mail@gmail.com, yaniv@coweb.co.il';
	$options = get_option('clu_mail'); 
	$to =  $options['admin_mail_addresss'] ;
	// error_log(' $to ' );	
	// error_log($to);	
	// $to = preg_replace('/[[:^print:]]/', '', $to);
	// error_log(print_r($to, true));	
	// Convert string to array
	$array = explode(',', $to);

	// Convert array back to a comma-separated string (in this case, it's unnecessary but still works)
	$admin_email_1 = implode(',', $array);
	// $to = get_field('email_notification','options');
	wp_mail($to,  $subject ,$message ,$headers );
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
	$host = $_SERVER['HTTP_HOST'];
	$headers = "MIME-Version: 1.0\r\n";
	$headers.= "Content-Type: text/html;charset=UTF-8\r\n";
	$headers.= "From: ".$site_url."  <info@".$host.">\r\n";
	$headers.= "X-Mailer: PHP/" . phpversion();		
	$user = get_userdata( $user_id );
	//$subject = sprintf( ' #001-%s Website: %s sign up for a free trial', time(), $user->display_name);
	$reset_key = get_password_reset_key($user);
	if (is_wp_error($reset_key)) {
		return; // Exit if there is an issue generating the key
	}

	//$reset_url = network_site_url("set-password/?action=rp&key=$reset_key&login=" . rawurlencode($user_info->user_login), 'login');
	
	$options = get_option('clu_pages_url');
	$url_page_password_set = trailingslashit($options['url_page_password_set']);
	$reset_url = $url_page_password_set . '?key=' . $reset_key . '&login=' . rawurlencode($user->user_login);

	
	// $subject = sprintf( 'Website: %s register', $user->display_name);

	// $message = "Hey, <br><br>" ; 
	// $message .= sprintf(__('%s your Account Has Been Approved <br><br>'), $user->display_name);
	// $message .= sprintf(
            // "Hi %s,\n\nYour account has been approved!\n\nTo set your password and access your account, please click the link below:\n\n%s\n\nIf you have any questions, please contact us.\n\nThank you.",
            // $user->display_name,
            // $reset_url
        // );
		
	$subject = sprintf(clu_get_message('notify_user_subject'), $user->display_name);
	$message = sprintf(clu_get_message('notify_user_body'), $user->display_name, $reset_url);
	//$message .= '[for debug: send_admin_trial_att_email() at inc/init_mailers.php]';	

	//$to= 'yaniv.sasson.mail@gmail.com, yaniv@coweb.co.il';
	$to= $user->user_email;
	// $to = get_field('email_notification','options');
	wp_mail($to,  $subject ,$message ,$headers );
}


add_filter('password_reset_expiration', function ($expiration) {
    return DAY_IN_SECONDS * 7; // Extend to 7 days
});




?>
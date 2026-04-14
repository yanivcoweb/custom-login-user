<?php 


/**
 * Adding fields to user
 */	
add_action( 'personal_options_update',  'save_extra_user_profile_fields' );
add_action( 'edit_user_profile_update', 'save_extra_user_profile_fields' );
function save_extra_user_profile_fields( $user_id ) {
	error_log('src\user-exta-fields.php - function save_extra_user_profile_fields( $user_id ) {');	
	if ( !current_user_can( 'edit_user', $user_id ) ) { 
		return false; 
	}
	update_user_meta( $user_id, 'company', $_POST['company'] );
	// update_user_meta( $user_id, 'phone', $_POST['phone'] );
	//update_user_meta( $user_id, 'address', $_POST['address'] );
	//update_user_meta( $user_id, 'birthday', $_POST['birthday'] );	
} 

/**
 * Adding fields to user
 */	
add_action( 'show_user_profile', 'extra_user_profile_fields'  );
add_action( 'edit_user_profile',  'extra_user_profile_fields' );
function extra_user_profile_fields( $user ) {
	error_log('src\user-exta-fields.php - function extra_user_profile_fields( $user ) {');		
	echo custom_login_users_get_template_html( 'user_exta_fields', $user);
}




?>

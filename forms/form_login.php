
<div class="login-form-container" style="max-width: 430px; margin: 30px auto;">
    <?php $options = get_option('clu_login_form'); ?>
    <?php $options_clu_pages_url = get_option('clu_pages_url'); ?>
         
    <!-- Show errors if there are any -->
    <?php if ( count( $attributes['errors'] ) > 0 ) : ?>
        <?php foreach ( $attributes['errors'] as $error ) : ?>
            <p class="red">
                <?php echo $error; ?>
            </p>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- The login form -->
	<form name="loginform" id="loginform" class="form-clu form-validation" method="post">
		<input type="hidden" name="action" value="ajax_login_user"> <!-- AJAX action -->
        <input type="hidden" name="login_nonce" value="<?php echo wp_create_nonce('login_form_nonce'); ?>"> <!-- Nonce -->
		
		<div class="form-row login-username">
			<label for="user_login"><?php echo $options['email_label'] ?? 'Email'; ?></label>
			<input type="text" name="log" id="user_login" class="input" value="" size="20" custom-required  />
			<div class="error-message" data-inputid="user_login" data-fieldtype="text" style="display: none;"><?php echo esc_html( $options['required_field_error'] ?? 'Required field' ); ?></div>
		</div>
		<div class="form-row login-password">
			<label for="user_pass"><?php echo $options['password_label'] ?? 'Password'; ?></label>
			<input type="password" name="pwd" id="user_pass" class="input" value="" size="20" custom-required  />
			<div class="error-message" data-inputid="user_pass" data-fieldtype="text" style="display: none;"><?php echo esc_html( $options['required_field_error'] ?? 'Required field' ); ?></div>
		</div>
		<div class="form-row">
			<?php echo esc_html( $options['pass_forgot'] ?? 'Forgot your password?' ); ?>
			<a class="forgot-password" href="<?php echo esc_url( $options_clu_pages_url['url_page_password_lost'] ?? '' ); ?>"><?php echo esc_html( $options['pass_reset_link'] ?? 'Reset Password' ); ?></a>
		</div>
		<div class="login-submit">
			<input type="submit" id="login-submit" class="button-- button-primary--" value="<?php echo $options['login_button'] ?? 'Log In'; ?>" />
		</div>
	</form>
	<div id="login-response"></div>
     
</div>

<div id="password-lost-form" class="widecolumn" style="max-width: 430px; margin: 30px auto;">
    <?php $options = get_option('clu_password_lost_form'); ?>
    
    <?php if ( count( $attributes['errors'] ) > 0 ) : ?>
        <?php foreach ( $attributes['errors'] as $error ) : ?>
            <p>
                <?php echo $error; ?>
            </p>
        <?php endforeach; ?>
    <?php endif; ?>
  
    <form id="passwordlostform" class="form-clu form-validation" method="post">
         <input type="hidden" name="action" value="ajax_password_lost_user"> <!-- AJAX action -->
        <input type="hidden" name="password_lost_nonce" value="<?php echo wp_create_nonce('password_lost_form_nonce'); ?>"> <!-- Nonce -->
		<div class="form-row">
            <label for="user_login">
                <?php echo $options['mail_text'] ?? 'Email Address'; ?>
            </label>         
            <input type="text" name="user_login" id="user_login" custom-required >
			<div class="error-message " data-inputid="user_login"data-fieldtype="text" style="display: none;">שדה חובה</div>
        </div>
 
        <p class="password-lost-submit">
            <input type="submit" class="password-lost-button-- button--"
                   value="<?php echo $options['renew_password'] ?? 'Reset Password'; ?>"/>
        </p>
    </form>
	<div id="password-lost-response"></div>
</div>

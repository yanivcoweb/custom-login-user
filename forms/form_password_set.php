<div id="password-set-form" class="widecolumn" style="max-width: 430px; margin: 30px auto;">
    <?php $options = get_option('clu_password_reset_form'); ?>

    <?php if (!empty($attributes['errors'])) : ?>
        <?php foreach ($attributes['errors'] as $error) : ?>
            <p class="error-message"><?php echo esc_html($error); ?></p>
        <?php endforeach; ?>
    <?php endif; ?>


	<form id="setpassform" class="form-clu form-validation" method="post" autocomplete="off">
	   <input type="hidden" name="action" value="ajax_password_set"> <!-- AJAX action -->
		<input type="hidden" name="set_password_nonce" value="<?php echo wp_create_nonce('set_password_form_nonce'); ?>"> <!-- Nonce -->
		
		<input type="hidden" name="rp_login" value="<?php echo esc_attr($attributes['login']); ?>" autocomplete="off" />
		<input type="hidden" name="rp_key" value="<?php echo esc_attr($attributes['key']); ?>" />

		<div class="form-row">
			<label for="new_password"><?php echo $options['new_password'] ?? 'New Password'; ?></label>
			<input type="password" name="new_password" id="new_password" class="input" custom-required autocomplete="off" />
			<div class="error-message" data-inputid="new_password" data-fieldtype="password" style="display: none;">Required field</div>

			<?php
			$rules = clu_get_password_rules();
			$requirements = [];
			$requirements[] = clu_get_message('password_min_length');
			if ( $rules['require_uppercase'] ) $requirements[] = clu_get_message('password_uppercase');
			if ( $rules['require_lowercase'] ) $requirements[] = clu_get_message('password_lowercase');
			if ( $rules['require_number'] )    $requirements[] = clu_get_message('password_number');
			if ( $rules['require_special'] )   $requirements[] = clu_get_message('password_special_char');
			?>
			<ul class="clu-password-requirements">
				<?php foreach ( $requirements as $req ) : ?>
					<li><?php echo esc_html( $req ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>

		<p class="resetpass-submit">
			<input type="submit" id="password-set-button" class="button-- register-button--" value="<?php echo $options['renew_password'] ?? 'Set Password'; ?>" />
		</p>
	</form>
	<div id="password-set-response"></div>

</div>

<div id="register-form" class="widecolumn" style="max-width: 430px; margin: 30px auto;">
    <?php $options = get_option('clu_register_form'); ?>
    
    <?php if ( $attributes['show_title'] ) : ?>
        <h3><?php echo $options['register_title'] ?? 'Register Now'; ?></h3>
    <?php endif; ?>
	
    <?php if ( count( $attributes['errors'] ) > 0 ) : ?>
        <?php foreach ( $attributes['errors'] as $error ) : ?>
            <p class="red">
                <?php echo $error; ?>
            </p>
        <?php endforeach; ?>
    <?php endif; ?>
	
    <?php /*
	<form id="signupform" class="form-clu form-validation" name="register" action="<?php echo wp_registration_url(); ?>" method="post">
	*/ ?>

	<form id="signupform" class="form-clu form-validation" method="post" novalidate >
        <input type="hidden" name="action" value="ajax_register_user"> <!-- AJAX action -->
        <input type="hidden" name="register_nonce" value="<?php echo wp_create_nonce('register_form_nonce'); ?>"> <!-- Nonce -->
		
		
		<?php
		$required_error = esc_html( $options['required_field_error'] ?? 'Required field' );
		$email_invalid  = esc_html( $options['email_invalid_error']  ?? 'Invalid email address' );
		?>

		<div class="form-row">
			<label for="first_name"><?php echo $options['first_name'] ?? 'First Name'; ?></label>
            <input type="text" name="first_name" id="first_name" custom-required aria-required="true" aria-describedby="first_name_error" placeholder="">
			<div class="error-message" data-inputid="first_name" data-fieldtype="text" style="display: none;"><?php echo $required_error; ?></div>
        </div>

        <div class="form-row">
			<label for="last_name"><?php echo $options['last_name'] ?? 'Last Name'; ?></label>
            <input type="text" name="last_name" id="last_name" custom-required aria-required="true" aria-describedby="last_name_error" placeholder="">
			<div class="error-message" data-inputid="last_name" data-fieldtype="text" style="display: none;"><?php echo $required_error; ?></div>
        </div>

        <div class="form-row">
			<label for="email"><?php echo $options['email'] ?? 'Email Address'; ?></label>
            <input type="email" name="email" id="email" custom-required aria-required="true" aria-describedby="email_error" placeholder="">
			<div class="error-message" data-inputid="email" data-fieldtype="email">
				<span class="email-empty"><?php echo $required_error; ?></span>
				<span class="email-incurrected"><?php echo $email_invalid; ?></span>
			</div>
        </div>

        <div class="form-row">
			<label for="company"><?php echo $options['company'] ?? 'Company Name'; ?></label>
            <input type="text" name="company" id="company" custom-required aria-required="true" aria-describedby="company_error" placeholder="">
			<div class="error-message" data-inputid="company" data-fieldtype="text" style="display: none;"><?php echo $required_error; ?></div>
        </div>

		<?php /*
		<div class="form-row">
            <label for="agree_terms">
                <input type="checkbox" name="agree_terms" id="agree_terms" custom-required aria-required="true">
                I agree to the <a href="#">privacy policy</a> and <a href="#">terms of use</a>.
            </label>
			<div class="error-message" data-inputid="agree_terms" data-fieldtype="checkbox" style="display: none;">You must agree to the terms and conditions</div>
        </div>
		*/ ?>
		

        <p class="signup-submit">
            <input type="submit" class="register-button" value="<?php echo $options['register_btn_text'] ?? 'Sumbit'; ?>"/>
        </p>
		
		<div id="signupform-register-response"></div>
		
    </form>
</div>

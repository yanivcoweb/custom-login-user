<?php
defined( 'ABSPATH' ) || exit;

$text = function ( $key ) {
    return clu_form_text( 'clu_my_account_form', $key );
};
$required_error = $text( 'required_field_error' );
?>
<div id="my-account" class="widecolumn clu-form-wrap clu-form-wrap--wide">

    <h3><?php echo esc_html( $text( 'title' ) ); ?></h3>

    <h4><?php echo esc_html( $text( 'profile_title' ) ); ?></h4>
    <form id="my-account-profile-form" class="form-clu form-validation" method="post" novalidate>
        <input type="hidden" name="action" value="ajax_update_profile">
        <input type="hidden" name="my_account_profile_nonce" value="<?php echo esc_attr( wp_create_nonce( 'my_account_profile_nonce' ) ); ?>">

        <div class="form-row">
            <label for="first_name"><?php echo esc_html( $text( 'first_name' ) ); ?></label>
            <input type="text" name="first_name" id="first_name" custom-required autocomplete="given-name" aria-required="true" aria-describedby="first_name_error" value="<?php echo esc_attr( $attributes['first_name'] ); ?>">
            <div class="error-message" id="first_name_error" data-inputid="first_name"><?php echo esc_html( $required_error ); ?></div>
        </div>

        <div class="form-row">
            <label for="last_name"><?php echo esc_html( $text( 'last_name' ) ); ?></label>
            <input type="text" name="last_name" id="last_name" custom-required autocomplete="family-name" aria-required="true" aria-describedby="last_name_error" value="<?php echo esc_attr( $attributes['last_name'] ); ?>">
            <div class="error-message" id="last_name_error" data-inputid="last_name"><?php echo esc_html( $required_error ); ?></div>
        </div>

        <div class="form-row">
            <label for="email"><?php echo esc_html( $text( 'email' ) ); ?></label>
            <input type="email" name="email" id="email" custom-required autocomplete="email" aria-required="true" aria-describedby="email_error" value="<?php echo esc_attr( $attributes['email'] ); ?>">
            <div class="error-message" id="email_error" data-inputid="email">
                <span class="email-empty"><?php echo esc_html( $required_error ); ?></span>
                <span class="email-invalid"><?php echo esc_html( $text( 'email_invalid_error' ) ); ?></span>
            </div>
        </div>

        <div class="form-row">
            <label for="company"><?php echo esc_html( $text( 'company' ) ); ?></label>
            <input type="text" name="company" id="company" autocomplete="organization" value="<?php echo esc_attr( $attributes['company'] ); ?>">
        </div>

        <p class="signup-submit">
            <input type="submit" class="register-button" value="<?php echo esc_attr( $text( 'profile_button' ) ); ?>">
        </p>

        <div id="my-account-profile-response" class="clu-response" role="status"></div>
    </form>

    <hr class="clu-separator">

    <h4><?php echo esc_html( $text( 'password_title' ) ); ?></h4>
    <form id="my-account-password-form" class="form-clu form-validation" method="post" novalidate>
        <input type="hidden" name="action" value="ajax_update_password">
        <input type="hidden" name="my_account_password_nonce" value="<?php echo esc_attr( wp_create_nonce( 'my_account_password_nonce' ) ); ?>">

        <div class="form-row">
            <label for="current_password"><?php echo esc_html( $text( 'current_password' ) ); ?></label>
            <input type="password" name="current_password" id="current_password" custom-required autocomplete="current-password" aria-required="true" aria-describedby="current_password_error">
            <div class="error-message" id="current_password_error" data-inputid="current_password"><?php echo esc_html( $required_error ); ?></div>
        </div>

        <div class="form-row">
            <label for="new_password"><?php echo esc_html( $text( 'new_password' ) ); ?></label>
            <input type="password" name="new_password" id="new_password" custom-required data-strength autocomplete="new-password" aria-required="true" aria-describedby="new_password_error">
            <div class="error-message" id="new_password_error" data-inputid="new_password"><?php echo esc_html( $required_error ); ?></div>
        </div>

        <div class="form-row">
            <label for="confirm_password"><?php echo esc_html( $text( 'confirm_password' ) ); ?></label>
            <input type="password" name="confirm_password" id="confirm_password" custom-required autocomplete="new-password" aria-required="true" aria-describedby="confirm_password_error">
            <div class="error-message" id="confirm_password_error" data-inputid="confirm_password"><?php echo esc_html( $required_error ); ?></div>
        </div>

        <p class="signup-submit">
            <input type="submit" class="register-button" value="<?php echo esc_attr( $text( 'password_button' ) ); ?>">
        </p>

        <div id="my-account-password-response" class="clu-response" role="status"></div>
    </form>

</div>

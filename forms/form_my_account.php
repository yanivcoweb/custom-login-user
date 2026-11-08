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

        <?php
        // These have labels of their own on the My Account Form tab; the others use the
        // label from the Register Fields tab.
        $labels = [
            'first_name' => $text( 'first_name' ),
            'last_name'  => $text( 'last_name' ),
            'email'      => $text( 'email' ),
            'company'    => $text( 'company' ),
        ];

        echo clu_render_form_fields( $attributes['fields'], $attributes['values'], $required_error, $text( 'email_invalid_error' ), $labels ); // phpcs:ignore WordPress.Security.EscapeOutput
        ?>

        <?php // Shown by validation.js once the email address is changed. ?>
        <div class="form-row clu-email-password-row" hidden>
            <label for="email_current_password"><?php echo esc_html( $text( 'email_password' ) ); ?></label>
            <input type="password" name="email_current_password" id="email_current_password" autocomplete="current-password" aria-describedby="email_current_password_error">
            <div class="error-message" id="email_current_password_error" data-inputid="email_current_password"><?php echo esc_html( $required_error ); ?></div>
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

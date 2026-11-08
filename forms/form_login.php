<?php
defined( 'ABSPATH' ) || exit;

$text = function ( $key ) {
    return clu_form_text( 'clu_login_form', $key );
};
?>
<div class="login-form-container clu-form-wrap">

    <?php if ( $attributes['show_title'] ) : ?>
        <h3><?php echo esc_html( $text( 'log_in_title' ) ); ?></h3>
    <?php endif; ?>

    <form name="loginform" id="loginform" class="form-clu form-validation" method="post" novalidate>
        <input type="hidden" name="action" value="ajax_login_user">
        <input type="hidden" name="login_nonce" value="<?php echo esc_attr( wp_create_nonce( 'login_form_nonce' ) ); ?>">
        <?php if ( $attributes['redirect'] !== '' ) : ?>
            <input type="hidden" name="redirect_to" value="<?php echo esc_url( $attributes['redirect'] ); ?>">
        <?php endif; ?>

        <div class="form-row login-username">
            <label for="user_login"><?php echo esc_html( $text( 'email_label' ) ); ?></label>
            <input type="text" name="log" id="user_login" class="input" value="" size="20" custom-required autocomplete="username" aria-required="true" aria-describedby="user_login_error">
            <div class="error-message" id="user_login_error" data-inputid="user_login"><?php echo esc_html( $text( 'required_field_error' ) ); ?></div>
        </div>

        <div class="form-row login-password">
            <label for="user_pass"><?php echo esc_html( $text( 'password_label' ) ); ?></label>
            <input type="password" name="pwd" id="user_pass" class="input" value="" size="20" custom-required autocomplete="current-password" aria-required="true" aria-describedby="user_pass_error">
            <div class="error-message" id="user_pass_error" data-inputid="user_pass"><?php echo esc_html( $text( 'required_field_error' ) ); ?></div>
        </div>

        <div class="form-row login-remember">
            <label for="rememberme">
                <input type="checkbox" name="rememberme" id="rememberme" value="1" checked>
                <?php echo esc_html( $text( 'remember_me' ) ); ?>
            </label>
        </div>

        <div class="form-row">
            <?php echo esc_html( $text( 'pass_forgot' ) ); ?>
            <a class="forgot-password" href="<?php echo esc_url( clu_get_page_url( 'url_page_password_lost', wp_lostpassword_url() ) ); ?>"><?php echo esc_html( $text( 'pass_reset_link' ) ); ?></a>
        </div>

        <?php echo clu_captcha_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>

        <div class="login-submit">
            <input type="submit" id="login-submit" class="button-- button-primary--" value="<?php echo esc_attr( $text( 'login_button' ) ); ?>">
        </div>
    </form>

    <div id="login-response" class="clu-response<?php echo $attributes['error'] !== '' ? ' clu-response--error' : ''; ?>" role="status"><?php echo $attributes['error']; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
</div>

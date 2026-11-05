<?php
defined( 'ABSPATH' ) || exit;

$text = function ( $key ) {
    return clu_form_text( 'clu_password_lost_form', $key );
};
?>
<div id="password-lost-form" class="widecolumn clu-form-wrap">

    <?php if ( $attributes['show_title'] ) : ?>
        <h3><?php echo esc_html( $text( 'fp_title' ) ); ?></h3>
        <p><?php echo esc_html( $text( 'enter_mail' ) ); ?></p>
    <?php endif; ?>

    <form id="passwordlostform" class="form-clu form-validation" method="post" novalidate>
        <input type="hidden" name="action" value="ajax_password_lost">
        <input type="hidden" name="password_lost_nonce" value="<?php echo esc_attr( wp_create_nonce( 'password_lost_form_nonce' ) ); ?>">

        <div class="form-row">
            <label for="user_login"><?php echo esc_html( $text( 'mail_text' ) ); ?></label>
            <input type="text" name="user_login" id="user_login" custom-required autocomplete="username" aria-required="true" aria-describedby="user_login_error">
            <div class="error-message" id="user_login_error" data-inputid="user_login"><?php echo esc_html( $text( 'required_field_error' ) ); ?></div>
        </div>

        <p class="password-lost-submit">
            <input type="submit" class="password-lost-button-- button--" value="<?php echo esc_attr( $text( 'renew_password' ) ); ?>">
        </p>
    </form>

    <div id="password-lost-response" class="clu-response" role="status"></div>
</div>

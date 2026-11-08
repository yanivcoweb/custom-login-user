<?php
defined( 'ABSPATH' ) || exit;

$text = function ( $key ) {
    return clu_form_text( 'clu_register_form', $key );
};
?>
<div id="register-form" class="widecolumn clu-form-wrap">

    <?php if ( $attributes['show_title'] ) : ?>
        <h3><?php echo esc_html( $text( 'register_title' ) ); ?></h3>
    <?php endif; ?>

    <form id="signupform" class="form-clu form-validation" method="post" novalidate>
        <input type="hidden" name="action" value="ajax_register_user">
        <input type="hidden" name="register_nonce" value="<?php echo esc_attr( wp_create_nonce( 'register_form_nonce' ) ); ?>">

        <?php // Trap for bots: hidden from people, and registration stops if it is filled. ?>
        <div class="clu-hp" aria-hidden="true">
            <label for="clu_website">Website</label>
            <input type="text" name="clu_website" id="clu_website" value="" tabindex="-1" autocomplete="off">
        </div>

        <?php echo clu_render_form_fields( clu_get_register_form_fields(), [], $text( 'required_field_error' ), $text( 'email_invalid_error' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

        <?php echo clu_captcha_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>

        <p class="signup-submit">
            <input type="submit" class="register-button" value="<?php echo esc_attr( $text( 'register_btn_text' ) ); ?>">
        </p>

        <div id="signupform-register-response" class="clu-response" role="status"></div>
    </form>
</div>

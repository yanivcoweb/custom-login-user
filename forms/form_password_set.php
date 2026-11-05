<?php
defined( 'ABSPATH' ) || exit;

$text = function ( $key ) {
    return clu_form_text( 'clu_password_reset_form', $key );
};
?>
<div id="password-set-form" class="widecolumn clu-form-wrap">

    <?php if ( $attributes['error'] !== '' ) : ?>

        <div class="error-message"><?php echo wp_kses_post( $attributes['error'] ); ?></div>
        <p>
            <a href="<?php echo esc_url( clu_get_page_url( 'url_page_password_lost', wp_lostpassword_url() ) ); ?>"><?php echo esc_html( clu_form_text( 'clu_login_form', 'pass_reset_link' ) ); ?></a>
        </p>

    <?php else : ?>

        <?php if ( $attributes['show_title'] ) : ?>
            <h3><?php echo esc_html( $text( 'choose_password' ) ); ?></h3>
        <?php endif; ?>

        <form id="setpassform" class="form-clu form-validation" method="post" autocomplete="off" novalidate>
            <input type="hidden" name="action" value="ajax_password_set">
            <input type="hidden" name="set_password_nonce" value="<?php echo esc_attr( wp_create_nonce( 'set_password_form_nonce' ) ); ?>">
            <input type="hidden" name="rp_login" value="<?php echo esc_attr( $attributes['login'] ); ?>">
            <input type="hidden" name="rp_key" value="<?php echo esc_attr( $attributes['key'] ); ?>">

            <div class="form-row">
                <label for="new_password"><?php echo esc_html( $text( 'new_password' ) ); ?></label>
                <input type="password" name="new_password" id="new_password" class="input" custom-required data-strength autocomplete="new-password" aria-required="true" aria-describedby="new_password_error">
                <div class="error-message" id="new_password_error" data-inputid="new_password"><?php echo esc_html( $text( 'required_field_error' ) ); ?></div>

                <?php
                $rules        = clu_get_password_rules();
                $requirements = [ clu_get_message( 'password_min_length' ) ];

                if ( $rules['require_uppercase'] ) {
                    $requirements[] = clu_get_message( 'password_uppercase' );
                }
                if ( $rules['require_lowercase'] ) {
                    $requirements[] = clu_get_message( 'password_lowercase' );
                }
                if ( $rules['require_number'] ) {
                    $requirements[] = clu_get_message( 'password_number' );
                }
                if ( $rules['require_special'] ) {
                    $requirements[] = clu_get_message( 'password_special_char' );
                }
                ?>
                <ul class="clu-password-requirements">
                    <?php foreach ( $requirements as $requirement ) : ?>
                        <li><?php echo esc_html( $requirement ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="form-row">
                <label for="confirm_password"><?php echo esc_html( $text( 'verify_password' ) ); ?></label>
                <input type="password" name="confirm_password" id="confirm_password" class="input" custom-required autocomplete="new-password" aria-required="true" aria-describedby="confirm_password_error">
                <div class="error-message" id="confirm_password_error" data-inputid="confirm_password"><?php echo esc_html( $text( 'required_field_error' ) ); ?></div>
            </div>

            <p class="resetpass-submit">
                <input type="submit" id="password-set-button" class="button-- register-button--" value="<?php echo esc_attr( $text( 'renew_password' ) ); ?>">
            </p>
        </form>

        <div id="password-set-response" class="clu-response" role="status"></div>

    <?php endif; ?>
</div>

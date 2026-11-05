<?php
defined( 'ABSPATH' ) || exit;

$text = function ( $key ) {
    return clu_form_text( 'clu_register_form', $key );
};
$required_error = $text( 'required_field_error' );
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

        <div class="form-row">
            <label for="first_name"><?php echo esc_html( $text( 'first_name' ) ); ?></label>
            <input type="text" name="first_name" id="first_name" custom-required autocomplete="given-name" aria-required="true" aria-describedby="first_name_error" placeholder="<?php echo esc_attr( clu_register_placeholder( 'Enter_your_first_name_text', 'First Name' ) ); ?>">
            <div class="error-message" id="first_name_error" data-inputid="first_name"><?php echo esc_html( $required_error ); ?></div>
        </div>

        <div class="form-row">
            <label for="last_name"><?php echo esc_html( $text( 'last_name' ) ); ?></label>
            <input type="text" name="last_name" id="last_name" custom-required autocomplete="family-name" aria-required="true" aria-describedby="last_name_error" placeholder="<?php echo esc_attr( clu_register_placeholder( 'Enter_your_last_name_text', 'Last Name' ) ); ?>">
            <div class="error-message" id="last_name_error" data-inputid="last_name"><?php echo esc_html( $required_error ); ?></div>
        </div>

        <div class="form-row">
            <label for="email"><?php echo esc_html( $text( 'email' ) ); ?></label>
            <input type="email" name="email" id="email" custom-required autocomplete="email" aria-required="true" aria-describedby="email_error" placeholder="<?php echo esc_attr( clu_register_placeholder( 'Enter_your_email_address_text', 'Email' ) ); ?>">
            <div class="error-message" id="email_error" data-inputid="email">
                <span class="email-empty"><?php echo esc_html( $required_error ); ?></span>
                <span class="email-invalid"><?php echo esc_html( $text( 'email_invalid_error' ) ); ?></span>
            </div>
        </div>

        <div class="form-row">
            <label for="company"><?php echo esc_html( $text( 'company' ) ); ?></label>
            <input type="text" name="company" id="company" custom-required autocomplete="organization" aria-required="true" aria-describedby="company_error" placeholder="<?php echo esc_attr( clu_register_placeholder( 'Enter_your_company_name_text', 'Company Name' ) ); ?>">
            <div class="error-message" id="company_error" data-inputid="company"><?php echo esc_html( $required_error ); ?></div>
        </div>

        <div class="form-row">
            <label for="customer_type"><?php echo esc_html( $text( 'customer_type' ) ); ?></label>
            <select name="customer_type" id="customer_type" custom-required aria-required="true" aria-describedby="customer_type_error">
                <option value=""><?php echo esc_html( $text( 'customer_type' ) ); ?></option>
                <?php foreach ( clu_get_customer_type_options() as $choice ) : ?>
                    <option value="<?php echo esc_attr( $choice ); ?>"><?php echo esc_html( $choice ); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="error-message" id="customer_type_error" data-inputid="customer_type"><?php echo esc_html( $required_error ); ?></div>
        </div>

        <div class="form-row">
            <label for="clu_region"><?php echo esc_html( $text( 'region' ) ); ?></label>
            <select name="region" id="clu_region" custom-required aria-required="true" aria-describedby="clu_region_error">
                <option value=""><?php echo esc_html( $text( 'region' ) ); ?></option>
                <option value="Africa">Africa</option>
                <option value="APAC">APAC</option>
                <option value="China">China</option>
                <option value="Europe">Europe</option>
                <option value="India">India</option>
                <option value="LATAM">LATAM</option>
                <option value="NA &amp; Canada">NA &amp; Canada</option>
                <option value="Middle East">Middle East</option>
            </select>
            <div class="error-message" id="clu_region_error" data-inputid="clu_region"><?php echo esc_html( $required_error ); ?></div>
        </div>

        <div class="form-row">
            <label for="clu_country"><?php echo esc_html( $text( 'country' ) ); ?></label>
            <select name="country" id="clu_country" custom-required aria-required="true" aria-describedby="clu_country_error">
                <option value=""><?php echo esc_html( $text( 'country' ) ); ?></option>
            </select>
            <div class="error-message" id="clu_country_error" data-inputid="clu_country"><?php echo esc_html( $required_error ); ?></div>
        </div>

        <p class="signup-submit">
            <input type="submit" class="register-button" value="<?php echo esc_attr( $text( 'register_btn_text' ) ); ?>">
        </p>

        <div id="signupform-register-response" class="clu-response" role="status"></div>
    </form>
</div>

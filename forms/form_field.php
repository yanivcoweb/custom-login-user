<?php
defined( 'ABSPATH' ) || exit;

$field       = $attributes['field'];
$value       = $attributes['value'];
$type        = $field['type'];
$is_required = clu_field_is_required( $field );
$input_id    = in_array( $type, [ 'region', 'country' ], true ) ? 'clu_' . $field['key'] : $field['key'];
$error_id    = $input_id . '_error';
$placeholder = clu_field_placeholder( $field );

$autocomplete = [
    'first_name' => 'given-name',
    'last_name'  => 'family-name',
    'email'      => 'email',
    'company'    => 'organization',
][ $field['key'] ] ?? ( $type === 'tel' ? 'tel' : ( $type === 'url' ? 'url' : '' ) );

$input_attributes = sprintf( 'name="%s" id="%s"', esc_attr( $field['key'] ), esc_attr( $input_id ) );

if ( $is_required ) {
    $input_attributes .= sprintf( ' custom-required aria-required="true" aria-describedby="%s"', esc_attr( $error_id ) );
}

if ( $autocomplete !== '' ) {
    $input_attributes .= sprintf( ' autocomplete="%s"', esc_attr( $autocomplete ) );
}
?>
<div class="form-row form-row--<?php echo esc_attr( $type ); ?>">
    <?php if ( $type === 'checkbox' ) : ?>
        <input type="checkbox" value="1" <?php echo $input_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php checked( $value, '1' ); ?>>
        <label for="<?php echo esc_attr( $input_id ); ?>"><?php echo wp_kses( $attributes['label'], clu_checkbox_label_tags() ); ?></label>
    <?php else : ?>
        <label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $attributes['label'] ); ?></label>

        <?php if ( $type === 'textarea' ) : ?>
            <textarea rows="4" <?php echo $input_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?> placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
        <?php elseif ( in_array( $type, [ 'select', 'region', 'country' ], true ) ) :
            $choices = clu_field_choices( $field, $attributes['region'] );

            // A value the user already has stays on the form, even if it left the list since.
            if ( $value !== '' && ! in_array( $value, $choices, true ) ) {
                $choices[] = $value;
            }
            ?>
            <select <?php echo $input_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
                <option value=""><?php echo esc_html( $placeholder !== '' ? $placeholder : wp_strip_all_tags( $attributes['label'] ) ); ?></option>
                <?php foreach ( $choices as $choice ) : ?>
                    <option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $value, $choice ); ?>><?php echo esc_html( $choice ); ?></option>
                <?php endforeach; ?>
            </select>
        <?php else : ?>
            <input type="<?php echo esc_attr( $type ); ?>" <?php echo $input_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?> value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
        <?php endif; ?>
    <?php endif; ?>

    <?php if ( $type === 'email' ) : ?>
        <div class="error-message" id="<?php echo esc_attr( $error_id ); ?>" data-inputid="<?php echo esc_attr( $input_id ); ?>">
            <span class="email-empty"><?php echo esc_html( $attributes['required_error'] ); ?></span>
            <span class="email-invalid"><?php echo esc_html( $attributes['email_invalid_error'] ); ?></span>
        </div>
    <?php elseif ( $is_required ) : ?>
        <div class="error-message" id="<?php echo esc_attr( $error_id ); ?>" data-inputid="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $attributes['required_error'] ); ?></div>
    <?php endif; ?>
</div>

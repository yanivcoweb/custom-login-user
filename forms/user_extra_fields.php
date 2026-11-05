<?php
defined( 'ABSPATH' ) || exit;

$user   = $attributes;
$fields = clu_get_admin_profile_fields( $user );

if ( ! $fields ) {
    return;
}
?>
<h3><?php esc_html_e( 'Additional user information', 'custom-login-users' ); ?></h3>

<table class="form-table">
    <?php foreach ( $fields as $field ) :
        $name  = $field['key'];
        $value = clu_get_user_field_value( $user, $field );
        ?>
        <tr>
            <th><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( wp_strip_all_tags( $field['label'] ) ); ?></label></th>
            <td>
                <?php if ( $field['type'] === 'textarea' ) : ?>
                    <textarea name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" rows="4" class="regular-text"><?php echo esc_textarea( $value ); ?></textarea>
                <?php elseif ( $field['type'] === 'checkbox' ) : ?>
                    <?php // The hidden field tells an unticked box from a form that does not hold this field. ?>
                    <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="">
                    <input type="checkbox" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $value, '1' ); ?>>
                <?php else : ?>
                    <input type="text" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

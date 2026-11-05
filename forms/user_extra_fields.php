<?php
defined( 'ABSPATH' ) || exit;

$user   = $attributes;
$fields = [
    'company'       => __( 'Company', 'custom-login-users' ),
    'customer_type' => __( 'Customer Type', 'custom-login-users' ),
    'region'        => __( 'Region', 'custom-login-users' ),
    'country'       => __( 'Country', 'custom-login-users' ),
];
?>
<h3><?php esc_html_e( 'Additional user information', 'custom-login-users' ); ?></h3>

<table class="form-table">
    <?php foreach ( $fields as $name => $label ) : ?>
        <tr>
            <th><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <input type="text" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( get_user_meta( $user->ID, $name, true ) ); ?>" class="regular-text" />
            </td>
        </tr>
    <?php endforeach; ?>
</table>

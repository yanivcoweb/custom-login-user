<?php	$user=  $attributes; ?>
<h3>Additional user information</h3>

<table class="form-table">
<tr>
	<th><label for="company">Company</label></th>
	<td>
		<input type="text" name="company" id="company" value="<?php echo esc_attr( get_the_author_meta( 'company', $user->ID ) ); ?>" class="regular-text" /><br />
	</td>
</tr>
<tr>
	<th><label for="customer_type">Customer Type</label></th>
	<td>
		<input type="text" name="customer_type" id="customer_type" value="<?php echo esc_attr( get_the_author_meta( 'customer_type', $user->ID ) ); ?>" class="regular-text" /><br />
	</td>
</tr>
<tr>
	<th><label for="region">Region</label></th>
	<td>
		<input type="text" name="region" id="region" value="<?php echo esc_attr( get_the_author_meta( 'region', $user->ID ) ); ?>" class="regular-text" /><br />
	</td>
</tr>
<tr>
	<th><label for="country">Country</label></th>
	<td>
		<input type="text" name="country" id="country" value="<?php echo esc_attr( get_the_author_meta( 'country', $user->ID ) ); ?>" class="regular-text" /><br />
	</td>
</tr>
<?php 
/*
<tr>
	<th><label for="phone">Phone</label></th>
	<td>
		<input type="text" name="phone" id="phone" value="<?php echo esc_attr( get_the_author_meta( 'phone', $user->ID ) ); ?>" class="regular-text" /><br />
	</td>
</tr>		
<tr>
	<th><label for="address">Address</label></th>
	<td>
		<input type="text" name="address" id="address" value="<?php echo esc_attr( get_the_author_meta( 'address', $user->ID ) ); ?>" class="regular-text" /><br />
	</td>
</tr>
<tr>
	<th><label for="birthday">Birthday</label></th>
	<td>
		<input type="text" name="birthday" id="birthday" value="<?php echo esc_attr( get_the_author_meta( 'birthday', $user->ID ) ); ?>" class="regular-text" /><br />
	</td>
</tr>
*/
?>
</table>
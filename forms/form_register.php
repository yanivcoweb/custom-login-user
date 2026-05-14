<div id="register-form" class="widecolumn" style="max-width: 430px; margin: 30px auto;">
    <?php $options = get_option('clu_register_form'); ?>
    
    <?php if ( $attributes['show_title'] ) : ?>
        <h3><?php echo $options['register_title'] ?? 'Register Now'; ?></h3>
    <?php endif; ?>
	
    <?php if ( count( $attributes['errors'] ) > 0 ) : ?>
        <?php foreach ( $attributes['errors'] as $error ) : ?>
            <p class="red">
                <?php echo $error; ?>
            </p>
        <?php endforeach; ?>
    <?php endif; ?>
	
    <?php /*
	<form id="signupform" class="form-clu form-validation" name="register" action="<?php echo wp_registration_url(); ?>" method="post">
	*/ ?>

	<form id="signupform" class="form-clu form-validation ddddd" method="post" novalidate >
        <input type="hidden" name="action" value="ajax_register_user"> <!-- AJAX action -->
        <input type="hidden" name="register_nonce" value="<?php echo wp_create_nonce('register_form_nonce'); ?>"> <!-- Nonce -->
		
		
		<?php
		$required_error = esc_html( $options['required_field_error'] ?? 'Required field' );
		$email_invalid  = esc_html( $options['email_invalid_error']  ?? 'Invalid email address' );
		?>

		<div class="form-row">
			<label for="first_name"><?php echo $options['first_name'] ?? 'First Name'; ?></label>
            <input type="text" name="first_name" id="first_name" custom-required aria-required="true" aria-describedby="first_name_error" placeholder="">
			<div class="error-message" data-inputid="first_name" data-fieldtype="text" style="display: none;"><?php echo $required_error; ?></div>
        </div>

        <div class="form-row">
			<label for="last_name"><?php echo $options['last_name'] ?? 'Last Name'; ?></label>
            <input type="text" name="last_name" id="last_name" custom-required aria-required="true" aria-describedby="last_name_error" placeholder="">
			<div class="error-message" data-inputid="last_name" data-fieldtype="text" style="display: none;"><?php echo $required_error; ?></div>
        </div>

        <div class="form-row">
			<label for="email"><?php echo $options['email'] ?? 'Email Address'; ?></label>
            <input type="email" name="email" id="email" custom-required aria-required="true" aria-describedby="email_error" placeholder="">
			<div class="error-message" data-inputid="email" data-fieldtype="email">
				<span class="email-empty"><?php echo $required_error; ?></span>
				<span class="email-incurrected"><?php echo $email_invalid; ?></span>
			</div>
        </div>

        <div class="form-row">
			<label for="company"><?php echo $options['company'] ?? 'Company Name'; ?></label>
            <input type="text" name="company" id="company" custom-required aria-required="true" aria-describedby="company_error" placeholder="">
			<div class="error-message" data-inputid="company" data-fieldtype="text" style="display: none;"><?php echo $required_error; ?></div>
        </div>

		<script type="text/javascript">
		var clu_countries = Object();
		clu_countries['Africa'] = "Algeria|Angola|Benin|Botswana|Burkina Faso|Burundi|Cameroon|Cape Verde|Central African Republic|Chad|Comoros|Congo|Côte d'Ivoire|Democratic Republic of the Congo|Djibouti|Egypt|Equatorial Guinea|Eritrea|Ethiopia|Gabon|Gambia|Ghana|Guinea|Guinea-Bissau|Kenya|Lesotho|Liberia|Libyan Arab Jamahiriya|Madagascar|Malawi|Mali|Mauritania|Mauritius|Mayotte|Morocco|Mozambique|Namibia|Niger|Nigeria|Réunion|Rwanda|Sao Tome and Principe|Senegal|Sierra Leone|Somalia|South Africa|Sudan|Swaziland|Togo|Tunisia|Uganda|United Republic of Tanzania|Western Sahara|Zambia|Zimbabwe";
		clu_countries['APAC'] = "Afghanistan|Australia|Armenia|Azerbaijan|Bangladesh|Bhutan|Brunei Darussalam|Cambodia|Democratic People's Republic of Korea|Fiji|French Polynesia|Guam|Georgia|Hong Kong Special Administrative Region|Indonesia|Iran (Islamic Republic of)|Japan|Kazakhstan|Kyrgyzstan|Lao People's Democratic Republic|Malaysia|Maldives|Micronesia (Federated States of)|Mongolia|Myanmar|Nepal|New Caledonia|New Zealand|Pakistan|Papua New Guinea|Philippines|Republic of Korea|Samoa|Singapore|Solomon Islands|Sri Lanka|Thailand|Timor-Leste|Tajikistan|Turkmenistan|Uzbekistan|Tonga|Vanuatu|Viet Nam";
		clu_countries['China'] = "China|Macao Special Administrative Region";
		clu_countries['Europe'] = "Albania|Austria|Belarus|Belgium|Bosnia and Herzegovina|Bulgaria|Channel Islands|Croatia|Cyprus|Czech Republic|Denmark|Estonia|Finland|France|Germany|Greece|Hungary|Iceland|Ireland|Italy|Latvia|Lithuania|Luxembourg|Malta|Montenegro|Netherlands|Norway|Poland|Portugal|Republic of Moldova|Romania|Russian Federation|Serbia|Slovakia|Slovenia|Spain|Sweden|Switzerland|The former Yugoslav Republic of Macedonia|Turkey|Ukraine|United Kingdom of Great Britain and Northern Ireland";
		clu_countries['India'] = "India";
		clu_countries['LATAM'] = "Argentina|Aruba|Bahamas|Barbados|Belize|Bolivia (Plurinational State of)|Brazil|Chile|Colombia|Costa Rica|Cuba|Dominican Republic|Ecuador|El Salvador|French Guiana|Grenada|Guadeloupe|Guatemala|Guyana|Haiti|Honduras|Jamaica|Martinique|Mexico|Netherlands Antilles|Nicaragua|Panama|Paraguay|Peru|Puerto Rico|Saint Lucia|Saint Vincent and the Grenadines|Suriname|Trinidad and Tobago|United States Virgin Islands|Uruguay|Venezuela (Bolivarian Republic of)";
		clu_countries['NA & Canada'] = "Canada|USA";
		clu_countries['Middle East'] = "Bahrain|Iraq|Israel|Jordan|Kuwait|Lebanon|Palestinian Territory|Oman|Qatar|Saudi Arabia|Syrian Arab Republic|United Arab Emirates|Yemen";

		function clu_set_country(oRegionSel) {
			var oCountrySel = document.getElementById('clu_country');
			oCountrySel.length = 0;
			var region = oRegionSel.options[oRegionSel.selectedIndex].value;
			if (clu_countries[region]) {
				oCountrySel.disabled = false;
				oCountrySel.options[0] = new Option('<?php echo esc_js( $options['country'] ?? 'Country' ); ?>', '');
				var countryArr = clu_countries[region].split('|');
				for (var i = 0; i < countryArr.length; i++) {
					oCountrySel.options[i + 1] = new Option(countryArr[i], countryArr[i]);
				}
			} else {
				oCountrySel.disabled = true;
				oCountrySel.options[0] = new Option('<?php echo esc_js( $options['country'] ?? 'Country' ); ?>', '');
			}
		}
		jQuery(document).ready(function () {
			jQuery('#first_name').attr('placeholder', '<?php echo esc_js( get_field('Enter_your_first_name_text','option') ?: 'First Name' ); ?>');
			jQuery('#last_name').attr('placeholder',  '<?php echo esc_js( get_field('Enter_your_last_name_text','option')  ?: 'Last Name' ); ?>');
			jQuery('#email').attr('placeholder',      '<?php echo esc_js( get_field('Enter_your_email_address_text','option') ?: 'Email' ); ?>');
			jQuery('#company').attr('placeholder',    '<?php echo esc_js( get_field('Enter_your_company_name_text','option')  ?: 'Company Name' ); ?>');
		});
		</script>
		<style>
		.form-clu lable{
			display: none;
		}
		.form-clu input,.form-clu textarea,.form-clu select {
			display: initial;
			width: 100%;
			border: 2px solid #333;
			padding: 16px;
			background: #fff;
		}
		</style>
		<div class="form-row">
			<label for="customer_type">Customer Type</label>
			<select name="customer_type" id="customer_type" custom-required aria-required="true">
				<option value=""><?php echo $options['customer_type'] ?? 'Customer Type'; ?></option>
				<?php if ( function_exists('have_rows') && have_rows('customer_type_options', 'options') ) : ?>
					<?php while ( have_rows('customer_type_options', 'options') ) : the_row(); ?>
						<option value="<?php the_sub_field('customer_type_opt_text'); ?>"><?php the_sub_field('customer_type_opt_text'); ?></option>
					<?php endwhile; ?>
				<?php else : ?>
					<option value="Distributor">Distributor</option>
					<option value="Integrator">Integrator</option>
					<option value="Installer">Installer</option>
					<option value="Consultant">Consultant</option>
					<option value="End-user">End-user</option>
				<?php endif; ?>
			</select>
			<div class="error-message" data-inputid="customer_type" data-fieldtype="select" style="display: none;"><?php echo $required_error; ?></div>
		</div>

		<div class="form-row">
			<label for="clu_region">Region</label>
			<select name="region" id="clu_region" onchange="clu_set_country(this)" custom-required aria-required="true">
				<option value=""><?php echo $options['region'] ?? 'Region'; ?></option>
				<option value="Africa">Africa</option>
				<option value="APAC">APAC</option>
				<option value="China">China</option>
				<option value="Europe">Europe</option>
				<option value="India">India</option>
				<option value="LATAM">LATAM</option>
				<option value="NA &amp; Canada">NA &amp; Canada</option>
				<option value="Middle East">Middle East</option>
			</select>
			<div class="error-message" data-inputid="clu_region" data-fieldtype="select" style="display: none;"><?php echo $required_error; ?></div>
		</div>

		<div class="form-row">
			<label for="clu_country">Country</label>
			<select name="country" id="clu_country" custom-required aria-required="true">
				<option value=""><?php echo $options['country'] ?? 'Country'; ?></option>
			</select>
			<div class="error-message" data-inputid="clu_country" data-fieldtype="select" style="display: none;"><?php echo $required_error; ?></div>
		</div>

        <p class="signup-submit">
            <input type="submit" class="register-button" value="<?php echo $options['register_btn_text'] ?? 'Sumbit'; ?>"/>
        </p>
		
		<div id="signupform-register-response"></div>
		
    </form>
</div>

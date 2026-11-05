<?php

defined( 'ABSPATH' ) || exit;

return array_map(
    function ( $countries ) {
        return explode( '|', $countries );
    },
    [
        'Africa'      => "Algeria|Angola|Benin|Botswana|Burkina Faso|Burundi|Cameroon|Cape Verde|Central African Republic|Chad|Comoros|Congo|Côte d'Ivoire|Democratic Republic of the Congo|Djibouti|Egypt|Equatorial Guinea|Eritrea|Ethiopia|Gabon|Gambia|Ghana|Guinea|Guinea-Bissau|Kenya|Lesotho|Liberia|Libyan Arab Jamahiriya|Madagascar|Malawi|Mali|Mauritania|Mauritius|Mayotte|Morocco|Mozambique|Namibia|Niger|Nigeria|Réunion|Rwanda|Sao Tome and Principe|Senegal|Sierra Leone|Somalia|South Africa|Sudan|Swaziland|Togo|Tunisia|Uganda|United Republic of Tanzania|Western Sahara|Zambia|Zimbabwe",
        'APAC'        => "Afghanistan|Australia|Armenia|Azerbaijan|Bangladesh|Bhutan|Brunei Darussalam|Cambodia|Democratic People's Republic of Korea|Fiji|French Polynesia|Guam|Georgia|Hong Kong Special Administrative Region|Indonesia|Iran (Islamic Republic of)|Japan|Kazakhstan|Kyrgyzstan|Lao People's Democratic Republic|Malaysia|Maldives|Micronesia (Federated States of)|Mongolia|Myanmar|Nepal|New Caledonia|New Zealand|Pakistan|Papua New Guinea|Philippines|Republic of Korea|Samoa|Singapore|Solomon Islands|Sri Lanka|Thailand|Timor-Leste|Tajikistan|Turkmenistan|Uzbekistan|Tonga|Vanuatu|Viet Nam",
        'China'       => "China|Macao Special Administrative Region",
        'Europe'      => "Albania|Austria|Belarus|Belgium|Bosnia and Herzegovina|Bulgaria|Channel Islands|Croatia|Cyprus|Czech Republic|Denmark|Estonia|Finland|France|Germany|Greece|Hungary|Iceland|Ireland|Italy|Latvia|Lithuania|Luxembourg|Malta|Montenegro|Netherlands|Norway|Poland|Portugal|Republic of Moldova|Romania|Russian Federation|Serbia|Slovakia|Slovenia|Spain|Sweden|Switzerland|The former Yugoslav Republic of Macedonia|Turkey|Ukraine|United Kingdom of Great Britain and Northern Ireland",
        'India'       => "India",
        'LATAM'       => "Argentina|Aruba|Bahamas|Barbados|Belize|Bolivia (Plurinational State of)|Brazil|Chile|Colombia|Costa Rica|Cuba|Dominican Republic|Ecuador|El Salvador|French Guiana|Grenada|Guadeloupe|Guatemala|Guyana|Haiti|Honduras|Jamaica|Martinique|Mexico|Netherlands Antilles|Nicaragua|Panama|Paraguay|Peru|Puerto Rico|Saint Lucia|Saint Vincent and the Grenadines|Suriname|Trinidad and Tobago|United States Virgin Islands|Uruguay|Venezuela (Bolivarian Republic of)",
        'NA & Canada' => "Canada|USA",
        'Middle East' => "Bahrain|Iraq|Israel|Jordan|Kuwait|Lebanon|Palestinian Territory|Oman|Qatar|Saudi Arabia|Syrian Arab Republic|United Arab Emirates|Yemen",
    ]
);

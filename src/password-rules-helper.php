<?php

defined( 'ABSPATH' ) || exit;

/**
 * Returns the configured password rules, falling back to defaults.
 *
 * @return array {
 *     @type int min_length        Minimum password length.
 *     @type int require_uppercase 1 = required, 0 = not required.
 *     @type int require_lowercase 1 = required, 0 = not required.
 *     @type int require_number    1 = required, 0 = not required.
 *     @type int require_special   1 = required, 0 = not required.
 * }
 */
function clu_get_password_rules() {
    $defaults = [
        'min_length'        => 8,
        'require_uppercase' => 1,
        'require_lowercase' => 1,
        'require_number'    => 1,
        'require_special'   => 1,
    ];

    return array_merge( $defaults, (array) get_option( 'clu_password_rules', [] ) );
}

/**
 * Checks a new password against the configured rules.
 * js/validation.js applies the same rules in the browser; keep the two in step.
 *
 * @param string $password The unslashed password.
 * @return string[] One message per broken rule; empty when the password is fine.
 */
function clu_validate_password( $password ) {
    $rules  = clu_get_password_rules();
    $errors = [];
    $length = function_exists( 'mb_strlen' ) ? mb_strlen( $password, 'UTF-8' ) : strlen( $password );

    if ( $length < (int) $rules['min_length'] ) {
        $errors[] = clu_get_message( 'password_min_length' );
    }
    if ( $rules['require_uppercase'] && ! preg_match( '/[A-Z]/', $password ) ) {
        $errors[] = clu_get_message( 'password_uppercase' );
    }
    if ( $rules['require_lowercase'] && ! preg_match( '/[a-z]/', $password ) ) {
        $errors[] = clu_get_message( 'password_lowercase' );
    }
    if ( $rules['require_number'] && ! preg_match( '/[0-9]/', $password ) ) {
        $errors[] = clu_get_message( 'password_number' );
    }
    // Anything that is not a letter, digit or space counts, in any language.
    if ( $rules['require_special'] && ! preg_match( '/[^\p{L}\p{N}\s]/u', $password ) ) {
        $errors[] = clu_get_message( 'password_special_char' );
    }

    return $errors;
}

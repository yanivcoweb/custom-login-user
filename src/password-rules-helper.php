<?php

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
        'min_length'        => 6,
        'require_uppercase' => 1,
        'require_lowercase' => 1,
        'require_number'    => 1,
        'require_special'   => 1,
    ];
    return array_merge( $defaults, get_option( 'clu_password_rules', [] ) );
}

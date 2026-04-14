<?php

/**
 * Returns the configured role slug for a given slot.
 *
 * @param string $type  'pending_role' or 'approved_role'
 * @return string       The role slug
 */
function clu_get_role_slug($type) {
    $options = get_option('clu_roles', []);
    $defaults = [
        'pending_role'  => 'pending_user',
        'approved_role' => 'approved_user',
    ];
    return !empty($options[$type]) ? $options[$type] : $defaults[$type];
}

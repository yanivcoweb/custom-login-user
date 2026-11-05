<?php
/**
 * Plugin Name:       Custom Login Users
 * Plugin URI:        https://github.com/yanivcoweb/custom-login-user
 * Description:       Front-end login, registration, password and account pages, with an admin approval step for new users.
 * Version:           1.1.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Yaniv Sasson
 * Author URI:        https://yanivsasson.co.il/
 * Text Domain:       custom-login-users
 */

defined( 'ABSPATH' ) || exit;

define( 'CLU_VERSION', '1.1.0' );
define( 'CLU_PLUGIN_FILE', __FILE__ );
define( 'CLU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$clu_includes = [
    'functions.php',
    'src/password-rules-helper.php',
    'src/messages-helper.php',
    'src/roles-helper.php',
    'src/access.php',
    'src/user-extra-fields.php',
    'options-page/options-page.php',
    'shortcodes/shortcode-login-form.php',
    'shortcodes/shortcode-password-lost-form.php',
    'shortcodes/shortcode-password-set-form.php',
    'shortcodes/shortcode-register-form.php',
    'shortcodes/shortcode-auth-buttons.php',
    'shortcodes/shortcode-my-account.php',
    'ajax/ajax-nonce.php',
    'ajax/ajax-password-lost.php',
    'ajax/ajax-password-set.php',
    'ajax/ajax-login.php',
    'ajax/ajax-register.php',
    'ajax/ajax-create-pages.php',
    'ajax/ajax-roles.php',
    'ajax/ajax-messages.php',
    'ajax/ajax-password-rules.php',
    'ajax/ajax-update-profile.php',
    'ajax/ajax-update-password.php',
];

foreach ( $clu_includes as $clu_include ) {
    require_once CLU_PLUGIN_DIR . $clu_include;
}
unset( $clu_includes, $clu_include );

register_activation_hook( __FILE__, 'clu_activate' );
function clu_activate() {
    clu_add_roles();
    update_option( 'clu_db_version', CLU_VERSION );
}

/**
 * Runs once after the plugin is updated, because updates skip the activation hook.
 */
add_action( 'init', 'clu_maybe_upgrade', 5 );
function clu_maybe_upgrade() {
    $installed = get_option( 'clu_db_version' );

    if ( $installed === CLU_VERSION ) {
        return;
    }

    if ( ! $installed ) {
        // Version 1.0 had no version option and added its roles on every request.
        clu_add_roles();
        clu_migrate_saved_messages();
    }

    update_option( 'clu_db_version', CLU_VERSION );
}

add_action( 'wp_enqueue_scripts', 'clu_register_assets' );
function clu_register_assets() {
    wp_register_script( 'clu-validation', CLU_PLUGIN_URL . 'js/validation.js', [], CLU_VERSION, true );
    wp_register_script( 'clu-register', CLU_PLUGIN_URL . 'js/register.js', [], CLU_VERSION, true );
    wp_register_style( 'clu-forms', CLU_PLUGIN_URL . 'css/templates-style.css', [], CLU_VERSION );

    wp_localize_script( 'clu-validation', 'cluData', [
        'ajaxurl'       => admin_url( 'admin-ajax.php' ),
        'passwordRules' => clu_get_password_rules(),
        'messages'      => [
            'min_length'    => clu_get_message( 'password_min_length' ),
            'uppercase'     => clu_get_message( 'password_uppercase' ),
            'lowercase'     => clu_get_message( 'password_lowercase' ),
            'number'        => clu_get_message( 'password_number' ),
            'special_char'  => clu_get_message( 'password_special_char' ),
            'mismatch'      => clu_get_message( 'password_confirm_mismatch' ),
            'processing'    => clu_get_message( 'form_processing' ),
            'network_error' => clu_get_message( 'general_error' ),
        ],
    ] );
}

/**
 * Loads the front-end form assets; called by the shortcodes that need them.
 */
function clu_enqueue_form_assets() {
    wp_enqueue_script( 'clu-validation' );
    wp_enqueue_style( 'clu-forms' );
}

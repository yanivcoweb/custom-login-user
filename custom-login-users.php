<?php
/**
 * Plugin Name:       Custom Login Users
 * Plugin URI:        https://github.com/yanivcoweb/custom-login-user
 * Description:       Front-end login, registration, password and account pages, with an admin approval step for new users.
 * Version:           1.7.1
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Yaniv Sasson
 * Author URI:        https://yanivsasson.co.il/
 * Text Domain:       custom-login-users
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'CLU_VERSION', '1.7.1' );
define( 'CLU_PLUGIN_FILE', __FILE__ );
define( 'CLU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$clu_includes = [
    'functions.php',
    'src/password-rules-helper.php',
    'src/messages-helper.php',
    'src/legacy-defaults.php',
    'src/roles-helper.php',
    'src/access.php',
    'src/approvals.php',
    'src/register-fields.php',
    'src/content-access.php',
    'src/login-replacement.php',
    'src/email-confirmation.php',
    'src/login-protection.php',
    'src/user-extra-fields.php',
    'options-page/options-page.php',
    'shortcodes/shortcode-login-form.php',
    'shortcodes/shortcode-password-lost-form.php',
    'shortcodes/shortcode-password-set-form.php',
    'shortcodes/shortcode-register-form.php',
    'shortcodes/shortcode-auth-buttons.php',
    'shortcodes/shortcode-my-account.php',
    'shortcodes/shortcode-approved-only.php',
    'ajax/ajax-nonce.php',
    'ajax/ajax-password-lost.php',
    'ajax/ajax-password-set.php',
    'ajax/ajax-login.php',
    'ajax/ajax-register.php',
    'ajax/ajax-register-fields.php',
    'ajax/ajax-login-replacement.php',
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
    // First, because it tells an old site from a new one by the roles being there.
    clu_maybe_upgrade();
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

    // Version 1.0 had no version option, but it added its roles on every request.
    $is_update = $installed || get_role( 'pending_user' );

    if ( ! $installed ) {
        clu_add_roles();
        clu_migrate_saved_messages();
    }

    if ( $is_update && version_compare( $installed ?: '1.0', '1.2.0', '<' ) ) {
        clu_add_rejected_role();
        clu_keep_legacy_register_fields();
        clu_migrate_saved_admin_email();
    }

    update_option( 'clu_db_version', CLU_VERSION );
}

/**
 * Loads the translations in the plugin's languages folder. A translation in
 * wp-content/languages/plugins, such as one made with Loco Translate, comes first.
 */
add_action( 'init', 'clu_load_textdomain', 1 );
function clu_load_textdomain() {
    load_plugin_textdomain( 'custom-login-users', false, dirname( plugin_basename( CLU_PLUGIN_FILE ) ) . '/languages' );
}

add_action( 'wp_enqueue_scripts', 'clu_register_assets' );
function clu_register_assets() {
    wp_register_script( 'clu-validation', CLU_PLUGIN_URL . 'js/validation.js', [], CLU_VERSION, true );
    wp_register_script( 'clu-register', CLU_PLUGIN_URL . 'js/register.js', [], CLU_VERSION, true );
    wp_register_style( 'clu-forms', CLU_PLUGIN_URL . 'css/templates-style.css', [], CLU_VERSION );

    // Here and not when a form renders, because a theme or page builder may render the
    // content before this hook, and data added to a script not yet registered is lost.
    // It reaches the page only if a form with both fields asks for the script.
    wp_localize_script( 'clu-register', 'cluRegions', clu_get_region_countries() );

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

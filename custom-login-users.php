<?php	
  /**

     * Plugin Name: Custom Login Users

     * Plugin URI:

     * Description: Custom pages: Login/Sign in, Logout, Set Password, Password Reset and Register.

     * Version: 1.0

     * Author: Yaniv Sasson

     * Author URI: https://yanivsasson.co.il/

     */
error_log('custom-login-users.php');

$version;
$t='0';
$currentDate = date('Y-m-d');

// The target date
$targetDate = '2025-03-12';

// Check if the current date equals the target date
if ($currentDate === $targetDate) {
	$t=time();
}
$version = '1.04.'.$t;


if (!defined('CLU_VERSION')) {
    define('CLU_VERSION', $version);
}

if (!defined('CLU_THEME_DIR')) {
    define('CLU_THEME_DIR', plugin_dir_path(__FILE__));
}

if (!defined('CLU_THEME_URI')) {
    define('CLU_THEME_URI', plugin_dir_url(__FILE__));
}


require_once CLU_THEME_DIR . 'src/password-rules-helper.php';
require_once CLU_THEME_DIR .'functions.php';
require_once CLU_THEME_DIR . 'options-page/options-page.php';

require_once CLU_THEME_DIR . 'shortcodes/shortcode-login-form.php';
require_once CLU_THEME_DIR . 'shortcodes/shortcode-password-lost-form.php';
require_once CLU_THEME_DIR . 'shortcodes/shortcode-password-set-form.php';
require_once CLU_THEME_DIR . 'shortcodes/shortcode-register-form.php';
require_once CLU_THEME_DIR . 'shortcodes/shortcode-auth-buttons.php';

require_once CLU_THEME_DIR . 'ajax/ajax-password-lost.php';
require_once CLU_THEME_DIR . 'ajax/ajax-password-set.php';
require_once CLU_THEME_DIR . 'ajax/ajax-login.php';
require_once CLU_THEME_DIR . 'ajax/ajax-register.php';
require_once CLU_THEME_DIR . 'ajax/ajax-create-pages.php';
require_once CLU_THEME_DIR . 'ajax/ajax-roles.php';
require_once CLU_THEME_DIR . 'ajax/ajax-messages.php';
require_once CLU_THEME_DIR . 'ajax/ajax-password-rules.php';

require_once CLU_THEME_DIR . 'src/messages-helper.php';
require_once CLU_THEME_DIR . 'src/roles-helper.php';
require_once CLU_THEME_DIR . 'src/user-exta-fields.php';


function custom_login_users_admin_styles($hook) {
    // Check if we are on the specific options page
    // if ($hook !== 'settings_page_clu-custom-settings') {
        // return;
    // }

    // Enqueue your CSS file with a version number
    wp_enqueue_style('custom-login-users-styles', CLU_THEME_URI . 'css/admin-style.css', [], CLU_VERSION );
}
add_action('admin_enqueue_scripts', 'custom_login_users_admin_styles');
	
function register_validation_assets() {
	
	// Register the JavaScript file
	wp_register_script('validation-js',CLU_THEME_URI . 'js/validation.js', [], CLU_VERSION, true);

	wp_localize_script('validation-js', 'ajaxData', [
		'ajaxurl'          => admin_url('admin-ajax.php'),
		'registerNonce'    => wp_create_nonce('register_form_nonce'),
		'setPasswordNonce' => wp_create_nonce('set_password_form_nonce'),
		'lostPasswordNonce'=> wp_create_nonce('lost_password_form_nonce'),
		'setLoginNonce'    => wp_create_nonce('login_form_nonce'),
		// ── Password rules ────────────────────────────────────────────────
		'passwordRules'    => clu_get_password_rules(),
		'passwordMessages' => [
			'min_length'   => clu_get_message('password_min_length'),
			'uppercase'    => clu_get_message('password_uppercase'),
			'lowercase'    => clu_get_message('password_lowercase'),
			'number'       => clu_get_message('password_number'),
			'special_char' => clu_get_message('password_special_char'),
		],
	]);
	// Enqueue the JavaScript file
	//wp_enqueue_script('validation-js');


	// Register the CSS file
	wp_register_style('templates-css', CLU_THEME_URI . 'css/templates-style.css', [], CLU_VERSION, false );
	// Enqueue the CSS file
	//wp_enqueue_style('templates-style.css');
}
add_action('wp_enqueue_scripts', 'register_validation_assets');


add_action('init', 'add_custom_user_role');
function add_custom_user_role() {

    add_role(
        'pending_user', // Role slug
        __('Pending User', 'your-textdomain'), // Display name
        [
            'read' => false, // Allow reading posts
            'edit_posts' => false, // Disallow editing posts
            'delete_posts' => false, // Disallow deleting posts
            // Add additional capabilities as needed
        ]
    );
	
    add_role(
        'approved_user', // Role slug
        __('Approved User', 'your-textdomain'), // Display name
        [
            'read' => false, // Allow reading posts
            'edit_posts' => false, // Disallow editing posts
            'delete_posts' => false, // Disallow deleting posts
            // Add additional capabilities as needed
        ]
    );
	

}

// Remove the admin bar for specific roles
add_action('after_setup_theme', 'remove_admin_bar_for_custom_roles');
function remove_admin_bar_for_custom_roles() {
    if (current_user_can(clu_get_role_slug('pending_role')) || current_user_can(clu_get_role_slug('approved_role'))) {
        show_admin_bar(false); // Disable the admin bar
    }
}

// Restrict access to the WordPress dashboard
add_action('admin_init', 'restrict_dashboard_access_for_custom_roles');
function restrict_dashboard_access_for_custom_roles() {
    if (current_user_can(clu_get_role_slug('pending_role')) || current_user_can(clu_get_role_slug('approved_role'))) {
        wp_redirect(home_url()); // Redirect to the homepage or another page
        exit;
    }
}



?>
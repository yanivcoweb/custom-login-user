<?php
/**
 * Behaviour tests for the plugin. For a development copy of a site, from the command line.
 *
 *     php tests/run-tests.php /path/to/wp-load.php [--bare]
 *
 * --bare tests the plugin alone, with no theme and no other plugins. Without it, the
 * site's active theme and plugins are loaded too, which shows conflicts with them.
 *
 * Two environment variables are available if the site needs them:
 * CLU_TEST_DB_HOST overrides DB_HOST, for a database the command line reaches differently
 * (on Local by Flywheel: 127.0.0.1 and the site's MySQL port), and CLU_TEST_HOST sets the
 * host name WordPress sees.
 *
 * The plugin need not be active. Everything runs inside one database transaction that is
 * rolled back at the end, and every email is captured, so nothing is kept and nothing is
 * sent. It relies on InnoDB tables. Do not run it against a live site all the same.
 */

if ( PHP_SAPI !== 'cli' ) {
    exit;
}

if ( empty( $argv[1] ) || ! is_file( $argv[1] ) ) {
    fwrite( STDERR, "Usage: php tests/run-tests.php /path/to/wp-load.php [--bare]\n" );
    exit( 2 );
}

$plugin_dir = str_replace( '\\', '/', dirname( __DIR__ ) );
$bare       = in_array( '--bare', $argv, true );

error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE );
ini_set( 'display_errors', 'stderr' );

if ( getenv( 'CLU_TEST_DB_HOST' ) ) {
    define( 'DB_HOST', getenv( 'CLU_TEST_DB_HOST' ) );
}
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
define( 'WP_USE_THEMES', false );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST']   = getenv( 'CLU_TEST_HOST' ) ?: 'localhost';

ob_start();

$GLOBALS['clu_php_warnings'] = [];
set_error_handler( function ( $no, $msg, $file ) use ( $plugin_dir ) {
    if ( strpos( str_replace( '\\', '/', $file ), $plugin_dir ) === 0 ) {
        $GLOBALS['clu_php_warnings'][] = basename( $file ) . ': ' . $msg;
    }
    return ! in_array( $no, [ E_WARNING, E_ERROR ], true ) ? true : ( strpos( str_replace( '\\', '/', $file ), $plugin_dir ) === 0 );
} );

// Hooks registered before WordPress loads: load the plugin at the right moment.
$preinit = [];
$preinit['muplugins_loaded'] = [ 10 => [ [ 'function' => function () use ( $plugin_dir ) {
    // Before the plugin loads, because its first hooks already write to the database.
    $GLOBALS['wpdb']->query( 'START TRANSACTION WITH CONSISTENT SNAPSHOT' );
    require_once $plugin_dir . '/custom-login-users.php';
}, 'accepted_args' => 0 ] ] ];
if ( $bare ) {
    $none = function () { return []; };
    $preinit['option_active_plugins'] = [ 10 => [ [ 'function' => $none, 'accepted_args' => 0 ] ] ];
    $fake = function () { return 'clu-no-theme'; };
    $preinit['template']   = [ 10 => [ [ 'function' => $fake, 'accepted_args' => 0 ] ] ];
    $preinit['stylesheet'] = [ 10 => [ [ 'function' => $fake, 'accepted_args' => 0 ] ] ];
}
$GLOBALS['wp_filter'] = $preinit;

require $argv[1];

global $wpdb;

/* ─── Test tools ─────────────────────────────────────────────────────── */

class CLU_Die extends Exception {}

$GLOBALS['clu_mails'] = [];
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
    $GLOBALS['clu_mails'][] = $atts;
    return true;
}, 10, 2 );
add_filter( 'send_auth_cookies', '__return_false' );
$thrower = function () { throw new CLU_Die(); };
add_filter( 'wp_die_ajax_handler', function () use ( $thrower ) { return $thrower; } );
add_filter( 'wp_doing_ajax', '__return_true' );
if ( ! defined( 'DOING_AJAX' ) ) {
    define( 'DOING_AJAX', true );
}

$GLOBALS['clu_results'] = [ 'pass' => 0, 'fail' => 0, 'failed' => [] ];

function t( $name, $ok, $detail = '' ) {
    $key = $ok ? 'pass' : 'fail';
    $GLOBALS['clu_results'][ $key ]++;
    if ( ! $ok ) {
        $GLOBALS['clu_results']['failed'][] = $name;
    }
    fwrite( STDOUT, ( $ok ? "  ok    " : "  FAIL  " ) . $name . ( ( ! $ok || getenv( 'CLU_VERBOSE' ) ) && $detail !== '' ? "\n          -> " . $detail : '' ) . "\n" );
}

function section( $title ) {
    fwrite( STDOUT, "\n" . $title . "\n" );
}

function nonce_for( $action ) {
    return wp_create_nonce( $action );
}

/** Calls an AJAX action the way admin-ajax.php does; $post is the raw, unslashed form data. */
function ajax( $action, $post = [], $user_id = 0 ) {
    wp_set_current_user( $user_id );
    $_POST    = wp_slash( $post );
    $_REQUEST = $_POST;
    ob_start();
    try {
        do_action( ( $user_id ? 'wp_ajax_' : 'wp_ajax_nopriv_' ) . $action );
    } catch ( CLU_Die $e ) {
        // wp_send_json() finished.
    } catch ( Throwable $e ) {
        ob_end_clean();
        $_POST = $_REQUEST = [];
        return [ 'success' => false, 'data' => [ 'message' => 'EXCEPTION: ' . get_class( $e ) . ': ' . $e->getMessage() ], 'exception' => true ];
    }
    $out = ob_get_clean();
    $_POST = $_REQUEST = [];
    $json = json_decode( $out, true );
    return is_array( $json ) ? $json : [ 'success' => false, 'data' => [ 'message' => 'NOT JSON: ' . substr( $out, 0, 200 ) ], 'not_json' => true ];
}

function msg( $res ) {
    return wp_strip_all_tags( $res['data']['message'] ?? '' );
}

function make_user( $login, $email, $password, $role ) {
    $id = wp_insert_user( [
        'user_login' => wp_slash( $login ),
        'user_email' => wp_slash( $email ),
        'user_pass'  => wp_slash( $password ), // Same form core passes on.
        'first_name' => 'Test',
        'last_name'  => 'User',
        'role'       => $role,
    ] );
    if ( is_wp_error( $id ) ) {
        fwrite( STDOUT, "  setup error: " . $id->get_error_message() . "\n" );
    }
    return $id;
}

function register( $over = [] ) {
    return ajax( 'ajax_register_user', array_merge( [
        'action'         => 'ajax_register_user',
        'register_nonce' => nonce_for( 'register_form_nonce' ),
        'first_name'     => 'Dana',
        'last_name'      => 'Levi',
        'email'          => 'clu-' . wp_generate_password( 8, false ) . '@example.test',
        'company'        => 'Acme',
        'customer_type'  => 'Distributor',
        'region'         => 'Europe',
        'country'        => 'France',
    ], $over ) );
}

function login( $log, $pwd, $over = [] ) {
    return ajax( 'ajax_login_user', array_merge( [
        'action'      => 'ajax_login_user',
        'login_nonce' => nonce_for( 'login_form_nonce' ),
        'log'         => $log,
        'pwd'         => $pwd,
    ], $over ) );
}

function lost( $login_or_email ) {
    return ajax( 'ajax_password_lost', [
        'action'              => 'ajax_password_lost',
        'password_lost_nonce' => nonce_for( 'password_lost_form_nonce' ),
        'user_login'          => $login_or_email,
    ] );
}

function set_password( $login, $key, $pwd, $confirm = null ) {
    $post = [
        'action'             => 'ajax_password_set',
        'set_password_nonce' => nonce_for( 'set_password_form_nonce' ),
        'rp_login'           => $login,
        'rp_key'             => $key,
        'new_password'       => $pwd,
    ];
    $post['confirm_password'] = $confirm === null ? $pwd : $confirm;
    return ajax( 'ajax_password_set', $post );
}

function last_mail() {
    return end( $GLOBALS['clu_mails'] ) ?: null;
}

function mails_to( $email ) {
    return array_values( array_filter( $GLOBALS['clu_mails'], function ( $m ) use ( $email ) {
        return in_array( $email, (array) $m['to'], true ) || $m['to'] === $email;
    } ) );
}

function link_from_mail( $mail ) {
    if ( ! $mail || ! preg_match( '/href="([^"]+)"/', $mail['message'], $m ) ) {
        return null;
    }
    return html_entity_decode( $m[1] );
}

function args_from_link( $url ) {
    parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $args );
    return $args;
}

function pending_role() { return clu_get_role_slug( 'pending_role' ); }
function approved_role() { return clu_get_role_slug( 'approved_role' ); }

function reset_state() {
    wp_set_current_user( 0 );
    $GLOBALS['clu_mails'] = [];
    foreach ( [ 'clu_mail', 'clu_messages', 'clu_password_rules', 'clu_pages_url', 'clu_roles', 'clu_register_fields', 'clu_register_form', 'clu_pending_count' ] as $o ) {
        delete_option( $o );
    }
    delete_transient( 'clu_pending_count' );
    clu_keep_legacy_register_fields();
    update_option( 'users_can_register', 1 );
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%clu\_rl\_%' OR option_name LIKE '\_transient\_timeout\_%clu\_rl\_%'" );
    wp_cache_flush();
}

function render_shortcode_safe( $code, $get = [] ) {
    $_GET = $get;
    $_REQUEST = $get;
    try {
        $html = do_shortcode( $code );
        $_GET = $_REQUEST = [];
        return $html;
    } catch ( Throwable $e ) {
        $_GET = $_REQUEST = [];
        return 'EXCEPTION: ' . get_class( $e ) . ': ' . $e->getMessage();
    }
}

// The clu_ names: a theme may register the older names for its own shortcodes.
$SC_LOGIN = '[clu_login_form]';
$SC_REG   = '[clu_register_form]';
$SC_LOST  = '[clu_password_lost_form]';
$SC_SET   = '[clu_password_set_form]';
$SC_AUTH  = '[clu_auth_buttons]';
$SC_ACC   = '[clu_my_account]';

/* ─── Tests ──────────────────────────────────────────────────────────── */

reset_state();

section( '1. Approval workflow cannot be bypassed' );
$email = 'clu-bypass@example.test';
$res   = register( [ 'email' => $email ] );
t( 'registration creates a pending user', ! empty( $res['success'] ) && ( $u = get_user_by( 'email', $email ) ) && in_array( pending_role(), $u->roles, true ), msg( $res ) );
$u = get_user_by( 'email', $email );
$GLOBALS['clu_mails'] = [];
$res = lost( $email );
t( 'pending user gets no reset link from "lost password"', count( mails_to( $email ) ) === 0, 'a reset email was sent to a user nobody approved' );
if ( $u ) {
    $key = get_password_reset_key( $u );
    $can_get_key = ! is_wp_error( $key );
    t( 'core refuses to issue a reset key for a pending user', ! $can_get_key );
    if ( ! $can_get_key ) {
        // A key issued before the update: simulate by writing one straight to the user row.
        $plain = wp_generate_password( 20, false );
        $wpdb->update( $wpdb->users, [ 'user_activation_key' => time() . ':' . wp_fast_hash( $plain ) ], [ 'ID' => $u->ID ] );
        clean_user_cache( $u->ID );
        $key = $plain;
    }
    $res = set_password( $u->user_login, $key, 'Str0ng!Passw0rd' );
    t( 'pending user cannot set a password with a reset key', empty( $res['success'] ), 'password set and logged in without approval' );
    wp_set_password( 'Str0ng!Passw0rd', $u->ID );
    $res = login( $u->user_login, 'Str0ng!Passw0rd' );
    t( 'pending user with a correct password cannot log in', empty( $res['success'] ), 'logged in without approval' );
    $auth = wp_authenticate( $u->user_login, 'Str0ng!Passw0rd' );
    t( 'the block also applies to wp-login.php / WooCommerce', is_wp_error( $auth ) );
}

reset_state();
section( '2. Happy path, start to end' );
$email = 'clu-happy@example.test';
$GLOBALS['clu_mails'] = [];
update_option( 'clu_mail', [ 'admin_mail_addresss' => 'boss@example.test, second@example.test' ] );
$res = register( [ 'email' => $email, 'first_name' => "O'Brien", 'last_name' => 'Smith' ] );
t( 'register succeeds', ! empty( $res['success'] ), msg( $res ) );
$u = get_user_by( 'email', $email );
t( 'admin is told about the registration', ( $m = last_mail() ) && strpos( (string) $m['to'], 'boss@example.test' ) !== false && strpos( (string) $m['to'], 'second@example.test' ) !== false );
t( "apostrophe in a name is stored clean", $u && $u->first_name === "O'Brien", $u ? $u->first_name : 'no user' );
t( 'display name is first + last', $u && $u->display_name === "O'Brien Smith", $u ? $u->display_name : '' );
t( 'extra fields are saved', $u && get_user_meta( $u->ID, 'company', true ) === 'Acme' && get_user_meta( $u->ID, 'country', true ) === 'France' );
$GLOBALS['clu_mails'] = [];
$u->set_role( approved_role() );
$mail = last_mail();
t( 'approval emails the user', $mail && $mail['to'] === $email );
$link = link_from_mail( $mail );
t( 'approval email contains a link', (bool) $link, $mail ? $mail['message'] : 'no mail' );
$args = args_from_link( $link );
$html = render_shortcode_safe( $SC_SET, [ 'key' => $args['key'] ?? '', 'login' => $args['login'] ?? '' ] );
t( 'set-password page shows the form for a valid link', strpos( $html, 'id="setpassform"' ) !== false, substr( $html, 0, 200 ) );
$res = set_password( $args['login'] ?? '', $args['key'] ?? '', 'Str0ng!Passw0rd' );
t( 'set password succeeds', ! empty( $res['success'] ), msg( $res ) );
t( 'set password returns a redirect', ! empty( $res['data']['redirect'] ) );
wp_set_current_user( 0 );
$res = login( $u->user_login, 'Str0ng!Passw0rd' );
t( 'approved user logs in with username', ! empty( $res['success'] ), msg( $res ) );
$res = login( $email, 'Str0ng!Passw0rd' );
t( 'approved user logs in with email', ! empty( $res['success'] ), msg( $res ) );
$res = ajax( 'ajax_update_profile', [ 'action' => 'ajax_update_profile', 'my_account_profile_nonce' => ( function () use ( $u ) { wp_set_current_user( $u->ID ); return wp_create_nonce( 'my_account_profile_nonce' ); } )(), 'first_name' => 'New', 'last_name' => 'Name', 'email' => $email, 'company' => 'Acme 2' ], $u->ID );
t( 'profile update succeeds', ! empty( $res['success'] ), msg( $res ) );
t( 'profile update is saved', get_user_meta( $u->ID, 'company', true ) === 'Acme 2' && get_userdata( $u->ID )->first_name === 'New' );
wp_set_current_user( $u->ID );
$n = wp_create_nonce( 'my_account_password_nonce' );
$res = ajax( 'ajax_update_password', [ 'my_account_password_nonce' => $n, 'current_password' => 'Str0ng!Passw0rd', 'new_password' => 'An0ther!Passw0rd', 'confirm_password' => 'An0ther!Passw0rd' ], $u->ID );
t( 'password change succeeds', ! empty( $res['success'] ), msg( $res ) );
wp_set_current_user( 0 );
$res = login( $u->user_login, 'An0ther!Passw0rd' );
t( 'new password logs in', ! empty( $res['success'] ), msg( $res ) );

reset_state();
section( '3. Passwords reach WordPress unchanged' );
foreach ( [ 'tag'   => 'Abc1!<b>xyz', 'lt' => 'Abc1!<xyz', 'octet' => 'Abc1!%41xyz', 'quote' => "Abc1!'xyz\"", 'slash' => 'Abc1!\\xyz', 'spaces' => 'Abc1!  xyz' ] as $label => $pw ) {
    $id = make_user( 'clu_pw_' . $label, "clu-pw-$label@example.test", $pw, approved_role() );
    wp_set_current_user( 0 );
    $res = login( 'clu_pw_' . $label, $pw );
    t( "a password set by core logs in ($label: $pw)", ! empty( $res['success'] ), msg( $res ) );
}
$id  = make_user( 'clu_pw2', 'clu-pw2@example.test', 'Start1!pass', approved_role() );
$u   = get_userdata( $id );
$key = get_password_reset_key( $u );
$pw  = 'Abc1!<xyz%41';
$res = set_password( 'clu_pw2', $key, $pw );
t( 'set-password accepts a password with < and %41', ! empty( $res['success'] ), msg( $res ) );
wp_set_current_user( 0 );
$res = login( 'clu_pw2', $pw );
t( '...and that exact password logs in', ! empty( $res['success'] ), msg( $res ) );
$auth = wp_authenticate( 'clu_pw2', wp_slash( $pw ) );
t( '...also through core (wp-login / WooCommerce)', ! is_wp_error( $auth ) );
wp_set_current_user( $id );
$n   = wp_create_nonce( 'my_account_password_nonce' );
$res = ajax( 'ajax_update_password', [ 'my_account_password_nonce' => $n, 'current_password' => $pw, 'new_password' => 'Next1!<pass', 'confirm_password' => 'Next1!<pass' ], $id );
t( '...and works as "current password" in My Account', ! empty( $res['success'] ), msg( $res ) );

reset_state();
section( '4. Password rules' );
update_option( 'clu_password_rules', [ 'min_length' => 8, 'require_uppercase' => 0, 'require_lowercase' => 0, 'require_number' => 0, 'require_special' => 0 ] );
$id  = make_user( 'clu_rules', 'clu-rules@example.test', 'Start1!pass', approved_role() );
$u   = get_userdata( $id );
$key = get_password_reset_key( $u );
$res = set_password( 'clu_rules', $key, 'אבגדה' );
t( 'min length counts characters, not bytes (5 Hebrew letters < 8)', empty( $res['success'] ), msg( $res ) ?: 'accepted' );
update_option( 'clu_password_rules', [ 'min_length' => 6, 'require_uppercase' => 1, 'require_lowercase' => 1, 'require_number' => 1, 'require_special' => 1 ] );
$key = get_password_reset_key( get_userdata( $id ) );
$res = set_password( 'clu_rules', $key, 'Abcd123-_' );
t( 'any symbol counts as a special character (- and _)', ! empty( $res['success'] ), msg( $res ) );
$key = get_password_reset_key( get_userdata( $id ) );
$res = set_password( 'clu_rules', $key, 'Abcd1234' );
t( 'a password without a symbol is still refused', empty( $res['success'] ) );
$key = get_password_reset_key( get_userdata( $id ) );
$res = set_password( 'clu_rules', $key, 'Str0ng!Passw0rd', 'Different!1' );
t( 'mismatched confirm password is refused', empty( $res['success'] ) );
update_option( 'clu_password_rules', [ 'min_length' => 12 ] + clu_get_password_rules() );
$saved = clu_default_messages();
update_option( 'clu_messages', $saved ); // What the admin's "Save Messages" stores.
update_option( 'clu_password_rules', [ 'min_length' => 15 ] + clu_get_password_rules() );
t( 'the min-length message follows the rule after messages were saved', strpos( clu_get_message( 'password_min_length' ), '15' ) !== false, clu_get_message( 'password_min_length' ) );
delete_option( 'clu_messages' );
t( 'default special-character message has balanced brackets', substr_count( clu_get_message( 'password_special_char' ), '(' ) === substr_count( clu_get_message( 'password_special_char' ), ')' ), clu_get_message( 'password_special_char' ) );

reset_state();
section( '5. Registration' );
update_option( 'users_can_register', 0 );
$email = 'clu-closed@example.test';
$res   = register( [ 'email' => $email ] );
t( 'closed registration refuses the AJAX request too', ! get_user_by( 'email', $email ), 'user was created while registration is closed' );
update_option( 'users_can_register', 1 );
$email = 'clu-hebrew@example.test';
$res   = register( [ 'email' => $email, 'first_name' => 'ישראל', 'last_name' => 'ישראלי' ] );
$u     = get_user_by( 'email', $email );
t( 'Hebrew name gets a real username', $u && preg_match( '/[a-z0-9]/i', $u->user_login ), $u ? 'login = "' . $u->user_login . '"' : msg( $res ) );
t( 'Hebrew name gets a real nicename (author URL)', $u && $u->user_nicename !== '' && preg_match( '/[a-z0-9]/i', $u->user_nicename ), $u ? 'nicename = "' . $u->user_nicename . '"' : '' );
$email = 'clu-long@example.test';
$res   = register( [ 'email' => $email, 'first_name' => str_repeat( 'a', 40 ), 'last_name' => str_repeat( 'b', 40 ) ] );
t( 'long names do not break registration', ! empty( $res['success'] ), msg( $res ) );
$email = 'clu-missing@example.test';
$res   = register( [ 'email' => $email, 'company' => '' ] );
t( 'a required field is enforced on the server too', ! get_user_by( 'email', $email ) );
$res = register( [ 'email' => 'not-an-email' ] );
t( 'an invalid email is refused', empty( $res['success'] ) );
$res = register( [ 'email' => 'clu-hebrew@example.test' ] );
t( 'an email already registered is refused', empty( $res['success'] ) );
$email = 'clu-hp@example.test';
$res   = register( [ 'email' => $email, 'clu_website' => 'http://spam.test' ] );
t( 'honeypot: a filled trap field creates no user', ! get_user_by( 'email', $email ) );
reset_state();
update_option( 'clu_messages', [ 'notify_client_subject' => '50% off for %s' ] );
$res = register( [ 'email' => 'clu-pct@example.test' ] );
t( 'a % in the subject template does not crash registration', empty( $res['exception'] ) && ! empty( $res['success'] ), msg( $res ) );
reset_state();
$GLOBALS['clu_mails'] = [];
$res = register( [ 'email' => 'clu-noadmin@example.test' ] );
$m   = last_mail();
t( 'with no address configured, admin_email is notified', $m && $m['to'] === get_option( 'admin_email' ), $m ? 'to = "' . print_r( $m['to'], true ) . '"' : 'no mail' );
reset_state();
$limited = false;
for ( $i = 0; $i < 40; $i++ ) {
    $res = register( [ 'email' => "clu-flood$i@example.test" ] );
    if ( empty( $res['success'] ) ) { $limited = true; break; }
}
t( 'a registration flood from one IP gets limited', $limited, '40 registrations went through' );

reset_state();
section( '6. Login responses' );
make_user( 'clu_known', 'clu-known@example.test', 'Start1!pass', approved_role() );
$a = login( 'clu_known', 'wrong' );
$b = login( 'clu_nobody_' . wp_generate_password( 6, false ), 'wrong' );
t( 'wrong password and unknown user answer the same', msg( $a ) === msg( $b ), msg( $a ) . ' | ' . msg( $b ) );
t( 'login failure is a failure', empty( $a['success'] ) && empty( $b['success'] ) );
$res = login( 'clu_known', 'Start1!pass', [ 'redirect_to' => home_url( '/some-page/' ) ] );
t( 'a local redirect_to is honoured', ( $res['data']['redirect'] ?? '' ) === home_url( '/some-page/' ), print_r( $res['data']['redirect'] ?? null, true ) );
$res = login( 'clu_known', 'Start1!pass', [ 'redirect_to' => 'https://evil.test/' ] );
t( 'a foreign redirect_to is ignored', strpos( (string) ( $res['data']['redirect'] ?? '' ), 'evil.test' ) === false );
t( 'login with no settings saved still returns a redirect', ! empty( $res['data']['redirect'] ), print_r( $res['data']['redirect'] ?? null, true ) );
$res = ajax( 'ajax_login_user', [ 'log' => 'x', 'pwd' => 'y', 'login_nonce' => 'bad' ] );
t( 'a bad nonce is refused', empty( $res['success'] ) );
$res = ajax( 'ajax_login_user', [ 'login_nonce' => nonce_for( 'login_form_nonce' ) ] );
t( 'missing fields do not cause warnings or broken JSON', empty( $res['not_json'] ) );

reset_state();
section( '7. Lost password' );
make_user( 'clu_lost', 'clu-lost@example.test', 'Start1!pass', approved_role() );
$a = lost( 'clu-lost@example.test' );
$b = lost( 'clu-nobody@example.test' );
t( 'known and unknown address answer the same', msg( $a ) === msg( $b ), msg( $a ) . ' | ' . msg( $b ) );
$m = mails_to( 'clu-lost@example.test' );
t( 'the known address gets the email', count( $m ) === 1 );
$GLOBALS['clu_mails'] = [];
for ( $i = 0; $i < 10; $i++ ) {
    lost( 'clu-lost@example.test' );
}
t( 'repeated requests do not flood one mailbox', count( mails_to( 'clu-lost@example.test' ) ) <= 3, count( mails_to( 'clu-lost@example.test' ) ) . ' emails' );
reset_state();
$plus_id = make_user( 'plususer', 'clu-plus@example.test', 'Start1!pass', approved_role() );
$wpdb->update( $wpdb->users, [ 'user_login' => 'plus+user@example.test' ], [ 'ID' => $plus_id ] );
clean_user_cache( $plus_id );
wp_cache_flush();
lost( 'clu-plus@example.test' );
$link = link_from_mail( last_mail() );
$args = args_from_link( $link );
t( 'a login with + survives the reset link', ( $args['login'] ?? '' ) === 'plus+user@example.test', 'login in link decodes to "' . ( $args['login'] ?? '' ) . '"' );
reset_state();
make_user( 'space user', 'clu-space@example.test', 'Start1!pass', approved_role() );
lost( 'clu-space@example.test' );
$link = link_from_mail( last_mail() );
t( 'a login with a space is encoded in the link', $link && strpos( $link, ' ' ) === false, (string) $link );

reset_state();
section( '8. Reset link lifetime' );
t( 'other sites/users keep the 1 day default', apply_filters( 'password_reset_expiration', DAY_IN_SECONDS ) === DAY_IN_SECONDS, (string) apply_filters( 'password_reset_expiration', DAY_IN_SECONDS ) );
$id = make_user( 'clu_age', 'clu-age@example.test', 'Start1!pass', pending_role() );
$GLOBALS['clu_mails'] = [];
get_userdata( $id )->set_role( approved_role() );
$args = args_from_link( link_from_mail( last_mail() ) );
$age  = function ( $seconds ) use ( $id, $wpdb ) {
    $row = $wpdb->get_var( $wpdb->prepare( "SELECT user_activation_key FROM {$wpdb->users} WHERE ID=%d", $id ) );
    list( , $hash ) = explode( ':', $row, 2 );
    $wpdb->update( $wpdb->users, [ 'user_activation_key' => ( time() - $seconds ) . ':' . $hash ], [ 'ID' => $id ] );
    clean_user_cache( $id );
};
$age( 3 * DAY_IN_SECONDS );
$html = render_shortcode_safe( $SC_SET, [ 'key' => $args['key'] ?? '', 'login' => $args['login'] ?? '' ] );
t( 'an approval link still works after 3 days', strpos( $html, 'id="setpassform"' ) !== false && strpos( $html, 'expired' ) === false, substr( wp_strip_all_tags( $html ), 0, 120 ) );
$age( 8 * DAY_IN_SECONDS );
$html = render_shortcode_safe( $SC_SET, [ 'key' => $args['key'] ?? '', 'login' => $args['login'] ?? '' ] );
t( 'an approval link expires after 7 days', strpos( $html, 'id="setpassform"' ) === false, substr( wp_strip_all_tags( $html ), 0, 120 ) );
$GLOBALS['clu_mails'] = [];
lost( 'clu-age@example.test' );
$args = args_from_link( link_from_mail( last_mail() ) );
$age( 3 * DAY_IN_SECONDS );
$html = render_shortcode_safe( $SC_SET, [ 'key' => $args['key'] ?? '', 'login' => $args['login'] ?? '' ] );
t( 'an ordinary reset link has expired after 3 days', strpos( $html, 'id="setpassform"' ) === false, 'a 3 day old reset link still shows the form' );
$html = render_shortcode_safe( $SC_SET, [ 'key' => 'bad', 'login' => 'nobody' ] );
t( 'an invalid link hides the form', strpos( $html, 'id="setpassform"' ) === false );
t( 'an invalid link shows its message as text, not escaped tags', strpos( $html, '&lt;' ) === false, substr( $html, 0, 200 ) );

reset_state();
section( '9. Hostile or missing request data' );
foreach ( [ $SC_LOGIN => [ 'login' => [ 'x' ] ], $SC_REG => [ 'register-errors' => [ 'x' ] ], $SC_LOST => [ 'errors' => [ 'x' ] ], $SC_SET => [ 'login' => [ 'x' ], 'key' => [ 'y' ] ] ] as $code => $get ) {
    wp_set_current_user( 0 );
    $html = render_shortcode_safe( $code, $get );
    t( "$code survives array parameters", strpos( $html, 'EXCEPTION' ) !== 0, $html );
}
$res = ajax( 'ajax_login_user', [ 'login_nonce' => [ 'x' ], 'log' => [ 'x' ], 'pwd' => [ 'y' ] ] );
t( 'array fields in a POST do not crash', empty( $res['exception'] ), msg( $res ) );
$res = lost( [ 'x' ] );
t( 'array user_login in lost password does not crash', empty( $res['exception'] ), msg( $res ) );

reset_state();
section( '10. Shortcodes render with nothing configured' );
foreach ( [ $SC_LOGIN, $SC_REG, $SC_LOST, $SC_AUTH, $SC_ACC ] as $code ) {
    wp_set_current_user( 0 );
    $html = render_shortcode_safe( $code );
    t( "$code renders", $html !== '' && strpos( $html, 'EXCEPTION' ) !== 0 && $html !== $code, substr( $html, 0, 200 ) );
}
$id = make_user( 'clu_in', 'clu-in@example.test', 'Start1!pass', approved_role() );
wp_set_current_user( $id );
foreach ( [ $SC_LOGIN, $SC_AUTH, $SC_ACC ] as $code ) {
    $html = render_shortcode_safe( $code );
    t( "$code renders for a logged-in user", $html !== '' && strpos( $html, 'EXCEPTION' ) !== 0, substr( $html, 0, 200 ) );
}
$html = render_shortcode_safe( $SC_LOGIN );
t( 'the logout link in "already logged in" is usable', (bool) preg_match( '/href="[^"]*action=logout[^"]*"/', $html ), substr( $html, 0, 300 ) );
wp_set_current_user( 0 );
$html = render_shortcode_safe( $SC_LOGIN );
t( 'the forgot-password link is never empty', ! preg_match( '/class="forgot-password" href=""/', $html ), '' );
foreach ( [ 'clu_login_form', 'clu_register_form', 'clu_password_lost_form', 'clu_password_set_form', 'clu_auth_buttons' ] as $tag ) {
    t( "the [$tag] shortcode from the README exists", shortcode_exists( $tag ) );
}
$html = render_shortcode_safe( $SC_REG );
t( 'the register form contains no inline script', stripos( $html, '<script' ) === false );

reset_state();
section( '11. Roles' );
wp_set_current_user( 1 );
$roles_nonce = wp_create_nonce( 'clu_roles_nonce' );
$res = ajax( 'clu_save_roles', [ 'nonce' => $roles_nonce, 'pending_role' => 'administrator', 'approved_role' => 'approved_user' ], 1 );
t( 'administrator cannot be the registration role', empty( $res['success'] ), msg( $res ) );
wp_set_current_user( 1 );
$res = ajax( 'clu_save_roles', [ 'nonce' => $roles_nonce, 'pending_role' => 'pending_user', 'approved_role' => 'administrator' ], 1 );
t( 'administrator cannot be the approval role', empty( $res['success'] ), msg( $res ) );
delete_option( 'clu_roles' );
update_option( 'clu_roles', [ 'pending_role' => 'administrator', 'approved_role' => 'approved_user' ] );
wp_set_current_user( 0 );
$email = 'clu-admin-role@example.test';
$res   = register( [ 'email' => $email ] );
$u     = get_user_by( 'email', $email );
t( 'an old saved privileged role never reaches a public sign-up', $u && ! in_array( 'administrator', $u->roles, true ), $u ? implode( ',', $u->roles ) : msg( $res ) );
delete_option( 'clu_roles' );
make_user( 'clu_role_holder', 'clu-rh@example.test', 'Start1!pass', 'pending_user' );
wp_set_current_user( 1 );
$res = ajax( 'clu_delete_role', [ 'nonce' => $roles_nonce, 'role_slug' => 'pending_user' ], 1 );
t( 'a role in use cannot be deleted', empty( $res['success'] ) && get_role( 'pending_user' ), msg( $res ) );
wp_set_current_user( 1 );
add_role( 'clu_tmp_role', 'Tmp' );
$res = ajax( 'clu_delete_role', [ 'nonce' => $roles_nonce, 'role_slug' => 'clu_tmp_role' ], 1 );
t( 'an unused custom role can be deleted', ! empty( $res['success'] ) && ! get_role( 'clu_tmp_role' ), msg( $res ) );
t( 'roles are not re-added on every request', ! has_action( 'init', 'add_custom_user_role' ) );
wp_set_current_user( 0 );
$res = ajax( 'clu_save_roles', [ 'nonce' => $roles_nonce, 'pending_role' => 'subscriber', 'approved_role' => 'subscriber' ] );
t( 'admin AJAX actions are not open to visitors', empty( $res['success'] ) );

reset_state();
section( '12. Profile fields in wp-admin' );
if ( $bare ) {
$id = make_user( 'clu_prof', 'clu-prof@example.test', 'Start1!pass', 'subscriber' );
wp_set_current_user( 1 );
$_POST = wp_slash( [ 'company' => '<script>alert(1)</script>Acme', 'customer_type' => 'X', 'region' => 'Y', 'country' => 'Z' ] );
do_action( 'edit_user_profile_update', $id );
t( 'company is sanitized when saved from wp-admin', strpos( (string) get_user_meta( $id, 'company', true ), '<script' ) === false, (string) get_user_meta( $id, 'company', true ) );
$_POST = [];
update_user_meta( $id, 'company', 'Keep' );
do_action( 'edit_user_profile_update', $id );
t( 'a save without the field keeps the value', get_user_meta( $id, 'company', true ) === 'Keep', (string) get_user_meta( $id, 'company', true ) );
wp_set_current_user( 0 );
}


reset_state();
section( '13. Front-end-only roles' );
$approved_id = make_user( 'clu_fe_approved', 'clu-fe-a@example.test', 'Start1!pass', approved_role() );
$plain_id    = make_user( 'clu_fe_plain', 'clu-fe-p@example.test', 'Start1!pass', 'subscriber' );
add_filter( 'wp_doing_ajax', '__return_false', 99 );
add_filter( 'wp_redirect', function () { throw new CLU_Die(); } );
$redirected = function ( $user_id ) {
    wp_set_current_user( $user_id );
    try {
        clu_restrict_dashboard_access();
    } catch ( CLU_Die $e ) {
        return true;
    }
    return false;
};
t( 'an approved user is sent away from the dashboard', $redirected( $approved_id ) );
t( 'a user with another role is not', ! $redirected( $plain_id ) );
t( 'an administrator is not', ! $redirected( 1 ) );
remove_all_filters( 'wp_redirect' );
remove_filter( 'wp_doing_ajax', '__return_false', 99 );
wp_set_current_user( $approved_id );
t( 'an approved user has no admin bar', apply_filters( 'show_admin_bar', true ) === false );
wp_set_current_user( 0 );
if ( $bare ) {
    wp_set_current_user( $plain_id );
    t( 'a user with another role keeps it', apply_filters( 'show_admin_bar', true ) === true );
    wp_set_current_user( 0 );
    foreach ( [ 'custom-login-form', 'custom-register-form', 'custom-password-lost-form', 'custom-password-set', 'custom-auth-buttons', 'my-account' ] as $tag ) {
        t( "the older shortcode name [$tag] still works", shortcode_exists( $tag ) );
    }
}

reset_state();
section( '14. URLs' );
add_filter( 'pre_option_home', function () { return 'http://clu-http.example'; } );
$u = clu_normalize_site_url( '/some-page/' );
t( 'a site on plain http keeps http links', strpos( $u, 'http://clu-http.example' ) === 0, $u );
$u = clu_normalize_site_url( 'javascript:alert(1)', 'http://clu-http.example/fallback/' );
t( 'a javascript: address in the settings cannot become a link', stripos( $u, 'javascript' ) === false, $u );
remove_all_filters( 'pre_option_home' );

reset_state();
section( '15. Approve and reject' );
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

/** Runs the users-screen handler the way a click does. Returns what it redirected to, or 'died'. */
function decide( $action, $user_ids, $nonce = null, $as = 1 ) {
    wp_set_current_user( $as );
    $_REQUEST = [ 'action' => $action, 'users' => (array) $user_ids, '_wpnonce' => $nonce === null ? wp_create_nonce( 'bulk-users' ) : $nonce ];
    $_GET     = $_REQUEST;
    $target   = 'nothing';
    $catch    = function ( $location ) use ( &$target ) {
        $target = $location;
        throw new CLU_Die();
    };
    add_filter( 'wp_redirect', $catch );
    try {
        clu_handle_user_decisions();
    } catch ( CLU_Die $e ) {
        if ( $target === 'nothing' ) {
            $target = 'died';
        }
    }
    remove_filter( 'wp_redirect', $catch );
    $_REQUEST = $_GET = [];
    wp_set_current_user( 0 );
    return $target;
}

function query_of( $url ) {
    parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $args );
    return $args;
}

$u1 = make_user( 'clu_d1', 'clu-d1@example.test', 'Start1!pass', pending_role() );
$u2 = make_user( 'clu_d2', 'clu-d2@example.test', 'Start1!pass', pending_role() );
$u3 = make_user( 'clu_d3', 'clu-d3@example.test', 'Start1!pass', pending_role() );
$plain = make_user( 'clu_d_plain', 'clu-dp@example.test', 'Start1!pass', 'subscriber' );

wp_set_current_user( 1 );
$actions = apply_filters( 'user_row_actions', [ 'edit' => 'x' ], get_userdata( $u1 ) );
t( 'a pending user gets Approve and Reject links', isset( $actions['clu_approve'], $actions['clu_reject'], $actions['edit'] ) );
$actions = apply_filters( 'user_row_actions', [], get_userdata( $plain ) );
t( 'a user with another role gets neither', ! isset( $actions['clu_approve'] ) && ! isset( $actions['clu_reject'] ) );
$bulk = apply_filters( 'bulk_actions-users', [] );
t( 'both are bulk actions too', isset( $bulk['clu_approve'], $bulk['clu_reject'] ) );
wp_set_current_user( $plain );
$actions = apply_filters( 'user_row_actions', [], get_userdata( $u1 ) );
t( 'someone who may not change roles gets no links', ! isset( $actions['clu_approve'] ) && ! apply_filters( 'bulk_actions-users', [] ) );
wp_set_current_user( 0 );

t( 'a request with a bad nonce does nothing', decide( 'clu_approve', $u1, 'bad' ) === 'died' && in_array( pending_role(), get_userdata( $u1 )->roles, true ) );
$GLOBALS['clu_mails'] = [];
$target = decide( 'clu_approve', $u1, null, $plain );
t( 'a subscriber cannot approve', in_array( pending_role(), get_userdata( $u1 )->roles, true ) && ( query_of( $target )['clu_skipped'] ?? 0 ) == 1 && ! $GLOBALS['clu_mails'], $target );
$target = decide( 'other_action', $u1 );
t( 'other actions on the screen are left alone', $target === 'nothing' );

$GLOBALS['clu_mails'] = [];
$target = decide( 'clu_approve', $u1 );
$mail   = last_mail();
t( 'approve changes the role', in_array( approved_role(), get_userdata( $u1 )->roles, true ) );
t( 'approve emails the set-password link', $mail && $mail['to'] === 'clu-d1@example.test' && strpos( $mail['message'], 'key=' ) !== false );
t( 'the redirect reports it', ( query_of( $target )['clu_approved'] ?? 0 ) == 1, $target );
t( 'the result does not stay in the address', in_array( 'clu_approved', wp_removable_query_args(), true ) );

$GLOBALS['clu_mails'] = [];
$target = decide( 'clu_reject', $u2 );
$mail   = last_mail();
$rejected_role = clu_get_role_slug( 'rejected_role' );
t( 'reject moves the user to the rejected role', in_array( $rejected_role, get_userdata( $u2 )->roles, true ) && ! in_array( pending_role(), get_userdata( $u2 )->roles, true ) );
t( 'reject emails the user', count( $GLOBALS['clu_mails'] ) === 1 && $mail['to'] === 'clu-d2@example.test' && strpos( $mail['subject'], 'not approved' ) !== false, $mail ? $mail['subject'] : 'no mail' );
t( 'the email holds no reset link', strpos( $mail['message'], 'key=' ) === false );
t( 'the redirect reports it', ( query_of( $target )['clu_rejected'] ?? 0 ) == 1, $target );

wp_set_password( 'Start2!pass', $u2 );
$res = login( 'clu_d2', 'Start2!pass' );
t( 'a rejected user cannot log in', empty( $res['success'] ) && strpos( msg( $res ), 'not approved' ) !== false, msg( $res ) );
t( '...through any form of the site', is_wp_error( wp_authenticate( 'clu_d2', 'Start2!pass' ) ) );
$res = login( 'clu_d2', 'wrong' );
t( 'a wrong password reveals nothing about the status', strpos( msg( $res ), 'not approved' ) === false, msg( $res ) );
$GLOBALS['clu_mails'] = [];
$res = lost( 'clu-d2@example.test' );
t( 'a rejected user gets no reset link', ! $GLOBALS['clu_mails'] && empty( $res['success'] ), msg( $res ) );
t( 'core refuses a reset key too', is_wp_error( get_password_reset_key( get_userdata( $u2 ) ) ) );
$plain_key = wp_generate_password( 20, false );
$wpdb->update( $wpdb->users, [ 'user_activation_key' => time() . ':' . wp_fast_hash( $plain_key ) ], [ 'ID' => $u2 ] );
clean_user_cache( $u2 );
$res = set_password( 'clu_d2', $plain_key, 'Str0ng!Passw0rd' );
t( 'a key from before the rejection stops working', empty( $res['success'] ), msg( $res ) );

wp_set_current_user( 1 );
$actions = apply_filters( 'user_row_actions', [], get_userdata( $u2 ) );
t( 'a rejected user can still be approved, and not rejected again', isset( $actions['clu_approve'] ) && ! isset( $actions['clu_reject'] ) );
wp_set_current_user( 0 );
$GLOBALS['clu_mails'] = [];
decide( 'clu_approve', $u2 );
$args = args_from_link( link_from_mail( last_mail() ) );
t( 'approving a rejected user sends the set-password link', in_array( approved_role(), get_userdata( $u2 )->roles, true ) && ! empty( $args['key'] ) );
$res = set_password( 'clu_d2', $args['key'] ?? '', 'Str0ng!Passw0rd' );
t( '...and that link works', ! empty( $res['success'] ), msg( $res ) );
wp_set_current_user( 0 );

$u4 = make_user( 'clu_d4', 'clu-d4@example.test', 'Start1!pass', pending_role() );
$GLOBALS['clu_mails'] = [];
$target = decide( 'clu_reject', [ $u3, $u4, $plain, 1, 999999 ] );
$q = query_of( $target );
t( 'a bulk action handles several users at once', ( $q['clu_rejected'] ?? 0 ) == 2 && count( $GLOBALS['clu_mails'] ) === 2, $target );
t( 'a bulk action skips anyone not waiting', ( $q['clu_skipped'] ?? 0 ) == 3 && in_array( 'subscriber', get_userdata( $plain )->roles, true ) && user_can( 1, 'manage_options' ), $target );
wp_set_current_user( 0 );

$u5 = make_user( 'clu_d5', 'clu-d5@example.test', 'Start1!pass', pending_role() );
$fail = function () { return false; };
add_filter( 'pre_wp_mail', $fail, 20 );
$target = decide( 'clu_approve', $u5 );
remove_filter( 'pre_wp_mail', $fail, 20 );
$q = query_of( $target );
t( 'a failed email is reported, and the decision still stands', ( $q['clu_mail_failed'] ?? 0 ) == 1 && ( $q['clu_approved'] ?? 0 ) == 1 && in_array( approved_role(), get_userdata( $u5 )->roles, true ), $target );

$_GET = [ 'clu_approved' => '1', 'clu_rejected' => '2', 'clu_mail_failed' => '1', 'clu_skipped' => '3' ];
$GLOBALS['pagenow'] = 'users.php';
ob_start();
clu_show_decision_notices();
$notices = ob_get_clean();
$GLOBALS['pagenow'] = 'index.php';
$_GET = [];
t( 'the screen shows what happened', substr_count( $notices, 'class="notice' ) === 4, $notices );

$u6 = make_user( 'clu_d6', 'clu-d6@example.test', 'Start1!pass', pending_role() );
$GLOBALS['clu_mails'] = [];
get_userdata( $u6 )->set_role( $rejected_role );
t( 'choosing the role on the user screen sends the same email', count( $GLOBALS['clu_mails'] ) === 1 && last_mail()['to'] === 'clu-d6@example.test' );
$u7 = make_user( 'clu_d7', 'clu-d7@example.test', 'Start1!pass', approved_role() );
$manager = WP_Session_Tokens::get_instance( $u7 );
$manager->create( time() + 3600 );
$GLOBALS['clu_mails'] = [];
get_userdata( $u7 )->set_role( $rejected_role );
t( 'moving an approved user there sends no rejection email', ! $GLOBALS['clu_mails'] );
t( '...and logs them out', ! $manager->get_all() );
wp_set_current_user( $u7 );
t( 'a rejected user is kept out of the dashboard too', clu_current_user_is_front_end_only() );
wp_set_current_user( 0 );

$u8 = make_user( 'clu_d8', 'clu-d8@example.test', 'Start1!pass', pending_role() );
add_filter( 'clu_send_rejection_email', '__return_false' );
$GLOBALS['clu_mails'] = [];
$target = decide( 'clu_reject', $u8 );
remove_filter( 'clu_send_rejection_email', '__return_false' );
t( 'the rejection email can be turned off, and that is no failure', ! $GLOBALS['clu_mails'] && empty( query_of( $target )['clu_mail_failed'] ), $target );
update_option( 'clu_messages', [ 'notify_rejected_subject' => '100% no for %s' ] );
$u9 = make_user( 'clu_d9', 'clu-d9@example.test', 'Start1!pass', pending_role() );
$GLOBALS['clu_mails'] = [];
decide( 'clu_reject', $u9 );
t( 'a % in the rejection subject is safe', last_mail() && last_mail()['subject'] === '100% no for Test User', last_mail() ? last_mail()['subject'] : 'no mail' );
delete_option( 'clu_messages' );

reset_state();
wp_set_current_user( 1 );
$res = ajax( 'clu_save_roles', [ 'nonce' => wp_create_nonce( 'clu_roles_nonce' ), 'pending_role' => 'rejected_user', 'approved_role' => 'approved_user' ], 1 );
t( 'the rejected role cannot be the pending role', empty( $res['success'] ), msg( $res ) );
wp_set_current_user( 1 );
$res = ajax( 'clu_delete_role', [ 'nonce' => wp_create_nonce( 'clu_roles_nonce' ), 'role_slug' => 'rejected_user' ], 1 );
t( 'the rejected role cannot be deleted', empty( $res['success'] ) && get_role( 'rejected_user' ), msg( $res ) );
ob_start();
clu_render_roles_tab();
$tab = ob_get_clean();
t( 'the Roles tab does not offer it', strpos( $tab, 'value="rejected_user"' ) === false );
wp_set_current_user( 0 );

section( '16. The count of users awaiting approval' );
reset_state();
foreach ( get_users( [ 'role' => pending_role(), 'fields' => 'ID' ] ) as $uid ) {
    get_userdata( $uid )->set_role( 'subscriber' );
}
t( 'it starts at zero', clu_get_pending_count() === 0, (string) clu_get_pending_count() );
t( 'zero is remembered', get_transient( 'clu_pending_count' ) !== false );
$res = register( [ 'email' => 'clu-count1@example.test' ] );
$res = register( [ 'email' => 'clu-count2@example.test' ] );
t( 'it follows new registrations', clu_get_pending_count() === 2, (string) clu_get_pending_count() );
$cu1 = get_user_by( 'email', 'clu-count1@example.test' );
decide( 'clu_approve', $cu1->ID );
t( 'it follows decisions', clu_get_pending_count() === 1, (string) clu_get_pending_count() );
$cu2 = get_user_by( 'email', 'clu-count2@example.test' );
wp_delete_user( $cu2->ID );
t( 'it follows deleted users', clu_get_pending_count() === 0, (string) clu_get_pending_count() );

$res = register( [ 'email' => 'clu-count3@example.test' ] );
$GLOBALS['menu']    = [ 70 => [ 'Users', 'list_users', 'users.php', '', 'menu-top', 'menu-users', 'dashicons-admin-users' ] ];
$GLOBALS['submenu'] = [];
wp_set_current_user( 1 );
clu_add_pending_menu();
t( 'the Users menu shows the count', strpos( $GLOBALS['menu'][70][0], 'awaiting-mod' ) !== false && strpos( $GLOBALS['menu'][70][0], '>1<' ) !== false, $GLOBALS['menu'][70][0] );
$sub = end( $GLOBALS['submenu']['users.php'] ) ?: [];
t( 'the menu links to the list of those users', ( $sub[2] ?? '' ) === 'users.php?role=' . pending_role(), print_r( $sub, true ) );
$GLOBALS['menu'] = [ 70 => [ 'Users', 'list_users', 'users.php' ] ];
$GLOBALS['submenu'] = [];
wp_set_current_user( $plain );
clu_add_pending_menu();
t( 'someone who may not decide does not see it', $GLOBALS['menu'][70][0] === 'Users' && ! $GLOBALS['submenu'] );
wp_set_current_user( 0 );
unset( $GLOBALS['menu'], $GLOBALS['submenu'] );

section( '17. Register fields' );

/** Saves a field list through the settings screen's handler. */
function save_fields( $rows ) {
    wp_set_current_user( 1 );
    $nonce = wp_create_nonce( 'clu_register_fields_nonce' );
    $res   = ajax( 'clu_save_register_fields', [ 'nonce' => $nonce, 'fields' => wp_json_encode( $rows ) ], 1 );
    wp_set_current_user( 0 );
    return $res;
}

/** A row of the field list, changed from what is saved now. */
function field_row( $key, $over = [] ) {
    foreach ( clu_get_register_fields() as $field ) {
        if ( $field['key'] === $key ) {
            return array_merge( $field, $over );
        }
    }
    return array_merge( [ 'key' => $key, 'label' => 'New', 'type' => 'text', 'enabled' => true, 'required' => false, 'account' => false, 'placeholder' => '', 'options' => [] ], $over );
}

function all_rows( $changes = [], $extra = [] ) {
    $rows = [];
    foreach ( clu_get_register_fields() as $field ) {
        $rows[] = array_merge( $field, $changes[ $field['key'] ] ?? [] );
    }
    return array_merge( $rows, $extra );
}

reset_state();
delete_option( 'clu_register_fields' );
$keys = array_column( clu_get_register_form_fields(), 'key' );
t( 'a new site asks only for names and email', $keys === [ 'first_name', 'last_name', 'email' ], implode( ',', $keys ) );
$res = ajax( 'ajax_register_user', [ 'register_nonce' => nonce_for( 'register_form_nonce' ), 'first_name' => 'A', 'last_name' => 'B', 'email' => 'clu-new@example.test' ] );
t( '...and registers with just those', ! empty( $res['success'] ) && get_user_by( 'email', 'clu-new@example.test' ), msg( $res ) );
$html = render_shortcode_safe( $SC_REG );
t( '...and the form has just those', strpos( $html, 'name="first_name"' ) !== false && strpos( $html, 'name="company"' ) === false && strpos( $html, 'name="region"' ) === false, substr( $html, 0, 300 ) );

reset_state();
$res = save_fields( all_rows( [
    'company'       => [ 'enabled' => false ],
    'customer_type' => [ 'required' => false ],
    'region'        => [ 'enabled' => false ],
    'country'       => [ 'enabled' => false ],
] ) );
t( 'the handler saves', ! empty( $res['success'] ), msg( $res ) );
$html = render_shortcode_safe( $SC_REG );
t( 'a field turned off leaves the form', strpos( $html, 'name="company"' ) === false );
$email = 'clu-off@example.test';
$res   = register( [ 'email' => $email, 'company' => 'Should not stay', 'customer_type' => '' ] );
$u     = get_user_by( 'email', $email );
t( 'a field turned off is not required', ! empty( $res['success'] ), msg( $res ) );
t( '...and what is posted for it is ignored', $u && get_user_meta( $u->ID, 'company', true ) === '' );
t( 'an optional field may stay empty', $u && get_user_meta( $u->ID, 'customer_type', true ) === '' );
$html = render_shortcode_safe( $SC_REG );
t( 'an optional field is not marked required', (bool) preg_match( '/<select name="customer_type" id="customer_type">/', $html ), substr( $html, 0, 400 ) );

reset_state();
$res = save_fields( all_rows( [ 'email' => [ 'enabled' => false, 'required' => false, 'account' => false ], 'first_name' => [ 'enabled' => false, 'label' => 'Nope' ], 'last_name' => [ 'enabled' => false ], 'company' => [ 'enabled' => false ], 'customer_type' => [ 'enabled' => false ], 'region' => [ 'enabled' => false ], 'country' => [ 'enabled' => false ] ] ) );
$email_field = array_values( array_filter( clu_get_register_fields(), function ( $f ) { return $f['key'] === 'email'; } ) )[0];
t( 'the email cannot be turned off', $email_field['enabled'] && $email_field['required'] && $email_field['account'] );
$email = 'dana.only@example.test';
$res   = ajax( 'ajax_register_user', [ 'register_nonce' => nonce_for( 'register_form_nonce' ), 'email' => $email ] );
$u     = get_user_by( 'email', $email );
t( 'a form with only an email works', ! empty( $res['success'] ) && $u, msg( $res ) );
t( '...the username comes from the email', $u && strpos( $u->user_login, 'dana.only' ) === 0, $u ? $u->user_login : '' );
t( '...and so does the display name', $u && $u->display_name !== '', $u ? $u->display_name : '' );
wp_set_current_user( 0 );

reset_state();
$res = save_fields( all_rows( [ 'email' => [ 'label' => 'כתובת דוא"ל' ] ], [
    [ 'key' => '', 'label' => 'Phone number', 'type' => 'tel', 'enabled' => true, 'required' => true, 'account' => true ],
    [ 'key' => '', 'label' => 'מספר עובדים', 'type' => 'number', 'enabled' => true, 'required' => false, 'account' => false ],
    [ 'key' => '', 'label' => 'Website', 'type' => 'url', 'enabled' => true, 'required' => false, 'account' => false ],
    [ 'key' => '', 'label' => 'Notes', 'type' => 'textarea', 'enabled' => true, 'required' => false, 'account' => false ],
    [ 'key' => '', 'label' => 'Plan', 'type' => 'select', 'enabled' => true, 'required' => true, 'account' => false, 'options' => [ 'Basic', 'Pro', ' ', 'Pro' ] ],
    [ 'key' => '', 'label' => 'I agree to the <a href="/terms/" target="_blank">terms</a><script>x</script>', 'type' => 'checkbox', 'enabled' => true, 'required' => true, 'account' => false ],
] ) );
t( 'new fields are saved', ! empty( $res['success'] ), msg( $res ) );
$keys = $res['data']['keys'] ?? [];
t( 'the screen learns their keys', in_array( 'clu_field_phone_number', $keys, true ), implode( ',', $keys ) );
t( 'a label in Hebrew still gets a key', in_array( 'clu_field_custom', $keys, true ), implode( ',', $keys ) );
$plan = array_values( array_filter( clu_get_register_fields(), function ( $f ) { return $f['key'] === 'clu_field_plan'; } ) )[0] ?? [];
t( 'dropdown options are cleaned', ( $plan['options'] ?? [] ) === [ 'Basic', 'Pro' ], print_r( $plan['options'] ?? null, true ) );
$terms = array_values( array_filter( clu_get_register_fields(), function ( $f ) { return $f['type'] === 'checkbox'; } ) )[0] ?? [];
t( 'a checkbox label keeps its link and loses anything else', strpos( $terms['label'] ?? '', '<a href="/terms/"' ) !== false && strpos( $terms['label'] ?? '', '<script' ) === false, $terms['label'] ?? '' );
$terms_key = $terms['key'] ?? '';
$html = render_shortcode_safe( $SC_REG );
t( 'new fields reach the form', strpos( $html, 'type="tel"' ) !== false && strpos( $html, 'name="clu_field_plan"' ) !== false && strpos( $html, '<textarea' ) !== false );
t( 'the checkbox label shows its link', strpos( $html, '<a href="/terms/"' ) !== false && strpos( $html, '<script' ) === false );
t( 'a label from the settings is escaped', strpos( $html, 'כתובת דוא&quot;ל' ) !== false || strpos( $html, 'כתובת דוא"ל' ) !== false );

$ok = [ 'clu_field_phone_number' => '+972 (50) 123-4567', 'clu_field_plan' => 'Pro', $terms_key => '1' ];
$email = 'clu-custom@example.test';
$GLOBALS['clu_mails'] = [];
$res = register( array_merge( $ok, [
    'email'                 => $email,
    'clu_field_custom'      => '12',
    'clu_field_website'     => 'example.com',
    'clu_field_notes'       => "line one\nline two<script>x</script>",
] ) );
$u = get_user_by( 'email', $email );
t( 'a registration with custom fields succeeds', ! empty( $res['success'] ) && $u, msg( $res ) );
t( 'the values are saved under their keys', $u && get_user_meta( $u->ID, 'clu_field_plan', true ) === 'Pro' && get_user_meta( $u->ID, 'clu_field_custom', true ) === '12' && get_user_meta( $u->ID, $terms_key, true ) === '1' );
t( 'a web address is completed', $u && get_user_meta( $u->ID, 'clu_field_website', true ) === 'http://example.com', $u ? get_user_meta( $u->ID, 'clu_field_website', true ) : '' );
t( 'long text keeps its lines and loses tags', $u && get_user_meta( $u->ID, 'clu_field_notes', true ) === "line one\nline twox" || ( $u && strpos( get_user_meta( $u->ID, 'clu_field_notes', true ), '<script' ) === false && strpos( get_user_meta( $u->ID, 'clu_field_notes', true ), "\n" ) !== false ), $u ? get_user_meta( $u->ID, 'clu_field_notes', true ) : '' );
$admin_mail = $GLOBALS['clu_mails'][0] ?? null;
t( 'the admin email lists the details', $admin_mail && strpos( $admin_mail['message'], 'Phone number' ) !== false && strpos( $admin_mail['message'], '+972 (50) 123-4567' ) !== false && strpos( $admin_mail['message'], $email ) !== false, $admin_mail ? $admin_mail['message'] : 'no mail' );
t( 'it links to the list of users awaiting approval', $admin_mail && strpos( $admin_mail['message'], 'users.php?role=' . pending_role() ) !== false );

foreach ( [
    'a required custom field'  => [ 'clu_field_phone_number' => '' ],
    'a required checkbox'      => [ $terms_key => '' ],
    'a required dropdown'      => [ 'clu_field_plan' => '' ],
] as $label => $over ) {
    $email = 'clu-req' . wp_generate_password( 6, false ) . '@example.test';
    $res   = register( array_merge( $ok, $over, [ 'email' => $email ] ) );
    t( "$label is enforced on the server", empty( $res['success'] ) && ! get_user_by( 'email', $email ), msg( $res ) );
}
foreach ( [
    'a choice outside the dropdown' => [ 'clu_field_plan' => 'Free forever' ],
    'a number that is not one'      => [ 'clu_field_custom' => 'twelve' ],
    'a phone with letters'          => [ 'clu_field_phone_number' => 'call me' ],
    'a javascript: web address'     => [ 'clu_field_website' => 'javascript:alert(1)' ],
    'a web address with spaces'     => [ 'clu_field_website' => 'not a url' ],
    'a region outside the list'     => [ 'region' => 'Atlantis' ],
    'a country of another region'   => [ 'region' => 'Europe', 'country' => 'Japan' ],
] as $label => $over ) {
    $email = 'clu-bad' . wp_generate_password( 6, false ) . '@example.test';
    $res   = register( array_merge( $ok, $over, [ 'email' => $email ] ) );
    t( "$label is refused", empty( $res['success'] ) && ! get_user_by( 'email', $email ), msg( $res ) );
}
$res = register( array_merge( $ok, [ 'email' => 'clu-badnum@example.test', 'clu_field_custom' => 'x' ] ) );
t( 'the answer names the field', strpos( msg( $res ), 'מספר עובדים' ) !== false, msg( $res ) );
$email = 'clu-arr@example.test';
$res   = register( array_merge( $ok, [ 'email' => $email, 'clu_field_phone_number' => [ 'x' ] ] ) );
t( 'an array in a custom field does not crash', empty( $res['exception'] ) && empty( $res['success'] ), msg( $res ) );
$email = 'clu-forge@example.test';
$res   = register( array_merge( $ok, [ 'email' => $email, 'wp_capabilities' => 'administrator', 'role' => 'administrator', 'clu_field_other' => 'x' ] ) );
$u     = get_user_by( 'email', $email );
t( 'nothing outside the field list reaches the account', $u && ! user_can( $u, 'manage_options' ) && get_user_meta( $u->ID, 'clu_field_other', true ) === '' && in_array( pending_role(), $u->roles, true ), $u ? implode( ',', $u->roles ) : msg( $res ) );

$rows = all_rows();
$res  = save_fields( array_merge( $rows, [ [ 'key' => 'wp_capabilities', 'label' => 'Sneaky', 'type' => 'text', 'enabled' => true ] ] ) );
$keys = array_column( clu_get_register_fields(), 'key' );
t( 'a field cannot be saved under a key of its own choosing', ! in_array( 'wp_capabilities', $keys, true ) && in_array( 'clu_field_sneaky', $keys, true ), implode( ',', $keys ) );
$res = save_fields( all_rows( [ 'clu_field_sneaky' => [ 'label' => 'Renamed' ] ] ) );
$keys = array_column( clu_get_register_fields(), 'key' );
t( 'renaming a field keeps its key, and so its data', in_array( 'clu_field_sneaky', $keys, true ) && ! in_array( 'clu_field_renamed', $keys, true ) );
$res = save_fields( all_rows( [ 'company' => [ 'type' => 'checkbox' ] ] ) );
$company = array_values( array_filter( clu_get_register_fields(), function ( $f ) { return $f['key'] === 'company'; } ) )[0];
t( 'a built-in field keeps its type', $company['type'] === 'text' );
$before = get_option( 'clu_register_fields' );
$res = save_fields( all_rows( [ 'company' => [ 'label' => '  ' ] ] ) );
t( 'an empty label is refused, and nothing changes', empty( $res['success'] ) && get_option( 'clu_register_fields' ) === $before, msg( $res ) );
$res = save_fields( all_rows( [], [ [ 'key' => '', 'label' => 'Empty list', 'type' => 'select', 'enabled' => true, 'options' => [ '', ' ' ] ] ] ) );
t( 'a dropdown without options is refused', empty( $res['success'] ), msg( $res ) );
$reordered = array_reverse( all_rows() );
save_fields( $reordered );
$keys = array_column( clu_get_register_fields(), 'key' );
t( 'the order is kept', $keys === array_column( $reordered, 'key' ), implode( ',', $keys ) );
save_fields( array_slice( all_rows(), 0, 2 ) );
$keys = array_column( clu_get_register_fields(), 'key' );
t( 'a built-in field cannot be removed', count( array_intersect( [ 'first_name', 'last_name', 'email', 'company', 'customer_type', 'region', 'country' ], $keys ) ) === 7, implode( ',', $keys ) );
t( '...and a removed custom field is gone', ! in_array( 'clu_field_plan', $keys, true ) || in_array( 'clu_field_plan', array_column( array_slice( all_rows(), 0, 2 ), 'key' ), true ), implode( ',', $keys ) );
wp_set_current_user( 0 );
$res = ajax( 'clu_save_register_fields', [ 'nonce' => 'x', 'fields' => '[]' ] );
t( 'visitors cannot save fields', empty( $res['success'] ) );
$subscriber = make_user( 'clu_sub_f', 'clu-subf@example.test', 'Start1!pass', 'subscriber' );
wp_set_current_user( $subscriber );
$nonce = wp_create_nonce( 'clu_register_fields_nonce' );
$res = ajax( 'clu_save_register_fields', [ 'nonce' => $nonce, 'fields' => '[]' ], $subscriber );
t( 'a subscriber cannot save fields', empty( $res['success'] ), msg( $res ) );
wp_set_current_user( 1 );
$nonce = wp_create_nonce( 'clu_register_fields_nonce' );
$res = ajax( 'clu_save_register_fields', [ 'nonce' => $nonce, 'fields' => 'not json' ], 1 );
t( 'broken data is refused', empty( $res['success'] ) && empty( $res['not_json'] ), msg( $res ) );
wp_set_current_user( 0 );

reset_state();
$html = render_shortcode_safe( $SC_REG );
t( 'with a region field, the country list waits for the region', (bool) preg_match( '/name="country"[^>]*>\s*<option value="">[^<]*<\/option>\s*<\/select>/', $html ), substr( $html, strpos( $html, 'name="country"' ) ?: 0, 200 ) );
save_fields( all_rows( [ 'region' => [ 'enabled' => false ] ] ) );
$html = render_shortcode_safe( $SC_REG );
t( 'without one, the country list offers every country', strpos( $html, 'value="France"' ) !== false && strpos( $html, 'value="Japan"' ) !== false );
$email = 'clu-country@example.test';
$res   = register( [ 'email' => $email, 'country' => 'Japan' ] );
t( '...and accepts any of them', ! empty( $res['success'] ), msg( $res ) );

reset_state();
wp_dequeue_script( 'clu-register' );
wp_deregister_script( 'clu-register' );
render_shortcode_safe( $SC_REG );
clu_register_assets();
t( 'the country list works when a theme renders the form before the scripts register', wp_script_is( 'clu-register', 'enqueued' ) && strpos( (string) wp_scripts()->get_data( 'clu-register', 'data' ), 'cluRegions' ) !== false && strpos( (string) wp_scripts()->get_data( 'clu-register', 'data' ), 'France' ) !== false );
wp_dequeue_script( 'clu-register' );
save_fields( all_rows( [ 'country' => [ 'enabled' => false ] ] ) );
render_shortcode_safe( $SC_REG );
t( 'its script stays away from a form without both fields', ! wp_script_is( 'clu-register', 'enqueued' ) );

section( '18. My Account follows the field list' );
reset_state();
save_fields( all_rows( [ 'company' => [ 'required' => false ] ], [
    [ 'key' => '', 'label' => 'Phone', 'type' => 'tel', 'enabled' => true, 'required' => false, 'account' => true ],
    [ 'key' => '', 'label' => 'Membership', 'type' => 'select', 'enabled' => true, 'required' => false, 'account' => false, 'options' => [ 'Gold' ] ],
] ) );
$ma = make_user( 'clu_ma', 'clu-ma@example.test', 'Start1!pass', approved_role() );
update_user_meta( $ma, 'clu_field_membership', 'Gold' );
update_user_meta( $ma, 'customer_type', 'Installer' );
wp_set_current_user( $ma );
$html = render_shortcode_safe( $SC_ACC );
t( 'a field marked for My Account shows there', strpos( $html, 'name="clu_field_phone"' ) !== false );
t( 'a field not marked for it does not', strpos( $html, 'name="clu_field_membership"' ) === false && strpos( $html, 'name="customer_type"' ) === false );
$profile = function ( $over ) use ( $ma ) {
    wp_set_current_user( $ma );
    return ajax( 'ajax_update_profile', array_merge( [ 'my_account_profile_nonce' => wp_create_nonce( 'my_account_profile_nonce' ), 'first_name' => 'A', 'last_name' => 'B', 'email' => 'clu-ma@example.test' ], $over ), $ma );
};
$res = $profile( [ 'clu_field_phone' => '050-1234567' ] );
t( 'it can be changed there', ! empty( $res['success'] ) && get_user_meta( $ma, 'clu_field_phone', true ) === '050-1234567', msg( $res ) );
$res = $profile( [ 'clu_field_phone' => 'nope' ] );
t( 'an invalid value is refused there too', empty( $res['success'] ) && get_user_meta( $ma, 'clu_field_phone', true ) === '050-1234567', msg( $res ) );
$res = $profile( [ 'clu_field_membership' => 'Platinum', 'customer_type' => 'Consultant' ] );
t( 'a forged request cannot change a field the form does not show', get_user_meta( $ma, 'clu_field_membership', true ) === 'Gold' && get_user_meta( $ma, 'customer_type', true ) === 'Installer' );
$res = $profile( [ 'wp_capabilities' => 'administrator' ] );
t( 'nor anything else', ! user_can( $ma, 'manage_options' ) );
$res = $profile( [ 'first_name' => '' ] );
t( 'a required name stays required', empty( $res['success'] ) );
save_fields( all_rows( [ 'first_name' => [ 'enabled' => false ], 'last_name' => [ 'enabled' => false, 'account' => false ] ] ) );
$res = $profile( [ 'first_name' => '', 'last_name' => 'Ignored' ] );
t( 'a name the register form does not ask for is optional', ! empty( $res['success'] ) && get_userdata( $ma )->first_name === '', msg( $res ) );
t( 'a name left out of My Account stays as it is', get_userdata( $ma )->last_name === 'B', get_userdata( $ma )->last_name );
save_fields( all_rows( [ 'country' => [ 'account' => true ], 'region' => [ 'account' => true ], 'clu_field_membership' => [ 'account' => true ] ] ) );
update_user_meta( $ma, 'region', 'Europe' );
update_user_meta( $ma, 'country', 'France' );
wp_set_current_user( $ma );
$html = render_shortcode_safe( $SC_ACC );
t( 'the country list opens on the saved region', (bool) preg_match( '/value="France"\s+selected/', $html ) && strpos( $html, 'value="Japan"' ) === false );
save_fields( all_rows( [ 'clu_field_membership' => [ 'options' => [ 'Silver' ] ] ] ) );
wp_set_current_user( $ma );
$html = render_shortcode_safe( $SC_ACC );
t( 'a saved value that left the dropdown stays on the form', (bool) preg_match( '/value="Gold"\s+selected/', $html ) );
$res = $profile( [ 'region' => 'Europe', 'country' => 'France', 'clu_field_membership' => 'Gold', 'first_name' => 'A' ] );
t( '...and can still be saved', ! empty( $res['success'] ) && get_user_meta( $ma, 'clu_field_membership', true ) === 'Gold', msg( $res ) );
$res = $profile( [ 'region' => 'Europe', 'country' => 'France', 'clu_field_membership' => 'Platinum', 'first_name' => 'A' ] );
t( '...but another value outside it cannot', empty( $res['success'] ), msg( $res ) );
wp_set_current_user( 0 );

section( '19. The admin screens follow the field list' );
reset_state();
save_fields( all_rows( [ 'region' => [ 'enabled' => false ], 'country' => [ 'enabled' => false ] ], [
    [ 'key' => '', 'label' => 'Notes', 'type' => 'textarea', 'enabled' => true, 'required' => false, 'account' => false ],
    [ 'key' => '', 'label' => 'Newsletter', 'type' => 'checkbox', 'enabled' => true, 'required' => false, 'account' => false ],
] ) );
if ( $bare ) {
    $ad = make_user( 'clu_ad', 'clu-ad@example.test', 'Start1!pass', 'subscriber' );
    update_user_meta( $ad, 'country', 'France' );
    wp_set_current_user( 1 );
    $html = clu_get_template_html( 'user_extra_fields', get_userdata( $ad ) );
    t( 'the user screen shows the fields on the form', strpos( $html, 'name="clu_field_notes"' ) !== false && strpos( $html, 'name="company"' ) !== false );
    t( 'a field turned off shows only where there is a value', strpos( $html, 'name="country"' ) !== false && strpos( $html, 'name="region"' ) === false );
    $_POST = wp_slash( [ 'clu_field_notes' => "a\nb<script>x</script>", 'clu_field_newsletter' => '1', 'first_name' => 'Keep' ] );
    do_action( 'edit_user_profile_update', $ad );
    $_POST = [];
    t( 'long text is saved with its lines, and clean', strpos( get_user_meta( $ad, 'clu_field_notes', true ), "\n" ) !== false && strpos( get_user_meta( $ad, 'clu_field_notes', true ), '<script' ) === false, get_user_meta( $ad, 'clu_field_notes', true ) );
    t( 'a ticked box is saved', get_user_meta( $ad, 'clu_field_newsletter', true ) === '1' );
    $_POST = wp_slash( [ 'clu_field_newsletter' => '' ] );
    do_action( 'edit_user_profile_update', $ad );
    $_POST = [];
    t( 'an unticked box is saved as such', get_user_meta( $ad, 'clu_field_newsletter', true ) === '' );
    wp_set_current_user( 0 );

    $_GET = [ 'role' => pending_role() ];
    $columns = apply_filters( 'manage_users_columns', [ 'username' => 'Username', 'email' => 'Email' ] );
    $_GET = [ 'role' => 'subscriber' ];
    $other = apply_filters( 'manage_users_columns', [ 'username' => 'Username' ] );
    $_GET = [];
    t( 'the list of waiting users has a details column', isset( $columns['clu_details'] ) );
    t( 'other lists do not', ! isset( $other['clu_details'] ) );
    update_user_meta( $ad, 'company', '<b>Acme</b> & sons' );
    $cell = apply_filters( 'manage_users_custom_column', '', 'clu_details', $ad );
    t( 'the column lists the details, escaped, without the email', strpos( $cell, '&lt;b&gt;Acme' ) !== false && strpos( $cell, '<b>Acme' ) === false && strpos( $cell, 'clu-ad@example.test' ) === false && strpos( $cell, 'France' ) !== false, $cell );
}

ob_start();
wp_set_current_user( 1 );
clu_render_register_fields_tab();
$tab = ob_get_clean();
wp_set_current_user( 0 );
t( 'the settings tab lists every field', strpos( $tab, 'data-key="clu_field_notes"' ) !== false && strpos( $tab, 'data-key="email"' ) !== false && strpos( $tab, 'id="clu-field-row-template"' ) !== false );
t( 'the email row cannot be turned off there', (bool) preg_match( '/data-key="email".*?clu-field-enabled"[^>]*disabled/s', $tab ) );

section( '20. Updating from an older version' );
reset_state();
delete_option( 'clu_register_fields' );
update_option( 'clu_register_form', [ 'company' => 'שם החברה', 'register_title' => 'Join' ] );
update_option( 'clu_db_version', '1.1.0' );
clu_maybe_upgrade();
$fields = array_column( clu_get_register_fields(), null, 'key' );
t( 'an updated site keeps all of its form fields on and required', $fields['company']['enabled'] && $fields['customer_type']['enabled'] && $fields['region']['required'] && $fields['country']['enabled'] );
t( 'it keeps the labels it saved', $fields['company']['label'] === 'שם החברה', $fields['company']['label'] );
t( 'it keeps the company field in My Account', $fields['company']['account'] );
t( 'it keeps the placeholders it had', $fields['first_name']['placeholder'] === 'First Name' && $fields['company']['placeholder'] === 'Company Name' );
t( 'it gets the rejected role', (bool) get_role( 'rejected_user' ) );
t( 'the version is recorded', get_option( 'clu_db_version' ) === CLU_VERSION );
update_option( 'clu_register_fields', [] );
$saved = [ [ 'key' => 'email', 'label' => 'X', 'enabled' => true ] ];
update_option( 'clu_register_fields', $saved );
update_option( 'clu_db_version', '1.1.0' );
clu_maybe_upgrade();
t( 'an update does not overwrite a saved field list', get_option( 'clu_register_fields' ) === $saved );

reset_state();
$old_body = "Hello,<br><br>{display_name} has registered on the website.<br><br>To approve the registration, click the link below and change the user role from \"Pending User\" to \"Approved User\": {edit_user_link}<br><br>";
update_option( 'clu_messages', [ 'notify_client_body' => $old_body, 'login_success' => 'שלום' ] );
update_option( 'clu_db_version', '1.1.0' );
clu_maybe_upgrade();
$saved = get_option( 'clu_messages' );
t( 'the old default of the admin email makes way for the new one', ! isset( $saved['notify_client_body'] ) && ( $saved['login_success'] ?? '' ) === 'שלום' );
update_option( 'clu_messages', [ 'notify_client_body' => 'My own text {edit_user_link}' ] );
update_option( 'clu_db_version', '1.1.0' );
clu_maybe_upgrade();
t( 'an admin email the site wrote itself stays', ( get_option( 'clu_messages' )['notify_client_body'] ?? '' ) === 'My own text {edit_user_link}' );
delete_option( 'clu_messages' );

reset_state();
delete_option( 'clu_register_fields' );
delete_option( 'clu_db_version' );
remove_role( 'pending_user' );
wp_cache_flush();
clu_maybe_upgrade();
t( 'a new site gets the short form', ! in_array( 'company', array_column( clu_get_register_form_fields(), 'key' ), true ) );
t( 'and no field in My Account that nobody asked for', ! in_array( 'company', array_column( clu_get_account_fields(), 'key' ), true ) );
t( 'and all three roles', get_role( 'pending_user' ) && get_role( 'approved_user' ) && get_role( 'rejected_user' ) );
reset_state();
delete_option( 'clu_register_fields' );
delete_option( 'clu_db_version' );
clu_maybe_upgrade();
t( 'a site from 1.0 counts as an update', in_array( 'company', array_column( clu_get_register_form_fields(), 'key' ), true ) );

reset_state();
section( '21. Nonces, templates, settings, update and uninstall' );
{
    $res = ajax( 'clu_refresh_nonce', [ 'field' => 'login_nonce' ] );
    wp_set_current_user( 0 );
    t( 'a fresh nonce is issued for a known form, and it validates', ! empty( $res['success'] ) && wp_verify_nonce( $res['data']['nonce'], 'login_form_nonce' ) );
    $res = ajax( 'clu_refresh_nonce', [ 'field' => 'something_else' ] );
    t( 'no nonce is issued for anything else', empty( $res['success'] ) );
    $res = ajax( 'ajax_login_user', [ 'log' => 'x', 'pwd' => 'y', 'login_nonce' => 'expired' ] );
    t( 'a dead nonce is reported with a code the form can retry on', ( $res['data']['code'] ?? '' ) === 'invalid_nonce' );

    $id = make_user( 'clu_tpl', 'clu-tpl@example.test', 'Start1!pass', approved_role() );
    wp_update_user( [ 'ID' => $id, 'display_name' => '{reset_link} <b>x</b>' ] );
    $GLOBALS['clu_mails'] = [];
    lost( 'clu-tpl@example.test' );
    $m = last_mail();
    t( 'a placeholder inside a name is not expanded', $m && substr_count( $m['message'], '<a ' ) === 1, $m ? $m['message'] : 'no mail' );

    update_option( 'clu_login_form', [ 'login_button' => '', 'email_label' => 'דוא"ל <b>' ] );
    t( 'an emptied label falls back to its default', clu_form_text( 'clu_login_form', 'login_button' ) === 'Log In' );
    wp_set_current_user( 0 );
    $html = render_shortcode_safe( $SC_LOGIN );
    t( 'labels are escaped in the form', strpos( $html, '<b>' ) === false && strpos( $html, 'value="Log In"' ) !== false, substr( $html, 0, 400 ) );
    delete_option( 'clu_login_form' );

    $html = render_shortcode_safe( '[clu_login_form show_title="false"]' );
    t( 'show_title="false" hides the title', strpos( $html, '<h3>' ) === false );
    $html = render_shortcode_safe( '[clu_login_form show_title="true"]' );
    t( 'show_title="true" shows the title', strpos( $html, 'Login to Your Account' ) !== false );
    $html = render_shortcode_safe( $SC_LOGIN, [ 'redirect_to' => home_url( '/x/' ) ] );
    t( 'the form carries a local redirect_to', strpos( $html, 'name="redirect_to"' ) !== false );
    $html = render_shortcode_safe( $SC_LOGIN, [ 'redirect_to' => 'https://evil.test/' ] );
    t( 'the form drops a foreign redirect_to', strpos( $html, 'evil.test' ) === false );

    ob_start();
    clu_render_messages_tab();
    $tab = ob_get_clean();
    $missing = [];
    foreach ( array_keys( clu_default_messages() ) as $key ) {
        if ( strpos( $tab, 'name="' . $key . '"' ) === false ) {
            $missing[] = $key;
        }
    }
    t( 'every message can be edited on the Messages tab', ! $missing, implode( ', ', $missing ) );

    $fields = [];
    foreach ( clu_get_settings_tabs() as $tab_def ) {
        if ( ! empty( $tab_def['option_name'] ) ) {
            foreach ( include $tab_def['fields_file'] as $f ) {
                $fields[ $tab_def['option_name'] ][] = $f['field'];
            }
        }
    }
    $src = '';
    foreach ( glob( CLU_PLUGIN_DIR . 'forms/*.php' ) as $file ) {
        $src .= file_get_contents( $file );
    }
    $dead = [];
    foreach ( $fields as $option => $keys ) {
        if ( in_array( $option, [ 'clu_mail', 'clu_pages_url' ], true ) ) {
            continue;
        }
        foreach ( $keys as $key ) {
            if ( strpos( $src, "'" . $key . "'" ) === false ) {
                $dead[] = "$option/$key";
            }
        }
    }
    t( 'every field on the form tabs is used by a form', ! $dead, implode( ', ', $dead ) );

    update_option( 'clu_messages', [
        'password_min_length'   => 'Password must be at least 6 characters long.',
        'password_special_char' => 'Password must contain at least one special character (!@#$%^&*().',
        'login_success'         => 'ברוך הבא',
    ] );
    delete_option( 'clu_db_version' );
    clu_maybe_upgrade();
    $saved = get_option( 'clu_messages' );
    t( 'updating from 1.0 clears the two stale saved defaults', ! isset( $saved['password_min_length'] ) && ! isset( $saved['password_special_char'] ) );
    t( 'updating from 1.0 keeps text the admin wrote', ( $saved['login_success'] ?? '' ) === 'ברוך הבא' );
    t( 'the version is recorded', get_option( 'clu_db_version' ) === CLU_VERSION );


    wp_set_current_user( 1 );
    delete_option( 'clu_pages_url' );
    wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'set-password', 'post_title' => 'Taken', 'post_content' => 'No form here' ] );
    wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'password-lost', 'post_title' => 'Old', 'post_content' => '[custom-password-lost-form]' ] );
    foreach ( [ 'login', 'register', 'my-account', 'logged-out', 'password-updated' ] as $slug ) {
        $existing = get_page_by_path( $slug );
        if ( $existing ) {
            $wpdb->update( $wpdb->posts, [ 'post_name' => $slug . '-moved-by-test' ], [ 'ID' => $existing->ID ] );
            clean_post_cache( $existing->ID );
        }
    }
    wp_cache_flush();
    $res = ajax( 'clu_create_pages', [ 'nonce' => wp_create_nonce( 'clu_create_pages_nonce' ) ], 1 );
    $by_slug = [];
    foreach ( $res['data']['results'] ?? [] as $row ) {
        $by_slug[ $row['slug'] ] = $row;
    }
    t( 'Setup Pages creates what is missing', ( $by_slug['login']['status'] ?? '' ) === 'created' && has_shortcode( get_page_by_path( 'login' )->post_content, 'clu_login_form' ), print_r( $by_slug['login'] ?? null, true ) );
    t( 'Setup Pages flags an existing page without a form', ( $by_slug['set-password']['status'] ?? '' ) === 'no_shortcode', print_r( $by_slug['set-password'] ?? null, true ) );
    t( 'Setup Pages accepts an existing page with the older shortcode', ( $by_slug['password-lost']['status'] ?? '' ) === 'exists', print_r( $by_slug['password-lost'] ?? null, true ) );
    t( 'Setup Pages saves the URLs', strpos( clu_get_page_url( 'url_page_login' ), '/login' ) !== false, clu_get_page_url( 'url_page_login' ) );
    wp_set_current_user( 0 );

    $marker_id = make_user( 'clu_uninst', 'clu-uninst@example.test', 'Start1!pass', 'subscriber' );
    update_user_meta( $marker_id, 'clu_approval_link', 1 );
    update_option( 'clu_mail', [ 'from_name' => 'x' ] );
    foreach ( [ 'pending_user', 'approved_user', 'rejected_user' ] as $r ) {
        foreach ( get_users( [ 'role' => $r, 'fields' => 'ID' ] ) as $uid ) {
            get_userdata( $uid )->set_role( 'subscriber' );
        }
    }
    wp_cache_flush();
    define( 'WP_UNINSTALL_PLUGIN', 'custom-login-users.php' );
    require CLU_PLUGIN_DIR . 'uninstall.php';
    wp_cache_flush();
    t( 'uninstall removes the settings', get_option( 'clu_mail', 'gone' ) === 'gone' && get_option( 'clu_db_version', 'gone' ) === 'gone' );
    t( 'uninstall removes the unused roles', ! get_role( 'pending_user' ) && ! get_role( 'approved_user' ) && ! get_role( 'rejected_user' ) );
    t( 'uninstall removes the field list', get_option( 'clu_register_fields', 'gone' ) === 'gone' );
    t( 'uninstall removes the flag on users', ! get_user_meta( $marker_id, 'clu_approval_link', true ) );
}

section( '22. PHP warnings raised from inside the plugin during all of the above' );
$warnings = array_values( array_unique( $GLOBALS['clu_php_warnings'] ) );
t( 'no PHP warnings', count( $warnings ) === 0, implode( "\n             ", array_slice( $warnings, 0, 12 ) ) );

/* ─── Done ───────────────────────────────────────────────────────────── */

$wpdb->query( 'ROLLBACK' );
$r = $GLOBALS['clu_results'];
fwrite( STDOUT, "\n" . str_repeat( '=', 60 ) . "\nPASS {$r['pass']}   FAIL {$r['fail']}\n" );
while ( ob_get_level() ) {
    ob_end_clean();
}

exit( $r['fail'] ? 1 : 0 );

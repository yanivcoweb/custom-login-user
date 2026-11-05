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
    foreach ( [ 'clu_mail', 'clu_messages', 'clu_password_rules', 'clu_pages_url', 'clu_roles' ] as $o ) {
        delete_option( $o );
    }
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
section( '15. Nonces, templates, settings, update and uninstall' );
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
    foreach ( [ 'pending_user', 'approved_user' ] as $r ) {
        foreach ( get_users( [ 'role' => $r, 'fields' => 'ID' ] ) as $uid ) {
            get_userdata( $uid )->set_role( 'subscriber' );
        }
    }
    wp_cache_flush();
    define( 'WP_UNINSTALL_PLUGIN', 'custom-login-users.php' );
    require CLU_PLUGIN_DIR . 'uninstall.php';
    wp_cache_flush();
    t( 'uninstall removes the settings', get_option( 'clu_mail', 'gone' ) === 'gone' && get_option( 'clu_db_version', 'gone' ) === 'gone' );
    t( 'uninstall removes the unused roles', ! get_role( 'pending_user' ) && ! get_role( 'approved_user' ) );
    t( 'uninstall removes the flag on users', ! get_user_meta( $marker_id, 'clu_approval_link', true ) );
}

section( '16. PHP warnings raised from inside the plugin during all of the above' );
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

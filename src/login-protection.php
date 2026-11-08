<?php

defined( 'ABSPATH' ) || exit;

/**
 * Login protection: a limit on failed logins, and a bot check on the forms.
 *
 * Both are off until set on the Login Protection tab. The limit counts failures from
 * every login form of the site. The bot check covers the plugin's login, register and
 * lost password forms, and the same three on wp-login.php.
 */

/**
 * Returns a value from the Login Protection tab.
 *
 * @param string $key
 * @return string
 */
function clu_get_security_setting( $key ) {
    $options = get_option( 'clu_security' );

    return is_array( $options ) && isset( $options[ $key ] ) ? trim( (string) $options[ $key ] ) : '';
}

/* ─── Failed logins ──────────────────────────────────────────────────── */

/**
 * Returns the limit on failed logins.
 *
 * @return array{on: bool, attempts: int, window: int} The window is in seconds.
 */
function clu_login_limit() {
    $attempts = (int) clu_get_security_setting( 'login_attempts' );
    $minutes  = (int) clu_get_security_setting( 'lockout_minutes' );

    /**
     * Filters the limit on failed logins.
     *
     * @param array $limit on, attempts, and window in seconds.
     */
    $limit = (array) apply_filters( 'clu_login_limit', [
        'on'       => clu_get_security_setting( 'limit_logins' ) === '1',
        'attempts' => $attempts > 0 ? $attempts : 5,
        'window'   => ( $minutes > 0 ? $minutes : 15 ) * MINUTE_IN_SECONDS,
    ] );

    return [
        'on'       => ! empty( $limit['on'] ),
        'attempts' => max( 1, (int) ( $limit['attempts'] ?? 5 ) ),
        'window'   => max( 60, (int) ( $limit['window'] ?? 15 * MINUTE_IN_SECONDS ) ),
    ];
}

/**
 * Returns where the failures of an address are counted.
 *
 * @param string $ip Defaults to the visitor's address.
 * @return string
 */
function clu_login_lock_key( $ip = '' ) {
    return 'clu_ll_' . md5( $ip !== '' ? $ip : clu_client_ip() );
}

/**
 * Returns how many seconds logins from an address are still refused, 0 when they are not.
 *
 * @param string $ip Defaults to the visitor's address.
 * @return int
 */
function clu_login_lock_remaining( $ip = '' ) {
    $limit = clu_login_limit();

    if ( ! $limit['on'] ) {
        return 0;
    }

    $data = get_transient( clu_login_lock_key( $ip ) );

    if ( ! is_array( $data ) || (int) ( $data['count'] ?? 0 ) < $limit['attempts'] ) {
        return 0;
    }

    return max( 0, (int) ( $data['until'] ?? 0 ) - time() );
}

/**
 * Counts a failed attempt from the visitor's address. Each failure starts the window
 * again, so the address is refused for the whole window after the last one.
 */
function clu_note_failed_login() {
    $limit = clu_login_limit();

    if ( ! $limit['on'] ) {
        return;
    }

    $key   = clu_login_lock_key();
    $data  = get_transient( $key );
    $count = is_array( $data ) ? (int) ( $data['count'] ?? 0 ) : 0;

    set_transient( $key, [ 'count' => $count + 1, 'until' => time() + $limit['window'] ], $limit['window'] );
}

/**
 * Returns the message for a refused address, with the minutes left.
 *
 * @return string
 */
function clu_login_locked_message() {
    $minutes = max( 1, (int) ceil( clu_login_lock_remaining() / MINUTE_IN_SECONDS ) );

    return str_replace( '{minutes}', (string) $minutes, clu_render_message( 'login_locked' ) );
}

/**
 * Counts every failed login on the site: the plugin's form, wp-login.php, WooCommerce,
 * XML-RPC. Not a refusal by the plugin itself, where the password was not the problem.
 */
add_action( 'wp_login_failed', 'clu_count_failed_login', 10, 2 );
function clu_count_failed_login( $username, $error = null ) {
    if ( is_wp_error( $error ) && strpos( (string) $error->get_error_code(), 'clu_' ) === 0 ) {
        return;
    }

    clu_note_failed_login();
}

/**
 * Refuses a login from an address over the limit, even with the right password, and
 * one on wp-login.php without a passed bot check.
 *
 * Runs last, because the password check that runs before would replace any earlier
 * answer. Only for an actual attempt: wp-login.php runs this on every visit too, with
 * no username.
 */
add_filter( 'authenticate', 'clu_protect_login', 100, 2 );
function clu_protect_login( $user, $username = '' ) {
    if ( ! is_string( $username ) || $username === '' ) {
        return $user;
    }

    if ( clu_login_lock_remaining() ) {
        return new WP_Error( 'clu_too_many_logins', clu_login_locked_message() );
    }

    if ( clu_is_wp_login_post() && ! clu_verify_captcha() ) {
        return new WP_Error( 'clu_captcha_failed', clu_render_message( 'captcha_failed' ) );
    }

    return $user;
}

/* ─── The bot check ──────────────────────────────────────────────────── */

/**
 * Returns the services the bot check can use.
 *
 * @return array
 */
function clu_captcha_services() {
    return [
        'turnstile' => [
            'label'  => 'Cloudflare Turnstile',
            'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'class'  => 'cf-turnstile',
            'field'  => 'cf-turnstile-response',
            'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        ],
        'recaptcha' => [
            'label'  => 'Google reCAPTCHA v2 (checkbox)',
            'script' => 'https://www.google.com/recaptcha/api.js',
            'class'  => 'g-recaptcha',
            'field'  => 'g-recaptcha-response',
            'verify' => 'https://www.google.com/recaptcha/api/siteverify',
        ],
    ];
}

/**
 * Returns the bot check in use, or null when it is off or its keys are missing.
 *
 * @return array|null The service, with its site_key and secret_key.
 */
function clu_captcha() {
    $services = clu_captcha_services();
    $name     = clu_get_security_setting( 'captcha' );
    $site_key = clu_get_security_setting( 'captcha_site_key' );
    $secret   = clu_get_security_setting( 'captcha_secret_key' );

    if ( ! isset( $services[ $name ] ) || $site_key === '' || $secret === '' ) {
        return null;
    }

    return $services[ $name ] + [
        'name'       => $name,
        'site_key'   => $site_key,
        'secret_key' => $secret,
    ];
}

/**
 * Loads the script of the bot check.
 */
function clu_enqueue_captcha_script() {
    $captcha = clu_captcha();

    if ( ! $captcha ) {
        return;
    }

    $src = $captcha['script'];

    if ( $captcha['name'] === 'recaptcha' ) {
        $src = add_query_arg( 'hl', substr( determine_locale(), 0, 2 ), $src );
    }

    wp_enqueue_script( 'clu-captcha', $src, [], null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
}

/**
 * Returns the bot check for a form, and loads its script. '' when it is off.
 *
 * @return string
 */
function clu_captcha_html() {
    if ( ! clu_captcha() ) {
        return '';
    }

    clu_enqueue_captcha_script();

    return clu_captcha_box();
}

/**
 * Returns the box the script of the bot check fills, without loading the script.
 *
 * @return string
 */
function clu_captcha_box() {
    $captcha = clu_captcha();

    if ( ! $captcha ) {
        return '';
    }

    return sprintf(
        '<div class="form-row clu-captcha-row"><div class="clu-captcha %s" data-sitekey="%s"></div></div>',
        esc_attr( $captcha['class'] ),
        esc_attr( $captcha['site_key'] )
    );
}

/**
 * Checks the answer of the bot check sent with the form. Each answer is asked about
 * once, since the service accepts it only once.
 *
 * Lets the request through when the service cannot be asked, or refuses the secret key:
 * a bot can cause neither, and refusing everyone then would lock the site's own admins
 * out. The problem is shown to them in wp-admin instead.
 *
 * @return bool
 */
function clu_verify_captcha() {
    static $answers = [];

    $captcha = clu_captcha();

    if ( ! $captcha ) {
        return true;
    }

    $token = isset( $_POST[ $captcha['field'] ] ) && is_string( $_POST[ $captcha['field'] ] ) ? trim( wp_unslash( $_POST[ $captcha['field'] ] ) ) : '';

    if ( $token === '' ) {
        return false;
    }

    if ( ! isset( $answers[ $token ] ) ) {
        $answers[ $token ] = clu_ask_captcha_service( $captcha, $token );
    }

    return $answers[ $token ];
}

/**
 * Asks the service about an answer.
 *
 * @param array  $captcha
 * @param string $token
 * @return bool
 */
function clu_ask_captcha_service( $captcha, $token ) {
    $response = wp_remote_post( $captcha['verify'], [
        'timeout' => 10,
        'body'    => [
            'secret'   => $captcha['secret_key'],
            'response' => $token,
            'remoteip' => clu_client_ip(),
        ],
    ] );

    $body   = is_wp_error( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );
    $errors = is_array( $body ) ? (array) ( $body['error-codes'] ?? [] ) : [];

    if ( ! is_array( $body ) ) {
        clu_note_captcha_problem( is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response ) );
        return true;
    }

    if ( array_intersect( [ 'missing-input-secret', 'invalid-input-secret' ], $errors ) ) {
        clu_note_captcha_problem( 'the secret key was refused' );
        return true;
    }

    if ( get_transient( 'clu_captcha_problem' ) ) {
        delete_transient( 'clu_captcha_problem' );
    }

    return ! empty( $body['success'] );
}

/**
 * Remembers for a day why the bot check could not be used, for the notice in wp-admin.
 *
 * @param string $reason
 */
function clu_note_captcha_problem( $reason ) {
    set_transient( 'clu_captcha_problem', sanitize_text_field( $reason ), DAY_IN_SECONDS );
}

add_action( 'admin_notices', 'clu_show_captcha_problem' );
function clu_show_captcha_problem() {
    $reason = get_transient( 'clu_captcha_problem' );

    if ( ! $reason || ! current_user_can( 'manage_options' ) || ! clu_captcha() ) {
        return;
    }

    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        esc_html( sprintf(
            /* translators: %s: the reason. */
            __( 'Custom Login Users could not use the bot check (%s), so forms are let through without it. Check the keys on the Login Protection tab.', 'custom-login-users' ),
            $reason
        ) )
    );
}

/**
 * Checks a new secret key when the settings are saved. A key the service refuses turns
 * the bot check off, so that nobody is locked out by a typing mistake.
 */
add_filter( 'pre_update_option_clu_security', 'clu_check_captcha_settings', 10, 2 );
function clu_check_captcha_settings( $value, $old ) {
    $value    = is_array( $value ) ? $value : [];
    $old      = is_array( $old ) ? $old : [];
    $services = clu_captcha_services();
    $name     = (string) ( $value['captcha'] ?? '' );

    if ( ! isset( $services[ $name ] ) ) {
        return $value;
    }

    $site_key = trim( (string) ( $value['captcha_site_key'] ?? '' ) );
    $secret   = trim( (string) ( $value['captcha_secret_key'] ?? '' ) );

    if ( $site_key === '' || $secret === '' ) {
        clu_settings_notice( 'Fill in both keys: the bot check stays off until then.', 'warning' );
        return $value;
    }

    if ( $name === ( $old['captcha'] ?? '' ) && $secret === ( $old['captcha_secret_key'] ?? '' ) ) {
        return $value;
    }

    $response = wp_remote_post( $services[ $name ]['verify'], [
        'timeout' => 10,
        'body'    => [
            'secret'   => $secret,
            'response' => 'clu-settings-check',
        ],
    ] );
    $body     = is_wp_error( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );

    if ( ! is_array( $body ) ) {
        clu_settings_notice( 'The secret key could not be checked now. Try a login in a private window before logging out.', 'warning' );
        return $value;
    }

    if ( array_intersect( [ 'missing-input-secret', 'invalid-input-secret' ], (array) ( $body['error-codes'] ?? [] ) ) ) {
        $value['captcha'] = '';
        clu_settings_notice( $services[ $name ]['label'] . ' refused the secret key, so the bot check was turned off. Check the key and save again.', 'error' );
        return $value;
    }

    clu_settings_notice( 'The secret key works. Check that the widget below shows no error before logging out.', 'success' );

    return $value;
}

/**
 * Shows a notice after the settings are saved, where WordPress shows its own.
 *
 * @param string $message
 * @param string $type error, warning or success.
 */
function clu_settings_notice( $message, $type ) {
    if ( function_exists( 'add_settings_error' ) ) {
        add_settings_error( 'clu_security', 'clu_captcha_' . $type, $message, $type );
    }
}

/* ─── The forms on wp-login.php ──────────────────────────────────────── */

/**
 * Tells whether this request posts a form on wp-login.php.
 *
 * @return bool
 */
function clu_is_wp_login_post() {
    return ( $GLOBALS['pagenow'] ?? '' ) === 'wp-login.php' && ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST';
}

/**
 * Prints the bot check on the three forms of wp-login.php.
 */
foreach ( [ 'login_form', 'register_form', 'lostpassword_form' ] as $clu_hook ) {
    add_action( $clu_hook, 'clu_print_captcha' );
}
unset( $clu_hook );

function clu_print_captcha() {
    echo clu_captcha_html(); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * The same on a login form a theme prints with wp_login_form(), which posts to
 * wp-login.php and would be refused without it.
 */
add_filter( 'login_form_middle', 'clu_add_captcha_to_login_form' );
function clu_add_captcha_to_login_form( $html ) {
    return $html . clu_captcha_html();
}

add_filter( 'registration_errors', 'clu_check_captcha_on_register' );
function clu_check_captcha_on_register( $errors ) {
    if ( clu_is_wp_login_post() && ! clu_verify_captcha() ) {
        $errors->add( 'clu_captcha_failed', clu_render_message( 'captcha_failed' ) );
    }

    return $errors;
}

/**
 * Only on wp-login.php: WooCommerce fires this too, from a form without the check.
 */
add_action( 'lostpassword_post', 'clu_check_captcha_on_lost_password' );
function clu_check_captcha_on_lost_password( $errors ) {
    if ( clu_is_wp_login_post() && ! clu_verify_captcha() ) {
        $errors->add( 'clu_captcha_failed', clu_render_message( 'captcha_failed' ) );
    }
}

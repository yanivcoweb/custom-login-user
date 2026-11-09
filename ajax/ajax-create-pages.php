<?php

defined( 'ABSPATH' ) || exit;

/**
 * Returns the pages the plugin can create, shared by the Setup tab and its handler.
 *
 * Titles are in the current language. A page with a form holds its shortcode; a page
 * without one names, under 'text', the short text it starts with.
 *
 * @return array
 */
function clu_get_setup_pages() {
    return [
        [
            'title'     => _x( 'Login', 'page title', 'custom-login-users' ),
            'slug'      => 'login',
            'shortcode' => 'clu_login_form',
            'legacy'    => 'custom-login-form',
            'url_field' => 'url_page_login',
        ],
        [
            'title'     => _x( 'Register', 'page title', 'custom-login-users' ),
            'slug'      => 'register',
            'shortcode' => 'clu_register_form',
            'legacy'    => 'custom-register-form',
            'url_field' => 'url_page_register',
        ],
        [
            'title'     => _x( 'Set Password', 'page title', 'custom-login-users' ),
            'slug'      => 'set-password',
            'shortcode' => 'clu_password_set_form',
            'legacy'    => 'custom-password-set',
            'url_field' => 'url_page_password_set',
        ],
        [
            'title'     => _x( 'Password Lost', 'page title', 'custom-login-users' ),
            'slug'      => 'password-lost',
            'shortcode' => 'clu_password_lost_form',
            'legacy'    => 'custom-password-lost-form',
            'url_field' => 'url_page_password_lost',
        ],
        [
            'title'     => _x( 'My Account', 'page title', 'custom-login-users' ),
            'slug'      => 'my-account',
            'shortcode' => 'clu_my_account',
            'legacy'    => 'my-account',
            'url_field' => 'url_page_redirect_after_login',
        ],
        [
            'title'     => _x( 'Logged Out', 'page title', 'custom-login-users' ),
            'slug'      => 'logged-out',
            'text'      => 'logged_out',
            'url_field' => 'url_page_redirect_after_logout',
        ],
        [
            'title'     => _x( 'Password Updated', 'page title', 'custom-login-users' ),
            'slug'      => 'password-updated',
            'text'      => 'password_updated',
            'url_field' => 'url_page_redirect_after_password_set',
        ],
    ];
}

/**
 * Tells whether a page that already exists holds the shortcode it needs. A page can
 * share the slug by chance, such as the my-account page of WooCommerce, and pointing
 * the settings at it would leave that step of the flow without a form.
 *
 * @param array   $page An item from clu_get_setup_pages().
 * @param WP_Post $post
 * @return bool
 */
function clu_setup_page_has_shortcode( $page, $post ) {
    if ( empty( $page['shortcode'] ) ) {
        return true;
    }

    return has_shortcode( $post->post_content, $page['shortcode'] )
        || has_shortcode( $post->post_content, $page['legacy'] );
}

/**
 * Returns the content a new page starts with.
 *
 * A page with a form holds only its shortcode, since the form brings its own heading
 * and text, set on the form's tab. A page without one would open empty, so it gets a
 * line that says what happened and a link onwards, which the admin can edit like any
 * other text.
 *
 * @param array $page An item from clu_get_setup_pages().
 * @param array $urls The addresses on the Pages URL tab, including the pages created
 *                    before this one in the same run.
 * @return string Block markup, which the classic editor shows as plain HTML.
 */
function clu_setup_page_content( $page, $urls ) {
    if ( ! empty( $page['shortcode'] ) ) {
        return "<!-- wp:shortcode -->\n[" . $page['shortcode'] . "]\n<!-- /wp:shortcode -->";
    }

    $link = function ( $url, $label ) {
        return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    };

    switch ( $page['text'] ?? '' ) {
        case 'logged_out':
            $login = ! empty( $urls['url_page_login'] ) ? $urls['url_page_login'] : wp_login_url();
            $lines = [
                esc_html__( 'You have logged out.', 'custom-login-users' ),
                $link( $login, __( 'Log in again', 'custom-login-users' ) ),
            ];
            break;

        case 'password_updated':
            // The set-password form logs the user in before it leads here.
            $lines = [
                esc_html__( 'Your new password has been saved.', 'custom-login-users' ),
                ! empty( $urls['url_page_redirect_after_login'] )
                    ? $link( $urls['url_page_redirect_after_login'], __( 'Go to my account', 'custom-login-users' ) )
                    : $link( home_url( '/' ), __( 'Go to the home page', 'custom-login-users' ) ),
            ];
            break;

        default:
            return '';
    }

    return implode( "\n\n", array_map( function ( $line ) {
        return "<!-- wp:paragraph -->\n<p>" . $line . "</p>\n<!-- /wp:paragraph -->";
    }, $lines ) );
}

/**
 * Creates the pages that are missing and saves the address of each, new or existing,
 * on the Pages URL tab. An existing page is left as it is.
 *
 * Titles and texts come in the site's language, not in the language of the admin who
 * clicked, because visitors read them.
 *
 * @return array One row for each page: slug, title, status, and url or message.
 */
function clu_create_setup_pages() {
    $options  = get_option( 'clu_pages_url', [] );
    $options  = is_array( $options ) ? $options : [];
    $results  = [];
    $switched = switch_to_locale( get_locale() );

    try {
        foreach ( clu_get_setup_pages() as $page ) {
            $existing = get_page_by_path( $page['slug'] );

            if ( $existing ) {
                $url                           = get_permalink( $existing->ID );
                $options[ $page['url_field'] ] = $url;
                $results[]                     = [
                    'slug'   => $page['slug'],
                    'title'  => $page['title'],
                    'status' => clu_setup_page_has_shortcode( $page, $existing ) ? 'exists' : 'no_shortcode',
                    'url'    => $url,
                ];
                continue;
            }

            $post_id = wp_insert_post( [
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_content' => clu_setup_page_content( $page, $options ),
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ], true );

            if ( is_wp_error( $post_id ) ) {
                $results[] = [
                    'slug'    => $page['slug'],
                    'title'   => $page['title'],
                    'status'  => 'error',
                    'message' => $post_id->get_error_message(),
                ];
                continue;
            }

            $url                           = get_permalink( $post_id );
            $options[ $page['url_field'] ] = $url;
            $results[]                     = [
                'slug'   => $page['slug'],
                'title'  => $page['title'],
                'status' => 'created',
                'url'    => $url,
            ];
        }
    } finally {
        if ( $switched ) {
            restore_previous_locale();
        }
    }

    update_option( 'clu_pages_url', $options );

    return $results;
}

add_action( 'wp_ajax_clu_create_pages', 'clu_create_pages_handler' );
function clu_create_pages_handler() {
    check_ajax_referer( 'clu_create_pages_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    wp_send_json_success( [ 'results' => clu_create_setup_pages() ] );
}

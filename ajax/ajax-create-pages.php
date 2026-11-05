<?php

defined( 'ABSPATH' ) || exit;

/**
 * Returns the pages the plugin can create, shared by the Setup tab and its handler.
 *
 * @return array
 */
function clu_get_setup_pages() {
    return [
        [
            'title'     => 'Login',
            'slug'      => 'login',
            'shortcode' => 'clu_login_form',
            'legacy'    => 'custom-login-form',
            'url_field' => 'url_page_login',
        ],
        [
            'title'     => 'Register',
            'slug'      => 'register',
            'shortcode' => 'clu_register_form',
            'legacy'    => 'custom-register-form',
            'url_field' => 'url_page_register',
        ],
        [
            'title'     => 'Set Password',
            'slug'      => 'set-password',
            'shortcode' => 'clu_password_set_form',
            'legacy'    => 'custom-password-set',
            'url_field' => 'url_page_password_set',
        ],
        [
            'title'     => 'Password Lost',
            'slug'      => 'password-lost',
            'shortcode' => 'clu_password_lost_form',
            'legacy'    => 'custom-password-lost-form',
            'url_field' => 'url_page_password_lost',
        ],
        [
            'title'     => 'My Account',
            'slug'      => 'my-account',
            'shortcode' => 'clu_my_account',
            'legacy'    => 'my-account',
            'url_field' => 'url_page_redirect_after_login',
        ],
        [
            'title'     => 'Logged Out',
            'slug'      => 'logged-out',
            'url_field' => 'url_page_redirect_after_logout',
        ],
        [
            'title'     => 'Password Updated',
            'slug'      => 'password-updated',
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

add_action( 'wp_ajax_clu_create_pages', 'clu_create_pages_handler' );
function clu_create_pages_handler() {
    check_ajax_referer( 'clu_create_pages_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
    }

    $options = get_option( 'clu_pages_url', [] );
    $options = is_array( $options ) ? $options : [];
    $results = [];

    foreach ( clu_get_setup_pages() as $page ) {
        $existing = get_page_by_path( $page['slug'] );

        if ( $existing ) {
            $url                          = get_permalink( $existing->ID );
            $options[ $page['url_field'] ] = $url;
            $results[]                    = [
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
            'post_content' => ! empty( $page['shortcode'] ) ? "<!-- wp:shortcode -->\n[" . $page['shortcode'] . "]\n<!-- /wp:shortcode -->" : '',
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

        $url                          = get_permalink( $post_id );
        $options[ $page['url_field'] ] = $url;
        $results[]                    = [
            'slug'   => $page['slug'],
            'title'  => $page['title'],
            'status' => 'created',
            'url'    => $url,
        ];
    }

    update_option( 'clu_pages_url', $options );

    wp_send_json_success( [ 'results' => $results ] );
}

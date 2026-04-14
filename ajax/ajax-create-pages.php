<?php

add_action('wp_ajax_clu_create_pages', 'clu_create_pages_handler');

function clu_create_pages_handler() {
    check_ajax_referer('clu_create_pages_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized.', 'clu')]);
    }

    $pages_to_create = [
        [
            'title'      => 'Login',
            'slug'       => 'login',
            'shortcode'  => '[custom-login-form]',
            'url_field'  => 'url_page_login',
        ],
        [
            'title'      => 'Register',
            'slug'       => 'register',
            'shortcode'  => '[custom-register-form]',
            'url_field'  => 'url_page_register',
        ],
        [
            'title'      => 'Set Password',
            'slug'       => 'set-password',
            'shortcode'  => '[custom-password-set]',
            'url_field'  => 'url_page_password_set',
        ],
        [
            'title'      => 'Password Lost',
            'slug'       => 'password-lost',
            'shortcode'  => '[custom-password-lost-form]',
            'url_field'  => 'url_page_password_lost',
        ],
        [
            'title'     => 'My Account',
            'slug'      => 'my-account',
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

    $options = get_option('clu_pages_url', []);
    $results = [];

    foreach ($pages_to_create as $page) {
        $existing = get_page_by_path($page['slug']);

        if ($existing) {
            $url = get_permalink($existing->ID);
            $options[$page['url_field']] = $url;
            if (!empty($page['slug_field'])) {
                $options[$page['slug_field']] = $page['slug'];
            }
            $results[] = [
                'title'  => $page['title'],
                'status' => 'exists',
                'url'    => $url,
            ];
        } else {
            $content = !empty($page['shortcode'])
                ? "<!-- wp:shortcode -->\n" . $page['shortcode'] . "\n<!-- /wp:shortcode -->"
                : '';

            $post_id = wp_insert_post([
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]);

            if (is_wp_error($post_id)) {
                $results[] = [
                    'title'   => $page['title'],
                    'status'  => 'error',
                    'message' => $post_id->get_error_message(),
                ];
                continue;
            }

            $url = get_permalink($post_id);
            $options[$page['url_field']] = $url;
            if (!empty($page['slug_field'])) {
                $options[$page['slug_field']] = $page['slug'];
            }
            $results[] = [
                'title'  => $page['title'],
                'status' => 'created',
                'url'    => $url,
            ];
        }
    }

    update_option('clu_pages_url', $options);

    wp_send_json_success(['results' => $results]);
}

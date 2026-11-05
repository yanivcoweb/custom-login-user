<?php

defined( 'ABSPATH' ) || exit;

/* ─── Who sees what ──────────────────────────────────────────────────── */

/**
 * Tells whether the current user may see restricted content: an approved user, a site
 * administrator, or someone who can edit this very post.
 *
 * @param int $post_id 0 for restricted content in general.
 * @return bool
 */
function clu_user_can_view_restricted( $post_id = 0 ) {
    $user    = wp_get_current_user();
    $allowed = $user->exists() && (
        in_array( clu_get_role_slug( 'approved_role' ), (array) $user->roles, true )
        || user_can( $user, 'manage_options' )
        || ( $post_id && user_can( $user, 'edit_post', $post_id ) )
    );

    /**
     * Filters whether a user may see restricted content, for example to let in a role
     * that existed on the site before the approval flow.
     *
     * @param bool    $allowed
     * @param WP_User $user
     * @param int     $post_id 0 for restricted content in general.
     */
    return (bool) apply_filters( 'clu_user_can_view_restricted', $allowed, $user, $post_id );
}

/**
 * Tells whether a post is restricted.
 *
 * @param int $post_id
 * @return bool
 */
function clu_post_is_restricted( $post_id ) {
    return $post_id && get_post_meta( $post_id, '_clu_restricted', true ) === '1' && ! clu_is_open_page( $post_id );
}

/**
 * Tells whether a page must stay open to everyone, because people need it on the way
 * in. Restricted, the login page would send visitors to itself without end, and the
 * set-password page would lock out every newly approved user.
 *
 * @param int $post_id
 * @return bool
 */
function clu_is_open_page( $post_id ) {
    $post = get_post( $post_id );

    if ( ! $post ) {
        return false;
    }

    $forms = [
        'clu_login_form',
        'custom-login-form',
        'clu_register_form',
        'custom-register-form',
        'clu_password_lost_form',
        'custom-password-lost-form',
        'clu_password_set_form',
        'custom-password-set',
    ];

    foreach ( $forms as $form ) {
        if ( has_shortcode( $post->post_content, $form ) ) {
            return true;
        }
    }

    // A form placed with a page builder is not in the content, but its address is saved.
    foreach ( [ 'url_page_login', 'url_page_register', 'url_page_password_lost', 'url_page_password_set', 'url_page_no_access' ] as $key ) {
        $url = clu_saved_page_url( $key );

        if ( $url !== '' && url_to_postid( $url ) === (int) $post->ID ) {
            return true;
        }
    }

    return false;
}

/**
 * Returns an address saved on the Pages URL tab, or '' when none is saved. Unlike
 * clu_get_page_url(), which falls back to the home page.
 *
 * @param string $key
 * @return string
 */
function clu_saved_page_url( $key ) {
    $options = get_option( 'clu_pages_url' );

    return is_array( $options ) && ! empty( $options[ $key ] ) ? clu_normalize_site_url( $options[ $key ] ) : '';
}

/**
 * Returns the login address that leads back to a post afterwards.
 *
 * @param int $post_id
 * @return string
 */
function clu_restricted_login_url( $post_id ) {
    $url = clu_get_page_url( 'url_page_login', wp_login_url() );

    return $post_id ? add_query_arg( 'redirect_to', rawurlencode( get_permalink( $post_id ) ), $url ) : $url;
}

/**
 * Returns what someone sees in place of restricted content.
 *
 * @param int $post_id
 * @return string
 */
function clu_restricted_message( $post_id = 0 ) {
    if ( is_user_logged_in() ) {
        $message = clu_render_message( 'content_no_access' );
    } else {
        $message = clu_format_message_template(
            clu_get_message( 'content_login_required' ),
            [
                '{login_url}'    => esc_url( clu_restricted_login_url( $post_id ) ),
                '{register_url}' => esc_url( clu_get_page_url( 'url_page_register', home_url( '/register/' ) ) ),
            ]
        );
    }

    return '<div class="clu-restricted">' . $message . '</div>';
}

/**
 * Keeps a page that differs from one user to the next out of page caches.
 */
function clu_do_not_cache() {
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }

    nocache_headers();
}

/* ─── Opening a restricted page ──────────────────────────────────────── */

/**
 * Stops the request itself, not only the content, because a template can show custom
 * fields and an SEO plugin can print the text in the page head. Visitors go to the
 * login page and come back afterwards. A logged-in user without access would only be
 * sent in circles there, so they get a message.
 */
add_action( 'template_redirect', 'clu_guard_restricted_page', 1 );
function clu_guard_restricted_page() {
    if ( ! is_singular() && ! ( is_home() && ! is_front_page() ) ) {
        return;
    }

    $post_id = (int) get_queried_object_id();
    $post    = get_post( $post_id );

    if ( $post && has_shortcode( $post->post_content, 'clu_approved_only' ) ) {
        clu_do_not_cache();
    }

    if ( ! clu_post_is_restricted( $post_id ) ) {
        return;
    }

    clu_do_not_cache();

    if ( clu_user_can_view_restricted( $post_id ) ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( clu_restricted_login_url( $post_id ) );
        exit;
    }

    $no_access_page = clu_saved_page_url( 'url_page_no_access' );

    if ( $no_access_page !== '' ) {
        wp_safe_redirect( $no_access_page );
        exit;
    }

    wp_die( clu_restricted_message( $post_id ), esc_html( get_bloginfo( 'name' ) ), [ 'response' => 403, 'back_link' => true ] ); // phpcs:ignore WordPress.Security.EscapeOutput
}

/* ─── Everywhere else the text can appear ────────────────────────────── */

/**
 * Covers archives, feeds and the REST API, which all pass through this filter. Late, so
 * that nothing added by a page builder at a later priority brings the content back.
 */
add_filter( 'the_content', 'clu_hide_restricted_content', 9999 );
function clu_hide_restricted_content( $content ) {
    $post_id = (int) get_the_ID();

    if ( ! clu_post_is_restricted( $post_id ) || clu_user_can_view_restricted( $post_id ) ) {
        return $content;
    }

    return clu_restricted_message( $post_id );
}

/**
 * Covers a handwritten excerpt, which does not pass through the content filter. Plain
 * text, because themes often print excerpts escaped.
 */
add_filter( 'get_the_excerpt', 'clu_hide_restricted_excerpt', 9999, 2 );
function clu_hide_restricted_excerpt( $excerpt, $post = null ) {
    $post_id = $post ? (int) $post->ID : (int) get_the_ID();

    if ( ! clu_post_is_restricted( $post_id ) || clu_user_can_view_restricted( $post_id ) ) {
        return $excerpt;
    }

    return trim( wp_strip_all_tags( clu_restricted_message( $post_id ) ) );
}

/**
 * Keeps restricted posts out of search results. Searching looks inside the content, so
 * a result would confirm that a post holds the word someone tried.
 */
add_action( 'pre_get_posts', 'clu_hide_restricted_from_search' );
function clu_hide_restricted_from_search( $query ) {
    if ( ! $query->get( 's' ) || ( is_admin() && ! wp_doing_ajax() ) || clu_user_can_view_restricted() ) {
        return;
    }

    $clause   = [ 'key' => '_clu_restricted', 'compare' => 'NOT EXISTS' ];
    $existing = $query->get( 'meta_query' );

    $query->set( 'meta_query', $existing ? [ 'relation' => 'AND', $clause, $existing ] : [ $clause ] );
}

/* ─── The edit screen ────────────────────────────────────────────────── */

/**
 * Returns the post types that offer the restriction. Not media files, which WordPress
 * serves directly, and not WooCommerce products, which could still be bought.
 *
 * @return string[]
 */
function clu_restrictable_post_types() {
    $types = array_diff( array_values( get_post_types( [ 'public' => true ] ) ), [ 'attachment', 'product' ] );

    /**
     * Filters the post types that offer the "approved users only" box.
     *
     * @param string[] $types
     */
    return (array) apply_filters( 'clu_restrictable_post_types', array_values( $types ) );
}

add_action( 'add_meta_boxes', 'clu_add_access_meta_box' );
function clu_add_access_meta_box() {
    add_meta_box( 'clu-access', __( 'Access', 'custom-login-users' ), 'clu_render_access_meta_box', clu_restrictable_post_types(), 'side' );
}

function clu_render_access_meta_box( $post ) {
    if ( clu_is_open_page( $post->ID ) ) {
        echo '<p>' . esc_html__( 'This page holds a login, registration or password form, or is set on the Pages URL tab, so it stays open to everyone.', 'custom-login-users' ) . '</p>';
        return;
    }

    wp_nonce_field( 'clu_save_access', 'clu_access_nonce' );
    ?>
    <p>
        <label>
            <input type="checkbox" name="clu_restricted" value="1" <?php checked( get_post_meta( $post->ID, '_clu_restricted', true ), '1' ); ?>>
            <?php esc_html_e( 'Approved users only', 'custom-login-users' ); ?>
        </label>
    </p>
    <p class="description"><?php esc_html_e( 'Visitors are sent to the login page. This does not cover child pages or uploaded files.', 'custom-login-users' ); ?></p>
    <?php
}

add_action( 'save_post', 'clu_save_access_meta_box', 10, 2 );
function clu_save_access_meta_box( $post_id, $post ) {
    // A quick edit or a save from code has no nonce, and leaves the setting as it is.
    if ( ! wp_verify_nonce( clu_post_text( 'clu_access_nonce' ), 'clu_save_access' ) ) {
        return;
    }

    if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['clu_restricted'] ) ) {
        update_post_meta( $post_id, '_clu_restricted', '1' );
    } else {
        delete_post_meta( $post_id, '_clu_restricted' );
    }
}

/**
 * Marks restricted pages on the lists in wp-admin, next to the title, as WordPress does
 * for a draft, so that what is protected can be seen at a glance.
 */
add_filter( 'display_post_states', 'clu_add_restricted_post_state', 10, 2 );
function clu_add_restricted_post_state( $states, $post ) {
    if ( clu_post_is_restricted( $post->ID ) ) {
        $states['clu_restricted'] = __( 'Approved users only', 'custom-login-users' );
    }

    return $states;
}

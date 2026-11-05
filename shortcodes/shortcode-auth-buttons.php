<?php

defined( 'ABSPATH' ) || exit;

/**
 * Renders login and register links, or the user's name and a logout link.
 *
 * @return string
 */
add_shortcode( 'custom-auth-buttons', 'clu_render_auth_buttons' );
add_shortcode( 'clu_auth_buttons', 'clu_render_auth_buttons' );
function clu_render_auth_buttons() {
    ob_start();

    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();
        $name = $user->first_name ? $user->first_name : $user->user_login;
        ?>
        <span class="user-dropdown">
            <span id="user-menu-toggle"><?php echo esc_html( $name ); ?></span>&nbsp;&nbsp;|&nbsp;&nbsp;<span id="user-dropdown-menu" class="dropdown-content"><a href="<?php echo esc_url( wp_logout_url( clu_get_page_url( 'url_page_redirect_after_logout' ) ) ); ?>"><?php echo esc_html( clu_get_message( 'logout_button_text' ) ); ?></a></span>
        </span>
        <?php
    } else {
        ?>
        <span class="auth-buttons">
            <a href="<?php echo esc_url( clu_get_page_url( 'url_page_login', home_url( '/login' ) ) ); ?>" class="auth-button"><?php echo esc_html( clu_get_message( 'login_button_text' ) ); ?></a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="<?php echo esc_url( clu_get_page_url( 'url_page_register', home_url( '/register' ) ) ); ?>" class="auth-button"><?php echo esc_html( clu_get_message( 'register_button_text' ) ); ?></a>
        </span>
        <?php
    }

    return ob_get_clean();
}

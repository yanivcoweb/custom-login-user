<?php 

function custom_login_register_buttons() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $first_name = $current_user->first_name ? esc_html($current_user->first_name) : esc_html($current_user->user_login);

        ob_start();
		
		$options = get_option('clu_pages_url'); 
		$url_page_redirect_after_logout =  $options['url_page_redirect_after_logout'] ;
				
        ?>
		
        <span class="user-dropdown">
            <span id="user-menu-toggle"><?php echo $first_name; ?></span>&nbsp;&nbsp;|&nbsp;&nbsp;<span id="user-dropdown-menu" class="dropdown-content"><a href="<?php echo wp_logout_url($url_page_redirect_after_logout); ?>"><?php echo esc_html(clu_get_message('logout_button_text')); ?></a></span>
        </span>


        <?php
    } else {
        $options          = get_option('clu_pages_url');
        $url_login        = !empty($options['url_page_login'])    ? $options['url_page_login']    : home_url('/login');
        $url_register     = !empty($options['url_page_register']) ? $options['url_page_register'] : home_url('/register');
        ob_start();
        ?>
        <span class="auth-buttons">
            <a href="<?php echo esc_url($url_login); ?>" class="auth-button"><?php echo esc_html(clu_get_message('login_button_text')); ?></a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="<?php echo esc_url($url_register); ?>" class="auth-button"><?php echo esc_html(clu_get_message('register_button_text')); ?></a>
        </span>

        <?php
    }

    return ob_get_clean();
}

add_shortcode('custom-auth-buttons', 'custom_login_register_buttons');

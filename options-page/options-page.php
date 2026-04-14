<?php


function my_custom_settings_page() {
    add_options_page(
        'Custom Login Users Settings',
        'Custom Login Users Settings',
        'manage_options',
        'my-custom-settings',
        'my_custom_settings_page_html'
    );
}
add_action('admin_menu', 'my_custom_settings_page');

// Define tabs, their settings groups, sections, and field files
function get_custom_settings_tabs() {
		return [
					'pages-url-settings' => [
						'title' => 'Pages URL',
						'settings_group' => 'pages-url-group',
						'section_id' => 'pages-url-section',
						'section_title' => 'Pages URL Settings',
						'callback' => 'my_custom_settings_section_callback',
						'fields_file' => CLU_THEME_DIR . 'options-page/arrays/array_pages_url.php',
						'option_name' => 'clu_pages_url',
					],
					'mail-settings' => [
						'title' => 'Mail',
						'settings_group' => 'mail-group',
						'section_id' => 'mail-section',
						'section_title' => 'Mail Settings',
						'callback' => 'my_custom_settings_section_callback',
						'fields_file' => CLU_THEME_DIR . 'options-page/arrays/array_mail.php',
						'option_name' => 'clu_mail',
					],
					'register-form-settings' => [
						'title' => 'Register Form',
						'settings_group' => 'register-form-group',
						'section_id' => 'register-form-section',
						'section_title' => 'Register Form Settings',
						'callback' => 'my_custom_settings_section_callback',
						'fields_file' => CLU_THEME_DIR . 'options-page/arrays/array_register_form.php',
						'option_name' => 'clu_register_form',
						'shortcode' => '[custom-register-form]',
					],
					'login-form-settings' => [
						'title' => 'Login Form',
						'settings_group' => 'login-form-group',
						'section_id' => 'login-form-section',
						'section_title' => 'Login Form Settings',
						'callback' => 'my_custom_settings_section_callback',
						'fields_file' => CLU_THEME_DIR . 'options-page/arrays/array_login_form.php',
						'option_name' => 'clu_login_form',
						'shortcode' => '[custom-login-form]',
					],
					'password-reset-form-settings' => [
						'title' => 'Password Reset Form',
						'settings_group' => 'password-reset-form-group',
						'section_id' => 'password-reset-form-section',
						'section_title' => 'Password Reset Form Settings',
						'callback' => 'my_custom_settings_section_callback',
						'fields_file' => CLU_THEME_DIR . 'options-page/arrays/array_password_reset_form.php',
						'option_name' => 'clu_password_reset_form',
						'shortcode' => '[custom-password-set]',
					],

					'password-lost-form-settings' => [
						'title' => 'Password Lost Form',
						'settings_group' => 'password-lost-form-group',
						'section_id' => 'password-lost-form-section',
						'section_title' => 'Password Lost Form Settings',
						'callback' => 'my_custom_settings_section_callback',
						'fields_file' => CLU_THEME_DIR . 'options-page/arrays/array_password_lost_form.php',
						'option_name' => 'clu_password_lost_form',
						'shortcode' => '[custom-password-lost-form]',
					],
					'errors-messages-settings' => [
						'title' => 'Errors Messages',
						'settings_group' => 'errors-messages-group',
						'section_id' => 'errors-messages-section',
						'section_title' => 'Errors Messages',
						'callback' => 'my_custom_settings_section_callback',
						'fields_file' => CLU_THEME_DIR . 'options-page/arrays/array_errors-messages.php',
						'option_name' => 'clu_errors_messages',
					],
					'password-rules-settings' => [
						'title' => 'Password Rules',
						'type'  => 'password_rules',
					],
					'messages' => [
						'title' => 'Messages',
						'type'  => 'messages',
					],
					'user-roles' => [
						'title' => 'User Roles',
						'type'  => 'roles',
					],
					'setup-pages' => [
						'title' => 'Setup Pages',
						'type'  => 'setup',
					],
					// Add more tabs here as needed
				];
}

function my_custom_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $tabs = get_custom_settings_tabs();
    ?>
    <div class="wrap clu-custom-settings">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div class="clu-shortcode-info notice notice-info inline">
            <p><strong>Auth Buttons Shortcode:</strong> <code>[custom-auth-buttons]</code></p>
        </div>
        <nav class="nav-tab-wrapper">
            <?php foreach ($tabs as $tab_id => $tab) : ?>
                <a href="#<?php echo esc_attr($tab_id); ?>" class="nav-tab"><?php echo esc_html($tab['title']); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php foreach ($tabs as $tab_id => $tab) : ?>
            <div id="<?php echo esc_attr($tab_id); ?>" class="tab-content ">
                <?php if ( isset($tab['type']) && $tab['type'] === 'password_rules' ) : ?>
                    <?php clu_render_password_rules_tab(); ?>
                <?php elseif ( isset($tab['type']) && $tab['type'] === 'messages' ) : ?>
                    <?php clu_render_messages_tab(); ?>
                <?php elseif ( isset($tab['type']) && $tab['type'] === 'roles' ) : ?>
                    <?php clu_render_roles_tab(); ?>
                <?php elseif ( isset($tab['type']) && $tab['type'] === 'setup' ) : ?>
                    <?php clu_render_setup_pages_tab(); ?>
                <?php else : ?>
                    <?php if ( ! empty( $tab['shortcode'] ) ) : ?>
                        <p class="clu-shortcode-box">
                            <strong>Shortcode:</strong>
                            <code><?php echo esc_html( $tab['shortcode'] ); ?></code>
                        </p>
                    <?php endif; ?>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields($tab['settings_group']);
                        do_settings_sections($tab['settings_group']);
                        submit_button('Save ' . esc_html($tab['title']));
                        ?>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .clu-shortcode-box { margin: 12px 0; padding: 8px 12px; background: #f0f6fc; border-left: 4px solid #2271b1; }
        .clu-shortcode-box code { font-size: 14px; background: none; color: #1d2327; }
        .clu-setup-table { margin-top: 12px; }
        .clu-setup-table th, .clu-setup-table td { padding: 8px 12px; }
        .clu-status--exists { color: #007017; }
        .clu-status--missing { color: #787c82; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.querySelectorAll('.nav-tab');
            const contents = document.querySelectorAll('.tab-content');
            tabs.forEach((tab, index) => {
                tab.addEventListener('click', function (e) {
                    e.preventDefault();
                    tabs.forEach(t => t.classList.remove('nav-tab-active'));
                    contents.forEach(c => c.classList.remove('active'));
                    tab.classList.add('nav-tab-active');
                    contents[index].classList.add('active');
                });
            });
            // Activate the first tab by default
            tabs[0].classList.add('nav-tab-active');
            contents[0].classList.add('active');
        });
    </script>
    <?php
}

function my_custom_settings_init() {
    $tabs = get_custom_settings_tabs();

    foreach ($tabs as $tab_id => $tab) {
        if ( isset($tab['type']) ) {
            continue;
        }

        register_setting($tab['settings_group'], $tab['option_name']);

        add_settings_section(
            $tab['section_id'],
            $tab['section_title'],
            $tab['callback'],
            $tab['settings_group']
        );

        $fields = include $tab['fields_file'];

        foreach ($fields as $field) {
            add_settings_field(
                $field['field'],
                $field['label'],
                function ($args) use ($tab) {
                    $options = get_option($tab['option_name']);
                    $value = $options[$args['label_for']] ?? $args['default'];
                    echo "<input type='text' id='{$args['label_for']}' name='{$tab['option_name']}[{$args['label_for']}]' value='" . esc_attr($value) . "' placeholder='" . esc_attr($args['placeholder']) . "' />";
                },
                $tab['settings_group'],
                $tab['section_id'],
                [
                    'label_for' => $field['field'],
                    'placeholder' => $field['placeholder'],
                    'default' => $field['default']
                ]
            );
        }
    }
}
add_action('admin_init', 'my_custom_settings_init');

function clu_render_password_rules_tab() {
    $rules = clu_get_password_rules();
    ?>
    <h2>Password Rules</h2>
    <p>Configure the password strength requirements applied on the Set Password form.</p>

    <table class="form-table">
        <tr>
            <th><label for="clu-pw-min-length">Minimum length</label></th>
            <td>
                <input type="number"
                       id="clu-pw-min-length"
                       min="1"
                       max="128"
                       value="<?php echo esc_attr( (int) $rules['min_length'] ); ?>"
                       style="width:80px;" />
                <p class="description">Password must be at least this many characters long.</p>
            </td>
        </tr>
        <tr>
            <th><label for="clu-pw-require-uppercase">Require uppercase letter</label></th>
            <td>
                <input type="checkbox"
                       id="clu-pw-require-uppercase"
                       value="1"
                       <?php checked( 1, (int) $rules['require_uppercase'] ); ?> />
                <p class="description">Password must contain at least one uppercase letter (A-Z).</p>
            </td>
        </tr>
        <tr>
            <th><label for="clu-pw-require-lowercase">Require lowercase letter</label></th>
            <td>
                <input type="checkbox"
                       id="clu-pw-require-lowercase"
                       value="1"
                       <?php checked( 1, (int) $rules['require_lowercase'] ); ?> />
                <p class="description">Password must contain at least one lowercase letter (a-z).</p>
            </td>
        </tr>
        <tr>
            <th><label for="clu-pw-require-number">Require number</label></th>
            <td>
                <input type="checkbox"
                       id="clu-pw-require-number"
                       value="1"
                       <?php checked( 1, (int) $rules['require_number'] ); ?> />
                <p class="description">Password must contain at least one digit (0-9).</p>
            </td>
        </tr>
        <tr>
            <th><label for="clu-pw-require-special">Require special character</label></th>
            <td>
                <input type="checkbox"
                       id="clu-pw-require-special"
                       value="1"
                       <?php checked( 1, (int) $rules['require_special'] ); ?> />
                <p class="description">Password must contain at least one special character (!@#$%^&amp;*()).</p>
            </td>
        </tr>
    </table>

    <p style="margin-top:20px;">
        <button type="button" id="clu-save-pw-rules-btn" class="button button-primary">Save Password Rules</button>
        <span id="clu-pw-rules-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
    </p>
    <div id="clu-pw-rules-result"></div>

    <script>
    (function($){
        var nonce = '<?php echo esc_js( wp_create_nonce('clu_password_rules_nonce') ); ?>';

        $('#clu-save-pw-rules-btn').on('click', function(){
            var $btn     = $(this);
            var $spinner = $('#clu-pw-rules-spinner');
            var $result  = $('#clu-pw-rules-result');

            $btn.prop('disabled', true);
            $spinner.css('display', 'inline-block');
            $result.html('');

            $.post(ajaxurl, {
                action            : 'clu_save_password_rules',
                nonce             : nonce,
                min_length        : $('#clu-pw-min-length').val(),
                require_uppercase : $('#clu-pw-require-uppercase').is(':checked') ? 1 : 0,
                require_lowercase : $('#clu-pw-require-lowercase').is(':checked') ? 1 : 0,
                require_number    : $('#clu-pw-require-number').is(':checked')    ? 1 : 0,
                require_special   : $('#clu-pw-require-special').is(':checked')   ? 1 : 0
            }, function(response){
                $btn.prop('disabled', false);
                $spinner.hide();
                var cls = response.success ? 'notice-success' : 'notice-error';
                $result.html('<div class="notice ' + cls + ' inline"><p>' + response.data.message + '</p></div>');
            }, 'json').fail(function(){
                $btn.prop('disabled', false);
                $spinner.hide();
                $result.html('<div class="notice notice-error inline"><p>Request failed.</p></div>');
            });
        });
    }(jQuery));
    </script>
    <?php
}

function clu_render_messages_tab() {
    $defaults = clu_default_messages();
    $saved    = get_option('clu_messages', []);

    // Helper: current value (saved or default)
    $val = function($key) use ($defaults, $saved) {
        return isset($saved[$key]) && $saved[$key] !== '' ? $saved[$key] : ($defaults[$key] ?? '');
    };

    $sections = [
        'AJAX Messages' => [
            'invalid_nonce'               => 'Invalid nonce',
            'general_error'               => 'General error',
            'login_success'               => 'Login: success',
            'register_required_fields'    => 'Register: required fields missing',
            'email_already_registered'    => 'Register: email already registered',
            'register_success'            => 'Register: success',
            'email_required'              => 'Password lost: email required',
            'user_not_found'              => 'Password lost: user not found',
            'reset_email_send_failed'     => 'Password lost: email send failed',
            'reset_email_sent'            => 'Password lost: email sent',
            'password_set_missing_fields' => 'Password set: missing fields',
            'invalid_reset_link'          => 'Password set: invalid reset link',
            'password_min_length'         => 'Password set: min length',
            'password_uppercase'          => 'Password set: uppercase required',
            'password_lowercase'          => 'Password set: lowercase required',
            'password_number'             => 'Password set: number required',
            'password_special_char'       => 'Password set: special char required',
            'password_reset_success'      => 'Password set: success',
        ],
        'Shortcode Text' => [
            'already_logged_in'       => 'Login form: already logged in (<code>{logout_url}</code> = logout link)',
            'user_logged_in_register' => 'Register form: already logged in',
            'registration_disabled'   => 'Register form: registration disabled',
            'invalid_reset_link_page' => 'Set password page: invalid link',
            'expired_reset_link_page' => 'Set password page: expired link',
            'logout_button_text'      => 'Auth buttons: logout text',
            'login_button_text'       => 'Auth buttons: login text',
            'register_button_text'    => 'Auth buttons: register text',
        ],
        'Email Templates' => [
            'reset_email_subject'   => 'Password reset email — subject',
            'reset_email_body'      => 'Password reset email — body (<code>%1$s</code> = name, <code>%2$s</code> = reset URL)',
            'notify_client_subject' => 'New registration email to admin — subject (<code>%s</code> = name)',
            'notify_client_body'    => 'New registration email to admin — body (<code>%1$s</code> = name, <code>%2$s</code> = edit user URL)',
            'notify_user_subject'   => 'Account approved email to user — subject (<code>%s</code> = name)',
            'notify_user_body'      => 'Account approved email to user — body (<code>%1$s</code> = name, <code>%2$s</code> = set-password URL)',
        ],
    ];

    // Keys that need a textarea instead of a text input
    $textarea_keys = ['already_logged_in', 'reset_email_body', 'notify_client_body', 'notify_user_body'];
    ?>
    <h2>Plugin Messages</h2>
    <p>Edit the text shown to users. Leave a field blank to use the built-in default.</p>

    <?php foreach ($sections as $section_title => $fields) : ?>
        <h3 style="border-bottom:1px solid #ddd;padding-bottom:6px;margin-top:24px;"><?php echo esc_html($section_title); ?></h3>
        <table class="form-table">
            <?php foreach ($fields as $key => $label) :
                $current = $val($key);
                $is_textarea = in_array($key, $textarea_keys, true);
            ?>
            <tr>
                <th><label for="clu-msg-<?php echo esc_attr($key); ?>"><?php echo wp_kses_post($label); ?></label></th>
                <td>
                    <?php if ($is_textarea) : ?>
                        <textarea id="clu-msg-<?php echo esc_attr($key); ?>"
                                  name="<?php echo esc_attr($key); ?>"
                                  class="large-text"
                                  rows="4"><?php echo esc_textarea($current); ?></textarea>
                    <?php else : ?>
                        <input type="text"
                               id="clu-msg-<?php echo esc_attr($key); ?>"
                               name="<?php echo esc_attr($key); ?>"
                               class="large-text"
                               value="<?php echo esc_attr($current); ?>" />
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>

    <p style="margin-top:20px;">
        <button type="button" id="clu-save-messages-btn" class="button button-primary">Save Messages</button>
        <button type="button" id="clu-restore-messages-btn" class="button" style="margin-left:8px;">Restore Defaults</button>
        <span id="clu-messages-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
    </p>
    <div id="clu-messages-result"></div>

    <script>
    (function($){
        var nonce = '<?php echo esc_js(wp_create_nonce('clu_messages_nonce')); ?>';

        $('#clu-save-messages-btn').on('click', function(){
            var $btn     = $(this);
            var $spinner = $('#clu-messages-spinner');
            var $result  = $('#clu-messages-result');
            var data     = { action: 'clu_save_messages', nonce: nonce };

            $('input[name], textarea[name]', '#<?php echo esc_js($tab_id ?? 'messages'); ?>').each(function(){
                data[$(this).attr('name')] = $(this).val();
            });

            // Collect all named inputs/textareas in this tab
            $('#clu-msg-invalid_nonce, #clu-msg-general_error, #clu-msg-login_success, #clu-msg-register_required_fields, #clu-msg-email_already_registered, #clu-msg-register_success, #clu-msg-email_required, #clu-msg-user_not_found, #clu-msg-reset_email_send_failed, #clu-msg-reset_email_sent, #clu-msg-password_set_missing_fields, #clu-msg-invalid_reset_link, #clu-msg-password_min_length, #clu-msg-password_uppercase, #clu-msg-password_lowercase, #clu-msg-password_number, #clu-msg-password_special_char, #clu-msg-password_reset_success, #clu-msg-already_logged_in, #clu-msg-user_logged_in_register, #clu-msg-registration_disabled, #clu-msg-invalid_reset_link_page, #clu-msg-expired_reset_link_page, #clu-msg-logout_button_text, #clu-msg-login_button_text, #clu-msg-register_button_text, #clu-msg-reset_email_subject, #clu-msg-reset_email_body, #clu-msg-notify_client_subject, #clu-msg-notify_client_body, #clu-msg-notify_user_subject, #clu-msg-notify_user_body').each(function(){
                data[$(this).attr('id').replace('clu-msg-', '')] = $(this).val();
            });

            $btn.prop('disabled', true);
            $spinner.css('display', 'inline-block');
            $result.html('');

            $.post(ajaxurl, data, function(response){
                $btn.prop('disabled', false);
                $spinner.hide();
                var cls = response.success ? 'notice-success' : 'notice-error';
                $result.html('<div class="notice ' + cls + ' inline"><p>' + response.data.message + '</p></div>');
            }, 'json').fail(function(){
                $btn.prop('disabled', false);
                $spinner.hide();
                $result.html('<div class="notice notice-error inline"><p>Request failed.</p></div>');
            });
        });

        $('#clu-restore-messages-btn').on('click', function(){
            if (!confirm('Restore all messages to their built-in defaults?')) return;
            var $btn     = $(this);
            var $spinner = $('#clu-messages-spinner');
            var $result  = $('#clu-messages-result');
            $btn.prop('disabled', true);
            $spinner.css('display', 'inline-block');
            $result.html('');
            $.post(ajaxurl, { action: 'clu_restore_default_messages', nonce: nonce }, function(response){
                $btn.prop('disabled', false);
                $spinner.hide();
                if (response.success) {
                    // Populate fields with returned defaults
                    $.each(response.data.defaults, function(key, value){
                        var $el = $('#clu-msg-' + key);
                        $el.val(value);
                    });
                    var cls = 'notice-success';
                    $result.html('<div class="notice ' + cls + ' inline"><p>' + response.data.message + '</p></div>');
                }
            }, 'json').fail(function(){
                $btn.prop('disabled', false);
                $spinner.hide();
                $result.html('<div class="notice notice-error inline"><p>Request failed.</p></div>');
            });
        });
    }(jQuery));
    </script>
    <?php
}

function clu_render_roles_tab() {
    global $wp_roles;
    $all_roles     = $wp_roles->roles;
    $options       = get_option('clu_roles', []);
    $pending_role  = !empty($options['pending_role'])  ? $options['pending_role']  : 'pending_user';
    $approved_role = !empty($options['approved_role']) ? $options['approved_role'] : 'approved_user';
    ?>
    <h2>Role Assignments</h2>
    <p>Choose which WordPress role is assigned on registration and which role means the user is approved.</p>

    <table class="form-table">
        <tr>
            <th><label for="clu-pending-role">Registration role (pending)</label></th>
            <td>
                <select id="clu-pending-role">
                    <?php foreach ($all_roles as $slug => $role) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug, $pending_role); ?>>
                            <?php echo esc_html($role['name']); ?> (<?php echo esc_html($slug); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Newly registered users are assigned this role.</p>
            </td>
        </tr>
        <tr>
            <th><label for="clu-approved-role">Approval role (approved)</label></th>
            <td>
                <select id="clu-approved-role">
                    <?php foreach ($all_roles as $slug => $role) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug, $approved_role); ?>>
                            <?php echo esc_html($role['name']); ?> (<?php echo esc_html($slug); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">When a user is changed to this role they receive a set-password email.</p>
            </td>
        </tr>
    </table>

    <p>
        <button type="button" id="clu-save-roles-btn" class="button button-primary">Save Role Assignments</button>
        <span id="clu-save-roles-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
    </p>
    <div id="clu-save-roles-result"></div>

    <hr style="margin:24px 0;">

    <h2>Create New Role</h2>
    <p>Create a custom WordPress role. Once created it will appear in the dropdowns above.</p>

    <table class="form-table">
        <tr>
            <th><label for="clu-new-role-slug">Role slug</label></th>
            <td>
                <input type="text" id="clu-new-role-slug" class="regular-text" placeholder="e.g. my_custom_role" />
                <p class="description">Lowercase letters, numbers, and underscores only.</p>
            </td>
        </tr>
        <tr>
            <th><label for="clu-new-role-name">Display name</label></th>
            <td><input type="text" id="clu-new-role-name" class="regular-text" placeholder="e.g. My Custom Role" /></td>
        </tr>
    </table>

    <p>
        <button type="button" id="clu-create-role-btn" class="button">Create Role</button>
        <span id="clu-create-role-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
    </p>
    <div id="clu-create-role-result"></div>

    <hr style="margin:24px 0;">

    <h2>Delete Role</h2>
    <p>Permanently delete a custom role. Users with this role will be left without a role.</p>

    <table class="form-table">
        <tr>
            <th><label for="clu-delete-role-select">Select role to delete</label></th>
            <td>
                <select id="clu-delete-role-select">
                    <option value="">— Select a role —</option>
                    <?php
                    $protected = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];
                    foreach ($all_roles as $slug => $role) :
                        if (in_array($slug, $protected, true)) continue;
                    ?>
                        <option value="<?php echo esc_attr($slug); ?>">
                            <?php echo esc_html($role['name']); ?> (<?php echo esc_html($slug); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>

    <p>
        <button type="button" id="clu-delete-role-btn" class="button button-link-delete">Delete Role</button>
        <span id="clu-delete-role-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
    </p>
    <div id="clu-delete-role-result"></div>

    <script>
    (function($){
        var nonce = '<?php echo esc_js(wp_create_nonce('clu_roles_nonce')); ?>';

        $('#clu-save-roles-btn').on('click', function(){
            var $btn     = $(this);
            var $spinner = $('#clu-save-roles-spinner');
            var $result  = $('#clu-save-roles-result');
            $btn.prop('disabled', true);
            $spinner.css('display', 'inline-block');
            $result.html('');
            $.post(ajaxurl, {
                action        : 'clu_save_roles',
                nonce         : nonce,
                pending_role  : $('#clu-pending-role').val(),
                approved_role : $('#clu-approved-role').val()
            }, function(response){
                $btn.prop('disabled', false);
                $spinner.hide();
                var cls = response.success ? 'notice-success' : 'notice-error';
                $result.html('<div class="notice ' + cls + ' inline"><p>' + response.data.message + '</p></div>');
            }, 'json').fail(function(){
                $btn.prop('disabled', false);
                $spinner.hide();
                $result.html('<div class="notice notice-error inline"><p>Request failed.</p></div>');
            });
        });

        $('#clu-create-role-btn').on('click', function(){
            var $btn     = $(this);
            var $spinner = $('#clu-create-role-spinner');
            var $result  = $('#clu-create-role-result');
            $btn.prop('disabled', true);
            $spinner.css('display', 'inline-block');
            $result.html('');
            $.post(ajaxurl, {
                action            : 'clu_create_role',
                nonce             : nonce,
                role_slug         : $('#clu-new-role-slug').val(),
                role_display_name : $('#clu-new-role-name').val()
            }, function(response){
                $btn.prop('disabled', false);
                $spinner.hide();
                var cls = response.success ? 'notice-success' : 'notice-error';
                $result.html('<div class="notice ' + cls + ' inline"><p>' + response.data.message + '</p></div>');
                if (response.success) {
                    var opt = $('<option>')
                        .val(response.data.slug)
                        .text(response.data.name + ' (' + response.data.slug + ')');
                    $('#clu-pending-role, #clu-approved-role').append(opt.clone());
                    $('#clu-delete-role-select').append(opt.clone());
                    $('#clu-new-role-slug, #clu-new-role-name').val('');
                }
            }, 'json').fail(function(){
                $btn.prop('disabled', false);
                $spinner.hide();
                $result.html('<div class="notice notice-error inline"><p>Request failed.</p></div>');
            });
        });

        $('#clu-delete-role-btn').on('click', function(){
            var slug = $('#clu-delete-role-select').val();
            if (!slug) {
                $('#clu-delete-role-result').html('<div class="notice notice-error inline"><p>Please select a role to delete.</p></div>');
                return;
            }
            if (!confirm('Are you sure you want to delete the role "' + slug + '"? This cannot be undone.')) {
                return;
            }
            var $btn     = $(this);
            var $spinner = $('#clu-delete-role-spinner');
            var $result  = $('#clu-delete-role-result');
            $btn.prop('disabled', true);
            $spinner.css('display', 'inline-block');
            $result.html('');
            $.post(ajaxurl, {
                action    : 'clu_delete_role',
                nonce     : nonce,
                role_slug : slug
            }, function(response){
                $btn.prop('disabled', false);
                $spinner.hide();
                var cls = response.success ? 'notice-success' : 'notice-error';
                $result.html('<div class="notice ' + cls + ' inline"><p>' + response.data.message + '</p></div>');
                if (response.success) {
                    $('#clu-delete-role-select option[value="' + slug + '"]').remove();
                    $('#clu-pending-role option[value="' + slug + '"]').remove();
                    $('#clu-approved-role option[value="' + slug + '"]').remove();
                }
            }, 'json').fail(function(){
                $btn.prop('disabled', false);
                $spinner.hide();
                $result.html('<div class="notice notice-error inline"><p>Request failed.</p></div>');
            });
        });
    }(jQuery));
    </script>
    <?php
}

function clu_render_setup_pages_tab() {
    $pages = [
        [
            'title'     => 'Login',
            'slug'      => 'login',
            'shortcode' => '[custom-login-form]',
        ],
        [
            'title'     => 'Register',
            'slug'      => 'register',
            'shortcode' => '[custom-register-form]',
        ],
        [
            'title'     => 'Set Password',
            'slug'      => 'set-password',
            'shortcode' => '[custom-password-set]',
        ],
        [
            'title'     => 'Password Lost',
            'slug'      => 'password-lost',
            'shortcode' => '[custom-password-lost-form]',
        ],
        [
            'title' => 'My Account',
            'slug'  => 'my-account',
        ],
        [
            'title' => 'Logged Out',
            'slug'  => 'logged-out',
        ],
        [
            'title' => 'Password Updated',
            'slug'  => 'password-updated',
        ],
    ];
    ?>
    <h2>Auto-Create Plugin Pages</h2>
    <p>Click the button below to automatically create all required pages and populate the <strong>Pages URL</strong> tab with their addresses.</p>
    <p>If a page with the same slug already exists it will be kept as-is and its URL will be saved to the settings.</p>

    <table class="widefat striped clu-setup-table">
        <thead>
            <tr>
                <th>Page Title</th>
                <th>Slug</th>
                <th>Shortcode</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pages as $page) :
                $existing = get_page_by_path($page['slug']);
            ?>
            <tr data-slug="<?php echo esc_attr($page['slug']); ?>">
                <td><?php echo esc_html($page['title']); ?></td>
                <td><code>/<?php echo esc_html($page['slug']); ?>/</code></td>
                <td><?php echo !empty($page['shortcode']) ? '<code>' . esc_html($page['shortcode']) . '</code>' : '&mdash;'; ?></td>
                <td class="clu-page-status">
                    <?php if ($existing) : ?>
                        <span class="clu-status clu-status--exists">&#10003; Exists &mdash; <a href="<?php echo esc_url(get_permalink($existing->ID)); ?>" target="_blank"><?php echo esc_url(get_permalink($existing->ID)); ?></a></span>
                    <?php else : ?>
                        <span class="clu-status clu-status--missing">&mdash; Not created yet</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top:16px;">
        <button type="button" id="clu-create-pages-btn" class="button button-primary">
            Create Pages &amp; Save URLs
        </button>
        <span id="clu-create-pages-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
    </p>
    <div id="clu-create-pages-result"></div>

    <script>
    (function($){
        $('#clu-create-pages-btn').on('click', function(){
            var $btn     = $(this);
            var $spinner = $('#clu-create-pages-spinner');
            var $result  = $('#clu-create-pages-result');

            $btn.prop('disabled', true);
            $spinner.css('display','inline-block');
            $result.html('');

            $.post(ajaxurl, {
                action : 'clu_create_pages',
                nonce  : '<?php echo esc_js(wp_create_nonce('clu_create_pages_nonce')); ?>'
            }, function(response){
                $btn.prop('disabled', false);
                $spinner.hide();

                if (!response.success) {
                    $result.html('<div class="notice notice-error inline"><p>' + (response.data.message || 'An error occurred.') + '</p></div>');
                    return;
                }

                var html = '<div class="notice notice-success inline"><p><strong>Done!</strong></p><ul>';
                $.each(response.data.results, function(i, item){
                    var icon = item.status === 'created' ? '&#10003; Created' : (item.status === 'exists' ? '&#8627; Already exists' : '&#10007; Error');
                    var link = item.url ? ' &mdash; <a href="' + item.url + '" target="_blank">' + item.url + '</a>' : (item.message ? ' &mdash; ' + item.message : '');
                    html += '<li><strong>' + item.title + '</strong>: ' + icon + link + '</li>';
                });
                html += '</ul><p>The <strong>Pages URL</strong> tab has been updated automatically.</p></div>';
                $result.html(html);

                // Refresh status cells in the table
                $.each(response.data.results, function(i, item){
                    if (item.url) {
                        $('tr[data-slug]').filter(function(){
                            return $(this).find('td:nth-child(2) code').text().trim() === '/' + $(this).data('slug') + '/';
                        });
                    }
                });
            }, 'json').fail(function(){
                $btn.prop('disabled', false);
                $spinner.hide();
                $result.html('<div class="notice notice-error inline"><p>Request failed. Please try again.</p></div>');
            });
        });
    }(jQuery));
    </script>
    <?php
}

function my_custom_settings_section_callback() {
    //echo '<p>Customize error messages below:</p>';
}

// function forms_text_section_callback() {
    // echo '<p>Customize text used in forms below:</p>';
// }


?>


<?php

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'clu_add_settings_page' );
function clu_add_settings_page() {
    add_options_page(
        'Custom Login Users Settings',
        'Custom Login Users',
        'manage_options',
        'clu-settings',
        'clu_render_settings_page'
    );
}

add_filter( 'plugin_action_links_' . plugin_basename( CLU_PLUGIN_FILE ), 'clu_add_settings_link' );
function clu_add_settings_link( $links ) {
    array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=clu-settings' ) ) . '">Settings</a>' );

    return $links;
}

add_action( 'admin_enqueue_scripts', 'clu_enqueue_admin_assets' );
function clu_enqueue_admin_assets( $hook ) {
    if ( $hook !== 'settings_page_clu-settings' ) {
        return;
    }

    wp_enqueue_style( 'clu-admin', CLU_PLUGIN_URL . 'css/admin-style.css', [], CLU_VERSION );
    wp_enqueue_script( 'clu-admin', CLU_PLUGIN_URL . 'js/admin.js', [ 'jquery' ], CLU_VERSION, true );
    wp_localize_script( 'clu-admin', 'cluAdmin', [
        'passwordRulesNonce' => wp_create_nonce( 'clu_password_rules_nonce' ),
        'messagesNonce'      => wp_create_nonce( 'clu_messages_nonce' ),
        'rolesNonce'         => wp_create_nonce( 'clu_roles_nonce' ),
        'createPagesNonce'   => wp_create_nonce( 'clu_create_pages_nonce' ),
        'registerFieldsNonce' => wp_create_nonce( 'clu_register_fields_nonce' ),
        'loginReplacementNonce' => wp_create_nonce( 'clu_login_replacement_nonce' ),
    ] );

    // For the preview of the bot check on the Login Protection tab.
    clu_enqueue_captcha_script();
}

/**
 * Returns the settings tabs. Tabs with a 'type' render themselves; the others are
 * built from their fields file and saved through the Settings API.
 *
 * @return array
 */
function clu_get_settings_tabs() {
    $fields_dir = CLU_PLUGIN_DIR . 'options-page/arrays/';

    return [
        'pages-url-settings' => [
            'title'          => 'Pages URL',
            'settings_group' => 'pages-url-group',
            'section_id'     => 'pages-url-section',
            'section_title'  => 'Pages URL Settings',
            'fields_file'    => $fields_dir . 'array_pages_url.php',
            'option_name'    => 'clu_pages_url',
        ],
        'mail-settings' => [
            'title'          => 'Mail',
            'settings_group' => 'mail-group',
            'section_id'     => 'mail-section',
            'section_title'  => 'Mail Settings',
            'fields_file'    => $fields_dir . 'array_mail.php',
            'option_name'    => 'clu_mail',
        ],
        'register-form-settings' => [
            'title'          => 'Register Form',
            'settings_group' => 'register-form-group',
            'section_id'     => 'register-form-section',
            'section_title'  => 'Register Form Settings',
            'fields_file'    => $fields_dir . 'array_register_form.php',
            'option_name'    => 'clu_register_form',
            'shortcode'      => '[clu_register_form]',
        ],
        'register-fields-settings' => [
            'title' => 'Register Fields',
            'type'  => 'register_fields',
        ],
        'login-form-settings' => [
            'title'          => 'Login Form',
            'settings_group' => 'login-form-group',
            'section_id'     => 'login-form-section',
            'section_title'  => 'Login Form Settings',
            'fields_file'    => $fields_dir . 'array_login_form.php',
            'option_name'    => 'clu_login_form',
            'shortcode'      => '[clu_login_form]',
        ],
        'password-reset-form-settings' => [
            'title'          => 'Password Reset Form',
            'settings_group' => 'password-reset-form-group',
            'section_id'     => 'password-reset-form-section',
            'section_title'  => 'Password Reset Form Settings',
            'fields_file'    => $fields_dir . 'array_password_reset_form.php',
            'option_name'    => 'clu_password_reset_form',
            'shortcode'      => '[clu_password_set_form]',
        ],
        'password-lost-form-settings' => [
            'title'          => 'Password Lost Form',
            'settings_group' => 'password-lost-form-group',
            'section_id'     => 'password-lost-form-section',
            'section_title'  => 'Password Lost Form Settings',
            'fields_file'    => $fields_dir . 'array_password_lost_form.php',
            'option_name'    => 'clu_password_lost_form',
            'shortcode'      => '[clu_password_lost_form]',
        ],
        'my-account-form-settings' => [
            'title'          => 'My Account Form',
            'settings_group' => 'my-account-form-group',
            'section_id'     => 'my-account-form-section',
            'section_title'  => 'My Account Form Settings',
            'fields_file'    => $fields_dir . 'array_my_account_form.php',
            'option_name'    => 'clu_my_account_form',
            'shortcode'      => '[clu_my_account]',
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
        'login-protection-settings' => [
            'title'          => 'Login Protection',
            'settings_group' => 'login-protection-group',
            'section_id'     => 'login-protection-section',
            'section_title'  => 'Login Protection',
            'fields_file'    => $fields_dir . 'array_security.php',
            'option_name'    => 'clu_security',
        ],
        'setup-pages' => [
            'title' => 'Setup Pages',
            'type'  => 'setup',
        ],
    ];
}

function clu_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // The default texts show in the language visitors see, whatever the admin's own.
    $switched = switch_to_locale( get_locale() );
    $tabs     = clu_get_settings_tabs();
    ?>
    <div class="wrap clu-custom-settings">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <div class="clu-shortcode-info notice notice-info inline">
            <p><strong>Auth Buttons Shortcode:</strong> <code>[clu_auth_buttons]</code></p>
            <p><strong>Approved Users Only:</strong> <code>[clu_approved_only]...[/clu_approved_only]</code> around part of a page, or the <strong>Access</strong> box on the edit screen for a whole page.</p>
            <p>The older shortcode names, such as <code>[custom-login-form]</code>, keep working on existing pages.</p>
        </div>
        <nav class="nav-tab-wrapper">
            <?php foreach ( $tabs as $tab_id => $tab ) : ?>
                <a href="#<?php echo esc_attr( $tab_id ); ?>" class="nav-tab"><?php echo esc_html( $tab['title'] ); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php foreach ( $tabs as $tab_id => $tab ) : ?>
            <div id="<?php echo esc_attr( $tab_id ); ?>" class="tab-content">
                <?php if ( ( $tab['type'] ?? '' ) === 'register_fields' ) : ?>
                    <?php clu_render_register_fields_tab(); ?>
                <?php elseif ( ( $tab['type'] ?? '' ) === 'password_rules' ) : ?>
                    <?php clu_render_password_rules_tab(); ?>
                <?php elseif ( ( $tab['type'] ?? '' ) === 'messages' ) : ?>
                    <?php clu_render_messages_tab(); ?>
                <?php elseif ( ( $tab['type'] ?? '' ) === 'roles' ) : ?>
                    <?php clu_render_roles_tab(); ?>
                <?php elseif ( ( $tab['type'] ?? '' ) === 'setup' ) : ?>
                    <?php clu_render_setup_pages_tab(); ?>
                <?php else : ?>
                    <?php if ( ! empty( $tab['shortcode'] ) ) : ?>
                        <p class="clu-shortcode-box">
                            <strong>Shortcode:</strong>
                            <code><?php echo esc_html( $tab['shortcode'] ); ?></code>
                            <?php if ( $tab_id !== 'my-account-form-settings' ) : ?>
                                &nbsp;Add <code>show_title="true"</code> to show the form title.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if ( $tab_id === 'register-form-settings' ) : ?>
                        <p>The fields of this form and their labels are on the <strong>Register Fields</strong> tab.</p>
                    <?php endif; ?>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( $tab['settings_group'] );
                        do_settings_sections( $tab['settings_group'] );
                        submit_button( 'Save ' . $tab['title'] );
                        ?>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php

    if ( $switched ) {
        restore_previous_locale();
    }
}

add_action( 'admin_init', 'clu_register_settings' );
function clu_register_settings() {
    foreach ( clu_get_settings_tabs() as $tab ) {
        if ( isset( $tab['type'] ) ) {
            continue;
        }

        $fields  = include $tab['fields_file'];
        $english = clu_untranslated( function () use ( $tab ) {
            return array_column( include $tab['fields_file'], 'default', 'field' );
        } );

        register_setting(
            $tab['settings_group'],
            $tab['option_name'],
            [
                'sanitize_callback' => function ( $input ) use ( $fields, $english, $tab ) {
                    $input    = is_array( $input ) ? $input : [];
                    $clean    = [];
                    $switched = switch_to_locale( get_locale() );
                    $site     = clu_form_text_defaults();

                    if ( $switched ) {
                        restore_previous_locale();
                    }

                    foreach ( $fields as $field ) {
                        $key   = $field['field'];
                        $type  = $field['type'] ?? 'text';
                        $value = $input[ $key ] ?? '';

                        if ( $type === 'email' ) {
                            $clean[ $key ] = sanitize_email( $value );
                        } elseif ( $type === 'checkbox' ) {
                            $clean[ $key ] = empty( $value ) ? '' : '1';
                        } elseif ( $type === 'number' ) {
                            $clean[ $key ] = is_numeric( $value ) && (int) $value > 0 ? (string) (int) $value : $field['default'];
                        } elseif ( $type === 'select' ) {
                            $clean[ $key ] = is_string( $value ) && isset( $field['options'][ $value ] ) ? $value : $field['default'];
                        } else {
                            $clean[ $key ] = sanitize_text_field( $value );

                            // A default left as it was is not stored, so that it keeps
                            // following the site's language.
                            if ( ! clu_is_custom_text( $clean[ $key ], $english[ $key ] ?? '', [ $field['default'], $site[ $tab['option_name'] ][ $key ] ?? '' ] ) ) {
                                $clean[ $key ] = '';
                            }
                        }
                    }

                    return $clean;
                },
            ]
        );

        add_settings_section(
            $tab['section_id'],
            $tab['section_title'],
            '__return_false',
            $tab['settings_group']
        );

        foreach ( $fields as $field ) {
            add_settings_field(
                $field['field'],
                $field['label'],
                function ( $args ) use ( $tab ) {
                    $options = get_option( $tab['option_name'] );
                    $options = is_array( $options ) ? $options : [];
                    $value   = $options[ $args['label_for'] ] ?? $args['default'];

                    // A text with a default shows what the form shows: the default when
                    // none was written.
                    if ( ( $args['type'] ?? 'text' ) === 'text' && $args['default'] !== '' ) {
                        $value = clu_form_text( $tab['option_name'], $args['label_for'] );
                    }
                    $type    = $args['type'] ?? 'text';
                    $name    = $tab['option_name'] . '[' . $args['label_for'] . ']';

                    if ( $type === 'checkbox' ) {
                        printf(
                            '<label class="clu-checkbox-label"><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>',
                            esc_attr( $args['label_for'] ),
                            esc_attr( $name ),
                            checked( $value, '1', false ),
                            esc_html( $args['checkbox_label'] )
                        );
                    } elseif ( $type === 'select' ) {
                        printf( '<select id="%s" name="%s">', esc_attr( $args['label_for'] ), esc_attr( $name ) );

                        foreach ( $args['options'] as $option_value => $option_label ) {
                            printf( '<option value="%s" %s>%s</option>', esc_attr( $option_value ), selected( $value, $option_value, false ), esc_html( $option_label ) );
                        }

                        echo '</select>';
                    } elseif ( $type === 'number' ) {
                        printf(
                            '<input type="number" id="%s" name="%s" value="%s" min="1" step="1" class="small-text">',
                            esc_attr( $args['label_for'] ),
                            esc_attr( $name ),
                            esc_attr( $value )
                        );
                    } else {
                        echo "<input type='" . esc_attr( $type ) . "' id='" . esc_attr( $args['label_for'] ) . "' name='" . esc_attr( $name ) . "' value='" . esc_attr( $value ) . "' placeholder='" . esc_attr( $args['placeholder'] ) . "' class='regular-text' />";
                    }

                    if ( ! empty( $args['description'] ) ) {
                        echo '<p class="description">' . wp_kses_post( $args['description'] ) . '</p>';
                    }
                },
                $tab['settings_group'],
                $tab['section_id'],
                [
                    'label_for'      => $field['field'],
                    'placeholder'    => $field['placeholder'] ?? '',
                    'default'        => $field['default'],
                    'type'           => $field['type'] ?? 'text',
                    'description'    => $field['description'] ?? '',
                    'checkbox_label' => $field['checkbox_label'] ?? '',
                    'options'        => $field['options'] ?? [],
                ]
            );
        }
    }
}

function clu_render_register_fields_tab() {
    $new_field = [
        'key'         => '',
        'label'       => '',
        'type'        => 'text',
        'enabled'     => true,
        'required'    => false,
        'account'     => true,
        'placeholder' => '',
        'options'     => [],
    ];
    ?>
    <h2>Register Fields</h2>
    <p>Choose what the register form asks for, in what order, and what users can change later on the My Account form. The email is always on, because the approval flow runs through it.</p>
    <p class="description">A checkbox label can hold a link, for example <code>I agree to the &lt;a href="/terms/"&gt;terms&lt;/a&gt;</code>.</p>

    <table class="widefat striped clu-fields-table">
        <thead>
            <tr>
                <th scope="col">Order</th>
                <th scope="col">On the form</th>
                <th scope="col">Label</th>
                <th scope="col">Type</th>
                <th scope="col">Required</th>
                <th scope="col">In My Account</th>
                <th scope="col">Placeholder</th>
                <th scope="col"><span class="screen-reader-text">Remove</span></th>
            </tr>
        </thead>
        <tbody id="clu-fields-rows">
            <?php foreach ( clu_get_register_fields() as $field ) : ?>
                <?php clu_render_register_field_row( $field ); ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <template id="clu-field-row-template">
        <?php clu_render_register_field_row( $new_field ); ?>
    </template>

    <p style="margin-top:16px;">
        <button type="button" id="clu-add-field-btn" class="button">Add Field</button>
    </p>
    <p>
        <button type="button" id="clu-save-fields-btn" class="button button-primary">Save Register Fields</button>
        <span id="clu-save-fields-spinner" class="spinner"></span>
    </p>
    <div id="clu-save-fields-result"></div>
    <?php
}

/**
 * Prints one row of the Register Fields table.
 *
 * @param array $field
 */
function clu_render_register_field_row( $field ) {
    $is_builtin = clu_is_builtin_field( $field['key'] );
    $is_email   = $field['key'] === 'email';
    $is_select  = $field['type'] === 'select';
    $types      = [ 'email' => 'Email', 'region' => 'Region list', 'country' => 'Country list, follows the region' ] + clu_custom_field_types();
    $from_acf   = $field['key'] === 'customer_type' && clu_get_acf_customer_types();
    ?>
    <tr class="clu-field-row" data-key="<?php echo esc_attr( $field['key'] ); ?>" data-type="<?php echo esc_attr( $field['type'] ); ?>">
        <td class="clu-field-move">
            <button type="button" class="button-link clu-field-up" aria-label="Move up">&uarr;</button>
            <button type="button" class="button-link clu-field-down" aria-label="Move down">&darr;</button>
        </td>
        <td class="clu-field-check">
            <input type="checkbox" class="clu-field-enabled" aria-label="On the form" <?php checked( $field['enabled'] ); ?> <?php disabled( $is_email ); ?>>
        </td>
        <td>
            <input type="text" class="clu-field-label regular-text" aria-label="Label" value="<?php echo esc_attr( $field['label'] ); ?>">
        </td>
        <td>
            <?php if ( $is_builtin ) : ?>
                <?php echo esc_html( $types[ $field['type'] ] ?? $field['type'] ); ?>
            <?php else : ?>
                <select class="clu-field-type" aria-label="Type">
                    <?php foreach ( clu_custom_field_types() as $type => $label ) : ?>
                        <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $field['type'], $type ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <?php if ( $is_select || ! $is_builtin ) : ?>
                <div class="clu-field-options-wrap" <?php echo $is_select ? '' : 'hidden'; ?>>
                    <?php if ( $from_acf ) : ?>
                        <p class="description">This site takes these choices from its ACF options page.</p>
                    <?php else : ?>
                        <textarea class="clu-field-options" rows="4" aria-label="Options, one per line" placeholder="One option per line"><?php echo esc_textarea( implode( "\n", $field['options'] ) ); ?></textarea>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </td>
        <td class="clu-field-check">
            <input type="checkbox" class="clu-field-required" aria-label="Required" <?php checked( $field['required'] ); ?> <?php disabled( $is_email ); ?>>
        </td>
        <td class="clu-field-check">
            <input type="checkbox" class="clu-field-account" aria-label="In My Account" <?php checked( $field['account'] ); ?> <?php disabled( $is_email ); ?>>
        </td>
        <td>
            <input type="text" class="clu-field-placeholder regular-text" aria-label="Placeholder" value="<?php echo esc_attr( $field['placeholder'] ); ?>">
        </td>
        <td>
            <?php if ( ! $is_builtin ) : ?>
                <button type="button" class="button-link button-link-delete clu-field-remove">Remove</button>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}

function clu_render_password_rules_tab() {
    $rules = clu_get_password_rules();
    ?>
    <h2>Password Rules</h2>
    <p>Configure the password strength requirements applied on the Set Password and My Account forms.</p>

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
                <p class="description">Password must contain at least one character that is not a letter, digit or space.</p>
            </td>
        </tr>
    </table>

    <p style="margin-top:20px;">
        <button type="button" id="clu-save-pw-rules-btn" class="button button-primary">Save Password Rules</button>
        <span id="clu-pw-rules-spinner" class="spinner"></span>
    </p>
    <div id="clu-pw-rules-result"></div>
    <?php
}

function clu_render_messages_tab() {
    $defaults = clu_default_messages();
    $english  = clu_english_messages();
    $saved    = get_option( 'clu_messages', [] );
    $saved    = is_array( $saved ) ? $saved : [];

    // What the site shows: the text an admin wrote, or the default.
    $val = function ( $key ) use ( $defaults, $english, $saved ) {
        $value = isset( $saved[ $key ] ) ? (string) $saved[ $key ] : '';

        return clu_is_custom_text( $value, $english[ $key ] ?? '', clu_legacy_defaults()['messages'][ $key ] ?? [] ) ? $value : ( $defaults[ $key ] ?? '' );
    };

    $sections = [
        'Form Messages' => [
            'invalid_nonce'               => 'Invalid nonce',
            'general_error'               => 'General error',
            'too_many_attempts'           => 'Too many attempts',
            'form_processing'             => 'Shown while a form is being sent',
            'login_success'               => 'Login: success',
            'login_failed'                => 'Login: wrong details',
            'login_second_step'           => 'Login: on to a second step, such as a code from a two-factor plugin',
            'login_locked'                => 'Login: refused after too many failed attempts (<code>{minutes}</code> = minutes left)',
            'captcha_failed'              => 'Bot check not passed',
            'account_pending'             => 'Account awaiting approval',
            'account_rejected'            => 'Account rejected',
            'account_unconfirmed'         => 'Account whose email address is not confirmed yet',
            'register_required_fields'    => 'Register: required fields missing',
            'register_invalid_field'      => 'Register and My Account: a value is not valid (<code>{field}</code> = its label)',
            'email_already_registered'    => 'Register: email already registered',
            'register_success'            => 'Register: success',
            'register_success_auto'       => 'Register: success, with automatic approval',
            'register_confirm_sent'       => 'Register: success, when the email address must be confirmed first',
            'confirm_email_send_failed'   => 'Register: the confirmation email could not be sent',
            'email_required'              => 'Password lost: email required',
            'reset_email_send_failed'     => 'Password lost: email send failed',
            'reset_email_sent'            => 'Password lost: request received',
            'password_set_missing_fields' => 'Password set: missing fields',
            'invalid_reset_link'          => 'Password set: invalid reset link',
            'password_min_length'         => 'Password: min length (<code>{min_length}</code> = the current rule)',
            'password_uppercase'          => 'Password: uppercase required',
            'password_lowercase'          => 'Password: lowercase required',
            'password_number'             => 'Password: number required',
            'password_special_char'       => 'Password: special char required',
            'password_reset_success'      => 'Password set: success',
        ],
        'My Account' => [
            'my_account_not_logged_in'      => 'Not logged in',
            'profile_update_success'        => 'Profile: saved',
            'profile_update_missing_fields' => 'Required fields missing',
            'profile_email_taken'           => 'Profile: email in use by another account',
            'password_update_success'       => 'Password: changed',
            'password_current_incorrect'    => 'Password: current password wrong',
            'email_change_password_required' => 'Profile: the email changed and the current password is missing',
            'password_confirm_mismatch'     => 'Password: the two new passwords differ',
        ],
        'Shortcode Text' => [
            'already_logged_in'       => 'Login form: already logged in (<code>{logout_url}</code> = logout link)',
            'user_logged_in_register' => 'Register form: already logged in',
            'registration_disabled'   => 'Register form: registration disabled',
            'invalid_reset_link_page' => 'Set password page: invalid link',
            'expired_reset_link_page' => 'Set password page: expired link',
            'email_confirmed'         => 'Email confirmation link: confirmed',
            'email_confirmed_auto'    => 'Email confirmation link: confirmed, with automatic approval',
            'email_already_confirmed' => 'Email confirmation link: used before',
            'email_confirm_invalid'   => 'Email confirmation link: invalid or expired',
            'logout_button_text'      => 'Auth buttons: logout text',
            'login_button_text'       => 'Auth buttons: login text',
            'register_button_text'    => 'Auth buttons: register text',
            'content_login_required'  => 'Restricted content: for a visitor (<code>{login_url}</code> = login page, <code>{register_url}</code> = register page)',
            'content_no_access'       => 'Restricted content: for a logged-in user who is not approved',
        ],
        'Email Templates' => [
            'email_link_text'       => 'Text of the link in password emails',
            'email_edit_user_text'  => 'Text of the {edit_user_link} link in the email to the admin',
            'email_pending_list_text' => 'Text of the {pending_users_link} link in the email to the admin',
            'email_confirm_link_text' => 'Text of the {confirm_link} link in the confirmation email',
            'confirm_email_subject'   => 'Confirm email address email to user — subject (<code>%s</code> = name)',
            'confirm_email_body'      => 'Confirm email address email to user — body (<code>{display_name}</code> = name, <code>{confirm_link}</code> = link, <code>{confirm_url}</code> = its URL)',
            'notify_received_subject' => 'Registration received email to user — subject (<code>%s</code> = name)',
            'notify_received_body'    => 'Registration received email to user — body (<code>{display_name}</code> = name)',
            'reset_email_subject'   => 'Password reset email — subject',
            'reset_email_body'      => 'Password reset email — body (<code>{display_name}</code> = name, <code>{reset_url}</code> = reset URL, <code>{reset_link}</code> = link)',
            'notify_client_subject' => 'New registration email to admin — subject (<code>%s</code> = name)',
            'notify_client_body'    => 'New registration email to admin — body (<code>{display_name}</code> = name, <code>{user_details}</code> = what the user filled in, <code>{pending_users_link}</code> = link to the users awaiting approval, <code>{pending_users_url}</code> = its URL, <code>{edit_user_link}</code> = link to edit this user, <code>{edit_user_url}</code> = its URL)',
            'notify_auto_subject'   => 'New user approved automatically, email to admin — subject (<code>%s</code> = name)',
            'notify_auto_body'      => 'New user approved automatically, email to admin — body (the same placeholders as the new registration email)',
            'notify_user_subject'   => 'Account approved email to user — subject (<code>%s</code> = name)',
            'notify_user_body'      => 'Account approved email to user — body (<code>{display_name}</code> = name, <code>{set_password_url}</code> = set-password URL, <code>{set_password_link}</code> = link)',
            'notify_rejected_subject' => 'Registration rejected email to user — subject (<code>%s</code> = name)',
            'notify_rejected_body'  => 'Registration rejected email to user — body (<code>{display_name}</code> = name)',
        ],
    ];

    $editor_keys = [ 'already_logged_in', 'reset_email_body', 'confirm_email_body', 'notify_received_body', 'notify_client_body', 'notify_auto_body', 'notify_user_body', 'notify_rejected_body' ];
    ?>
    <h2>Plugin Messages</h2>
    <p>Edit the text shown to users. Leave a field blank to use the built-in default.</p>

    <?php foreach ( $sections as $section_title => $fields ) : ?>
        <h3 style="border-bottom:1px solid #ddd;padding-bottom:6px;margin-top:24px;"><?php echo esc_html( $section_title ); ?></h3>
        <table class="form-table">
            <?php foreach ( $fields as $key => $label ) : ?>
            <tr>
                <th><label for="clu-msg-<?php echo esc_attr( $key ); ?>"><?php echo wp_kses_post( $label ); ?></label></th>
                <td>
                    <?php if ( in_array( $key, $editor_keys, true ) ) : ?>
                        <?php
                        wp_editor(
                            $val( $key ),
                            'clu_msg_' . str_replace( '-', '_', $key ),
                            [
                                'textarea_name' => $key,
                                'textarea_rows' => 7,
                                'media_buttons' => false,
                                'teeny'         => true,
                                'quicktags'     => true,
                                'tinymce'       => [
                                    'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo',
                                    'toolbar2' => '',
                                ],
                            ]
                        );
                        ?>
                    <?php else : ?>
                        <input type="text"
                               id="clu-msg-<?php echo esc_attr( $key ); ?>"
                               name="<?php echo esc_attr( $key ); ?>"
                               class="large-text"
                               value="<?php echo esc_attr( $val( $key ) ); ?>" />
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>

    <p style="margin-top:20px;">
        <button type="button" id="clu-save-messages-btn" class="button button-primary">Save Messages</button>
        <button type="button" id="clu-restore-messages-btn" class="button" style="margin-inline-start:8px;">Restore Defaults</button>
        <span id="clu-messages-spinner" class="spinner"></span>
    </p>
    <div id="clu-messages-result"></div>
    <?php
}

function clu_render_roles_tab() {
    $all_roles     = wp_roles()->roles;
    $pending_role  = clu_get_role_slug( 'pending_role' );
    $approved_role = clu_get_role_slug( 'approved_role' );

    // Roles that can manage the site are not offered: see clu_role_is_privileged().
    $assignable = array_filter(
        $all_roles,
        function ( $slug ) {
            return ! clu_role_is_privileged( $slug ) && ! clu_role_is_reserved( $slug );
        },
        ARRAY_FILTER_USE_KEY
    );
    ?>
    <h2>Role Assignments</h2>
    <p>Choose which WordPress role is assigned on registration and which role means the user is approved.</p>

    <table class="form-table">
        <tr>
            <th><label for="clu-pending-role">Registration role (pending)</label></th>
            <td>
                <select id="clu-pending-role">
                    <?php foreach ( $assignable as $slug => $role ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $pending_role ); ?>>
                            <?php echo esc_html( $role['name'] ); ?> (<?php echo esc_html( $slug ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Newly registered users are assigned this role, and cannot log in until they leave it.</p>
            </td>
        </tr>
        <tr>
            <th><label for="clu-approved-role">Approval role (approved)</label></th>
            <td>
                <select id="clu-approved-role">
                    <?php foreach ( $assignable as $slug => $role ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $approved_role ); ?>>
                            <?php echo esc_html( $role['name'] ); ?> (<?php echo esc_html( $slug ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">When a user is changed to this role they receive a set-password email.</p>
            </td>
        </tr>
    </table>
    <p class="description">Rejecting a user from the <strong>Users</strong> list moves them to the Rejected User role and sends them an email. They cannot log in, and you can still approve them later, or delete them.</p>
    <p class="description">When new users confirm their email address first (on the <strong>Mail</strong> tab), they have the Unconfirmed User role until they do. You can approve one directly, or delete the accounts nobody confirmed.</p>

    <table class="form-table">
        <tr>
            <th><label for="clu-auto-approve">Automatic approval</label></th>
            <td>
                <label class="clu-checkbox-label">
                    <input type="checkbox" id="clu-auto-approve" value="1" <?php checked( clu_auto_approve_is_on() ); ?>>
                    Approve new users automatically
                </label>
                <p class="description">New users get the set-password email at once, and you get an email about each one. With the email confirmation on, this happens once the address is confirmed. Users already waiting stay in the list until you approve them. On a site open to everyone, use it together with the email confirmation and a bot check, or bots get working accounts.</p>
            </td>
        </tr>
    </table>

    <p>
        <button type="button" id="clu-save-roles-btn" class="button button-primary">Save Role Assignments</button>
        <span id="clu-save-roles-spinner" class="spinner"></span>
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
        <span id="clu-create-role-spinner" class="spinner"></span>
    </p>
    <div id="clu-create-role-result"></div>

    <hr style="margin:24px 0;">

    <h2>Delete Role</h2>
    <p>Permanently delete a custom role. A role that is assigned above, or that users still have, cannot be deleted.</p>

    <table class="form-table">
        <tr>
            <th><label for="clu-delete-role-select">Select role to delete</label></th>
            <td>
                <select id="clu-delete-role-select">
                    <option value="">— Select a role —</option>
                    <?php
                    $protected = [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ];
                    foreach ( $all_roles as $slug => $role ) :
                        if ( in_array( $slug, $protected, true ) || clu_role_is_reserved( $slug ) ) {
                            continue;
                        }
                        ?>
                        <option value="<?php echo esc_attr( $slug ); ?>">
                            <?php echo esc_html( $role['name'] ); ?> (<?php echo esc_html( $slug ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>

    <p>
        <button type="button" id="clu-delete-role-btn" class="button button-link-delete">Delete Role</button>
        <span id="clu-delete-role-spinner" class="spinner"></span>
    </p>
    <div id="clu-delete-role-result"></div>
    <?php
}

function clu_render_setup_pages_tab() {
    ?>
    <h2>Auto-Create Plugin Pages</h2>
    <p>Click the button below to automatically create all required pages and populate the <strong>Pages URL</strong> tab with their addresses.</p>
    <p>If a page with the same slug already exists it will be kept as-is and its URL will be saved to the settings.</p>
    <p>New pages get their titles in the site's language. The two pages without a form start with a short text in that language and a link onwards, which you can edit like any page.</p>

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
            <?php foreach ( clu_get_setup_pages() as $page ) :
                $existing = get_page_by_path( $page['slug'] );
                ?>
            <tr data-slug="<?php echo esc_attr( $page['slug'] ); ?>">
                <td><?php echo esc_html( $page['title'] ); ?></td>
                <td><code>/<?php echo esc_html( $page['slug'] ); ?>/</code></td>
                <td><?php echo ! empty( $page['shortcode'] ) ? '<code>[' . esc_html( $page['shortcode'] ) . ']</code>' : 'None &mdash; a short text'; ?></td>
                <td class="clu-page-status">
                    <?php if ( ! $existing ) : ?>
                        <span class="clu-status clu-status--missing">&mdash; Not created yet</span>
                    <?php elseif ( clu_setup_page_has_shortcode( $page, $existing ) ) : ?>
                        <span class="clu-status clu-status--exists">Exists &mdash; <a href="<?php echo esc_url( get_permalink( $existing->ID ) ); ?>" target="_blank"><?php echo esc_url( get_permalink( $existing->ID ) ); ?></a></span>
                    <?php else : ?>
                        <span class="clu-status clu-status--warning">Exists, but its content has no form shortcode &mdash; <a href="<?php echo esc_url( get_permalink( $existing->ID ) ); ?>" target="_blank"><?php echo esc_url( get_permalink( $existing->ID ) ); ?></a></span>
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
        <span id="clu-create-pages-spinner" class="spinner"></span>
    </p>
    <div id="clu-create-pages-result"></div>

    <hr style="margin:24px 0;">

    <h2>Replace wp-login.php</h2>
    <p>
        <label>
            <input type="checkbox" id="clu-replace-login" <?php checked( get_option( 'clu_replace_wp_login' ), '1' ); ?>>
            Send wp-login.php and the links to it to the pages above
        </label>
    </p>
    <p class="description">Logging out, password-protected posts and the expired-session popup in wp-admin keep using wp-login.php. A form on another plugin's login screen, such as for two-factor or CAPTCHA, will not appear on your login page.</p>
    <p class="description"><strong>Before turning this on,</strong> try your login page in a private window. If it ever stops working, the default form is still at <code><?php echo esc_html( site_url( 'wp-login.php?clu_native=1' ) ); ?></code>.</p>
    <p>
        <button type="button" id="clu-save-replace-login-btn" class="button">Save</button>
        <span id="clu-replace-login-spinner" class="spinner"></span>
    </p>
    <div id="clu-replace-login-result"></div>

    <hr style="margin:24px 0;">

    <h2>Templates</h2>
    <p>To change the HTML of a form, copy its file from the plugin's <code>forms</code> folder to a <code>custom-login-users</code> folder in the theme, and edit the copy. A child theme's copy comes first.</p>
    <?php $overrides = clu_theme_template_overrides(); ?>
    <?php if ( $overrides ) : ?>
        <p><strong>This theme replaces:</strong></p>
        <ul class="ul-disc">
            <?php foreach ( $overrides as $name => $path ) : ?>
                <li><code><?php echo esc_html( $name . '.php' ); ?></code> &mdash; <?php echo esc_html( wp_normalize_path( $path ) ); ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="description">After updating the plugin, compare these copies with its new files: a copy keeps the old form, without what the update added to it.</p>
    <?php else : ?>
        <p class="description">This theme replaces none of them.</p>
    <?php endif; ?>
    <?php
}

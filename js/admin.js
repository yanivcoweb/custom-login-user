/* Settings screen: tabs and the buttons that save over AJAX. */
jQuery(function ($) {
    'use strict';

    const config = window.cluAdmin || {};
    const TAB_KEY = 'cluActiveTab';

    /* ── Tabs ─────────────────────────────────────────────────────────── */

    const $tabs   = $('.clu-custom-settings .nav-tab');
    const $panels = $('.clu-custom-settings .tab-content');

    function activateTab($tab) {
        $tabs.removeClass('nav-tab-active');
        $panels.removeClass('active');
        $tab.addClass('nav-tab-active');
        $($tab.attr('href')).addClass('active');
    }

    $tabs.on('click', function (event) {
        event.preventDefault();
        activateTab($(this));

        try {
            sessionStorage.setItem(TAB_KEY, $(this).attr('href'));
        } catch (error) {
            // Storage can be blocked; the tab just won't be remembered after a save.
        }
    });

    // Saving a tab reloads the page: come back to the same tab.
    let savedTab = null;

    try {
        savedTab = sessionStorage.getItem(TAB_KEY);
    } catch (error) {
        // See above.
    }

    const $savedTab = savedTab ? $tabs.filter('[href="' + savedTab + '"]') : $();
    activateTab($savedTab.length ? $savedTab : $tabs.first());

    /* ── Shared AJAX helpers ──────────────────────────────────────────── */

    function showNotice($result, type, text) {
        $result.empty().append(
            $('<div class="notice inline">').addClass('notice-' + type).append($('<p>').text(text))
        );
    }

    /**
     * Posts for one button: disables it, shows its spinner and reports the answer.
     * onDone gets the response when the server handled the request.
     */
    function post($button, $spinner, $result, data, onDone) {
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.empty();

        $.post(ajaxurl, data, function (response) {
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');
            onDone(response);
        }, 'json').fail(function () {
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');
            showNotice($result, 'error', 'Request failed.');
        });
    }

    function reportMessage($result) {
        return function (response) {
            const message = (response.data && response.data.message) || '';
            showNotice($result, response.success ? 'success' : 'error', message);
        };
    }

    /* ── Register fields ──────────────────────────────────────────────── */

    const $fieldRows = $('#clu-fields-rows');

    // The built-in fields have no type control, and only a dropdown has options.
    function syncFieldRow($row) {
        const $type = $row.find('.clu-field-type');

        if ($type.length) {
            $row.find('.clu-field-options-wrap').prop('hidden', $type.val() !== 'select');
            $row.find('.clu-field-placeholder').prop('disabled', $type.val() === 'checkbox');
        }
    }

    $fieldRows.find('.clu-field-row').each(function () {
        syncFieldRow($(this));
    });

    $fieldRows.on('change', '.clu-field-type', function () {
        syncFieldRow($(this).closest('.clu-field-row'));
    });

    $fieldRows.on('click', '.clu-field-up', function () {
        const $row = $(this).closest('.clu-field-row');

        $row.prev().before($row);
        $(this).trigger('focus');
    });

    $fieldRows.on('click', '.clu-field-down', function () {
        const $row = $(this).closest('.clu-field-row');

        $row.next().after($row);
        $(this).trigger('focus');
    });

    $fieldRows.on('click', '.clu-field-remove', function () {
        const $row = $(this).closest('.clu-field-row');

        if ($row.attr('data-key') && !window.confirm('Remove this field? What users already filled in stays in the database, but it will no longer show.')) {
            return;
        }

        $row.remove();
    });

    $('#clu-add-field-btn').on('click', function () {
        const $row = $($('#clu-field-row-template').html().trim());

        $fieldRows.append($row);
        syncFieldRow($row);
        $row.find('.clu-field-label').trigger('focus');
    });

    $('#clu-save-fields-btn').on('click', function () {
        const $result = $('#clu-save-fields-result');

        const fields = $fieldRows.find('.clu-field-row').map(function () {
            const $row  = $(this);
            const $type = $row.find('.clu-field-type');

            return {
                key: $row.attr('data-key'),
                type: $type.length ? $type.val() : $row.attr('data-type'),
                label: $row.find('.clu-field-label').val(),
                enabled: $row.find('.clu-field-enabled').is(':checked'),
                required: $row.find('.clu-field-required').is(':checked'),
                account: $row.find('.clu-field-account').is(':checked'),
                placeholder: $row.find('.clu-field-placeholder').val(),
                options: ($row.find('.clu-field-options').val() || '').split('\n'),
            };
        }).get();

        post($(this), $('#clu-save-fields-spinner'), $result, {
            action: 'clu_save_register_fields',
            nonce: config.registerFieldsNonce,
            fields: JSON.stringify(fields),
        }, function (response) {
            reportMessage($result)(response);

            if (response.success) {
                // Rows keep their order on the server, so they can learn their keys in turn.
                $fieldRows.find('.clu-field-row').each(function (index) {
                    $(this).attr('data-key', response.data.keys[index]);
                });
            }
        });
    });

    /* ── Password rules ───────────────────────────────────────────────── */

    $('#clu-save-pw-rules-btn').on('click', function () {
        const $result = $('#clu-pw-rules-result');

        post($(this), $('#clu-pw-rules-spinner'), $result, {
            action: 'clu_save_password_rules',
            nonce: config.passwordRulesNonce,
            min_length: $('#clu-pw-min-length').val(),
            require_uppercase: $('#clu-pw-require-uppercase').is(':checked') ? 1 : 0,
            require_lowercase: $('#clu-pw-require-lowercase').is(':checked') ? 1 : 0,
            require_number: $('#clu-pw-require-number').is(':checked') ? 1 : 0,
            require_special: $('#clu-pw-require-special').is(':checked') ? 1 : 0,
        }, reportMessage($result));
    });

    /* ── Messages ─────────────────────────────────────────────────────── */

    $('#clu-save-messages-btn').on('click', function () {
        const $result = $('#clu-messages-result');
        const data    = { action: 'clu_save_messages', nonce: config.messagesNonce };

        if (window.tinyMCE) {
            window.tinyMCE.triggerSave();
        }

        $('input[name], textarea[name]', '#messages').each(function () {
            data[$(this).attr('name')] = $(this).val();
        });

        post($(this), $('#clu-messages-spinner'), $result, data, reportMessage($result));
    });

    $('#clu-restore-messages-btn').on('click', function () {
        if (!window.confirm('Restore all messages to their built-in defaults?')) {
            return;
        }

        const $result = $('#clu-messages-result');

        post($(this), $('#clu-messages-spinner'), $result, {
            action: 'clu_restore_default_messages',
            nonce: config.messagesNonce,
        }, function (response) {
            if (response.success) {
                $.each(response.data.defaults, function (key, value) {
                    const $field = $('#clu-msg-' + key);

                    if ($field.length) {
                        $field.val(value);
                        return;
                    }

                    const editorId = 'clu_msg_' + key.replace(/-/g, '_');

                    if (window.tinyMCE && window.tinyMCE.get(editorId)) {
                        window.tinyMCE.get(editorId).setContent(value);
                    }
                    $('#' + editorId).val(value);
                });
            }
            reportMessage($result)(response);
        });
    });

    /* ── Roles ────────────────────────────────────────────────────────── */

    $('#clu-save-roles-btn').on('click', function () {
        const $result = $('#clu-save-roles-result');

        post($(this), $('#clu-save-roles-spinner'), $result, {
            action: 'clu_save_roles',
            nonce: config.rolesNonce,
            pending_role: $('#clu-pending-role').val(),
            approved_role: $('#clu-approved-role').val(),
        }, reportMessage($result));
    });

    $('#clu-create-role-btn').on('click', function () {
        const $result = $('#clu-create-role-result');

        post($(this), $('#clu-create-role-spinner'), $result, {
            action: 'clu_create_role',
            nonce: config.rolesNonce,
            role_slug: $('#clu-new-role-slug').val(),
            role_display_name: $('#clu-new-role-name').val(),
        }, function (response) {
            reportMessage($result)(response);

            if (response.success) {
                const $option = $('<option>')
                    .val(response.data.slug)
                    .text(response.data.name + ' (' + response.data.slug + ')');

                $('#clu-pending-role, #clu-approved-role, #clu-delete-role-select').append($option.clone());
                $('#clu-new-role-slug, #clu-new-role-name').val('');
            }
        });
    });

    $('#clu-delete-role-btn').on('click', function () {
        const slug    = $('#clu-delete-role-select').val();
        const $result = $('#clu-delete-role-result');

        if (!slug) {
            showNotice($result, 'error', 'Please select a role to delete.');
            return;
        }

        if (!window.confirm('Are you sure you want to delete the role "' + slug + '"? This cannot be undone.')) {
            return;
        }

        post($(this), $('#clu-delete-role-spinner'), $result, {
            action: 'clu_delete_role',
            nonce: config.rolesNonce,
            role_slug: slug,
        }, function (response) {
            reportMessage($result)(response);

            if (response.success) {
                $('#clu-delete-role-select, #clu-pending-role, #clu-approved-role')
                    .find('option')
                    .filter(function () {
                        return $(this).val() === slug;
                    })
                    .remove();
            }
        });
    });

    /* ── Replacing wp-login.php ───────────────────────────────────────── */

    $('#clu-save-replace-login-btn').on('click', function () {
        const $result = $('#clu-replace-login-result');

        post($(this), $('#clu-replace-login-spinner'), $result, {
            action: 'clu_save_login_replacement',
            nonce: config.loginReplacementNonce,
            enabled: $('#clu-replace-login').is(':checked') ? 1 : 0,
        }, function (response) {
            if (!response.success) {
                $('#clu-replace-login').prop('checked', false);
            }

            if (response.success && response.data.warning) {
                showNotice($result, 'warning', response.data.message);
                return;
            }

            reportMessage($result)(response);
        });
    });

    /* ── Setup pages ──────────────────────────────────────────────────── */

    const SETUP_STATUS = {
        created: { label: 'Created', css: 'clu-status--exists' },
        exists: { label: 'Exists', css: 'clu-status--exists' },
        no_shortcode: { label: 'Exists, but its content has no form shortcode', css: 'clu-status--warning' },
        error: { label: 'Error', css: 'clu-status--warning' },
    };

    $('#clu-create-pages-btn').on('click', function () {
        const $result = $('#clu-create-pages-result');

        post($(this), $('#clu-create-pages-spinner'), $result, {
            action: 'clu_create_pages',
            nonce: config.createPagesNonce,
        }, function (response) {
            if (!response.success) {
                reportMessage($result)(response);
                return;
            }

            $.each(response.data.results, function (index, item) {
                const status = SETUP_STATUS[item.status] || SETUP_STATUS.error;
                const $cell  = $('tr[data-slug="' + item.slug + '"] .clu-page-status').empty();
                const $label = $('<span class="clu-status">').addClass(status.css).text(status.label);

                $cell.append($label);

                if (item.url) {
                    $label.append(' — ', $('<a target="_blank">').attr('href', item.url).text(item.url));
                } else if (item.message) {
                    $label.append(' — ', document.createTextNode(item.message));
                }
            });

            const failed = response.data.results.some(function (item) {
                return item.status === 'error';
            });

            if (failed) {
                showNotice($result, 'warning', 'Some pages could not be created. See the table above. The Pages URL tab has been updated for the others.');
            } else {
                showNotice($result, 'success', 'Done. The Pages URL tab has been updated.');
            }
        });
    });
});

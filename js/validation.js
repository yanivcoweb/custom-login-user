/* Front-end forms: validation in the browser, then submission over AJAX. */
(function () {
    'use strict';

    const config   = window.cluData || {};
    const messages = config.messages || {};

    // Where each form shows the server's answer.
    const RESPONSE_ELEMENTS = {
        'loginform': 'login-response',
        'passwordlostform': 'password-lost-response',
        'signupform': 'signupform-register-response',
        'setpassform': 'password-set-response',
        'my-account-profile-form': 'my-account-profile-response',
        'my-account-password-form': 'my-account-password-response',
    };

    // These forms show the user's own data, so a successful save leaves it in place.
    const KEEP_VALUES_ON_SUCCESS = ['my-account-profile-form'];

    // The same rules as clu_validate_password() in PHP; keep the two in step.
    function passwordErrors(password) {
        const rules  = config.passwordRules || {};
        const errors = [];

        if ([...password].length < (parseInt(rules.min_length, 10) || 8)) {
            errors.push(messages.min_length);
        }
        if (rules.require_uppercase == 1 && !/[A-Z]/.test(password)) {
            errors.push(messages.uppercase);
        }
        if (rules.require_lowercase == 1 && !/[a-z]/.test(password)) {
            errors.push(messages.lowercase);
        }
        if (rules.require_number == 1 && !/[0-9]/.test(password)) {
            errors.push(messages.number);
        }
        if (rules.require_special == 1 && !/[^\p{L}\p{N}\s]/u.test(password)) {
            errors.push(messages.special_char);
        }

        return errors;
    }

    function errorBox(form, input) {
        return form.querySelector(`.error-message[data-inputid="${input.id}"]`);
    }

    function hideError(form, input) {
        const box = errorBox(form, input);

        if (box) {
            box.style.display = 'none';
        }
    }

    // Shows the box's own text, or the given lines in its place.
    function showError(form, input, lines) {
        const box = errorBox(form, input);

        if (!box) {
            return;
        }

        if (box.dataset.requiredText === undefined) {
            box.dataset.requiredText = box.textContent.trim();
        }

        box.textContent = '';
        (lines || [box.dataset.requiredText]).forEach((line) => {
            const row = document.createElement('div');
            row.textContent = line;
            box.appendChild(row);
        });
        box.style.display = 'block';
    }

    function showEmailError(form, input, type) {
        const box = errorBox(form, input);

        if (box) {
            box.querySelector('.email-empty').style.display   = type === 'empty' ? 'block' : 'none';
            box.querySelector('.email-invalid').style.display = type === 'invalid' ? 'block' : 'none';
            box.style.display = 'block';
        }
    }

    function validatePasswordField(form, input) {
        const value = input.value.trim();

        if (!value) {
            showError(form, input);
            return false;
        }

        if (input.hasAttribute('data-strength')) {
            const errors = passwordErrors(value);

            if (errors.length) {
                showError(form, input, errors);
                return false;
            }
        }

        if (input.id === 'confirm_password' && value !== form.querySelector('#new_password').value.trim()) {
            showError(form, input, [messages.mismatch]);
            return false;
        }

        hideError(form, input);
        return true;
    }

    function validateForm(form) {
        let isValid = true;

        form.querySelectorAll('[custom-required]').forEach((input) => {
            if (input.type === 'password') {
                isValid = validatePasswordField(form, input) && isValid;
            } else if (input.type === 'email') {
                const value = input.value.trim();

                if (value && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    hideError(form, input);
                } else {
                    showEmailError(form, input, value ? 'invalid' : 'empty');
                    isValid = false;
                }
            } else if (input.type === 'checkbox' ? !input.checked : !input.value.trim()) {
                showError(form, input);
                isValid = false;
            } else {
                hideError(form, input);
            }
        });

        return isValid;
    }

    function showResponse(output, message, state) {
        output.innerHTML = message; // Server messages are sanitized and may hold links.
        output.classList.remove('clu-response--pending', 'clu-response--success', 'clu-response--error');
        output.classList.add('clu-response--' + state);
    }

    // A cached page or a login in another tab can leave the form with a dead nonce.
    async function refreshNonce(form) {
        const field = form.querySelector('input[name$="_nonce"]');

        if (!field) {
            return false;
        }

        const body = new FormData();
        body.append('action', 'clu_refresh_nonce');
        body.append('field', field.name);

        try {
            const response = await fetch(config.ajaxurl, { method: 'POST', body });
            const result   = await response.json();

            if (result.success && result.data.nonce) {
                field.value = result.data.nonce;
                return true;
            }
        } catch (error) {
            // Falls through, and the caller shows the original error.
        }

        return false;
    }

    async function submitForm(form, output, mayRetry) {
        let result;

        try {
            const response = await fetch(config.ajaxurl, { method: 'POST', body: new FormData(form) });
            result = await response.json();
        } catch (error) {
            result = null;
        }

        const data = (result && result.data) || {};

        if (result && !result.success && data.code === 'invalid_nonce' && mayRetry && await refreshNonce(form)) {
            return submitForm(form, output, false);
        }

        if (!result || !data.message) {
            showResponse(output, messages.network_error, 'error');
            return;
        }

        showResponse(output, data.message, result.success ? 'success' : 'error');

        if (result.success) {
            if (!KEEP_VALUES_ON_SUCCESS.includes(form.id)) {
                form.reset();
            }
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.form-validation').forEach((form) => {
            const output = document.getElementById(RESPONSE_ELEMENTS[form.id]);

            if (!output) {
                return;
            }

            // Editing a field clears its error.
            form.querySelectorAll('[custom-required]').forEach((input) => {
                input.addEventListener('focus', () => hideError(form, input));
                input.addEventListener('change', () => hideError(form, input));
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (!validateForm(form)) {
                    return;
                }

                const button = form.querySelector('[type="submit"]');

                if (button) {
                    button.disabled = true;
                }
                showResponse(output, messages.processing, 'pending');

                try {
                    await submitForm(form, output, true);
                } finally {
                    if (button) {
                        button.disabled = false;
                    }
                }
            });
        });
    });
}());

/* Users list: a rejection sends an email that cannot be taken back, so it asks first. */
(function () {
    'use strict';

    const message = (window.cluUsersList || {}).confirmReject;

    document.addEventListener('click', (event) => {
        if (event.target.closest('a.clu-reject-link') && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    document.addEventListener('submit', (event) => {
        const button = event.submitter;

        if (!button || (button.id !== 'doaction' && button.id !== 'doaction2')) {
            return;
        }

        const select = event.target.querySelector(button.id === 'doaction' ? 'select[name="action"]' : 'select[name="action2"]');

        if (select && select.value === 'clu_reject' && !window.confirm(message)) {
            event.preventDefault();
        }
    });
}());

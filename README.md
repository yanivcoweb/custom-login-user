# Custom Login Users

A WordPress plugin that adds front-end pages for login, registration, password reset and account details, with an admin approval step for new users.

**Author:** Yaniv Sasson
**Version:** 1.1.0

---

## Features

- Front-end pages for Login, Register, Lost Password, Set Password and My Account, all submitted over AJAX
- User approval workflow (Pending → Approved). A pending user cannot log in or reset a password on any form of the site, wp-login.php and WooCommerce included
- Admin notification email on new registrations, and an email with a set-password link upon approval
- Every label and message can be edited in the settings, so the forms work in any language, including right-to-left
- Configurable password rules (min length, uppercase, lowercase, numbers, symbols)
- Custom user roles: `Pending User` and `Approved User`, both kept out of the WordPress dashboard
- Protection against spam and abuse: a hidden bot trap and a limit on the registration form, a limit on reset emails, and the same answer for known and unknown accounts on the login and lost-password forms

The plugin adds pages next to the default ones. `wp-login.php` keeps working, and core links to it are not redirected.

---

## Shortcodes

| Shortcode | Older name, still supported | Description |
|---|---|---|
| `[clu_login_form]` | `[custom-login-form]` | Login form |
| `[clu_register_form]` | `[custom-register-form]` | Registration form |
| `[clu_password_lost_form]` | `[custom-password-lost-form]` | "Forgot password" form |
| `[clu_password_set_form]` | `[custom-password-set]` | Set or reset password form, opened from the emailed link |
| `[clu_my_account]` | `[my-account]` | Profile and change-password forms |
| `[clu_auth_buttons]` | `[custom-auth-buttons]` | Login and register links, or the user's name and a logout link |

The forms accept `show_title="true"` to show their title from the settings.

The login form honours `?redirect_to=` for addresses on the same site.

---

## User Approval Workflow

1. A user registers via the front-end form and is assigned the **Pending User** role.
2. The site admin receives an email with a link to edit the user. With no address set on the Mail tab, it goes to the site admin email.
3. The admin changes the user's role to **Approved User**.
4. The user automatically receives an email with a link to set their password. That link is valid for 7 days. Other reset links stay at the WordPress default of 1 day.
5. After setting a password, the user is logged in.

Registration follows the WordPress setting **Settings > General > Anyone can register**. When it is off, the form and its AJAX endpoint are both closed.

---

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin from the **Plugins** menu in WordPress.
3. Go to **Settings > Custom Login Users**.
4. On the **Setup Pages** tab, click **Create Pages & Save URLs**, or create the pages yourself with the shortcodes above and fill in the **Pages URL** tab.
5. Turn on **Anyone can register** in **Settings > General** if the site takes registrations.

---

## Settings

- **Pages URL** — The login, register, password and redirect addresses
- **Mail** — Sender details and who hears about new registrations
- **Register, Login, Password Reset, Password Lost and My Account Form** — Labels, buttons and validation texts
- **Password Rules** — Requirements for new passwords
- **Messages** — Texts shown to users, and the email templates
- **User Roles** — Which roles count as pending and approved. Roles that can manage the site are not allowed here, since the pending role goes to anyone who fills in the public form
- **Setup Pages** — Creates the pages

---

## Filters

| Filter | Use |
|---|---|
| `clu_rate_limit` | Change a limit, or return 0 to turn it off. Receives the limit and the action: `register` (10 an hour per IP), `lost_password_ip` (20 per 15 minutes), `lost_password_account` (3 emails per 15 minutes) |
| `clu_client_ip` | Return the real visitor IP on a site behind a proxy or CDN, where `REMOTE_ADDR` is the proxy |
| `clu_approval_link_lifetime` | Seconds an approval link stays valid |
| `clu_user_is_pending` | Decide whether a user counts as waiting for approval |
| `clu_privileged_capabilities` | The capabilities that disqualify a role from being pending or approved |

---

## Requirements

- WordPress 5.0+
- PHP 7.4+

Tested on WordPress 7.1 with PHP 8.4.

---

## Tests

For a development copy of a site, from the command line. Nothing is kept and no email is sent, since everything runs inside a transaction that is rolled back. Do not run them against a live site all the same.

```bash
php tests/run-tests.php /path/to/wp-load.php --bare
```

`--bare` tests the plugin alone. Without it, the site's active theme and plugins load too, which shows conflicts with them. See the top of the file for the two optional environment variables.

---

## Changelog

### 1.1.0

Before updating a live site

- All plugin functions now start with `clu_`, so a theme that calls an old name such as `render_login_form()` needs updating. The same goes for the asset handles `validation-js` and `templates-css` (now `clu-validation` and `clu-forms`), the JS object `ajaxData` (now `cluData`), and the settings screen address (now `page=clu-settings`). Shortcodes, AJAX actions and saved settings keep their names.
- A pending user can no longer log in. If a site uses a role that real users hold as its pending role, such as Subscriber, those users are blocked too. Choose a dedicated role on the User Roles tab, or use the `clu_user_is_pending` filter.
- New sites start with a minimum password length of 8 instead of 6. Sites that saved their rules keep them.

Security

- A pending user could get a reset link from the lost-password form and log in without approval. Pending users are now blocked from logging in and from resetting a password, on every form of the site.
- The registration endpoint stayed open when registration was turned off.
- The 7 day link lifetime applied to every password reset on the site, admins included. It now applies to approval emails only.
- Login and lost-password answers no longer reveal whether an account exists.
- A role such as Administrator could be chosen as the role for public sign-ups.
- Added the bot trap and the limits listed above, and removed usernames from the error log.
- The company field from wp-admin was saved without being sanitized.

Fixes

- Names with no Latin letters, such as Hebrew names, could not register at all.
- Passwords with characters such as `<` or `%41`, or double spaces, did not match between this plugin and WordPress or WooCommerce, including between the set-password and change-password forms of the plugin itself.
- A plugin function name clashed with other code using common names, which crashed the site. All functions now carry the `clu_` prefix.
- The register form crashed without ACF, and depended on jQuery being loaded before it.
- A `%` in an email subject template crashed registration, after the user was already created.
- Registrations notified nobody when no address was set on the Mail tab.
- `?login[]=x` on a form page crashed the page.
- Long names could not register, and the required fields were checked only in the browser.
- The min-length message stayed at its old number after the rule changed, and the set-password form was shown even for a dead link.
- After login, `?redirect_to=` was ignored, and with no redirect set on the Pages URL tab the user stayed on the form.
- Links are no longer forced to https on a site that runs on http.
- Roles deleted on the settings screen came back on the next request, and roles still in use could be deleted.

Improvements

- Forms keep working on cached pages, by fetching a fresh nonce when the printed one has expired.
- Added a confirm-password field on the set-password form, and a Remember Me checkbox on login.
- The My Account form and the hard-coded texts of the forms can now be edited in the settings.
- Removed the settings that had no effect: the Errors Messages tab and unused fields on the form tabs.
- The password rules now count characters, not bytes, and accept any symbol as a special character.
- The settings screen remembers its tab after saving, updates the Setup Pages table without a reload, and warns about an existing page that has no form shortcode. Its assets load on that screen only.
- Added `uninstall.php`, removed the debug output, and removed the retired code.

### 1.0

First release.

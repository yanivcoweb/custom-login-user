# Custom Login Users

A WordPress plugin that replaces the default login, registration, and password management pages with fully customizable front-end forms.

**Author:** Yaniv Sasson  
**Version:** 1.0

---

## Features

- Custom front-end pages for Login, Register, Password Reset, and Set Password
- AJAX-powered forms with nonce security
- User approval workflow (Pending → Approved)
- Admin notification emails on new registrations
- User notification email with a password-set link upon approval
- Configurable error messages via the admin settings page
- Configurable password rules (min length, uppercase, lowercase, numbers, special characters)
- Custom user roles: `Pending User` and `Approved User`
- Blocks pending/approved users from accessing the WordPress dashboard

---

## Shortcodes

| Shortcode | Description |
|---|---|
| `[clu_login_form]` | Renders the login form |
| `[clu_register_form]` | Renders the registration form |
| `[clu_password_lost_form]` | Renders the "forgot password" form |
| `[clu_password_set_form]` | Renders the set/reset password form |
| `[clu_auth_buttons]` | Renders login/logout buttons |

---

## User Approval Workflow

1. A user registers via the front-end form and is assigned the **Pending User** role.
2. The site admin receives an email notification with a link to edit the user.
3. The admin changes the user's role to **Approved User**.
4. The user automatically receives an email with a link to set their password.
5. After setting a password, the user can log in normally.

---

## Installation

1. Upload the `custom-login-users` folder to `/wp-content/plugins/`.
2. Activate the plugin from the **Plugins** menu in WordPress.
3. Go to **Settings > Custom Login Users** to configure pages, messages, and password rules.
4. Create the necessary pages in WordPress and add the appropriate shortcodes.

---

## Settings

The plugin settings page allows you to configure:

- **Page URLs** — Set the URLs for the login, register, password lost, and password set pages
- **Error Messages** — Customize all form validation and error messages
- **Email Messages** — Customize the notification emails sent to admin and users
- **Password Rules** — Set requirements for password strength
- **User Roles** — Configure the role slugs used for pending and approved users

---

## Requirements

- WordPress 5.0+
- PHP 7.4+

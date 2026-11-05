# Custom Login Users

A WordPress plugin that adds front-end pages for login, registration, password reset and account details, with an admin approval step for new users.

**Author:** Yaniv Sasson
**Version:** 1.4.0

---

## Features

- Front-end pages for Login, Register, Lost Password, Set Password and My Account, all submitted over AJAX
- User approval workflow (Pending → Approved or Rejected), with Approve and Reject links and bulk actions on the Users list. A pending or rejected user cannot log in or reset a password on any form of the site, wp-login.php and WooCommerce included
- Restricted content: whole pages or parts of a page for approved users only
- Flexible register form: turn fields on and off, make them required or optional, change their order, add your own, and choose which ones users can change on My Account
- Admin notification email on new registrations with what the user filled in, an email with a set-password link upon approval, and a rejection email
- Every label and message can be edited in the settings, so the forms work in any language, including right-to-left
- Configurable password rules (min length, uppercase, lowercase, numbers, symbols)
- Custom user roles: `Pending User`, `Approved User` and `Rejected User`, all kept out of the WordPress dashboard
- Protection against spam and abuse: a hidden bot trap and a limit on the registration form, a limit on reset emails, and the same answer for known and unknown accounts on the login and lost-password forms

The plugin adds pages next to the default ones, and `wp-login.php` keeps working. To send `wp-login.php` and the links to it to your pages instead, turn on **Replace wp-login.php**.

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
| `[clu_approved_only]...[/clu_approved_only]` | | Shows what it encloses to approved users only |

The forms accept `show_title="true"` to show their title from the settings.

The login form honours `?redirect_to=` for addresses on the same site.

---

## User Approval Workflow

1. A user registers via the front-end form and is assigned the **Pending User** role.
2. The site admin receives an email with what the user filled in and a link to the users awaiting approval. With no address set on the Mail tab, it goes to the site admin email. The Users menu shows how many are waiting.
3. On **Users > Awaiting Approval**, which also lists the registration details, the admin clicks **Approve** or **Reject**, or selects several users and uses the bulk actions. Changing the role on the user's own screen does the same.
4. An approved user automatically receives an email with a link to set their password. That link is valid for 7 days. Other reset links stay at the WordPress default of 1 day.
5. After setting a password, the user is logged in.

Rejecting moves the user to the **Rejected User** role and emails them. They cannot log in or reset a password, and they cannot register again with the same address. The account stays, so a mistake can be undone with **Approve**, which sends the set-password email. To remove someone without an email, such as a bot, use the normal **Delete** link.

Registration follows the WordPress setting **Settings > General > Anyone can register**. When it is off, the form and its AJAX endpoint are both closed.

---

## Replacing wp-login.php

Off by default. Create the pages, try the login page in a private window, then turn it on at the bottom of the **Setup Pages** tab. It needs a published login page of this site.

| On `wp-login.php` | With this on |
|---|---|
| The login screen, also from `/wp-admin/` | Your login page, and back to where they were going afterwards |
| Registration, posted or not | Your register page, or the login page if none is set |
| Lost password | Your lost-password page, if set |
| The reset link in emails from WordPress itself, such as **Send password reset** in wp-admin | Your set-password page, if set |
| After logging out with no address of its own | Your page for after logout, or the login page |

Links from `wp_login_url()`, `wp_registration_url()` and `wp_lostpassword_url()` lead to your pages too.

These keep running on `wp-login.php`: logging out, password-protected posts, the admin email check, data requests, recovery mode, the expired-session popup in wp-admin, and actions of other plugins. A login form of another plugin that posts to `wp-login.php` keeps working, and a failed attempt ends on your login page with the error.

**The way back in.** If the login page ever stops working, the default form is still at:

```
/wp-login.php?clu_native=1
```

If a page is deleted or stops being published, that screen goes back to the default form by itself. Recovery mode always uses the default form.

Before turning this on, know that:

- A form that another plugin adds to the default login screen, such as two-factor or CAPTCHA, does not appear on your login page. Those plugins usually check it when the login runs too, which could then fail. Try this on a staging copy first
- The admin email check that WordPress shows from time to time appears only after a login through the default form
- `wp-login.php` still accepts login attempts by post, so this does not protect against password guessing
- It was tested on a single site, not on a multisite network

---

## Restricted Content

Approved users, site administrators and whoever can edit the page can see restricted content.

**A whole page or post:** tick **Approved users only** in the **Access** box on its edit screen. The lists in wp-admin mark it next to the title.

- A visitor is sent to the login page, and comes back to the page after logging in
- A logged-in user who is not approved sees a plain message, or goes to the page set under **Page for users without access** on the Pages URL tab
- Search, including REST search, does not find it. Archives, feeds and the REST API show the message in place of its text and excerpt
- Pages that people need on the way in, with a login, registration or password form or set on the Pages URL tab, stay open even if ticked
- These pages are kept out of page caches

**Part of a page:**

```
[clu_approved_only]Price list[/clu_approved_only]
[clu_approved_only message="Members see the prices here."]...[/clu_approved_only]
[clu_approved_only message=""]Others see nothing here, not even a message.[/clu_approved_only]
```

The default texts are on the Messages tab.

What this does not cover:

- **Uploaded files.** A PDF linked from a restricted page can still be opened by anyone who has its direct address
- **Turning the plugin off.** Deactivating or deleting it opens every restricted page
- **Child pages.** Each page is restricted on its own
- **Titles and addresses.** A restricted post keeps its title in archives, feeds and menus, and its address in sitemaps
- **Anything an archive template prints by itself,** such as custom fields, and comments in the site-wide comments feed
- **WooCommerce products,** which do not offer the box, since they could still be bought. The `clu_restrictable_post_types` filter can add them

---

## Register Fields

On **Settings > Custom Login Users > Register Fields**, each field has:

- **On the form**: whether the register form asks for it. The email is always on
- **Label** and **Placeholder**
- **Required**: on the register form, and on My Account too
- **In My Account**: whether users can change it later. A field left out cannot be changed there, even with a forged request

A new site starts with first name, last name and email. Company, Customer Type, Region and Country are built in, ready to be turned on. A site updated from an older version keeps its form as it was.

Custom fields can be text, long text, phone, number, web address, dropdown or checkbox, and are checked on the server by their type. A checkbox label can hold a link, for terms of use. Values are saved as user meta under `clu_field_<name>`, a key that stays the same when the label changes. Removing a field keeps what users already filled in, in the database.

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
- **Register Fields** — What the register form asks for
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
| `clu_send_rejection_email` | Return false to reject users without emailing them. Receives the user |
| `clu_region_countries` | The regions and countries of the Region and Country fields |
| `clu_user_can_view_restricted` | Decide who sees restricted content, for example to let in a role that was on the site before the approval flow. Receives the answer, the user and the post ID, which is 0 for restricted content in general |
| `clu_restrictable_post_types` | The post types that offer the Access box |

The action `clu_decision_mail_failed` fires when an approval or rejection email could not be sent.

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

### 1.4.0

Before updating a live site

- On a site where anyone can register, sign-ups through the form built into WordPress, at `wp-login.php?action=register`, now wait for approval like all others. WordPress no longer emails those users about a password, since they cannot set one until approved.

Security

- That form was a way around the approval step: it created a working account in the default role, and no admin heard about it.

Added

- **Replace wp-login.php**, off by default: `wp-login.php` and the links to it lead to your pages. See Replacing wp-login.php above.
- The login page shows an error when a login posted to `wp-login.php` failed and was sent there.

### 1.3.0

Added

- Restricted content: an Access box for a whole page or post, and the `[clu_approved_only]` shortcode for part of one. See what it does and does not cover under Restricted Content above.
- A setting for the page that logged-in users without access are sent to, and two new messages.

Nothing changes on an existing site until a page is restricted.

### 1.2.0

Before updating a live site

- The register form stays as it is: on the update, all its fields stay on and required, with their saved labels and placeholders. Those labels move from the Register Form tab to the new Register Fields tab.
- A field that is required on the register form is now required on My Account too. Until now, Company was optional there. To keep it so, make it optional on the Register Fields tab.
- The functions `clu_register_placeholder()` and `clu_get_customer_type_options()` are gone, in case a theme calls them. Choices and placeholders from ACF options keep working.
- The default of the email to the admin changed. A site that wrote its own text keeps it, and `{edit_user_link}` keeps working in it.

Added

- Approve and Reject links and bulk actions on the Users list, a rejection email, and a Rejected User role that cannot log in.
- A count of users awaiting approval on the Users menu, a link to their list, and a column there with what they filled in. The email to the admin lists it too.
- The Register Fields tab: fields on and off, required or optional, in any order, your own custom fields, and which of them appear on My Account.
- The screen reports when an approval or rejection email could not be sent.

Fixes

- A user moved to a role that may not log in stayed logged in until the session ended. Their sessions now end at once.
- Without ACF, the placeholders of a new form were fixed English text. They now come from the settings.

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

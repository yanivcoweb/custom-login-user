<div id="my-account" class="widecolumn" style="max-width: 530px; margin: 30px auto;">

    <h3>My Account</h3>

    <!-- Profile Details -->
    <h4>Update Profile</h4>
    <form id="my-account-profile-form" class="form-clu form-validation" method="post" novalidate>
        <input type="hidden" name="action" value="ajax_update_profile">
        <input type="hidden" name="my_account_profile_nonce" value="<?php echo wp_create_nonce('my_account_profile_nonce'); ?>">

        <div class="form-row">
            <label for="first_name">First Name</label>
            <input type="text" name="first_name" id="first_name" custom-required aria-required="true"
                   value="<?php echo esc_attr($attributes['first_name']); ?>">
            <div class="error-message" data-inputid="first_name" data-fieldtype="text" style="display:none;">Required field</div>
        </div>

        <div class="form-row">
            <label for="last_name">Last Name</label>
            <input type="text" name="last_name" id="last_name" custom-required aria-required="true"
                   value="<?php echo esc_attr($attributes['last_name']); ?>">
            <div class="error-message" data-inputid="last_name" data-fieldtype="text" style="display:none;">Required field</div>
        </div>

        <div class="form-row">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" custom-required aria-required="true"
                   value="<?php echo esc_attr($attributes['email']); ?>">
            <div class="error-message" data-inputid="email" data-fieldtype="email">
                <span class="email-empty">Required field</span>
                <span class="email-incurrected">Invalid email address</span>
            </div>
        </div>

        <div class="form-row">
            <label for="company">Company Name</label>
            <input type="text" name="company" id="company"
                   value="<?php echo esc_attr($attributes['company']); ?>">
        </div>

        <p class="signup-submit">
            <input type="submit" class="register-button" value="Update Profile">
        </p>

        <div id="my-account-profile-response"></div>
    </form>

    <hr style="margin: 40px 0;">

    <!-- Change Password -->
    <h4>Change Password</h4>
    <form id="my-account-password-form" class="form-clu form-validation" method="post" novalidate>
        <input type="hidden" name="action" value="ajax_update_password">
        <input type="hidden" name="my_account_password_nonce" value="<?php echo wp_create_nonce('my_account_password_nonce'); ?>">

        <div class="form-row">
            <label for="current_password">Current Password</label>
            <input type="password" name="current_password" id="current_password" aria-required="true">
        </div>

        <div class="form-row">
            <label for="new_password">New Password</label>
            <input type="password" name="new_password" id="new_password" custom-required aria-required="true">
            <div class="error-message" data-inputid="new_password" data-fieldtype="password" style="display:none;"></div>
        </div>

        <div class="form-row">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" name="confirm_password" id="confirm_password" aria-required="true">
        </div>

        <p class="signup-submit">
            <input type="submit" class="register-button" value="Change Password">
        </p>

        <div id="my-account-password-response"></div>
    </form>

</div>

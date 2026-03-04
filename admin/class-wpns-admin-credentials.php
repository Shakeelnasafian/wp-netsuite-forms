<?php

class WPNS_Admin_Credentials {
    public function render(): void {
        $credentials = WPNS_Credential_Model::get_all();

        echo '<div class="wrap wpns-credentials">';
        echo '<h1>' . esc_html__('NetSuite Credentials', 'wp-netsuite-forms') . '</h1>';

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>' . esc_html__('Profile Name', 'wp-netsuite-forms') . '</th><th>' . esc_html__('Account ID', 'wp-netsuite-forms') . '</th><th>' . esc_html__('Script ID', 'wp-netsuite-forms') . '</th><th>' . esc_html__('Actions', 'wp-netsuite-forms') . '</th></tr></thead>';
        echo '<tbody>';
        if (empty($credentials)) {
            echo '<tr><td colspan="4">' . esc_html__('No credentials found.', 'wp-netsuite-forms') . '</td></tr>';
        } else {
            foreach ($credentials as $cred) {
                echo '<tr data-credential="' . esc_attr(wp_json_encode($cred)) . '">';
                echo '<td>' . esc_html($cred->profile_name) . '</td>';
                echo '<td>' . esc_html($cred->account_id) . '</td>';
                echo '<td>' . esc_html($cred->script_id) . '</td>';
                echo '<td>';
                echo '<button type="button" class="button wpns-edit-credential">' . esc_html__('Edit', 'wp-netsuite-forms') . '</button> ';
                echo '<button type="button" class="button wpns-test-credential" data-credential-id="' . esc_attr($cred->id) . '">' . esc_html__('Test', 'wp-netsuite-forms') . '</button> ';
                echo '<button type="button" class="button-link-delete wpns-delete-credential" data-credential-id="' . esc_attr($cred->id) . '">' . esc_html__('Delete', 'wp-netsuite-forms') . '</button>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';

        echo '<h2>' . esc_html__('Add / Edit Credential', 'wp-netsuite-forms') . '</h2>';
        echo '<form id="wpns-credential-form">';
        echo '<input type="hidden" name="credential_id" id="wpns-credential-id" value="0">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="wpns-profile-name">' . esc_html__('Profile Name', 'wp-netsuite-forms') . '</label></th><td><input type="text" id="wpns-profile-name" name="profile_name" class="regular-text" required></td></tr>';
        echo '<tr><th><label for="wpns-account-id">' . esc_html__('Account ID', 'wp-netsuite-forms') . '</label></th><td><input type="text" id="wpns-account-id" name="account_id" class="regular-text" required></td></tr>';
        echo '<tr><th><label for="wpns-realm">' . esc_html__('Realm', 'wp-netsuite-forms') . '</label></th><td><input type="text" id="wpns-realm" name="realm" class="regular-text" required></td></tr>';
        echo '<tr><th><label for="wpns-consumer-key">' . esc_html__('Consumer Key', 'wp-netsuite-forms') . '</label></th><td><input type="password" id="wpns-consumer-key" name="consumer_key" class="regular-text" required> <button type="button" class="button-link wpns-toggle-secret">' . esc_html__('Show', 'wp-netsuite-forms') . '</button></td></tr>';
        echo '<tr><th><label for="wpns-consumer-secret">' . esc_html__('Consumer Secret', 'wp-netsuite-forms') . '</label></th><td><input type="password" id="wpns-consumer-secret" name="consumer_secret" class="regular-text" required> <button type="button" class="button-link wpns-toggle-secret">' . esc_html__('Show', 'wp-netsuite-forms') . '</button></td></tr>';
        echo '<tr><th><label for="wpns-token-key">' . esc_html__('Token Key', 'wp-netsuite-forms') . '</label></th><td><input type="password" id="wpns-token-key" name="token_key" class="regular-text" required> <button type="button" class="button-link wpns-toggle-secret">' . esc_html__('Show', 'wp-netsuite-forms') . '</button></td></tr>';
        echo '<tr><th><label for="wpns-token-secret">' . esc_html__('Token Secret', 'wp-netsuite-forms') . '</label></th><td><input type="password" id="wpns-token-secret" name="token_secret" class="regular-text" required> <button type="button" class="button-link wpns-toggle-secret">' . esc_html__('Show', 'wp-netsuite-forms') . '</button></td></tr>';
        echo '<tr><th><label for="wpns-script-id">' . esc_html__('Script ID', 'wp-netsuite-forms') . '</label></th><td><input type="text" id="wpns-script-id" name="script_id" class="regular-text" required></td></tr>';
        echo '<tr><th><label for="wpns-deploy-id">' . esc_html__('Deploy ID', 'wp-netsuite-forms') . '</label></th><td><input type="text" id="wpns-deploy-id" name="deploy_id" class="regular-text" value="1"></td></tr>';
        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-primary" id="wpns-save-credential">' . esc_html__('Save Credential', 'wp-netsuite-forms') . '</button> <span class="wpns-credential-status"></span></p>';
        echo '</form>';

        echo '</div>';
    }
}

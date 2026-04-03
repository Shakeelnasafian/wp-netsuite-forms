<?php

class WPNS_Admin_Credentials {
    /**
     * Render the CF7-inspired admin UI for managing NetSuite credentials.
     *
     * Outputs a credentials list table and an Add / Edit form below it,
     * with show/hide toggles on secret fields and a Reset button to clear
     * the form back to "Add new" state.
     */
    public function render(): void {
        $credentials = WPNS_Credential_Model::get_all();

        echo '<div class="wrap wpns-credentials">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'NetSuite Credentials', 'wp-netsuite-forms' ) . '</h1>';
        echo '<hr class="wp-header-end">';

        // ── Credentials table ──────────────────────────────────────────
        echo '<table class="widefat striped">';
        echo '<thead><tr>'
            . '<th>' . esc_html__( 'Profile Name', 'wp-netsuite-forms' ) . '</th>'
            . '<th>' . esc_html__( 'Account ID',   'wp-netsuite-forms' ) . '</th>'
            . '<th>' . esc_html__( 'Script ID',    'wp-netsuite-forms' ) . '</th>'
            . '<th>' . esc_html__( 'Actions',      'wp-netsuite-forms' ) . '</th>'
            . '</tr></thead>';
        echo '<tbody>';

        if ( empty( $credentials ) ) {
            echo '<tr><td colspan="4" style="color:#777;">'
                . esc_html__( 'No credentials saved yet.', 'wp-netsuite-forms' )
                . '</td></tr>';
        } else {
            foreach ( $credentials as $cred ) {
                echo '<tr data-credential="' . esc_attr( wp_json_encode( $cred ) ) . '">';
                echo '<td><strong>' . esc_html( $cred->profile_name ) . '</strong></td>';
                echo '<td>' . esc_html( $cred->account_id ) . '</td>';
                echo '<td>' . esc_html( $cred->script_id ) . '</td>';
                echo '<td>';
                echo '<button type="button" class="button wpns-edit-credential">'
                    . esc_html__( 'Edit', 'wp-netsuite-forms' ) . '</button> ';
                echo '<button type="button" class="button wpns-test-credential"'
                    . ' data-credential-id="' . esc_attr( $cred->id ) . '">'
                    . esc_html__( 'Test', 'wp-netsuite-forms' ) . '</button> ';
                echo '<button type="button" class="button-link-delete wpns-delete-credential"'
                    . ' data-credential-id="' . esc_attr( $cred->id ) . '">'
                    . esc_html__( 'Delete', 'wp-netsuite-forms' ) . '</button>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        // ── Add / Edit form ────────────────────────────────────────────
        echo '<h2 id="wpns-credential-form-title" style="margin-top:24px;">'
            . esc_html__( 'Add New Credential', 'wp-netsuite-forms' ) . '</h2>';

        echo '<form id="wpns-credential-form">';
        echo '<input type="hidden" name="credential_id" id="wpns-credential-id" value="0">';

        echo '<table class="form-table"><tbody>';

        echo '<tr><th><label for="wpns-profile-name">'
            . esc_html__( 'Profile Name', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-profile-name" name="profile_name"'
            . ' class="regular-text" required></td></tr>';

        echo '<tr><th><label for="wpns-account-id">'
            . esc_html__( 'Account ID', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-account-id" name="account_id"'
            . ' class="regular-text" required></td></tr>';

        echo '<tr><th><label for="wpns-realm">'
            . esc_html__( 'Realm', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-realm" name="realm"'
            . ' class="regular-text" required></td></tr>';

        foreach ( [
            'consumer_key'    => __( 'Consumer Key',    'wp-netsuite-forms' ),
            'consumer_secret' => __( 'Consumer Secret', 'wp-netsuite-forms' ),
            'token_key'       => __( 'Token Key',       'wp-netsuite-forms' ),
            'token_secret'    => __( 'Token Secret',    'wp-netsuite-forms' ),
        ] as $field_id => $field_label ) {
            $input_id = 'wpns-' . str_replace( '_', '-', $field_id );
            echo '<tr><th><label for="' . esc_attr( $input_id ) . '">'
                . esc_html( $field_label ) . '</label></th>';
            echo '<td>'
                . '<input type="password" id="' . esc_attr( $input_id ) . '"'
                . ' name="' . esc_attr( $field_id ) . '" class="regular-text" required>'
                . ' <button type="button" class="button-link wpns-toggle-secret">'
                . esc_html__( 'Show', 'wp-netsuite-forms' ) . '</button>'
                . '</td></tr>';
        }

        echo '<tr><th><label for="wpns-script-id">'
            . esc_html__( 'Script ID', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-script-id" name="script_id"'
            . ' class="regular-text" required></td></tr>';

        echo '<tr><th><label for="wpns-deploy-id">'
            . esc_html__( 'Deploy ID', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-deploy-id" name="deploy_id"'
            . ' class="regular-text" value="1"></td></tr>';

        echo '</tbody></table>';

        echo '<p>';
        echo '<button type="submit" class="button button-primary" id="wpns-save-credential">'
            . esc_html__( 'Save Credential', 'wp-netsuite-forms' ) . '</button>';
        echo ' <button type="button" class="button" id="wpns-reset-credential">'
            . esc_html__( 'Cancel', 'wp-netsuite-forms' ) . '</button>';
        echo ' <span class="wpns-credential-status"></span>';
        echo '</p>';

        echo '</form>';
        echo '</div>';
    }
}

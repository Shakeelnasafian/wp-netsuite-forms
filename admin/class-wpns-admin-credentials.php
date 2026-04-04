<?php

class WPNS_Admin_Credentials {
    /** Map of CRM type => display label. */
    private const CRM_LABELS = [
        'netsuite' => 'NetSuite',
        'odoo'     => 'Odoo',
        'zoho'     => 'Zoho CRM',
        'hubspot'  => 'HubSpot',
        'webhook'  => 'Webhook / REST',
    ];

    /**
     * Render the multi-CRM credentials admin page.
     */
    public function render(): void {
        $credentials = WPNS_Credential_Model::get_all();

        echo '<div class="wrap wpns-credentials">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'CRM Connections', 'wp-netsuite-forms' ) . '</h1>';
        echo '<hr class="wp-header-end">';

        // ── Connection table ───────────────────────────────────────────────
        echo '<table class="widefat striped">';
        echo '<thead><tr>'
            . '<th>' . esc_html__( 'Profile Name',  'wp-netsuite-forms' ) . '</th>'
            . '<th>' . esc_html__( 'CRM Type',      'wp-netsuite-forms' ) . '</th>'
            . '<th>' . esc_html__( 'Connection ID', 'wp-netsuite-forms' ) . '</th>'
            . '<th>' . esc_html__( 'Actions',       'wp-netsuite-forms' ) . '</th>'
            . '</tr></thead>';
        echo '<tbody>';

        if ( empty( $credentials ) ) {
            echo '<tr><td colspan="4" style="color:#777;">'
                . esc_html__( 'No CRM connections saved yet. Add one below.', 'wp-netsuite-forms' )
                . '</td></tr>';
        } else {
            foreach ( $credentials as $cred ) {
                $crm_type  = $cred->crm_type ?? 'netsuite';
                $crm_label = self::CRM_LABELS[ $crm_type ] ?? ucfirst( $crm_type );
                $conn_info = $crm_type === 'netsuite' ? esc_html( $cred->account_id ?? '' ) : '—';

                // Only expose non-sensitive fields to the DOM.
                $safe_cred = [
                    'id'           => $cred->id,
                    'crm_type'     => $cred->crm_type     ?? 'netsuite',
                    'profile_name' => $cred->profile_name ?? '',
                    'account_id'   => $cred->account_id   ?? '',
                    'realm'        => $cred->realm         ?? '',
                    'script_id'    => $cred->script_id    ?? '',
                    'deploy_id'    => $cred->deploy_id    ?? '1',
                    // config_json intentionally omitted (may contain API keys/tokens).
                ];
                echo '<tr data-credential="' . esc_attr( wp_json_encode( $safe_cred ) ) . '">';
                echo '<td><strong>' . esc_html( $cred->profile_name ) . '</strong></td>';
                echo '<td><span class="wpns-crm-badge wpns-crm-' . esc_attr( $crm_type ) . '">'
                    . esc_html( $crm_label ) . '</span></td>';
                echo '<td>' . $conn_info . '</td>';
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

        // ── Add / Edit form ────────────────────────────────────────────────
        echo '<h2 id="wpns-credential-form-title" style="margin-top:24px;">'
            . esc_html__( 'Add New Connection', 'wp-netsuite-forms' ) . '</h2>';

        echo '<form id="wpns-credential-form">';
        echo '<input type="hidden" name="credential_id" id="wpns-credential-id" value="0">';

        // ── CRM type selector ──────────────────────────────────────────────
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="wpns-crm-type">'
            . esc_html__( 'CRM Type', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><select id="wpns-crm-type" name="crm_type" class="regular-text">';
        foreach ( self::CRM_LABELS as $type => $label ) {
            echo '<option value="' . esc_attr( $type ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';

        // ── Common: profile name ───────────────────────────────────────────
        echo '<tr><th><label for="wpns-profile-name">'
            . esc_html__( 'Profile Name', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-profile-name" name="profile_name"'
            . ' class="regular-text" placeholder="' . esc_attr__( 'e.g. Production NetSuite', 'wp-netsuite-forms' ) . '" required></td></tr>';

        echo '</tbody></table>';

        // ── NetSuite fields ────────────────────────────────────────────────
        echo '<div class="wpns-crm-fields" data-crm="netsuite">';
        echo '<h3>' . esc_html__( 'NetSuite OAuth 1.0a', 'wp-netsuite-forms' ) . '</h3>';
        echo '<table class="form-table"><tbody>';

        $ns_fields = [
            'account_id'   => [ __( 'Account ID',   'wp-netsuite-forms' ), 'text',     false ],
            'realm'        => [ __( 'Realm',         'wp-netsuite-forms' ), 'text',     false ],
            'consumer_key' => [ __( 'Consumer Key',  'wp-netsuite-forms' ), 'password', true  ],
            'consumer_secret' => [ __( 'Consumer Secret', 'wp-netsuite-forms' ), 'password', true ],
            'token_key'    => [ __( 'Token Key',     'wp-netsuite-forms' ), 'password', true  ],
            'token_secret' => [ __( 'Token Secret',  'wp-netsuite-forms' ), 'password', true  ],
            'script_id'    => [ __( 'Script ID',     'wp-netsuite-forms' ), 'text',     false ],
            'deploy_id'    => [ __( 'Deploy ID',     'wp-netsuite-forms' ), 'text',     false ],
        ];
        foreach ( $ns_fields as $field_id => $meta ) {
            [ $label, $input_type, $is_secret ] = $meta;
            $input_id = 'wpns-' . str_replace( '_', '-', $field_id );
            echo '<tr><th><label for="' . esc_attr( $input_id ) . '">' . esc_html( $label ) . '</label></th>';
            echo '<td><input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $input_id ) . '"'
                . ' name="' . esc_attr( $field_id ) . '" class="regular-text"'
                . ( $field_id === 'deploy_id' ? ' value="1"' : '' ) . '>';
            if ( $is_secret ) {
                echo ' <button type="button" class="button-link wpns-toggle-secret">'
                    . esc_html__( 'Show', 'wp-netsuite-forms' ) . '</button>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';

        // ── Odoo fields ────────────────────────────────────────────────────
        echo '<div class="wpns-crm-fields" data-crm="odoo" style="display:none;">';
        echo '<h3>' . esc_html__( 'Odoo Connection', 'wp-netsuite-forms' ) . '</h3>';
        echo '<p class="description">'
            . esc_html__( 'Payload keys must match Odoo field names for the chosen model (e.g. partner_name, email_from for crm.lead).', 'wp-netsuite-forms' )
            . '</p>';
        echo '<table class="form-table"><tbody>';
        $odoo_fields = [
            'url'      => [ __( 'Odoo URL',      'wp-netsuite-forms' ), 'https://company.odoo.com', false ],
            'database' => [ __( 'Database Name', 'wp-netsuite-forms' ), 'mycompany',                false ],
            'username' => [ __( 'Username / Email', 'wp-netsuite-forms' ), 'admin@example.com',     false ],
            'api_key'  => [ __( 'API Key',       'wp-netsuite-forms' ), '',                         true  ],
            'model'    => [ __( 'Model',         'wp-netsuite-forms' ), 'crm.lead',                  false ],
        ];
        foreach ( $odoo_fields as $key => $meta ) {
            [ $label, $placeholder, $is_secret ] = $meta;
            echo '<tr><th><label for="wpns-odoo-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
            echo '<td><input type="' . ( $is_secret ? 'password' : 'text' ) . '"'
                . ' id="wpns-odoo-' . esc_attr( $key ) . '"'
                . ' name="config[' . esc_attr( $key ) . ']"'
                . ' class="regular-text"'
                . ' placeholder="' . esc_attr( $placeholder ) . '">';
            if ( $is_secret ) {
                echo ' <button type="button" class="button-link wpns-toggle-secret">'
                    . esc_html__( 'Show', 'wp-netsuite-forms' ) . '</button>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';

        // ── Zoho fields ────────────────────────────────────────────────────
        echo '<div class="wpns-crm-fields" data-crm="zoho" style="display:none;">';
        echo '<h3>' . esc_html__( 'Zoho CRM — OAuth 2.0', 'wp-netsuite-forms' ) . '</h3>';
        echo '<p class="description">'
            . esc_html__( 'Create a Self Client in Zoho API Console, generate a refresh token with scope ZohoCRM.modules.ALL, and paste it here.', 'wp-netsuite-forms' )
            . '</p>';
        echo '<table class="form-table"><tbody>';
        $zoho_fields = [
            'client_id'     => [ __( 'Client ID',     'wp-netsuite-forms' ), false ],
            'client_secret' => [ __( 'Client Secret', 'wp-netsuite-forms' ), true  ],
            'refresh_token' => [ __( 'Refresh Token', 'wp-netsuite-forms' ), true  ],
            'data_center'   => [ __( 'Data Center',   'wp-netsuite-forms' ), false ],
            'module'        => [ __( 'Module',        'wp-netsuite-forms' ), false ],
        ];
        $zoho_placeholder = [
            'data_center' => 'com',
            'module'      => 'Leads',
        ];
        foreach ( $zoho_fields as $key => $meta ) {
            [ $label, $is_secret ] = $meta;
            $ph = $zoho_placeholder[ $key ] ?? '';
            echo '<tr><th><label for="wpns-zoho-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
            echo '<td><input type="' . ( $is_secret ? 'password' : 'text' ) . '"'
                . ' id="wpns-zoho-' . esc_attr( $key ) . '"'
                . ' name="config[' . esc_attr( $key ) . ']"'
                . ' class="regular-text"'
                . ( $ph ? ' placeholder="' . esc_attr( $ph ) . '"' : '' ) . '>';
            if ( $is_secret ) {
                echo ' <button type="button" class="button-link wpns-toggle-secret">'
                    . esc_html__( 'Show', 'wp-netsuite-forms' ) . '</button>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';

        // ── HubSpot fields ─────────────────────────────────────────────────
        echo '<div class="wpns-crm-fields" data-crm="hubspot" style="display:none;">';
        echo '<h3>' . esc_html__( 'HubSpot — Private App Token', 'wp-netsuite-forms' ) . '</h3>';
        echo '<p class="description">'
            . esc_html__( 'Create a Private App in HubSpot → Settings → Integrations → Private Apps. Grant CRM write scopes and copy the access token.', 'wp-netsuite-forms' )
            . '</p>';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="wpns-hubspot-access-token">' . esc_html__( 'Access Token', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="password" id="wpns-hubspot-access-token" name="config[access_token]" class="regular-text">'
            . ' <button type="button" class="button-link wpns-toggle-secret">' . esc_html__( 'Show', 'wp-netsuite-forms' ) . '</button></td></tr>';
        echo '<tr><th><label for="wpns-hubspot-object-type">' . esc_html__( 'Object Type', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><select id="wpns-hubspot-object-type" name="config[object_type]">'
            . '<option value="contacts">Contacts</option>'
            . '<option value="deals">Deals</option>'
            . '<option value="companies">Companies</option>'
            . '<option value="tickets">Tickets</option>'
            . '</select></td></tr>';
        echo '</tbody></table></div>';

        // ── Webhook fields ─────────────────────────────────────────────────
        echo '<div class="wpns-crm-fields" data-crm="webhook" style="display:none;">';
        echo '<h3>' . esc_html__( 'Webhook / REST Endpoint', 'wp-netsuite-forms' ) . '</h3>';
        echo '<p class="description">'
            . esc_html__( 'Send form submissions as JSON to any HTTP endpoint (Zapier, Make, custom API, etc.).', 'wp-netsuite-forms' )
            . '</p>';
        echo '<table class="form-table"><tbody>';
        $wh_fields = [
            'url'          => [ __( 'Endpoint URL', 'wp-netsuite-forms' ), 'https://hooks.zapier.com/hooks/catch/…', 'text' ],
            'method'       => [ __( 'Method',       'wp-netsuite-forms' ), 'POST',                                   'text' ],
            'headers_json' => [ __( 'Extra Headers (JSON)', 'wp-netsuite-forms' ), '{"X-Custom":"value"}',           'text' ],
            'auth_type'    => [ __( 'Auth Type',    'wp-netsuite-forms' ), 'none',                                   'select' ],
            'auth_value'   => [ __( 'Auth Value',   'wp-netsuite-forms' ), '',                                       'password' ],
            'auth_param'   => [ __( 'Auth Param Name', 'wp-netsuite-forms' ), 'api_key',                             'text' ],
        ];
        foreach ( $wh_fields as $key => $meta ) {
            [ $label, $placeholder, $input_type ] = $meta;
            $elem_id = 'wpns-wh-' . str_replace( '_', '-', $key );
            echo '<tr><th><label for="' . esc_attr( $elem_id ) . '">' . esc_html( $label ) . '</label></th><td>';
            if ( $input_type === 'select' ) {
                echo '<select id="' . esc_attr( $elem_id ) . '" name="config[' . esc_attr( $key ) . ']">'
                    . '<option value="none">None</option>'
                    . '<option value="bearer">Bearer Token</option>'
                    . '<option value="basic">Basic Auth (user:pass)</option>'
                    . '<option value="api_key_header">API Key — Header</option>'
                    . '<option value="api_key_query">API Key — Query Param</option>'
                    . '</select>';
            } else {
                echo '<input type="' . esc_attr( $input_type ) . '"'
                    . ' id="' . esc_attr( $elem_id ) . '"'
                    . ' name="config[' . esc_attr( $key ) . ']"'
                    . ' class="regular-text"'
                    . ( $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '' ) . '>';
                if ( $input_type === 'password' ) {
                    echo ' <button type="button" class="button-link wpns-toggle-secret">'
                        . esc_html__( 'Show', 'wp-netsuite-forms' ) . '</button>';
                }
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';

        // ── Form actions ───────────────────────────────────────────────────
        echo '<p style="margin-top:16px;">';
        echo '<button type="submit" class="button button-primary" id="wpns-save-credential">'
            . esc_html__( 'Save Connection', 'wp-netsuite-forms' ) . '</button>';
        echo ' <button type="button" class="button" id="wpns-reset-credential">'
            . esc_html__( 'Cancel', 'wp-netsuite-forms' ) . '</button>';
        echo ' <span class="wpns-credential-status" role="status" aria-live="polite" aria-atomic="true"></span>';
        echo '</p>';

        echo '</form>';
        echo '</div>';
    }
}

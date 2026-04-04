<?php

class WPNS_Admin_Form_Edit {
    /**
     * Render the CF7-inspired admin form editor interface.
     *
     * Outputs the complete editor UI including the title bar, shortcode copy bar,
     * tab navigation (Form, Mail, NetSuite, Settings), tag generator panel,
     * draggable field list, and save controls.
     */
    public function render(): void {
        $form_id     = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        $form        = $form_id ? WPNS_Form_Model::get( $form_id ) : null;
        $fields      = $form_id ? WPNS_Field_Model::get_fields( $form_id ) : [];
        $settings    = $form_id ? WPNS_Settings_Model::get( $form_id ) : null;
        $credentials = WPNS_Credential_Model::get_all();

        $static_values = [];
        if ( $settings && ! empty( $settings->static_values_json ) ) {
            $decoded = json_decode( $settings->static_values_json, true );
            if ( is_array( $decoded ) ) {
                $static_values = $decoded;
            }
        }

        echo '<div class="wrap wpns-app">';

        // ── Title bar ─────────────────────────────────────────────────────
        echo '<form id="wpns-form-edit">';
        echo '<input type="hidden" name="form_id" value="' . esc_attr( $form_id ) . '">';

        echo '<div class="wpns-title-bar">';
        echo '<input type="text" id="wpns-form-name" name="name" class="wpns-title-input"'
            . ' value="' . esc_attr( $form->name ?? '' ) . '"'
            . ' placeholder="' . esc_attr__( 'Enter form name…', 'wp-netsuite-forms' ) . '"'
            . ' required>';
        echo '<div class="wpns-title-actions">';
        echo '<button type="submit" class="button button-primary button-large" id="wpns-save-form">'
            . esc_html__( 'Save Form', 'wp-netsuite-forms' ) . '</button>';
        echo '<span class="wpns-save-status"></span>';
        echo '</div>';
        echo '</div>'; // .wpns-title-bar

        // ── Shortcode bar (edit mode only) ────────────────────────────────
        if ( $form_id ) {
            $shortcode = '[wpns_form id="' . $form_id . '"]';
            echo '<div class="wpns-shortcode-bar">';
            echo '<label>' . esc_html__( 'Shortcode:', 'wp-netsuite-forms' ) . '</label>';
            echo '<input type="text" class="wpns-shortcode-input" readonly value="' . esc_attr( $shortcode ) . '">';
            echo '<button type="button" class="button wpns-copy-shortcode">'
                . esc_html__( 'Copy', 'wp-netsuite-forms' ) . '</button>';
            echo '<span class="wpns-copy-confirm" style="display:none;">'
                . esc_html__( 'Copied!', 'wp-netsuite-forms' ) . '</span>';
            echo '</div>';
        }

        // ── Tab navigation ────────────────────────────────────────────────
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="#" class="nav-tab nav-tab-active" data-tab="form">'
            . esc_html__( 'Form', 'wp-netsuite-forms' ) . '</a>';
        echo '<a href="#" class="nav-tab" data-tab="mail">'
            . esc_html__( 'Mail', 'wp-netsuite-forms' ) . '</a>';
        echo '<a href="#" class="nav-tab" data-tab="mapping">'
            . esc_html__( 'CRM Integration', 'wp-netsuite-forms' ) . '</a>';
        echo '<a href="#" class="nav-tab" data-tab="settings">'
            . esc_html__( 'Settings', 'wp-netsuite-forms' ) . '</a>';
        echo '</h2>';

        // ══════════════════════════════════════════════════════════════════
        // Tab: Form Fields
        // ══════════════════════════════════════════════════════════════════
        echo '<div class="wpns-tab-content" data-tab="form">';

        // Generate Tag toolbar
        echo '<div class="wpns-tag-bar">';
        echo '<span class="wpns-tag-bar-label">' . esc_html__( 'Generate Tag:', 'wp-netsuite-forms' ) . '</span>';
        $tag_types = [
            'text'     => __( 'text',          'wp-netsuite-forms' ),
            'email'    => __( 'email',         'wp-netsuite-forms' ),
            'tel'      => __( 'tel',           'wp-netsuite-forms' ),
            'number'   => __( 'number',        'wp-netsuite-forms' ),
            'textarea' => __( 'textarea',      'wp-netsuite-forms' ),
            'select'   => __( 'dropdown',      'wp-netsuite-forms' ),
            'checkbox' => __( 'checkboxes',    'wp-netsuite-forms' ),
            'radio'    => __( 'radio buttons', 'wp-netsuite-forms' ),
            'file'     => __( 'file',          'wp-netsuite-forms' ),
            'hidden'   => __( 'hidden',        'wp-netsuite-forms' ),
        ];
        foreach ( $tag_types as $type => $label ) {
            echo '<button type="button" class="button wpns-tag-btn" data-type="' . esc_attr( $type ) . '">'
                . esc_html( $label ) . '</button>';
        }
        echo '</div>'; // .wpns-tag-bar

        // Tag generator panel (hidden initially)
        echo '<div id="wpns-tag-panel" class="wpns-tag-panel" style="display:none;">';
        echo '<div class="wpns-tag-panel-header">';
        echo '<h3 class="wpns-tag-panel-title">' . esc_html__( 'Add a Form Field', 'wp-netsuite-forms' ) . '</h3>';
        echo '<button type="button" id="wpns-tg-cancel" class="wpns-tag-panel-close" aria-label="'
            . esc_attr__( 'Close', 'wp-netsuite-forms' ) . '">&times;</button>';
        echo '</div>';
        echo '<div class="wpns-tag-panel-body">';
        echo '<table class="form-table"><tbody>';

        echo '<tr>';
        echo '<th><label>' . esc_html__( 'Field Type', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><span id="wpns-tg-type-display" class="wpns-type-badge"></span></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th><label for="wpns-tg-label">' . esc_html__( 'Label', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-tg-label" class="regular-text"'
            . ' placeholder="' . esc_attr__( 'e.g. Your Name', 'wp-netsuite-forms' ) . '"></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th><label for="wpns-tg-name">' . esc_html__( 'Name', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-tg-name" class="regular-text"'
            . ' placeholder="' . esc_attr__( 'e.g. your_name', 'wp-netsuite-forms' ) . '">'
            . '<p class="description">' . esc_html__( 'Used as the field identifier and token. Only letters, numbers, and underscores.', 'wp-netsuite-forms' ) . '</p></td>';
        echo '</tr>';

        echo '<tr id="wpns-tg-placeholder-row">';
        echo '<th><label for="wpns-tg-placeholder">' . esc_html__( 'Placeholder', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-tg-placeholder" class="regular-text"></td>';
        echo '</tr>';

        echo '<tr id="wpns-tg-required-row">';
        echo '<th>' . esc_html__( 'Required', 'wp-netsuite-forms' ) . '</th>';
        echo '<td><label><input type="checkbox" id="wpns-tg-required"> '
            . esc_html__( 'Required field', 'wp-netsuite-forms' ) . '</label></td>';
        echo '</tr>';

        echo '</tbody></table>';
        echo '<p class="wpns-tag-panel-footer">';
        echo '<button type="button" class="button button-primary" id="wpns-tg-insert">'
            . esc_html__( 'Insert Tag', 'wp-netsuite-forms' ) . '</button>';
        echo '</p>';
        echo '</div>'; // .wpns-tag-panel-body
        echo '</div>'; // #wpns-tag-panel

        // Field list
        echo '<div class="wpns-field-list-wrap">';
        echo '<ul id="wpns-fields-list">';

        foreach ( $fields as $field ) {
            $options = [];
            if ( ! empty( $field->options_json ) ) {
                $decoded_opts = json_decode( $field->options_json, true );
                if ( is_array( $decoded_opts ) ) {
                    $options = $decoded_opts;
                }
            }
            $this->render_field_row( $field, $options, false );
        }

        // Hidden template for JS cloning
        $this->render_field_row( null, [], true );

        echo '</ul>';

        if ( empty( $fields ) ) {
            echo '<div class="wpns-empty-state">';
            echo '<p>' . esc_html__( 'No fields yet. Use the Generate Tag buttons above to add fields to your form.', 'wp-netsuite-forms' ) . '</p>';
            echo '</div>';
        }

        echo '</div>'; // .wpns-field-list-wrap
        echo '</div>'; // [data-tab="form"]

        // ══════════════════════════════════════════════════════════════════
        // Tab: Mail
        // ══════════════════════════════════════════════════════════════════
        echo '<div class="wpns-tab-content" data-tab="mail" style="display:none;">';
        echo '<div class="wpns-mail-layout">';

        // Main email settings
        echo '<div class="wpns-mail-main">';
        $enable_email = $settings ? (int) $settings->enable_email : 1;
        echo '<table class="form-table"><tbody>';

        echo '<tr><th>' . esc_html__( 'Enable Email', 'wp-netsuite-forms' ) . '</th>';
        echo '<td><label><input type="checkbox" name="enable_email" value="1"'
            . checked( $enable_email, 1, false ) . '> '
            . esc_html__( 'Send email notification on submission', 'wp-netsuite-forms' )
            . '</label></td></tr>';

        echo '<tr><th><label for="wpns-email-from-name">' . esc_html__( 'From Name', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-email-from-name" name="email_from_name"'
            . ' class="regular-text" value="' . esc_attr( $settings->email_from_name ?? '' ) . '"></td></tr>';

        echo '<tr><th><label for="wpns-email-from-address">' . esc_html__( 'From Email', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="email" id="wpns-email-from-address" name="email_from_address"'
            . ' class="regular-text" value="' . esc_attr( $settings->email_from_address ?? '' ) . '"></td></tr>';

        echo '<tr><th><label for="wpns-email-to">' . esc_html__( 'To', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-email-to" name="email_to"'
            . ' class="regular-text" value="' . esc_attr( $settings->email_to ?? '' ) . '"></td></tr>';

        echo '<tr><th><label for="wpns-email-cc">' . esc_html__( 'CC', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-email-cc" name="email_cc"'
            . ' class="regular-text" value="' . esc_attr( $settings->email_cc ?? '' ) . '"></td></tr>';

        echo '<tr><th><label for="wpns-email-bcc">' . esc_html__( 'BCC', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-email-bcc" name="email_bcc"'
            . ' class="regular-text" value="' . esc_attr( $settings->email_bcc ?? '' ) . '"></td></tr>';

        echo '<tr><th><label for="wpns-email-subject">' . esc_html__( 'Subject', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="text" id="wpns-email-subject" name="email_subject"'
            . ' class="large-text" value="' . esc_attr( $settings->email_subject ?? '' ) . '"></td></tr>';

        echo '<tr><th><label for="wpns-email-body">' . esc_html__( 'Message Body', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><textarea id="wpns-email-body" name="email_body" rows="10" class="large-text">'
            . esc_textarea( $settings->email_body ?? '' ) . '</textarea>'
            . '<p class="description">' . esc_html__( 'Click Mail Tags on the right to insert field values.', 'wp-netsuite-forms' ) . '</p></td></tr>';

        echo '</tbody></table>';
        echo '</div>'; // .wpns-mail-main

        // Mail tags sidebar
        echo '<div class="wpns-mail-sidebar">';
        echo '<div class="wpns-sidebar-box">';
        echo '<div class="wpns-sidebar-box-header"><h3>' . esc_html__( 'Mail Tags', 'wp-netsuite-forms' ) . '</h3></div>';
        echo '<div class="wpns-sidebar-box-body">';
        echo '<p>' . esc_html__( 'Click a tag to insert it into the focused Subject or Body field:', 'wp-netsuite-forms' ) . '</p>';
        echo '<div class="wpns-token-list">';
        foreach ( $fields as $field ) {
            $token = '{' . $field->field_name . '}';
            echo '<button type="button" class="button wpns-email-token"'
                . ' data-token="' . esc_attr( $token ) . '">'
                . esc_html( $token ) . '</button>';
        }
        if ( empty( $fields ) ) {
            echo '<p style="font-size:12px;color:#aaa;margin:0;">'
                . esc_html__( 'Add fields to your form first.', 'wp-netsuite-forms' ) . '</p>';
        }
        echo '</div>';
        echo '</div>'; // .wpns-sidebar-box-body
        echo '</div>'; // .wpns-sidebar-box
        echo '</div>'; // .wpns-mail-sidebar

        echo '</div>'; // .wpns-mail-layout
        echo '</div>'; // [data-tab="mail"]

        // ══════════════════════════════════════════════════════════════════
        // Tab: CRM Integration
        // ══════════════════════════════════════════════════════════════════
        echo '<div class="wpns-tab-content" data-tab="mapping" style="display:none;">';
        echo '<table class="form-table"><tbody>';

        echo '<tr><th><label for="wpns-credential-id">'
            . esc_html__( 'CRM Connection', 'wp-netsuite-forms' ) . '</label></th><td>';
        echo '<select id="wpns-credential-id" name="credential_id">';
        echo '<option value="0">' . esc_html__( '— Select Connection —', 'wp-netsuite-forms' ) . '</option>';
        $selected_cred = $settings->credential_id ?? 0;
        $crm_labels    = [
            'netsuite' => 'NetSuite',
            'odoo'     => 'Odoo',
            'zoho'     => 'Zoho CRM',
            'hubspot'  => 'HubSpot',
            'webhook'  => 'Webhook',
        ];
        foreach ( $credentials as $cred ) {
            $crm_label = $crm_labels[ $cred->crm_type ?? 'netsuite' ] ?? ucfirst( $cred->crm_type ?? '' );
            echo '<option value="' . esc_attr( $cred->id ) . '"'
                . selected( $selected_cred, $cred->id, false ) . '>'
                . esc_html( $cred->profile_name . ' [' . $crm_label . ']' ) . '</option>';
        }
        echo '</select>';
        echo ' <button type="button" class="button" id="wpns-test-netsuite">'
            . esc_html__( 'Test Connection', 'wp-netsuite-forms' ) . '</button>';
        echo '<span class="wpns-test-result"></span>';
        echo '</td></tr>';

        $enable_netsuite = $settings ? (int) $settings->enable_netsuite : 1;
        echo '<tr><th>' . esc_html__( 'Enable CRM Push', 'wp-netsuite-forms' ) . '</th>';
        echo '<td><label><input type="checkbox" name="enable_netsuite" value="1"'
            . checked( $enable_netsuite, 1, false ) . '> '
            . esc_html__( 'Send submission data to the selected CRM', 'wp-netsuite-forms' )
            . '</label></td></tr>';

        echo '<tr><th><label for="wpns-payload-template">'
            . esc_html__( 'Field Mapping (JSON)', 'wp-netsuite-forms' ) . '</label></th><td>';
        echo '<textarea id="wpns-payload-template" name="payload_template"'
            . ' class="large-text code" rows="14">'
            . esc_textarea( $settings->payload_template ?? '{ }' ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Map CRM field names to form tokens. Example: {"email": "{{email}}", "firstname": "{{first_name}}"}. For NetSuite RESTlets, paste your full JSON payload template.', 'wp-netsuite-forms' ) . '</p>';
        echo '<div class="wpns-payload-toolbar">';
        echo '<button type="button" class="button" id="wpns-format-json">'
            . esc_html__( 'Format JSON', 'wp-netsuite-forms' ) . '</button>';
        echo '<button type="button" class="button" id="wpns-validate-json">'
            . esc_html__( 'Validate JSON', 'wp-netsuite-forms' ) . '</button>';
        echo '<select id="wpns-insert-token"><option value="">'
            . esc_html__( '— Insert Token —', 'wp-netsuite-forms' ) . '</option>';
        foreach ( $fields as $field ) {
            $token = '{{' . $field->field_name . '}}';
            echo '<option value="' . esc_attr( $token ) . '">' . esc_html( $token ) . '</option>';
        }
        echo '</select>';
        echo '<button type="button" class="button" id="wpns-preview-json">'
            . esc_html__( 'Preview', 'wp-netsuite-forms' ) . '</button>';
        echo '</div>'; // .wpns-payload-toolbar
        echo '</td></tr>';

        echo '</tbody></table>';

        echo '<h3>' . esc_html__( 'Static Values', 'wp-netsuite-forms' ) . '</h3>';
        echo '<p class="description">' . esc_html__( 'Inject fixed values into every payload (e.g. record type, source tag).', 'wp-netsuite-forms' ) . '</p>';
        echo '<table class="widefat wpns-static-table">';
        echo '<thead><tr>'
            . '<th>' . esc_html__( 'Field Path / Key', 'wp-netsuite-forms' ) . '</th>'
            . '<th>' . esc_html__( 'Static Value', 'wp-netsuite-forms' ) . '</th>'
            . '<th style="width:80px;"></th>'
            . '</tr></thead>';
        echo '<tbody id="wpns-static-values-body">';
        foreach ( $static_values as $path => $value ) {
            echo '<tr class="wpns-static-row">';
            echo '<td><input type="text" class="regular-text wpns-static-path" value="' . esc_attr( $path ) . '"></td>';
            echo '<td><input type="text" class="regular-text wpns-static-value" value="' . esc_attr( $value ) . '"></td>';
            echo '<td><button type="button" class="button-link button-link-delete wpns-remove-static">'
                . esc_html__( 'Remove', 'wp-netsuite-forms' ) . '</button></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '<p><button type="button" class="button" id="wpns-add-static">'
            . esc_html__( 'Add Row', 'wp-netsuite-forms' ) . '</button></p>';
        echo '</div>'; // [data-tab="mapping"]

        // ══════════════════════════════════════════════════════════════════
        // Tab: Settings
        // ══════════════════════════════════════════════════════════════════
        echo '<div class="wpns-tab-content" data-tab="settings" style="display:none;">';
        echo '<table class="form-table"><tbody>';

        echo '<tr><th><label for="wpns-form-description">'
            . esc_html__( 'Description', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><textarea id="wpns-form-description" name="description" rows="3" class="large-text">'
            . esc_textarea( $form->description ?? '' ) . '</textarea></td></tr>';

        echo '<tr><th><label for="wpns-form-status">'
            . esc_html__( 'Status', 'wp-netsuite-forms' ) . '</label></th><td>';
        echo '<select id="wpns-form-status" name="status">';
        $status = $form->status ?? 'active';
        echo '<option value="active"' . selected( $status, 'active', false ) . '>'
            . esc_html__( 'Active', 'wp-netsuite-forms' ) . '</option>';
        echo '<option value="inactive"' . selected( $status, 'inactive', false ) . '>'
            . esc_html__( 'Inactive', 'wp-netsuite-forms' ) . '</option>';
        echo '</select></td></tr>';

        echo '<tr><th><label for="wpns-form-success">'
            . esc_html__( 'Success Message', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><textarea id="wpns-form-success" name="success_message" rows="3" class="large-text">'
            . esc_textarea( $form->success_message ?? '' ) . '</textarea>';
        echo '<p class="description">'
            . esc_html__( 'Displayed to the user after a successful submission.', 'wp-netsuite-forms' )
            . '</p></td></tr>';

        echo '<tr><th><label for="wpns-form-redirect">'
            . esc_html__( 'Redirect URL', 'wp-netsuite-forms' ) . '</label></th>';
        echo '<td><input type="url" id="wpns-form-redirect" name="redirect_url"'
            . ' class="regular-text" value="' . esc_attr( $form->redirect_url ?? '' ) . '">';
        echo '<p class="description">'
            . esc_html__( 'Optional: redirect to this URL after submission instead of showing the success message.', 'wp-netsuite-forms' )
            . '</p></td></tr>';

        $enable_recaptcha = $settings ? (int) ( $settings->enable_recaptcha ?? 0 ) : 0;
        $rc_site_key      = get_option( 'wpns_recaptcha_site_key', '' );
        echo '<tr><th>' . esc_html__( 'reCAPTCHA v3', 'wp-netsuite-forms' ) . '</th>';
        echo '<td><label><input type="checkbox" name="enable_recaptcha" value="1"'
            . checked( $enable_recaptcha, 1, false ) . '> '
            . esc_html__( 'Enable reCAPTCHA v3 spam protection for this form', 'wp-netsuite-forms' )
            . '</label>';
        if ( ! $rc_site_key ) {
            echo '<p class="description" style="color:#d63638;">'
                . sprintf(
                    /* translators: %s: settings page link */
                    esc_html__( 'reCAPTCHA keys are not configured. %s first.', 'wp-netsuite-forms' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=wpns-settings' ) ) . '">'
                        . esc_html__( 'Add your keys in Settings', 'wp-netsuite-forms' )
                    . '</a>'
                )
                . '</p>';
        }
        echo '</td></tr>';

        echo '</tbody></table>';
        echo '</div>'; // [data-tab="settings"]

        echo '</form>';
        echo '</div>'; // .wrap.wpns-app
    }

    /**
     * Render a CF7-style collapsible field card for the admin form editor.
     *
     * Outputs a list item with a collapsed header (showing label, type badge, required badge,
     * and edit/delete controls) and an expandable body with all field settings. When
     * $is_template is true, the item is hidden and used as a JS clone template.
     *
     * @param object|null $field       Field data object, or null for the template.
     * @param array       $options     Options array for select/radio/checkbox fields.
     * @param bool        $is_template Whether to output as a hidden JS template.
     */
    private function render_field_row( ?object $field, array $options, bool $is_template = false ): void {
        $style   = $is_template ? ' style="display:none;"' : '';
        $classes = 'wpns-field-item' . ( $is_template ? ' wpns-field-template' : '' );

        $field_name  = $field->field_name  ?? '';
        $field_label = $field->field_label ?? '';
        $field_type  = $field->field_type  ?? 'text';
        $placeholder = $field->placeholder ?? '';
        $default_val = $field->default_val ?? '';
        $css_class   = $field->css_class   ?? '';
        $is_required = ! empty( $field->is_required );

        $label_display    = $field_label ?: __( '(no label)', 'wp-netsuite-forms' );
        $label_extra_cls  = $field_label ? '' : ' wpns-no-label';
        $req_hidden_style = $is_required ? '' : ' style="display:none;"';

        $types_with_options = [ 'select', 'radio', 'checkbox' ];
        $opts_hidden        = in_array( $field_type, $types_with_options, true ) ? '' : ' style="display:none;"';

        echo '<li class="' . esc_attr( $classes ) . '"' . $style . '>';

        // ── Collapsed header ─────────────────────────────────────────────
        echo '<div class="wpns-field-header">';
        echo '<span class="dashicons dashicons-menu wpns-drag-handle" title="'
            . esc_attr__( 'Drag to reorder', 'wp-netsuite-forms' ) . '"></span>';

        echo '<div class="wpns-field-summary">';
        echo '<strong class="wpns-field-label-text' . esc_attr( $label_extra_cls ) . '">'
            . esc_html( $label_display ) . '</strong>';
        echo '<span class="wpns-type-badge wpns-type-' . esc_attr( $field_type ) . '">'
            . esc_html( $field_type ) . '</span>';
        echo '<span class="wpns-required-badge"' . $req_hidden_style . '>'
            . esc_html__( 'required', 'wp-netsuite-forms' ) . '</span>';
        echo '</div>';

        echo '<div class="wpns-field-ctrl">';
        echo '<button type="button" class="wpns-toggle-field">'
            . esc_html__( 'Edit', 'wp-netsuite-forms' ) . '</button>';
        echo '<span class="wpns-field-ctrl-sep">|</span>';
        echo '<button type="button" class="wpns-remove-field">'
            . esc_html__( 'Delete', 'wp-netsuite-forms' ) . '</button>';
        echo '</div>';

        echo '</div>'; // .wpns-field-header

        // ── Expandable body ──────────────────────────────────────────────
        echo '<div class="wpns-field-body">';
        echo '<div class="wpns-field-body-grid">';

        echo '<div class="wpns-fg"><label>' . esc_html__( 'Label', 'wp-netsuite-forms' ) . '</label>'
            . '<input type="text" class="wpns-field-label" value="' . esc_attr( $field_label ) . '"'
            . ' placeholder="' . esc_attr__( 'Label text', 'wp-netsuite-forms' ) . '"></div>';

        echo '<div class="wpns-fg"><label>' . esc_html__( 'Name / Slug', 'wp-netsuite-forms' ) . '</label>'
            . '<input type="text" class="wpns-field-name" value="' . esc_attr( $field_name ) . '"'
            . ' placeholder="' . esc_attr__( 'field_name', 'wp-netsuite-forms' ) . '"></div>';

        echo '<div class="wpns-fg"><label>' . esc_html__( 'Type', 'wp-netsuite-forms' ) . '</label>';
        echo '<select class="wpns-field-type">';
        $types = [ 'text', 'email', 'tel', 'number', 'select', 'radio', 'checkbox', 'textarea', 'file', 'hidden' ];
        foreach ( $types as $t ) {
            echo '<option value="' . esc_attr( $t ) . '"' . selected( $field_type, $t, false ) . '>'
                . esc_html( ucfirst( $t ) ) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="wpns-fg"><label>' . esc_html__( 'Required', 'wp-netsuite-forms' ) . '</label>'
            . '<label style="margin-top:4px;display:flex;align-items:center;gap:6px;">'
            . '<input type="checkbox" class="wpns-field-required"' . checked( $is_required, true, false ) . '> '
            . esc_html__( 'Required', 'wp-netsuite-forms' ) . '</label></div>';

        echo '<div class="wpns-fg"><label>' . esc_html__( 'Placeholder', 'wp-netsuite-forms' ) . '</label>'
            . '<input type="text" class="wpns-field-placeholder" value="' . esc_attr( $placeholder ) . '"'
            . ' placeholder="' . esc_attr__( 'Placeholder text', 'wp-netsuite-forms' ) . '"></div>';

        echo '<div class="wpns-fg"><label>' . esc_html__( 'Default Value', 'wp-netsuite-forms' ) . '</label>'
            . '<input type="text" class="wpns-field-default" value="' . esc_attr( $default_val ) . '"></div>';

        echo '<div class="wpns-fg"><label>' . esc_html__( 'CSS Class', 'wp-netsuite-forms' ) . '</label>'
            . '<input type="text" class="wpns-field-css" value="' . esc_attr( $css_class ) . '"></div>';

        echo '</div>'; // .wpns-field-body-grid

        // Options section (select / radio / checkbox)
        echo '<div class="wpns-field-options"' . $opts_hidden . '>';
        echo '<h4>' . esc_html__( 'Options', 'wp-netsuite-forms' ) . '</h4>';
        echo '<div class="wpns-options-list">';
        foreach ( $options as $opt ) {
            $opt_label = $opt['label'] ?? '';
            $opt_value = $opt['value'] ?? '';
            echo '<div class="wpns-option-row">';
            echo '<input type="text" class="wpns-option-label"'
                . ' placeholder="' . esc_attr__( 'Label', 'wp-netsuite-forms' ) . '"'
                . ' value="' . esc_attr( $opt_label ) . '">';
            echo '<input type="text" class="wpns-option-value"'
                . ' placeholder="' . esc_attr__( 'Value', 'wp-netsuite-forms' ) . '"'
                . ' value="' . esc_attr( $opt_value ) . '">';
            echo '<button type="button" class="button-link wpns-remove-option">'
                . esc_html__( 'Remove', 'wp-netsuite-forms' ) . '</button>';
            echo '</div>';
        }
        echo '</div>'; // .wpns-options-list
        echo '<button type="button" class="button wpns-add-option">'
            . esc_html__( 'Add Option', 'wp-netsuite-forms' ) . '</button>';
        echo '</div>'; // .wpns-field-options

        // ── Conditional logic ──────────────────────────────────────────────
        $condition     = [];
        if ( ! $is_template && ! empty( $field->condition_json ) ) {
            $decoded_cond = json_decode( $field->condition_json, true );
            if ( is_array( $decoded_cond ) ) {
                $condition = $decoded_cond;
            }
        }
        $cond_enabled  = ! empty( $condition );
        $cond_field    = $condition['field']    ?? '';
        $cond_operator = $condition['operator'] ?? '=';
        $cond_value    = $condition['value']    ?? '';
        $cond_body_vis = $cond_enabled ? '' : ' style="display:none;"';

        echo '<div class="wpns-condition-section">';
        echo '<label class="wpns-condition-toggle-label">';
        echo '<input type="checkbox" class="wpns-condition-enable"' . checked( $cond_enabled, true, false ) . '> ';
        echo esc_html__( 'Enable conditional logic (show/hide this field based on another field)', 'wp-netsuite-forms' );
        echo '</label>';

        echo '<div class="wpns-condition-body"' . $cond_body_vis . '>';
        echo '<div class="wpns-condition-row">';
        echo '<span class="wpns-condition-label-text">' . esc_html__( 'Show this field when', 'wp-netsuite-forms' ) . '</span>';

        echo '<select class="wpns-condition-field">';
        echo '<option value="">' . esc_html__( '— select field —', 'wp-netsuite-forms' ) . '</option>';
        foreach ( $fields as $sibling ) {
            if ( ! $is_template && $field && $sibling->id === $field->id ) {
                continue; // Can't condition on self.
            }
            echo '<option value="' . esc_attr( $sibling->field_name ) . '"'
                . selected( $cond_field, $sibling->field_name, false ) . '>'
                . esc_html( $sibling->field_label ?: $sibling->field_name ) . '</option>';
        }
        echo '</select>';

        echo '<select class="wpns-condition-operator">';
        $operators = [
            '='         => __( 'equals',           'wp-netsuite-forms' ),
            '!='        => __( 'not equals',        'wp-netsuite-forms' ),
            'contains'  => __( 'contains',          'wp-netsuite-forms' ),
            '!contains' => __( 'does not contain',  'wp-netsuite-forms' ),
            'empty'     => __( 'is empty',          'wp-netsuite-forms' ),
            'not_empty' => __( 'is not empty',      'wp-netsuite-forms' ),
        ];
        foreach ( $operators as $op => $op_label ) {
            echo '<option value="' . esc_attr( $op ) . '"' . selected( $cond_operator, $op, false ) . '>'
                . esc_html( $op_label ) . '</option>';
        }
        echo '</select>';

        echo '<input type="text" class="wpns-condition-value"'
            . ' placeholder="' . esc_attr__( 'value', 'wp-netsuite-forms' ) . '"'
            . ' value="' . esc_attr( $cond_value ) . '">';

        echo '</div>'; // .wpns-condition-row
        echo '</div>'; // .wpns-condition-body
        echo '</div>'; // .wpns-condition-section

        echo '</div>'; // .wpns-field-body
        echo '</li>';
    }
}

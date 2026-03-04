<?php

class WPNS_Admin_Form_Edit {
    public function render(): void {
        $form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
        $form = $form_id ? WPNS_Form_Model::get($form_id) : null;
        $fields = $form_id ? WPNS_Field_Model::get_fields($form_id) : [];
        $settings = $form_id ? WPNS_Settings_Model::get($form_id) : null;
        $credentials = WPNS_Credential_Model::get_all();

        $static_values = [];
        if ($settings && !empty($settings->static_values_json)) {
            $decoded = json_decode($settings->static_values_json, true);
            if (is_array($decoded)) {
                $static_values = $decoded;
            }
        }

        echo '<div class="wrap wpns-form-edit">';
        echo '<h1>' . esc_html($form_id ? __('Edit Form', 'wp-netsuite-forms') : __('Add New Form', 'wp-netsuite-forms')) . '</h1>';

        if ($form_id) {
            echo '<p class="wpns-shortcode">' . esc_html__('Shortcode:', 'wp-netsuite-forms') . ' <code>[wpns_form id="' . esc_html($form_id) . '"]</code></p>';
        }

        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="#" class="nav-tab nav-tab-active" data-tab="details">' . esc_html__('Form Details', 'wp-netsuite-forms') . '</a>';
        echo '<a href="#" class="nav-tab" data-tab="fields">' . esc_html__('Form Fields', 'wp-netsuite-forms') . '</a>';
        echo '<a href="#" class="nav-tab" data-tab="mapping">' . esc_html__('NetSuite Mapping', 'wp-netsuite-forms') . '</a>';
        echo '<a href="#" class="nav-tab" data-tab="email">' . esc_html__('Email Notification', 'wp-netsuite-forms') . '</a>';
        echo '</h2>';

        echo '<form id="wpns-form-edit">';
        echo '<input type="hidden" name="form_id" value="' . esc_attr($form_id) . '">';

        echo '<div class="wpns-tab-content" data-tab="details">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="wpns-form-name">' . esc_html__('Form Name', 'wp-netsuite-forms') . '</label></th><td><input type="text" id="wpns-form-name" name="name" class="regular-text" value="' . esc_attr($form->name ?? '') . '" required></td></tr>';
        echo '<tr><th><label for="wpns-form-description">' . esc_html__('Description', 'wp-netsuite-forms') . '</label></th><td><textarea id="wpns-form-description" name="description" rows="3" class="large-text">' . esc_textarea($form->description ?? '') . '</textarea></td></tr>';
        echo '<tr><th><label for="wpns-form-status">' . esc_html__('Status', 'wp-netsuite-forms') . '</label></th><td><select id="wpns-form-status" name="status">';
        $status = $form->status ?? 'active';
        echo '<option value="active"' . selected($status, 'active', false) . '>' . esc_html__('Active', 'wp-netsuite-forms') . '</option>';
        echo '<option value="inactive"' . selected($status, 'inactive', false) . '>' . esc_html__('Inactive', 'wp-netsuite-forms') . '</option>';
        echo '</select></td></tr>';
        echo '<tr><th><label for="wpns-form-success">' . esc_html__('Success Message', 'wp-netsuite-forms') . '</label></th><td><textarea id="wpns-form-success" name="success_message" rows="3" class="large-text">' . esc_textarea($form->success_message ?? '') . '</textarea></td></tr>';
        echo '<tr><th><label for="wpns-form-redirect">' . esc_html__('Redirect URL', 'wp-netsuite-forms') . '</label></th><td><input type="url" id="wpns-form-redirect" name="redirect_url" class="regular-text" value="' . esc_attr($form->redirect_url ?? '') . '"></td></tr>';
        echo '</tbody></table>';
        echo '</div>';

        echo '<div class="wpns-tab-content" data-tab="fields" style="display:none;">';
        echo '<div class="wpns-fields-toolbar"><button type="button" class="button" id="wpns-add-field">' . esc_html__('Add Field', 'wp-netsuite-forms') . '</button></div>';
        echo '<ul id="wpns-fields-list">';

        foreach ($fields as $field) {
            $options = [];
            if (!empty($field->options_json)) {
                $decoded_options = json_decode($field->options_json, true);
                if (is_array($decoded_options)) {
                    $options = $decoded_options;
                }
            }
            $this->render_field_row($field, $options);
        }

        $this->render_field_row(null, [], true);
        echo '</ul>';
        echo '</div>';

        echo '<div class="wpns-tab-content" data-tab="mapping" style="display:none;">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="wpns-credential-id">' . esc_html__('Credential Profile', 'wp-netsuite-forms') . '</label></th><td>';
        echo '<select id="wpns-credential-id" name="credential_id">';
        echo '<option value="0">' . esc_html__('Select Credential', 'wp-netsuite-forms') . '</option>';
        $selected_cred = $settings->credential_id ?? 0;
        foreach ($credentials as $cred) {
            echo '<option value="' . esc_attr($cred->id) . '"' . selected($selected_cred, $cred->id, false) . '>' . esc_html($cred->profile_name) . '</option>';
        }
        echo '</select>';
        echo ' <button type="button" class="button" id="wpns-test-netsuite">' . esc_html__('Test Connection', 'wp-netsuite-forms') . '</button>';
        echo '<span class="wpns-test-result"></span>';
        echo '</td></tr>';

        $enable_netsuite = $settings ? (int) $settings->enable_netsuite : 1;
        echo '<tr><th>' . esc_html__('Enable NetSuite', 'wp-netsuite-forms') . '</th><td><label><input type="checkbox" name="enable_netsuite" value="1"' . checked($enable_netsuite, 1, false) . '> ' . esc_html__('Send to NetSuite', 'wp-netsuite-forms') . '</label></td></tr>';

        echo '<tr><th><label for="wpns-payload-template">' . esc_html__('Payload Template (JSON)', 'wp-netsuite-forms') . '</label></th><td>';
        echo '<textarea id="wpns-payload-template" name="payload_template" class="large-text code" rows="12">' . esc_textarea($settings->payload_template ?? '{ }') . '</textarea>';
        echo '<div class="wpns-payload-toolbar">';
        echo '<button type="button" class="button" id="wpns-format-json">' . esc_html__('Format JSON', 'wp-netsuite-forms') . '</button> ';
        echo '<button type="button" class="button" id="wpns-validate-json">' . esc_html__('Validate JSON', 'wp-netsuite-forms') . '</button> ';
        echo '<select id="wpns-insert-token"><option value="">' . esc_html__('Insert Token', 'wp-netsuite-forms') . '</option>';
        foreach ($fields as $field) {
            $token = '{{' . $field->field_name . '}}';
            echo '<option value="' . esc_attr($token) . '">' . esc_html($token) . '</option>';
        }
        echo '</select> ';
        echo '<button type="button" class="button" id="wpns-preview-json">' . esc_html__('Preview', 'wp-netsuite-forms') . '</button>';
        echo '</div>';
        echo '</td></tr>';
        echo '</tbody></table>';

        echo '<h3>' . esc_html__('Static Values', 'wp-netsuite-forms') . '</h3>';
        echo '<table class="widefat wpns-static-table">';
        echo '<thead><tr><th>' . esc_html__('NetSuite Path', 'wp-netsuite-forms') . '</th><th>' . esc_html__('Static Value', 'wp-netsuite-forms') . '</th><th></th></tr></thead>';
        echo '<tbody id="wpns-static-values-body">';
        if (!empty($static_values)) {
            foreach ($static_values as $path => $value) {
                echo '<tr class="wpns-static-row"><td><input type="text" class="regular-text wpns-static-path" value="' . esc_attr($path) . '"></td><td><input type="text" class="regular-text wpns-static-value" value="' . esc_attr($value) . '"></td><td><button type="button" class="button-link wpns-remove-static">' . esc_html__('Remove', 'wp-netsuite-forms') . '</button></td></tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';
        echo '<p><button type="button" class="button" id="wpns-add-static">' . esc_html__('Add Static Value', 'wp-netsuite-forms') . '</button></p>';
        echo '</div>';

        echo '<div class="wpns-tab-content" data-tab="email" style="display:none;">';
        $enable_email = $settings ? (int) $settings->enable_email : 1;
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>' . esc_html__('Enable Email', 'wp-netsuite-forms') . '</th><td><label><input type="checkbox" name="enable_email" value="1"' . checked($enable_email, 1, false) . '> ' . esc_html__('Send Email Notification', 'wp-netsuite-forms') . '</label></td></tr>';
        echo '<tr><th><label for="wpns-email-from-name">' . esc_html__('From Name', 'wp-netsuite-forms') . '</label></th><td><input type="text" id="wpns-email-from-name" name="email_from_name" class="regular-text" value="' . esc_attr($settings->email_from_name ?? '') . '"></td></tr>';
        echo '<tr><th><label for="wpns-email-from-address">' . esc_html__('From Email', 'wp-netsuite-forms') . '</label></th><td><input type="email" id="wpns-email-from-address" name="email_from_address" class="regular-text" value="' . esc_attr($settings->email_from_address ?? '') . '"></td></tr>';
        echo '<tr><th><label for="wpns-email-to">' . esc_html__('To', 'wp-netsuite-forms') . '</label></th><td><input type="text" id="wpns-email-to" name="email_to" class="regular-text" value="' . esc_attr($settings->email_to ?? '') . '"></td></tr>';
        echo '<tr><th><label for="wpns-email-cc">' . esc_html__('CC', 'wp-netsuite-forms') . '</label></th><td><input type="text" id="wpns-email-cc" name="email_cc" class="regular-text" value="' . esc_attr($settings->email_cc ?? '') . '"></td></tr>';
        echo '<tr><th><label for="wpns-email-bcc">' . esc_html__('BCC', 'wp-netsuite-forms') . '</label></th><td><input type="text" id="wpns-email-bcc" name="email_bcc" class="regular-text" value="' . esc_attr($settings->email_bcc ?? '') . '"></td></tr>';
        echo '<tr><th><label for="wpns-email-subject">' . esc_html__('Subject', 'wp-netsuite-forms') . '</label></th><td><input type="text" id="wpns-email-subject" name="email_subject" class="large-text" value="' . esc_attr($settings->email_subject ?? '') . '">';
        echo '<div class="wpns-token-list">';
        foreach ($fields as $field) {
            $token = '{' . $field->field_name . '}';
            echo '<button type="button" class="button-link wpns-email-token" data-token="' . esc_attr($token) . '">' . esc_html($token) . '</button>';
        }
        echo '</div>';
        echo '</td></tr>';
        echo '<tr><th><label for="wpns-email-body">' . esc_html__('Body', 'wp-netsuite-forms') . '</label></th><td><textarea id="wpns-email-body" name="email_body" rows="10" class="large-text">' . esc_textarea($settings->email_body ?? '') . '</textarea></td></tr>';
        echo '</tbody></table>';
        echo '</div>';

        echo '<p><button type="submit" class="button button-primary" id="wpns-save-form">' . esc_html__('Save Form', 'wp-netsuite-forms') . '</button> <span class="wpns-save-status"></span></p>';
        echo '</form>';
        echo '</div>';
    }

    private function render_field_row(?object $field, array $options, bool $is_template = false): void {
        $classes = 'wpns-field-row';
        if ($is_template) {
            $classes .= ' wpns-field-template';
        }

        $style = $is_template ? ' style="display:none;"' : '';
        $field_name = $field->field_name ?? '';
        $field_label = $field->field_label ?? '';
        $field_type = $field->field_type ?? 'text';
        $placeholder = $field->placeholder ?? '';
        $default_val = $field->default_val ?? '';
        $css_class = $field->css_class ?? '';
        $is_required = !empty($field->is_required);

        echo '<li class="' . esc_attr($classes) . '"' . $style . '>';
        echo '<div class="wpns-field-row-header">';
        echo '<span class="dashicons dashicons-move"></span>';
        echo '<input type="text" class="wpns-field-label" placeholder="' . esc_attr__('Label', 'wp-netsuite-forms') . '" value="' . esc_attr($field_label) . '">';
        echo '<input type="text" class="wpns-field-name" placeholder="' . esc_attr__('Name', 'wp-netsuite-forms') . '" value="' . esc_attr($field_name) . '">';
        echo '<select class="wpns-field-type">';
        $types = ['text','email','tel','number','select','radio','checkbox','textarea','file','hidden'];
        foreach ($types as $type) {
            echo '<option value="' . esc_attr($type) . '"' . selected($field_type, $type, false) . '>' . esc_html(ucfirst($type)) . '</option>';
        }
        echo '</select>';
        echo '<label class="wpns-required"><input type="checkbox" class="wpns-field-required"' . checked($is_required, true, false) . '> ' . esc_html__('Required', 'wp-netsuite-forms') . '</label>';
        echo '<button type="button" class="button-link wpns-remove-field">' . esc_html__('Delete', 'wp-netsuite-forms') . '</button>';
        echo '</div>';
        echo '<div class="wpns-field-row-body">';
        echo '<input type="text" class="wpns-field-placeholder" placeholder="' . esc_attr__('Placeholder', 'wp-netsuite-forms') . '" value="' . esc_attr($placeholder) . '">';
        echo '<input type="text" class="wpns-field-default" placeholder="' . esc_attr__('Default Value', 'wp-netsuite-forms') . '" value="' . esc_attr($default_val) . '">';
        echo '<input type="text" class="wpns-field-css" placeholder="' . esc_attr__('CSS Class', 'wp-netsuite-forms') . '" value="' . esc_attr($css_class) . '">';

        echo '<div class="wpns-field-options">';
        echo '<div class="wpns-options-list">';
        if (!empty($options)) {
            foreach ($options as $opt) {
                $opt_label = $opt['label'] ?? '';
                $opt_value = $opt['value'] ?? '';
                echo '<div class="wpns-option-row">';
                echo '<input type="text" class="wpns-option-label" placeholder="' . esc_attr__('Option Label', 'wp-netsuite-forms') . '" value="' . esc_attr($opt_label) . '">';
                echo '<input type="text" class="wpns-option-value" placeholder="' . esc_attr__('Option Value', 'wp-netsuite-forms') . '" value="' . esc_attr($opt_value) . '">';
                echo '<button type="button" class="button-link wpns-remove-option">' . esc_html__('Remove', 'wp-netsuite-forms') . '</button>';
                echo '</div>';
            }
        }
        echo '</div>';
        echo '<button type="button" class="button wpns-add-option">' . esc_html__('Add Option', 'wp-netsuite-forms') . '</button>';
        echo '</div>';

        echo '</div>';
        echo '</li>';
    }
}

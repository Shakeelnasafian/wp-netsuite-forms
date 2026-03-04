<?php

class WPNS_Form_Processor {
    /**
     * Handle an incoming AJAX form submission for WP Netsuite Forms.
     *
     * Validates the AJAX nonce, validates and sanitizes submitted fields (including required checks),
     * processes file uploads, merges UTM tracking data, optionally posts a payload to NetSuite and/or
     * sends email notifications according to form settings, persists the submission record, and
     * sends a JSON response containing either validation errors or a success message (and optional
     * redirect URL).
     */
    public function handle_ajax(): void {
        check_ajax_referer('wpns_form_nonce', 'nonce');

        $form_id = isset($_POST['wpns_form_id']) ? absint($_POST['wpns_form_id']) : 0;
        if (!$form_id) {
            wp_send_json_error(['message' => __('Invalid form.', 'wp-netsuite-forms')]);
        }

        $form = WPNS_Form_Model::get($form_id);
        if (!$form) {
            wp_send_json_error(['message' => __('Form not found.', 'wp-netsuite-forms')]);
        }

        $fields = WPNS_Field_Model::get_fields($form_id);
        $errors = [];
        $submitted_data = [];
        $file_urls = [];

        foreach ($fields as $field) {
            $name = (string) $field->field_name;
            $label = (string) $field->field_label;
            $type = (string) $field->field_type;
            $is_required = !empty($field->is_required);

            if ($type === 'file') {
                if ($is_required) {
                    if (
                        !isset($_FILES[$name])
                        || empty($_FILES[$name]['name'])
                        || (isset($_FILES[$name]['error']) && $_FILES[$name]['error'] !== UPLOAD_ERR_OK)
                    ) {
                        $errors[$name] = sprintf(__('%s is required.', 'wp-netsuite-forms'), $label);
                    }
                }
                continue;
            }

            if (!isset($_POST[$name])) {
                $value = '';
            } else {
                $value = wp_unslash($_POST[$name]);
            }

            if (is_array($value)) {
                $sanitized = array_map('sanitize_text_field', $value);
            } else {
                if ($type === 'email') {
                    $sanitized = sanitize_email((string) $value);
                } elseif ($type === 'textarea') {
                    $sanitized = sanitize_textarea_field((string) $value);
                } else {
                    $sanitized = sanitize_text_field((string) $value);
                }
            }

            if ($is_required) {
                $is_empty = is_array($sanitized) ? count(array_filter($sanitized, 'strlen')) === 0 : trim((string) $sanitized) === '';
                if ($is_empty) {
                    $errors[$name] = sprintf(__('%s is required.', 'wp-netsuite-forms'), $label);
                }
            }

            $submitted_data[$name] = $sanitized;
        }

        if (!empty($errors)) {
            wp_send_json_error(['errors' => $errors]);
        }

        foreach ($fields as $field) {
            if ($field->field_type !== 'file') {
                continue;
            }
            $name = (string) $field->field_name;
            if (!isset($_FILES[$name])) {
                continue;
            }
            $file = $_FILES[$name];
            if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }
            if (is_array($file['name'])) {
                $first = reset($file['name']);
                $tmp = reset($file['tmp_name']);
                if ($first && $tmp) {
                    $url = WPNS_File_Uploader::upload_from_path($tmp, $first);
                    if ($url !== '') {
                        $file_urls[$name] = $url;
                        $submitted_data[$name] = $url;
                    }
                }
            } else {
                if (!empty($file['tmp_name'])) {
                    $url = WPNS_File_Uploader::upload_from_path($file['tmp_name'], $file['name']);
                    if ($url !== '') {
                        $file_urls[$name] = $url;
                        $submitted_data[$name] = $url;
                    }
                }
            }
        }

        $utm_data = WPNS_UTM_Tracker::get_utm_data();
        $submitted_data = array_merge($submitted_data, $utm_data);

        $settings = WPNS_Settings_Model::get($form_id);
        $payload = '';
        $ns_response = '';
        $ns_success = 0;
        $error_message = '';
        $email_sent = 0;

        $image_url = '';
        if (!empty($file_urls)) {
            $image_url = reset($file_urls);
        }

        if ($settings && !empty($settings->enable_netsuite) && !empty($settings->credential_id)) {
            $credential = WPNS_Credential_Model::get((int) $settings->credential_id);
            if ($credential) {
                $payload = WPNS_Payload_Builder::build(
                    (string) ($settings->payload_template ?? ''),
                    $submitted_data,
                    (string) ($settings->static_values_json ?? ''),
                    $image_url
                );

                $client = new WPNS_Netsuite_Client($credential);
                $result = $client->post($payload);
                $ns_response = $result['response'] ?? '';
                $ns_success = !empty($result['success']) ? 1 : 0;
                if (!$ns_success) {
                    $error_message = $result['response'] ?? __('NetSuite request failed.', 'wp-netsuite-forms');
                }
            } else {
                $error_message = __('Credential profile not found.', 'wp-netsuite-forms');
            }
        }

        if ($settings && !empty($settings->enable_email)) {
            $email_sent = WPNS_Email_Notifier::send($settings, $submitted_data) ? 1 : 0;
        }

        WPNS_Submission_Model::create([
            'form_id' => $form_id,
            'submitted_data' => wp_json_encode($submitted_data),
            'netsuite_payload' => $payload,
            'netsuite_response' => $ns_response,
            'email_sent' => $email_sent,
            'ns_success' => $ns_success,
            'error_message' => $error_message,
            'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ]);

        $success_message = $form->success_message ?? '';
        if ($success_message === '') {
            $success_message = __('Thank you! Your submission has been received.', 'wp-netsuite-forms');
        }

        $response_data = ['message' => $success_message];
        if (!empty($form->redirect_url)) {
            $response_data['redirect_url'] = esc_url_raw($form->redirect_url);
        }

        wp_send_json_success($response_data);
    }
}

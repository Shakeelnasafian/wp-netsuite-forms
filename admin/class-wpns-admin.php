<?php

class WPNS_Admin {
    private WPNS_Loader $loader;

    public function __construct(WPNS_Loader $loader) {
        $this->loader = $loader;
    }

    public function init(): void {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('wp_ajax_wpns_save_form', [$this, 'ajax_save_form']);
        add_action('wp_ajax_wpns_delete_form', [$this, 'ajax_delete_form']);
        add_action('wp_ajax_wpns_save_credential', [$this, 'ajax_save_credential']);
        add_action('wp_ajax_wpns_delete_credential', [$this, 'ajax_delete_credential']);
        add_action('wp_ajax_wpns_test_netsuite', [$this, 'ajax_test_netsuite']);
        add_action('wp_ajax_wpns_delete_submission', [$this, 'ajax_delete_submission']);
    }

    public function register_menus(): void {
        add_menu_page(
            __('WP NetSuite Forms', 'wp-netsuite-forms'),
            __('WP NetSuite', 'wp-netsuite-forms'),
            'manage_options',
            'wpns-forms',
            [$this, 'page_forms'],
            'dashicons-migrate',
            30
        );

        add_submenu_page('wpns-forms', __('All Forms', 'wp-netsuite-forms'), __('All Forms', 'wp-netsuite-forms'), 'manage_options', 'wpns-forms', [$this, 'page_forms']);
        add_submenu_page('wpns-forms', __('Add New Form', 'wp-netsuite-forms'), __('Add New', 'wp-netsuite-forms'), 'manage_options', 'wpns-form-edit', [$this, 'page_form_edit']);
        add_submenu_page('wpns-forms', __('NetSuite Credentials', 'wp-netsuite-forms'), __('Credentials', 'wp-netsuite-forms'), 'manage_options', 'wpns-credentials', [$this, 'page_credentials']);
        add_submenu_page('wpns-forms', __('Submissions', 'wp-netsuite-forms'), __('Submissions', 'wp-netsuite-forms'), 'manage_options', 'wpns-submissions', [$this, 'page_submissions']);
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, 'wpns') === false) {
            return;
        }

        wp_enqueue_style('wpns-admin', WPNS_PLUGIN_URL . 'admin/css/admin.css', [], WPNS_VERSION);

        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('wpns-form-builder', WPNS_PLUGIN_URL . 'admin/js/form-builder.js', ['jquery', 'jquery-ui-sortable'], WPNS_VERSION, true);
        wp_enqueue_script('wpns-payload-editor', WPNS_PLUGIN_URL . 'admin/js/payload-editor.js', ['jquery'], WPNS_VERSION, true);
        wp_enqueue_script('wpns-credentials', WPNS_PLUGIN_URL . 'admin/js/credentials.js', ['jquery'], WPNS_VERSION, true);

        wp_localize_script('wpns-form-builder', 'wpns_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpns_admin_nonce'),
        ]);
    }

    public function page_forms(): void {
        (new WPNS_Admin_Forms())->render();
    }

    public function page_form_edit(): void {
        (new WPNS_Admin_Form_Edit())->render();
    }

    public function page_credentials(): void {
        (new WPNS_Admin_Credentials())->render();
    }

    public function page_submissions(): void {
        (new WPNS_Admin_Submissions())->render();
    }

    public function ajax_save_form(): void {
        check_ajax_referer('wpns_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'wp-netsuite-forms')]);
        }

        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;

        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $status = sanitize_text_field(wp_unslash($_POST['status'] ?? 'active'));
        $success_message = wp_kses_post(wp_unslash($_POST['success_message'] ?? ''));
        $redirect_url = esc_url_raw(wp_unslash($_POST['redirect_url'] ?? ''));

        $fields_json = wp_unslash($_POST['fields_json'] ?? '[]');
        $fields = json_decode($fields_json, true);
        if (!is_array($fields)) {
            $fields = [];
        }

        $allowed_types = ['text','email','tel','number','select','radio','checkbox','textarea','file','hidden'];
        $sanitized_fields = [];
        $sort_order = 0;
        foreach ($fields as $field) {
            $field_name = sanitize_key($field['field_name'] ?? '');
            $field_label = sanitize_text_field($field['field_label'] ?? '');
            $field_type = sanitize_text_field($field['field_type'] ?? 'text');
            if (!in_array($field_type, $allowed_types, true)) {
                $field_type = 'text';
            }

            $options = [];
            if (!empty($field['options']) && is_array($field['options'])) {
                foreach ($field['options'] as $opt) {
                    $opt_label = sanitize_text_field($opt['label'] ?? '');
                    $opt_value = sanitize_text_field($opt['value'] ?? '');
                    if ($opt_label === '' && $opt_value === '') {
                        continue;
                    }
                    $options[] = ['label' => $opt_label, 'value' => $opt_value];
                }
            }

            $sanitized_fields[] = [
                'field_name' => $field_name,
                'field_label' => $field_label,
                'field_type' => $field_type,
                'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                'default_val' => sanitize_text_field($field['default_val'] ?? ''),
                'options' => $options,
                'is_required' => !empty($field['is_required']) ? 1 : 0,
                'css_class' => sanitize_text_field($field['css_class'] ?? ''),
                'sort_order' => $sort_order,
            ];
            $sort_order++;
        }

        if ($form_id) {
            $updated = WPNS_Form_Model::update($form_id, [
                'name' => $name,
                'description' => $description,
                'status' => $status,
                'success_message' => $success_message,
                'redirect_url' => $redirect_url,
            ]);
            if (!$updated) {
                wp_send_json_error(['message' => __('Failed to update form.', 'wp-netsuite-forms')]);
            }
        } else {
            $form_id = WPNS_Form_Model::create([
                'name' => $name,
                'description' => $description,
                'status' => $status,
                'success_message' => $success_message,
                'redirect_url' => $redirect_url,
            ]);
            if (!$form_id) {
                wp_send_json_error(['message' => __('Failed to create form.', 'wp-netsuite-forms')]);
            }
        }

        WPNS_Field_Model::save_fields($form_id, $sanitized_fields);

        $static_values = wp_unslash($_POST['static_values_json'] ?? '{}');

        $settings_saved = WPNS_Settings_Model::save($form_id, [
            'credential_id' => absint($_POST['credential_id'] ?? 0),
            'payload_template' => wp_unslash($_POST['payload_template'] ?? ''),
            'static_values_json' => $static_values,
            'email_to' => sanitize_text_field(wp_unslash($_POST['email_to'] ?? '')),
            'email_cc' => sanitize_text_field(wp_unslash($_POST['email_cc'] ?? '')),
            'email_bcc' => sanitize_text_field(wp_unslash($_POST['email_bcc'] ?? '')),
            'email_subject' => sanitize_text_field(wp_unslash($_POST['email_subject'] ?? '')),
            'email_body' => wp_kses_post(wp_unslash($_POST['email_body'] ?? '')),
            'email_from_name' => sanitize_text_field(wp_unslash($_POST['email_from_name'] ?? '')),
            'email_from_address' => sanitize_email(wp_unslash($_POST['email_from_address'] ?? '')),
            'enable_netsuite' => !empty($_POST['enable_netsuite']) ? 1 : 0,
            'enable_email' => !empty($_POST['enable_email']) ? 1 : 0,
        ]);

        if (!$settings_saved) {
            wp_send_json_error(['message' => __('Failed to save settings.', 'wp-netsuite-forms')]);
        }

        wp_send_json_success([
            'form_id' => $form_id,
            'shortcode' => '[wpns_form id="' . $form_id . '"]',
        ]);
    }

    public function ajax_delete_form(): void {
        check_ajax_referer('wpns_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'wp-netsuite-forms')]);
        }

        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        if (!$form_id) {
            wp_send_json_error(['message' => __('Invalid form.', 'wp-netsuite-forms')]);
        }

        WPNS_Form_Model::delete($form_id);
        wp_send_json_success(['message' => __('Form deleted.', 'wp-netsuite-forms')]);
    }

    public function ajax_save_credential(): void {
        check_ajax_referer('wpns_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'wp-netsuite-forms')]);
        }

        $cred_id = isset($_POST['credential_id']) ? absint($_POST['credential_id']) : 0;
        $data = [
            'profile_name' => sanitize_text_field(wp_unslash($_POST['profile_name'] ?? '')),
            'account_id' => sanitize_text_field(wp_unslash($_POST['account_id'] ?? '')),
            'realm' => sanitize_text_field(wp_unslash($_POST['realm'] ?? '')),
            'consumer_key' => sanitize_text_field(wp_unslash($_POST['consumer_key'] ?? '')),
            'consumer_secret' => sanitize_text_field(wp_unslash($_POST['consumer_secret'] ?? '')),
            'token_key' => sanitize_text_field(wp_unslash($_POST['token_key'] ?? '')),
            'token_secret' => sanitize_text_field(wp_unslash($_POST['token_secret'] ?? '')),
            'script_id' => sanitize_text_field(wp_unslash($_POST['script_id'] ?? '')),
            'deploy_id' => sanitize_text_field(wp_unslash($_POST['deploy_id'] ?? '1')),
        ];

        if ($cred_id) {
            $ok = WPNS_Credential_Model::update($cred_id, $data);
        } else {
            $cred_id = WPNS_Credential_Model::create($data);
            $ok = (bool) $cred_id;
        }

        if (!$ok) {
            wp_send_json_error(['message' => __('Failed to save credential.', 'wp-netsuite-forms')]);
        }

        wp_send_json_success(['credential_id' => $cred_id]);
    }

    public function ajax_delete_credential(): void {
        check_ajax_referer('wpns_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'wp-netsuite-forms')]);
        }

        $cred_id = isset($_POST['credential_id']) ? absint($_POST['credential_id']) : 0;
        if (!$cred_id) {
            wp_send_json_error(['message' => __('Invalid credential.', 'wp-netsuite-forms')]);
        }

        WPNS_Credential_Model::delete($cred_id);
        wp_send_json_success(['message' => __('Credential deleted.', 'wp-netsuite-forms')]);
    }

    public function ajax_test_netsuite(): void {
        check_ajax_referer('wpns_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'wp-netsuite-forms')]);
        }

        $credential_id = isset($_POST['credential_id']) ? absint($_POST['credential_id']) : 0;
        if (!$credential_id && !empty($_POST['form_id'])) {
            $settings = WPNS_Settings_Model::get(absint($_POST['form_id']));
            if ($settings && !empty($settings->credential_id)) {
                $credential_id = (int) $settings->credential_id;
            }
        }

        if (!$credential_id) {
            wp_send_json_error(['message' => __('Credential not specified.', 'wp-netsuite-forms')]);
        }

        $credential = WPNS_Credential_Model::get($credential_id);
        if (!$credential) {
            wp_send_json_error(['message' => __('Credential not found.', 'wp-netsuite-forms')]);
        }

        $client = new WPNS_Netsuite_Client($credential);
        $result = $client->post('{}');

        if (!empty($result['success'])) {
            wp_send_json_success(['message' => __('Connection successful.', 'wp-netsuite-forms'), 'response' => $result['response']]);
        }

        wp_send_json_error([
            'message' => __('Connection failed.', 'wp-netsuite-forms'),
            'response' => $result['response'] ?? '',
            'http_code' => $result['http_code'] ?? 0,
        ]);
    }

    public function ajax_delete_submission(): void {
        check_ajax_referer('wpns_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'wp-netsuite-forms')]);
        }

        $submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
        if (!$submission_id) {
            wp_send_json_error(['message' => __('Invalid submission.', 'wp-netsuite-forms')]);
        }

        WPNS_Submission_Model::delete($submission_id);
        wp_send_json_success(['message' => __('Submission deleted.', 'wp-netsuite-forms')]);
    }
}

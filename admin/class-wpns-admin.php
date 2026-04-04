<?php

class WPNS_Admin {
    private WPNS_Loader $loader;

    /**
     * Initialize the admin controller with its dependency loader.
     *
     * @param WPNS_Loader $loader The loader responsible for registering WordPress hooks and actions used by this admin controller.
     */
    public function __construct(WPNS_Loader $loader) {
        $this->loader = $loader;
    }

    /**
     * Registers admin menu and asset hooks and binds plugin AJAX action handlers.
     *
     * Hooks the controller's menu and enqueue callbacks and registers the AJAX endpoints
     * used by the admin UI (save/delete form, save/delete credential, test NetSuite, delete submission).
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action( 'wp_ajax_wpns_save_form',            [ $this, 'ajax_save_form' ] );
        add_action( 'wp_ajax_wpns_delete_form',           [ $this, 'ajax_delete_form' ] );
        add_action( 'wp_ajax_wpns_save_credential',       [ $this, 'ajax_save_credential' ] );
        add_action( 'wp_ajax_wpns_delete_credential',     [ $this, 'ajax_delete_credential' ] );
        add_action( 'wp_ajax_wpns_test_netsuite',         [ $this, 'ajax_test_netsuite' ] );
        add_action( 'wp_ajax_wpns_delete_submission',     [ $this, 'ajax_delete_submission' ] );
        add_action( 'wp_ajax_wpns_retry_submission',      [ $this, 'ajax_retry_submission' ] );
        add_action( 'wp_ajax_wpns_save_recaptcha',        [ $this, 'ajax_save_recaptcha' ] );
        add_action( 'admin_post_wpns_export_csv',         [ $this, 'handle_csv_export' ] );
    }

    /**
     * Register the plugin's top-level admin menu and its subpages.
     *
     * Creates a "WP NetSuite" top-level menu (label: "WP NetSuite Forms", capability: manage_options,
     * slug: `wpns-forms`, icon: `dashicons-migrate`) and the following submenus:
     * - All Forms (slug: `wpns-forms`)
     * - Add New (slug: `wpns-form-edit`)
     * - Credentials (slug: `wpns-credentials`)
     * - Submissions (slug: `wpns-submissions`)
     */
    public function register_menus(): void {
        add_menu_page(
            __( 'WP CRM Forms', 'wp-netsuite-forms' ),
            __( 'WP CRM Forms', 'wp-netsuite-forms' ),
            'manage_options',
            'wpns-forms',
            [ $this, 'page_forms' ],
            'dashicons-networking',
            30
        );

        add_submenu_page( 'wpns-forms', __( 'All Forms',         'wp-netsuite-forms' ), __( 'All Forms',    'wp-netsuite-forms' ), 'manage_options', 'wpns-forms',       [ $this, 'page_forms' ] );
        add_submenu_page( 'wpns-forms', __( 'Add New Form',      'wp-netsuite-forms' ), __( 'Add New',      'wp-netsuite-forms' ), 'manage_options', 'wpns-form-edit',   [ $this, 'page_form_edit' ] );
        add_submenu_page( 'wpns-forms', __( 'CRM Connections',   'wp-netsuite-forms' ), __( 'CRM Connections', 'wp-netsuite-forms' ), 'manage_options', 'wpns-credentials', [ $this, 'page_credentials' ] );
        add_submenu_page( 'wpns-forms', __( 'Submissions',       'wp-netsuite-forms' ), __( 'Submissions',  'wp-netsuite-forms' ), 'manage_options', 'wpns-submissions', [ $this, 'page_submissions' ] );
        add_submenu_page( 'wpns-forms', __( 'Settings',          'wp-netsuite-forms' ), __( 'Settings',     'wp-netsuite-forms' ), 'manage_options', 'wpns-settings',    [ $this, 'page_settings' ] );
    }

    /**
     * Enqueues WP NetSuite Forms admin styles and JavaScript for plugin-related admin pages.
     *
     * Only enqueues assets when the current admin page hook contains "wpns". Also localizes
     * AJAX URL and an admin nonce to the form-builder script.
     *
     * @param string $hook The current admin page hook name provided by WordPress.
     */
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

    /**
     * Render the admin page that lists all WP NetSuite forms.
     */
    public function page_forms(): void {
        (new WPNS_Admin_Forms())->render();
    }

    /**
     * Display the admin page for creating or editing a form.
     */
    public function page_form_edit(): void {
        (new WPNS_Admin_Form_Edit())->render();
    }

    /**
     * Render the NetSuite Credentials administration page in the WP admin.
     */
    public function page_credentials(): void {
        (new WPNS_Admin_Credentials())->render();
    }

    /**
     * Display the Submissions admin page in the WordPress admin.
     */
    public function page_submissions(): void {
        ( new WPNS_Admin_Submissions() )->render();
    }

    /** Render the Settings page (reCAPTCHA config). */
    public function page_settings(): void {
        $site_key  = esc_attr( get_option( 'wpns_recaptcha_site_key', '' ) );
        $secret    = esc_attr( get_option( 'wpns_recaptcha_secret_key', '' ) );
        $threshold = esc_attr( get_option( 'wpns_recaptcha_score_threshold', '0.5' ) );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'WP NetSuite Forms — Settings', 'wp-netsuite-forms' ) . '</h1>';
        echo '<h2>' . esc_html__( 'reCAPTCHA v3', 'wp-netsuite-forms' ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Enter your Google reCAPTCHA v3 keys. Enable reCAPTCHA per-form in the form editor → Settings tab.', 'wp-netsuite-forms' ) . '</p>';
        echo '<form id="wpns-recaptcha-form">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="wpns-rc-site-key">'   . esc_html__( 'Site Key',         'wp-netsuite-forms' ) . '</label></th><td><input type="text" id="wpns-rc-site-key"   class="regular-text" value="' . $site_key  . '"></td></tr>';
        echo '<tr><th><label for="wpns-rc-secret-key">' . esc_html__( 'Secret Key',       'wp-netsuite-forms' ) . '</label></th><td><input type="password" id="wpns-rc-secret-key" class="regular-text" value="' . $secret . '"> <button type="button" class="button-link wpns-toggle-secret">' . esc_html__( 'Show', 'wp-netsuite-forms' ) . '</button></td></tr>';
        echo '<tr><th><label for="wpns-rc-threshold">'  . esc_html__( 'Score Threshold',  'wp-netsuite-forms' ) . '</label></th><td><input type="number" id="wpns-rc-threshold" class="small-text" min="0" max="1" step="0.1" value="' . $threshold . '"><p class="description">' . esc_html__( '0.0 = allow all, 1.0 = block most bots. Recommended: 0.5', 'wp-netsuite-forms' ) . '</p></td></tr>';
        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save Settings', 'wp-netsuite-forms' ) . '</button> <span class="wpns-rc-status"></span></p>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Handle the AJAX request to create or update a form and its related settings and fields.
     *
     * Validates nonce and user capability, sanitizes input (form metadata, field definitions, options,
     * and settings), persists the form record and its fields, saves form settings, and returns a JSON
     * success payload with the form ID and shortcode or a JSON error on failure.
     */
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

            // Sanitize condition_json.
            $condition_json = '';
            if ( ! empty( $field['condition_json'] ) ) {
                $raw_cond = json_decode( wp_unslash( $field['condition_json'] ), true );
                if ( is_array( $raw_cond ) ) {
                    $allowed_ops    = [ '=', '!=', 'contains', '!contains', 'empty', 'not_empty' ];
                    $cond_field_key = sanitize_key( $raw_cond['field']    ?? '' );
                    $cond_operator  = sanitize_text_field( $raw_cond['operator'] ?? '=' );
                    $cond_value     = sanitize_text_field( $raw_cond['value']    ?? '' );
                    if ( $cond_field_key && in_array( $cond_operator, $allowed_ops, true ) ) {
                        $condition_json = wp_json_encode( [
                            'field'    => $cond_field_key,
                            'operator' => $cond_operator,
                            'value'    => $cond_value,
                        ] );
                    }
                }
            }

            $sanitized_fields[] = [
                'field_name'     => $field_name,
                'field_label'    => $field_label,
                'field_type'     => $field_type,
                'placeholder'    => sanitize_text_field( $field['placeholder'] ?? '' ),
                'default_val'    => sanitize_text_field( $field['default_val'] ?? '' ),
                'options'        => $options,
                'is_required'    => ! empty( $field['is_required'] ) ? 1 : 0,
                'css_class'      => sanitize_text_field( $field['css_class'] ?? '' ),
                'sort_order'     => $sort_order,
                'condition_json' => $condition_json,
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
            'enable_netsuite'  => ! empty( $_POST['enable_netsuite'] )  ? 1 : 0,
            'enable_email'     => ! empty( $_POST['enable_email'] )     ? 1 : 0,
            'enable_recaptcha' => ! empty( $_POST['enable_recaptcha'] ) ? 1 : 0,
        ] );

        if (!$settings_saved) {
            wp_send_json_error(['message' => __('Failed to save settings.', 'wp-netsuite-forms')]);
        }

        wp_send_json_success([
            'form_id' => $form_id,
            'shortcode' => '[wpns_form id="' . $form_id . '"]',
        ]);
    }

    /**
     * Handles the AJAX request to delete a form, validating nonce and user capability,
     * validating the submitted form ID, deleting the form record, and returning a JSON success or error response.
     *
     * The handler verifies the admin nonce and that the current user has the `manage_options`
     * capability. If the `form_id` POST parameter is missing or invalid, it returns a JSON error;
     * otherwise it deletes the form and returns a JSON success message.
     */
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

    /**
     * Handle an AJAX request to create or update a NetSuite credential and return a JSON response.
     *
     * Validates the nonce `wpns_admin_nonce` (request field `nonce`) and the `manage_options` capability.
     * Reads and sanitizes the following POST fields: `credential_id` (optional), `profile_name`, `account_id`,
     * `realm`, `consumer_key`, `consumer_secret`, `token_key`, `token_secret`, `script_id`, and `deploy_id`.
     * Creates a new credential or updates an existing one and responds with a JSON success containing
     * `credential_id` on success. On failure or insufficient permissions it responds with a JSON error
     * and an explanatory `message` (e.g., "Unauthorized." or "Failed to save credential.").
     */
    public function ajax_save_credential(): void {
        check_ajax_referer( 'wpns_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'wp-netsuite-forms' ) ] );
        }

        $cred_id  = isset( $_POST['credential_id'] ) ? absint( $_POST['credential_id'] ) : 0;
        $crm_type = sanitize_key( wp_unslash( $_POST['crm_type'] ?? 'netsuite' ) );

        // Build config array from POST based on CRM type.
        $config = [];
        $allowed_config_keys = [
            'odoo'     => [ 'url', 'database', 'username', 'api_key', 'model' ],
            'zoho'     => [ 'client_id', 'client_secret', 'refresh_token', 'data_center', 'module' ],
            'hubspot'  => [ 'access_token', 'object_type' ],
            'webhook'  => [ 'url', 'method', 'headers_json', 'auth_type', 'auth_value', 'auth_param' ],
        ];

        if ( isset( $allowed_config_keys[ $crm_type ] ) ) {
            $raw_config = isset( $_POST['config'] ) && is_array( $_POST['config'] )
                ? wp_unslash( $_POST['config'] )
                : [];
            foreach ( $allowed_config_keys[ $crm_type ] as $key ) {
                $config[ $key ] = sanitize_text_field( $raw_config[ $key ] ?? '' );
            }
        }

        $data = [
            'crm_type'        => $crm_type,
            'profile_name'    => sanitize_text_field( wp_unslash( $_POST['profile_name']    ?? '' ) ),
            // NetSuite fields (empty for other CRM types — still stored for schema compat).
            'account_id'      => sanitize_text_field( wp_unslash( $_POST['account_id']      ?? '' ) ),
            'realm'           => sanitize_text_field( wp_unslash( $_POST['realm']           ?? '' ) ),
            'consumer_key'    => sanitize_text_field( wp_unslash( $_POST['consumer_key']    ?? '' ) ),
            'consumer_secret' => sanitize_text_field( wp_unslash( $_POST['consumer_secret'] ?? '' ) ),
            'token_key'       => sanitize_text_field( wp_unslash( $_POST['token_key']       ?? '' ) ),
            'token_secret'    => sanitize_text_field( wp_unslash( $_POST['token_secret']    ?? '' ) ),
            'script_id'       => sanitize_text_field( wp_unslash( $_POST['script_id']       ?? '' ) ),
            'deploy_id'       => sanitize_text_field( wp_unslash( $_POST['deploy_id']       ?? '1' ) ),
            'config'          => $config,
        ];

        if ( $cred_id ) {
            $ok = WPNS_Credential_Model::update( $cred_id, $data );
        } else {
            $cred_id = WPNS_Credential_Model::create( $data );
            $ok      = (bool) $cred_id;
        }

        if ( ! $ok ) {
            wp_send_json_error( [ 'message' => __( 'Failed to save credential.', 'wp-netsuite-forms' ) ] );
        }

        wp_send_json_success( [ 'credential_id' => $cred_id ] );
    }

    /**
     * Handle the AJAX request to delete a NetSuite credential.
     *
     * Performs nonce and capability checks, reads the `credential_id` from POST,
     * deletes the credential via WPNS_Credential_Model::delete, and sends a JSON
     * response indicating success or an error (unauthorized or invalid credential).
     */
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

    /**
     * Tests a NetSuite credential by issuing a dummy request and responds with JSON indicating success or failure.
     *
     * Validates the admin AJAX nonce and the current user's `manage_options` capability.
     * Resolves `credential_id` from POST (or from the form's settings when `form_id` is provided), loads the credential,
     * performs a test request against NetSuite, and returns a JSON success payload with the provider response on success
     * or a JSON error with optional response details and an HTTP status code on failure.
     */
    public function ajax_test_netsuite(): void {
        check_ajax_referer( 'wpns_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'wp-netsuite-forms' ) ] );
        }

        $credential_id = isset( $_POST['credential_id'] ) ? absint( $_POST['credential_id'] ) : 0;
        if ( ! $credential_id && ! empty( $_POST['form_id'] ) ) {
            $settings = WPNS_Settings_Model::get( absint( $_POST['form_id'] ) );
            if ( $settings && ! empty( $settings->credential_id ) ) {
                $credential_id = (int) $settings->credential_id;
            }
        }

        if ( ! $credential_id ) {
            wp_send_json_error( [ 'message' => __( 'Credential not specified.', 'wp-netsuite-forms' ) ] );
        }

        $credential = WPNS_Credential_Model::get( $credential_id );
        if ( ! $credential ) {
            wp_send_json_error( [ 'message' => __( 'Credential not found.', 'wp-netsuite-forms' ) ] );
        }

        $client = WPNS_CRM_Factory::make( $credential );
        $result = $client->test();

        if ( ! empty( $result['success'] ) ) {
            wp_send_json_success( [ 'message' => __( 'Connection successful.', 'wp-netsuite-forms' ), 'response' => $result['response'] ] );
        }

        wp_send_json_error( [
            'message'   => __( 'Connection failed.', 'wp-netsuite-forms' ),
            'response'  => $result['response']  ?? '',
            'http_code' => $result['http_code'] ?? 0,
        ] );
    }

    /**
     * Handle an AJAX request to delete a submission.
     *
     * Verifies the admin nonce and that the current user has the `manage_options` capability.
     * Expects `submission_id` in POST as a positive integer; responds with a JSON error if missing or invalid.
     * On success deletes the submission record and sends a JSON success response.
     */
    public function ajax_delete_submission(): void {
        check_ajax_referer('wpns_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'wp-netsuite-forms')]);
        }

        $submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
        if (!$submission_id) {
            wp_send_json_error(['message' => __('Invalid submission.', 'wp-netsuite-forms')]);
        }

        WPNS_Submission_Model::delete( $submission_id );
        wp_send_json_success( [ 'message' => __( 'Submission deleted.', 'wp-netsuite-forms' ) ] );
    }

    /** Retry a failed NetSuite submission. */
    public function ajax_retry_submission(): void {
        check_ajax_referer( 'wpns_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'wp-netsuite-forms' ) ] );
        }

        $sub_id     = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
        $submission = $sub_id ? WPNS_Submission_Model::get( $sub_id ) : null;

        if ( ! $submission ) {
            wp_send_json_error( [ 'message' => __( 'Submission not found.', 'wp-netsuite-forms' ) ] );
        }

        $settings = WPNS_Settings_Model::get( (int) $submission->form_id );
        if ( ! $settings || empty( $settings->credential_id ) ) {
            wp_send_json_error( [ 'message' => __( 'No credential configured for this form.', 'wp-netsuite-forms' ) ] );
        }

        $credential = WPNS_Credential_Model::get( (int) $settings->credential_id );
        if ( ! $credential ) {
            wp_send_json_error( [ 'message' => __( 'Credential not found.', 'wp-netsuite-forms' ) ] );
        }

        $client = WPNS_CRM_Factory::make( $credential );
        $result = $client->post( (string) $submission->netsuite_payload );

        if ( ! empty( $result['success'] ) ) {
            WPNS_Submission_Model::mark_ns_success( $sub_id, $result['response'] ?? '' );
            wp_send_json_success( [ 'message' => __( 'Retry successful.', 'wp-netsuite-forms' ) ] );
        }

        wp_send_json_error( [
            'message' => __( 'Retry failed: ', 'wp-netsuite-forms' ) . ( $result['response'] ?? 'Unknown error' ),
        ] );
    }

    /** Save reCAPTCHA v3 settings. */
    public function ajax_save_recaptcha(): void {
        check_ajax_referer( 'wpns_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'wp-netsuite-forms' ) ] );
        }

        update_option( 'wpns_recaptcha_site_key',        sanitize_text_field( wp_unslash( $_POST['site_key']        ?? '' ) ) );
        update_option( 'wpns_recaptcha_secret_key',      sanitize_text_field( wp_unslash( $_POST['secret_key']      ?? '' ) ) );
        update_option( 'wpns_recaptcha_score_threshold', (string) min( 1.0, max( 0.0, (float) ( $_POST['threshold'] ?? 0.5 ) ) ) );

        wp_send_json_success( [ 'message' => __( 'Settings saved.', 'wp-netsuite-forms' ) ] );
    }

    /** Stream CSV export of form submissions. */
    public function handle_csv_export(): void {
        if ( ! check_admin_referer( 'wpns_export_csv' ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'wp-netsuite-forms' ) );
        }
        $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        if ( ! $form_id ) {
            wp_die( esc_html__( 'Invalid form.', 'wp-netsuite-forms' ) );
        }
        WPNS_CSV_Exporter::export( $form_id );
    }
}

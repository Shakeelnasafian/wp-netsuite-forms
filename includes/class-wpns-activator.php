<?php

class WPNS_Activator {
    public static function activate(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $forms_table = $wpdb->prefix . 'wpns_forms';
        $fields_table = $wpdb->prefix . 'wpns_fields';
        $creds_table = $wpdb->prefix . 'wpns_credentials';
        $settings_table = $wpdb->prefix . 'wpns_form_settings';
        $subs_table = $wpdb->prefix . 'wpns_submissions';

        $sql_forms = "CREATE TABLE $forms_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            status ENUM('active','inactive') DEFAULT 'active',
            success_message TEXT,
            redirect_url VARCHAR(500),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset;";

        $sql_fields = "CREATE TABLE $fields_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NOT NULL,
            field_name VARCHAR(100) NOT NULL,
            field_label VARCHAR(255) NOT NULL,
            field_type ENUM('text','email','tel','number','select','radio','checkbox','textarea','file','hidden') NOT NULL,
            placeholder VARCHAR(255),
            default_val VARCHAR(500),
            options_json TEXT,
            is_required TINYINT(1) DEFAULT 0,
            css_class VARCHAR(255),
            sort_order INT DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset;";

        $sql_creds = "CREATE TABLE $creds_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            profile_name VARCHAR(255) NOT NULL,
            account_id VARCHAR(100) NOT NULL,
            realm VARCHAR(100) NOT NULL,
            consumer_key TEXT NOT NULL,
            consumer_secret TEXT NOT NULL,
            token_key TEXT NOT NULL,
            token_secret TEXT NOT NULL,
            script_id VARCHAR(50) NOT NULL,
            deploy_id VARCHAR(10) DEFAULT '1',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset;";

        $sql_settings = "CREATE TABLE $settings_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NOT NULL,
            credential_id BIGINT UNSIGNED,
            payload_template LONGTEXT,
            static_values_json LONGTEXT,
            email_to VARCHAR(500),
            email_cc VARCHAR(500),
            email_bcc VARCHAR(500),
            email_subject VARCHAR(500),
            email_body LONGTEXT,
            email_from_name VARCHAR(255),
            email_from_address VARCHAR(255),
            enable_netsuite TINYINT(1) DEFAULT 1,
            enable_email TINYINT(1) DEFAULT 1,
            PRIMARY KEY  (id),
            UNIQUE KEY form_id (form_id)
        ) $charset;";

        $sql_subs = "CREATE TABLE $subs_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NOT NULL,
            submitted_data LONGTEXT,
            netsuite_payload LONGTEXT,
            netsuite_response LONGTEXT,
            email_sent TINYINT(1) DEFAULT 0,
            ns_success TINYINT(1) DEFAULT 0,
            error_message TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset;";

        dbDelta($sql_forms);
        dbDelta($sql_fields);
        dbDelta($sql_creds);
        dbDelta($sql_settings);
        dbDelta($sql_subs);

        update_option('wpns_version', WPNS_VERSION);
    }
}

<?php

class WPNS_Activator {
    /**
     * Run on plugin activation: create/upgrade all database tables.
     */
    public static function activate(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $forms_table    = $wpdb->prefix . 'wpns_forms';
        $fields_table   = $wpdb->prefix . 'wpns_fields';
        $creds_table    = $wpdb->prefix . 'wpns_credentials';
        $settings_table = $wpdb->prefix . 'wpns_form_settings';
        $subs_table     = $wpdb->prefix . 'wpns_submissions';

        // NOTE: dbDelta requires exactly two spaces before PRIMARY KEY and KEY lines.
        $sql_forms = "CREATE TABLE $forms_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            status ENUM('active','inactive') DEFAULT 'active',
            success_message TEXT,
            redirect_url VARCHAR(500),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY created_at (created_at)
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
            condition_json TEXT,
            is_required TINYINT(1) DEFAULT 0,
            css_class VARCHAR(255),
            sort_order INT DEFAULT 0,
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY form_sort (form_id, sort_order)
        ) $charset;";

        $sql_creds = "CREATE TABLE $creds_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            crm_type VARCHAR(50) NOT NULL DEFAULT 'netsuite',
            profile_name VARCHAR(255) NOT NULL,
            account_id VARCHAR(100) NOT NULL DEFAULT '',
            realm VARCHAR(100) NOT NULL DEFAULT '',
            consumer_key TEXT NOT NULL,
            consumer_secret TEXT NOT NULL,
            token_key TEXT NOT NULL,
            token_secret TEXT NOT NULL,
            script_id VARCHAR(50) NOT NULL DEFAULT '',
            deploy_id VARCHAR(10) DEFAULT '1',
            config_json LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY crm_type (crm_type)
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
            enable_recaptcha TINYINT(1) DEFAULT 0,
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
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY ns_success (ns_success),
            KEY created_at (created_at)
        ) $charset;";

        dbDelta( $sql_forms );
        dbDelta( $sql_fields );
        dbDelta( $sql_creds );
        dbDelta( $sql_settings );
        dbDelta( $sql_subs );

        // Add new columns if they don't exist yet (safe for existing installs).
        $creds_cols = $wpdb->get_col( "SHOW COLUMNS FROM $creds_table" );
        if ( ! in_array( 'crm_type',    $creds_cols, true ) ) {
            $wpdb->query( "ALTER TABLE $creds_table ADD COLUMN crm_type VARCHAR(50) NOT NULL DEFAULT 'netsuite' AFTER id" );
        }
        if ( ! in_array( 'config_json', $creds_cols, true ) ) {
            $wpdb->query( "ALTER TABLE $creds_table ADD COLUMN config_json LONGTEXT AFTER deploy_id" );
        }

        update_option( 'wpns_version', WPNS_VERSION );
    }

    /**
     * Run on every plugins_loaded: upgrade the schema when the stored version
     * is older than the current plugin version.
     */
    public static function maybe_upgrade(): void {
        $installed = get_option( 'wpns_version', '0.0.0' );

        if ( version_compare( $installed, WPNS_VERSION, '>=' ) ) {
            return;
        }

        // Re-run activation to apply new indexes / columns via dbDelta.
        self::activate();
    }
}

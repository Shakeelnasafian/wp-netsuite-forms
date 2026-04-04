<?php

class WPNS_Settings_Model {
    /**
     * Insert or update a form's settings row in the wpns_form_settings table.
     *
     * Saves provided settings for the given form ID; updates the existing row if one exists
     * for the form_id, otherwise inserts a new row. Boolean-like flags `enable_netsuite`
     * and `enable_email` are normalized to 1 (enabled) or 0 (disabled).
     *
     * @param int   $form_id The ID of the form to save settings for.
     * @param array $data    Associative array of settings. Recognized keys:
     *                       - credential_id (int)
     *                       - payload_template (string)
     *                       - static_values_json (string)
     *                       - email_to (string)
     *                       - email_cc (string)
     *                       - email_bcc (string)
     *                       - email_subject (string)
     *                       - email_body (string)
     *                       - email_from_name (string)
     *                       - email_from_address (string)
     *                       - enable_netsuite (truthy to enable)
     *                       - enable_email (truthy to enable)
     * @return bool True if the insert or update operation succeeded, false otherwise.
     */
    public static function save(int $form_id, array $data): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_form_settings';

        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE form_id=%d", $form_id));

        $payload_template = $data['payload_template'] ?? '';
        $static_values_json = $data['static_values_json'] ?? '';

        $enable_recaptcha = ! empty( $data['enable_recaptcha'] ) ? 1 : 0;

        if ($existing_id) {
            $sql = $wpdb->prepare(
                "UPDATE $table SET credential_id=%d, payload_template=%s, static_values_json=%s, email_to=%s, email_cc=%s, email_bcc=%s, email_subject=%s, email_body=%s, email_from_name=%s, email_from_address=%s, enable_netsuite=%d, enable_email=%d, enable_recaptcha=%d WHERE form_id=%d",
                $data['credential_id'] ?? 0,
                $payload_template,
                $static_values_json,
                $data['email_to'] ?? '',
                $data['email_cc'] ?? '',
                $data['email_bcc'] ?? '',
                $data['email_subject'] ?? '',
                $data['email_body'] ?? '',
                $data['email_from_name'] ?? '',
                $data['email_from_address'] ?? '',
                !empty($data['enable_netsuite']) ? 1 : 0,
                !empty($data['enable_email']) ? 1 : 0,
                $enable_recaptcha,
                $form_id
            );
            return $wpdb->query($sql) !== false;
        }

        $sql = $wpdb->prepare(
            "INSERT INTO $table (form_id, credential_id, payload_template, static_values_json, email_to, email_cc, email_bcc, email_subject, email_body, email_from_name, email_from_address, enable_netsuite, enable_email, enable_recaptcha)
             VALUES (%d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d, %d)",
            $form_id,
            $data['credential_id'] ?? 0,
            $payload_template,
            $static_values_json,
            $data['email_to'] ?? '',
            $data['email_cc'] ?? '',
            $data['email_bcc'] ?? '',
            $data['email_subject'] ?? '',
            $data['email_body'] ?? '',
            $data['email_from_name'] ?? '',
            $data['email_from_address'] ?? '',
            !empty($data['enable_netsuite']) ? 1 : 0,
            !empty($data['enable_email']) ? 1 : 0,
            $enable_recaptcha
        );

        return $wpdb->query($sql) !== false;
    }

    /**
     * Fetches the settings row for a given form ID.
     *
     * @param int $form_id The form's database ID whose settings to retrieve.
     * @return object|null The settings row as an object when found, or `null` if no matching row exists.
     */
    public static function get(int $form_id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_form_settings';
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE form_id=%d", $form_id);
        $row = $wpdb->get_row($sql);
        return $row ?: null;
    }

    /**
     * Deletes the settings row for the specified form from the wpns_form_settings table.
     *
     * @param int $form_id The ID of the form whose settings should be deleted.
     * @return bool `true` if the delete operation succeeded, `false` otherwise.
     */
    public static function delete_by_form(int $form_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_form_settings';
        $sql = $wpdb->prepare("DELETE FROM $table WHERE form_id=%d", $form_id);
        return $wpdb->query($sql) !== false;
    }
}

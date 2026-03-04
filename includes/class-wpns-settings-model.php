<?php

class WPNS_Settings_Model {
    public static function save(int $form_id, array $data): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_form_settings';

        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE form_id=%d", $form_id));

        $payload_template = $data['payload_template'] ?? '';
        $static_values_json = $data['static_values_json'] ?? '';

        if ($existing_id) {
            $sql = $wpdb->prepare(
                "UPDATE $table SET credential_id=%d, payload_template=%s, static_values_json=%s, email_to=%s, email_cc=%s, email_bcc=%s, email_subject=%s, email_body=%s, email_from_name=%s, email_from_address=%s, enable_netsuite=%d, enable_email=%d WHERE form_id=%d",
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
                $form_id
            );
            return $wpdb->query($sql) !== false;
        }

        $sql = $wpdb->prepare(
            "INSERT INTO $table (form_id, credential_id, payload_template, static_values_json, email_to, email_cc, email_bcc, email_subject, email_body, email_from_name, email_from_address, enable_netsuite, enable_email)
             VALUES (%d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d)",
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
            !empty($data['enable_email']) ? 1 : 0
        );

        return $wpdb->query($sql) !== false;
    }

    public static function get(int $form_id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_form_settings';
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE form_id=%d", $form_id);
        $row = $wpdb->get_row($sql);
        return $row ?: null;
    }

    public static function delete_by_form(int $form_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_form_settings';
        $sql = $wpdb->prepare("DELETE FROM $table WHERE form_id=%d", $form_id);
        return $wpdb->query($sql) !== false;
    }
}

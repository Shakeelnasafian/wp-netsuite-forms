<?php

class WPNS_Submission_Model {
    public static function create(array $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';

        $sql = $wpdb->prepare(
            "INSERT INTO $table (form_id, submitted_data, netsuite_payload, netsuite_response, email_sent, ns_success, error_message, ip_address, user_agent)
             VALUES (%d, %s, %s, %s, %d, %d, %s, %s, %s)",
            $data['form_id'] ?? 0,
            $data['submitted_data'] ?? '',
            $data['netsuite_payload'] ?? '',
            $data['netsuite_response'] ?? '',
            !empty($data['email_sent']) ? 1 : 0,
            !empty($data['ns_success']) ? 1 : 0,
            $data['error_message'] ?? '',
            $data['ip_address'] ?? '',
            $data['user_agent'] ?? ''
        );

        $result = $wpdb->query($sql);
        if ($result === false) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    public static function get(int $id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id);
        $row = $wpdb->get_row($sql);
        return $row ?: null;
    }

    public static function get_by_form(int $form_id, int $limit = 50, int $offset = 0): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE form_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d", $form_id, $limit, $offset);
        return $wpdb->get_results($sql) ?: [];
    }

    public static function get_all(int $limit = 50, int $offset = 0): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql = $wpdb->prepare("SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $limit, $offset);
        return $wpdb->get_results($sql) ?: [];
    }

    public static function count_by_form(int $form_id): int {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql = $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE form_id=%d", $form_id);
        return (int) $wpdb->get_var($sql);
    }

    public static function count_all(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    public static function delete(int $id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql = $wpdb->prepare("DELETE FROM $table WHERE id=%d", $id);
        return $wpdb->query($sql) !== false;
    }
}

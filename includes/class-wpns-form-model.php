<?php

class WPNS_Form_Model {
    public static function create(array $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_forms';

        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $status = $data['status'] ?? 'active';
        $success_message = $data['success_message'] ?? '';
        $redirect_url = $data['redirect_url'] ?? '';

        $sql = $wpdb->prepare(
            "INSERT INTO $table (name, description, status, success_message, redirect_url)
             VALUES (%s, %s, %s, %s, %s)",
            $name,
            $description,
            $status,
            $success_message,
            $redirect_url
        );

        $result = $wpdb->query($sql);
        if ($result === false) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    public static function update(int $id, array $data): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_forms';

        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $status = $data['status'] ?? 'active';
        $success_message = $data['success_message'] ?? '';
        $redirect_url = $data['redirect_url'] ?? '';

        $sql = $wpdb->prepare(
            "UPDATE $table SET name=%s, description=%s, status=%s, success_message=%s, redirect_url=%s WHERE id=%d",
            $name,
            $description,
            $status,
            $success_message,
            $redirect_url,
            $id
        );

        return $wpdb->query($sql) !== false;
    }

    public static function delete(int $id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_forms';

        WPNS_Field_Model::delete_by_form($id);
        if (method_exists('WPNS_Settings_Model', 'delete_by_form')) {
            WPNS_Settings_Model::delete_by_form($id);
        }

        $sql = $wpdb->prepare("DELETE FROM $table WHERE id=%d", $id);
        return $wpdb->query($sql) !== false;
    }

    public static function get(int $id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_forms';
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id);
        $row = $wpdb->get_row($sql);
        return $row ?: null;
    }

    public static function get_all(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_forms';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC") ?: [];
    }

    public static function get_active(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_forms';
        return $wpdb->get_results("SELECT * FROM $table WHERE status='active' ORDER BY created_at DESC") ?: [];
    }
}

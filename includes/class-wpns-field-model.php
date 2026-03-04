<?php

class WPNS_Field_Model {
    public static function save_fields(int $form_id, array $fields): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_fields';

        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE form_id=%d", $form_id));

        foreach ($fields as $field) {
            $options_json = '';
            if (isset($field['options']) && is_array($field['options'])) {
                $options_json = wp_json_encode($field['options']);
            } elseif (isset($field['options_json'])) {
                $options_json = (string) $field['options_json'];
            }

            $sql = $wpdb->prepare(
                "INSERT INTO $table (form_id, field_name, field_label, field_type, placeholder, default_val, options_json, is_required, css_class, sort_order)
                 VALUES (%d, %s, %s, %s, %s, %s, %s, %d, %s, %d)",
                $form_id,
                $field['field_name'] ?? '',
                $field['field_label'] ?? '',
                $field['field_type'] ?? 'text',
                $field['placeholder'] ?? '',
                $field['default_val'] ?? '',
                $options_json,
                !empty($field['is_required']) ? 1 : 0,
                $field['css_class'] ?? '',
                isset($field['sort_order']) ? (int) $field['sort_order'] : 0
            );

            $result = $wpdb->query($sql);
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    public static function get_fields(int $form_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_fields';
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE form_id=%d ORDER BY sort_order ASC, id ASC", $form_id);
        return $wpdb->get_results($sql) ?: [];
    }

    public static function delete_by_form(int $form_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_fields';
        $sql = $wpdb->prepare("DELETE FROM $table WHERE form_id=%d", $form_id);
        return $wpdb->query($sql) !== false;
    }
}

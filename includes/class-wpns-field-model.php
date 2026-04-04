<?php

class WPNS_Field_Model {
    /**
     * Replace all fields for a form with the provided field definitions.
     *
     * @param int $form_id The ID of the form whose fields will be replaced.
     * @param array $fields Array of field definitions. Each element should be an associative array that may contain:
     *                      - 'field_name' (string) default: ''.
     *                      - 'field_label' (string) default: ''.
     *                      - 'field_type' (string) default: 'text'.
     *                      - 'placeholder' (string) default: ''.
     *                      - 'default_val' (string) default: ''.
     *                      - 'options' (array) if present will be JSON-encoded and stored in `options_json`.
     *                      - 'options_json' (string) used if 'options' is not provided.
     *                      - 'is_required' (truthy value) stored as 1 when non-empty, otherwise 0.
     *                      - 'css_class' (string) default: ''.
     *                      - 'sort_order' (int) default: 0.
     * @return bool `true` if all fields were inserted successfully, `false` if any database insert failed.
     */
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

            $condition_json = isset( $field['condition_json'] ) ? (string) $field['condition_json'] : '';

            $sql = $wpdb->prepare(
                "INSERT INTO $table (form_id, field_name, field_label, field_type, placeholder, default_val, options_json, condition_json, is_required, css_class, sort_order)
                 VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %d, %s, %d)",
                $form_id,
                $field['field_name'] ?? '',
                $field['field_label'] ?? '',
                $field['field_type'] ?? 'text',
                $field['placeholder'] ?? '',
                $field['default_val'] ?? '',
                $options_json,
                $condition_json,
                ! empty( $field['is_required'] ) ? 1 : 0,
                $field['css_class'] ?? '',
                isset( $field['sort_order'] ) ? (int) $field['sort_order'] : 0
            );

            $result = $wpdb->query($sql);
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retrieve all field records for a given form, ordered by sort_order then id.
     *
     * @param int $form_id The form's ID whose fields will be fetched.
     * @return array An array of field row objects (empty array if none found).
     */
    public static function get_fields(int $form_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_fields';
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE form_id=%d ORDER BY sort_order ASC, id ASC", $form_id);
        return $wpdb->get_results($sql) ?: [];
    }

    /**
     * Delete all field records for the specified form.
     *
     * @param int $form_id The ID of the form whose fields should be removed.
     * @return bool True if the delete operation completed (including when no rows matched), false on database error.
     */
    public static function delete_by_form(int $form_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_fields';
        $sql = $wpdb->prepare("DELETE FROM $table WHERE form_id=%d", $form_id);
        return $wpdb->query($sql) !== false;
    }
}

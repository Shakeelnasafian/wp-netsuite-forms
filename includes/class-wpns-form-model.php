<?php

class WPNS_Form_Model {
    /**
     * Create a new form record in the wpns_forms database table.
     *
     * @param array $data {
     *     Associative array of form properties. Supported keys:
     *     - string $name The form name (defaults to empty string).
     *     - string $description The form description (defaults to empty string).
     *     - string $status The form status (defaults to 'active').
     *     - string $success_message Message shown on successful submit (defaults to empty string).
     *     - string $redirect_url URL to redirect after submit (defaults to empty string).
     * }
     * @return int|false The inserted row ID on success, `false` on database error.
     */
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

    /**
     * Update an existing form record by ID using provided data (applies defaults for missing fields).
     *
     * @param int $id The ID of the form to update.
     * @param array $data Associative array of form fields. Recognized keys:
     *                    - 'name' (string, default: ''),
     *                    - 'description' (string, default: ''),
     *                    - 'status' (string, default: 'active'),
     *                    - 'success_message' (string, default: ''),
     *                    - 'redirect_url' (string, default: '').
     * @return bool `true` if the update did not fail, `false` otherwise.
     */
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

    /**
     * Delete a form and its related data.
     *
     * Removes the form identified by the given ID and also deletes any related fields and settings when applicable.
     *
     * @param int $id The ID of the form to delete.
     * @return bool `true` if the deletion succeeded, `false` if a database error occurred.
     */
    public static function delete(int $id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_forms';

        WPNS_Field_Model::delete_by_form( $id );
        WPNS_Settings_Model::delete_by_form( $id );
        WPNS_Submission_Model::delete_by_form( $id );

        $sql = $wpdb->prepare( "DELETE FROM $table WHERE id=%d", $id );
        return $wpdb->query( $sql ) !== false;
    }

    /**
     * Retrieve a form record by its ID.
     *
     * @param int $id The form's database ID.
     * @return object|null The form row as an object if found, `null` otherwise.
     */
    public static function get(int $id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_forms';
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id);
        $row = $wpdb->get_row($sql);
        return $row ?: null;
    }

    /**
     * Retrieve all form records ordered by newest first.
     *
     * @return array An array of form objects from the wpns_forms table ordered by `created_at` descending, or an empty array if no records exist.
     */
    public static function get_all(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_forms';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC") ?: [];
    }

    /**
     * Retrieve all forms with status 'active', ordered by created_at descending.
     *
     * @return array<int, stdClass> Array of form row objects; empty array if no active forms exist.
     */
    public static function get_active(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_forms';
        return $wpdb->get_results("SELECT * FROM $table WHERE status='active' ORDER BY created_at DESC") ?: [];
    }
}

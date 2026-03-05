<?php

class WPNS_Submission_Model {
    /**
     * Insert a new submission record into the wpns_submissions table.
     *
     * Accepts an associative $data array to populate submission fields; missing keys use sensible defaults.
     *
     * @param array $data {
     *     Associative array of submission values.
     *
     *     @type int    $form_id            Form identifier (default 0).
     *     @type string $submitted_data     Serialized or raw submitted form data (default '').
     *     @type string $netsuite_payload   Payload sent to NetSuite (default '').
     *     @type string $netsuite_response  Response received from NetSuite (default '').
     *     @type mixed  $email_sent         Truthy value marks email_sent = 1, otherwise 0.
     *     @type mixed  $ns_success         Truthy value marks ns_success = 1, otherwise 0.
     *     @type string $error_message      Error message text (default '').
     *     @type string $ip_address         Client IP address (default '').
     *     @type string $user_agent         Client user agent string (default '').
     * }
     * @return int|false The newly inserted row ID on success, `false` on failure.
     */
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

    /**
     * Retrieve a submission record by its ID.
     *
     * @param int $id The submission ID to retrieve.
     * @return object|null The submission row object if found, `null` if no matching record exists.
     */
    public static function get(int $id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id);
        $row = $wpdb->get_row($sql);
        return $row ?: null;
    }

    /**
     * Retrieve submissions for a specific form, ordered by newest first, with pagination.
     *
     * @param int $form_id ID of the form to filter submissions by.
     * @param int $limit Maximum number of submissions to return. Defaults to 50.
     * @param int $offset Number of submissions to skip for pagination. Defaults to 0.
     * @return array Array of submission row objects; empty array if none found.
     */
    public static function get_by_form(int $form_id, int $limit = 50, int $offset = 0): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE form_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d", $form_id, $limit, $offset);
        return $wpdb->get_results($sql) ?: [];
    }

    /**
     * Retrieve a page of submissions ordered by newest first.
     *
     * @param int $limit  Maximum number of submissions to return.
     * @param int $offset Number of submissions to skip before starting to collect the result set.
     * @return object[] An array of submission row objects; empty array if none found.
     */
    public static function get_all(int $limit = 50, int $offset = 0): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql = $wpdb->prepare("SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $limit, $offset);
        return $wpdb->get_results($sql) ?: [];
    }

    /**
     * Count submissions that belong to a specific form.
     *
     * @param int $form_id The form's ID to filter submissions by.
     * @return int The number of submissions for the given form.
     */
    public static function count_by_form(int $form_id): int {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql = $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE form_id=%d", $form_id);
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get the total number of submissions in the wpns_submissions table.
     *
     * @return int The total count of submission records.
     */
    public static function count_all(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * Delete a submission record by its ID.
     *
     * @param int $id ID of the submission to delete.
     * @return bool `true` if the deletion succeeded, `false` otherwise.
     */
    public static function delete(int $id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql = $wpdb->prepare("DELETE FROM $table WHERE id=%d", $id);
        return $wpdb->query($sql) !== false;
    }
}

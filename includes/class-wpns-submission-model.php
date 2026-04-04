<?php

class WPNS_Submission_Model {
    /**
     * Insert a new submission record.
     *
     * @param array $data Associative array with keys: form_id, submitted_data,
     *                    netsuite_payload, netsuite_response, email_sent,
     *                    ns_success, error_message, ip_address, user_agent.
     * @return int|false Inserted row ID or false on failure.
     */
    public static function create( array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';

        $sql = $wpdb->prepare(
            "INSERT INTO $table
             (form_id, submitted_data, netsuite_payload, netsuite_response,
              email_sent, ns_success, error_message, ip_address, user_agent)
             VALUES (%d, %s, %s, %s, %d, %d, %s, %s, %s)",
            $data['form_id']          ?? 0,
            $data['submitted_data']   ?? '',
            $data['netsuite_payload'] ?? '',
            $data['netsuite_response'] ?? '',
            ! empty( $data['email_sent'] )  ? 1 : 0,
            ! empty( $data['ns_success'] )  ? 1 : 0,
            $data['error_message']    ?? '',
            $data['ip_address']       ?? '',
            $data['user_agent']       ?? ''
        );

        $result = $wpdb->query( $sql );
        if ( $result === false ) {
            wpns_log( 'Failed to create submission', [ 'form_id' => $data['form_id'] ?? 0 ] );
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Retrieve a single submission by ID.
     */
    public static function get( int $id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", $id ) );
        return $row ?: null;
    }

    /**
     * Paginated submissions for one form, newest first.
     */
    public static function get_by_form( int $form_id, int $limit = 50, int $offset = 0 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql   = $wpdb->prepare(
            "SELECT * FROM $table WHERE form_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $form_id, $limit, $offset
        );
        return $wpdb->get_results( $sql ) ?: [];
    }

    /**
     * All submissions for a form (no limit) — used for CSV export.
     */
    public static function get_all_for_form( int $form_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql   = $wpdb->prepare(
            "SELECT * FROM $table WHERE form_id=%d ORDER BY created_at DESC",
            $form_id
        );
        return $wpdb->get_results( $sql ) ?: [];
    }

    /**
     * Paginated submissions across all forms, newest first.
     */
    public static function get_all( int $limit = 50, int $offset = 0 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql   = $wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit, $offset
        );
        return $wpdb->get_results( $sql ) ?: [];
    }

    /**
     * Failed NetSuite submissions that have a payload (eligible for retry).
     */
    public static function get_ns_failed( int $limit = 100 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql   = $wpdb->prepare(
            "SELECT * FROM $table
             WHERE ns_success = 0 AND netsuite_payload != ''
             ORDER BY created_at DESC LIMIT %d",
            $limit
        );
        return $wpdb->get_results( $sql ) ?: [];
    }

    /** Count all submissions. */
    public static function count_all(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
    }

    /** Count submissions for one form. */
    public static function count_by_form( int $form_id ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        return (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE form_id=%d", $form_id )
        );
    }

    /** Count failed NetSuite submissions, optionally filtered by form. */
    public static function count_ns_failed( int $form_id = 0 ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        if ( $form_id ) {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE form_id=%d AND ns_success=0 AND netsuite_payload!=''",
                $form_id
            );
        } else {
            $sql = "SELECT COUNT(*) FROM $table WHERE ns_success=0 AND netsuite_payload!=''";
        }
        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Date string (DATETIME) of the most recent submission for a form, or null if none.
     */
    public static function get_last_submission_date( int $form_id ): ?string {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $val   = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT created_at FROM $table WHERE form_id=%d ORDER BY created_at DESC LIMIT 1",
                $form_id
            )
        );
        return $val ?: null;
    }

    /**
     * Mark a submission as successfully sent to NetSuite (used by the retry queue).
     */
    public static function mark_ns_success( int $id, string $response = '' ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        $sql   = $wpdb->prepare(
            "UPDATE $table SET ns_success=1, netsuite_response=%s, error_message='' WHERE id=%d",
            $response, $id
        );
        return $wpdb->query( $sql ) !== false;
    }

    /** Delete a single submission. */
    public static function delete( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        return $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE id=%d", $id ) ) !== false;
    }

    /** Delete all submissions belonging to a form. */
    public static function delete_by_form( int $form_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_submissions';
        return $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE form_id=%d", $form_id ) ) !== false;
    }
}

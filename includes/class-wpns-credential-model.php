<?php

class WPNS_Credential_Model {
    /**
     * Insert a new credential row.
     *
     * For NetSuite credentials the standard OAuth columns are populated.
     * For other CRM types the data is stored as encrypted JSON in config_json.
     *
     * @param array $data  {
     *   crm_type (string, default 'netsuite'),
     *   profile_name (string),
     *   // NetSuite-specific:
     *   account_id, realm, consumer_key, consumer_secret, token_key, token_secret, script_id, deploy_id
     *   // All other CRM types — arbitrary config stored in config_json.
     *   config (array)
     * }
     * @return int|false Inserted row ID, or false on failure.
     */
    public static function create( array $data ) {
        global $wpdb;
        $table    = $wpdb->prefix . 'wpns_credentials';
        $crm_type = sanitize_key( $data['crm_type'] ?? 'netsuite' );

        $config_json = '';
        if ( ! empty( $data['config'] ) && is_array( $data['config'] ) ) {
            $config_json = self::encrypt( wp_json_encode( $data['config'] ) );
        }

        $sql = $wpdb->prepare(
            "INSERT INTO $table (crm_type, profile_name, account_id, realm, consumer_key, consumer_secret, token_key, token_secret, script_id, deploy_id, config_json)
             VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
            $crm_type,
            $data['profile_name']    ?? '',
            $data['account_id']      ?? '',
            $data['realm']           ?? '',
            self::encrypt( $data['consumer_key']    ?? '' ),
            self::encrypt( $data['consumer_secret'] ?? '' ),
            self::encrypt( $data['token_key']       ?? '' ),
            self::encrypt( $data['token_secret']    ?? '' ),
            $data['script_id']       ?? '',
            $data['deploy_id']       ?? '1',
            $config_json
        );

        $result = $wpdb->query( $sql );
        return $result === false ? false : (int) $wpdb->insert_id;
    }

    /**
     * Update an existing credential row.
     *
     * @param int   $id
     * @param array $data  Same keys as create().
     * @return bool
     */
    public static function update( int $id, array $data ): bool {
        global $wpdb;
        $table    = $wpdb->prefix . 'wpns_credentials';
        $crm_type = sanitize_key( $data['crm_type'] ?? 'netsuite' );

        $config_json = '';
        if ( ! empty( $data['config'] ) && is_array( $data['config'] ) ) {
            $config_json = self::encrypt( wp_json_encode( $data['config'] ) );
        }

        $sql = $wpdb->prepare(
            "UPDATE $table SET crm_type=%s, profile_name=%s, account_id=%s, realm=%s, consumer_key=%s, consumer_secret=%s, token_key=%s, token_secret=%s, script_id=%s, deploy_id=%s, config_json=%s WHERE id=%d",
            $crm_type,
            $data['profile_name']    ?? '',
            $data['account_id']      ?? '',
            $data['realm']           ?? '',
            self::encrypt( $data['consumer_key']    ?? '' ),
            self::encrypt( $data['consumer_secret'] ?? '' ),
            self::encrypt( $data['token_key']       ?? '' ),
            self::encrypt( $data['token_secret']    ?? '' ),
            $data['script_id']       ?? '',
            $data['deploy_id']       ?? '1',
            $config_json,
            $id
        );

        return $wpdb->query( $sql ) !== false;
    }

    /**
     * Delete a credential by ID.
     *
     * @param int $id
     * @return bool
     */
    public static function delete( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_credentials';
        $sql   = $wpdb->prepare( "DELETE FROM $table WHERE id=%d", $id );
        return $wpdb->query( $sql ) !== false;
    }

    /**
     * Fetch and decrypt a single credential row.
     *
     * @param int $id
     * @return object|null
     */
    public static function get( int $id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_credentials';
        $sql   = $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", $id );
        $row   = $wpdb->get_row( $sql );
        return $row ? self::decrypt_row( $row ) : null;
    }

    /**
     * Fetch and decrypt all credential rows.
     *
     * @return object[]
     */
    public static function get_all(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_credentials';
        $rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" ) ?: [];
        return array_map( [ self::class, 'decrypt_row' ], $rows );
    }

    // ── Encryption helpers ────────────────────────────────────────────────────

    private static function decrypt_row( object $row ): object {
        $row->consumer_key    = self::decrypt( $row->consumer_key    ?? '' );
        $row->consumer_secret = self::decrypt( $row->consumer_secret ?? '' );
        $row->token_key       = self::decrypt( $row->token_key       ?? '' );
        $row->token_secret    = self::decrypt( $row->token_secret    ?? '' );

        // Decrypt config_json if present.
        if ( ! empty( $row->config_json ) ) {
            $row->config_json = self::decrypt( $row->config_json );
        }

        return $row;
    }

    public static function encrypt( string $value ): string {
        if ( $value === '' ) {
            return '';
        }
        $key       = self::crypt_key();
        $iv        = self::crypt_iv();
        $encrypted = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return $encrypted === false ? '' : base64_encode( $encrypted );
    }

    public static function decrypt( string $value ): string {
        if ( $value === '' ) {
            return '';
        }
        $raw = base64_decode( $value, true );
        if ( $raw === false ) {
            return '';
        }
        $key       = self::crypt_key();
        $iv        = self::crypt_iv();
        $decrypted = openssl_decrypt( $raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return $decrypted === false ? '' : $decrypted;
    }

    private static function crypt_key(): string {
        return hash( 'sha256', wp_salt( 'auth' ), true );
    }

    private static function crypt_iv(): string {
        return substr( hash( 'sha256', wp_salt( 'auth' ) . 'iv', true ), 0, 16 );
    }
}

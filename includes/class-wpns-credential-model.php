<?php

class WPNS_Credential_Model {
    /**
     * Insert a credential record into the wpns_credentials table, encrypting sensitive fields.
     *
     * Accepts an associative array of credential properties; `consumer_key`, `consumer_secret`,
     * `token_key`, and `token_secret` will be encrypted before storage. `deploy_id` defaults to `'1'`
     * when not provided.
     *
     * @param array $data {
     *     Credential fields.
     *
     *     @type string $profile_name
     *     @type string $account_id
     *     @type string $realm
     *     @type string $consumer_key
     *     @type string $consumer_secret
     *     @type string $token_key
     *     @type string $token_secret
     *     @type string $script_id
     *     @type string $deploy_id Optional. Defaults to '1'.
     * }
     * @return int|false Inserted row ID on success, `false` on database failure.
     */
    public static function create(array $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_credentials';

        $sql = $wpdb->prepare(
            "INSERT INTO $table (profile_name, account_id, realm, consumer_key, consumer_secret, token_key, token_secret, script_id, deploy_id)
             VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)",
            $data['profile_name'] ?? '',
            $data['account_id'] ?? '',
            $data['realm'] ?? '',
            self::encrypt($data['consumer_key'] ?? ''),
            self::encrypt($data['consumer_secret'] ?? ''),
            self::encrypt($data['token_key'] ?? ''),
            self::encrypt($data['token_secret'] ?? ''),
            $data['script_id'] ?? '',
            $data['deploy_id'] ?? '1'
        );

        $result = $wpdb->query($sql);
        if ($result === false) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update a credential record by ID with the provided data.
     *
     * Updates the credential row identified by `$id` using values from `$data`.
     * The credential fields `consumer_key`, `consumer_secret`, `token_key`, and `token_secret`
     * are encrypted before being stored.
     *
     * @param int   $id   The ID of the credential row to update.
     * @param array $data Associative array of values to set. Recognized keys:
     *                    - 'profile_name' (string)
     *                    - 'account_id' (string)
     *                    - 'realm' (string)
     *                    - 'consumer_key' (string)
     *                    - 'consumer_secret' (string)
     *                    - 'token_key' (string)
     *                    - 'token_secret' (string)
     *                    - 'script_id' (string)
     *                    - 'deploy_id' (string) Defaults to '1' if omitted.
     * @return bool `true` if the update succeeded, `false` otherwise.
     */
    public static function update(int $id, array $data): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_credentials';

        $sql = $wpdb->prepare(
            "UPDATE $table SET profile_name=%s, account_id=%s, realm=%s, consumer_key=%s, consumer_secret=%s, token_key=%s, token_secret=%s, script_id=%s, deploy_id=%s WHERE id=%d",
            $data['profile_name'] ?? '',
            $data['account_id'] ?? '',
            $data['realm'] ?? '',
            self::encrypt($data['consumer_key'] ?? ''),
            self::encrypt($data['consumer_secret'] ?? ''),
            self::encrypt($data['token_key'] ?? ''),
            self::encrypt($data['token_secret'] ?? ''),
            $data['script_id'] ?? '',
            $data['deploy_id'] ?? '1',
            $id
        );

        return $wpdb->query($sql) !== false;
    }

    /**
     * Remove a credential row by its ID from the wpns_credentials table.
     *
     * @param int $id The credential record ID to delete.
     * @return bool `true` if the row was deleted (query succeeded), `false` otherwise.
     */
    public static function delete(int $id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_credentials';
        $sql = $wpdb->prepare("DELETE FROM $table WHERE id=%d", $id);
        return $wpdb->query($sql) !== false;
    }

    /**
     * Retrieve a credential row by ID with decrypted sensitive fields.
     *
     * Fetches the row from the wpns_credentials table and returns it after decrypting
     * its credential fields.
     *
     * @param int $id The credential row ID to fetch.
     * @return object|null The credential object with decrypted consumer/token fields, or `null` if not found.
     */
    public static function get(int $id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_credentials';
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id);
        $row = $wpdb->get_row($sql);
        return $row ? self::decrypt_row($row) : null;
    }

    /**
     * Retrieve all credential records from the wpns_credentials table, decrypting sensitive fields.
     *
     * @return object[] An array of row objects with `consumer_key`, `consumer_secret`, `token_key`, and `token_secret` decrypted; returns an empty array if no records exist.
     */
    public static function get_all(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_credentials';
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC") ?: [];
        return array_map([self::class, 'decrypt_row'], $rows);
    }

    /**
     * Decrypts credential fields on a database row object.
     *
     * Decrypts `consumer_key`, `consumer_secret`, `token_key`, and `token_secret` on the provided row.
     *
     * @param object $row Row object containing encrypted credential fields.
     * @return object The same row object with the credential fields decrypted.
     */
    private static function decrypt_row(object $row): object {
        $row->consumer_key = self::decrypt($row->consumer_key ?? '');
        $row->consumer_secret = self::decrypt($row->consumer_secret ?? '');
        $row->token_key = self::decrypt($row->token_key ?? '');
        $row->token_secret = self::decrypt($row->token_secret ?? '');
        return $row;
    }

    /**
     * Encrypts a string using AES-256-CBC with a derived key and initialization vector.
     *
     * @param string $value The plaintext to encrypt. An empty string will be treated as no data.
     * @return string The base64-encoded ciphertext, or an empty string if input is empty or encryption fails.
     */
    private static function encrypt(string $value): string {
        if ($value === '') {
            return '';
        }
        $key = self::crypt_key();
        $iv = self::crypt_iv();
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            return '';
        }
        return base64_encode($encrypted);
    }

    /**
     * Decrypts a base64-encoded encrypted credential value and returns its plaintext.
     *
     * @param string $value Base64-encoded encrypted string (empty string returns empty).
     * @return string The decrypted plaintext, or an empty string if input is empty or decryption fails.
     */
    private static function decrypt(string $value): string {
        if ($value === '') {
            return '';
        }
        $raw = base64_decode($value, true);
        if ($raw === false) {
            return '';
        }
        $key = self::crypt_key();
        $iv = self::crypt_iv();
        $decrypted = openssl_decrypt($raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted === false ? '' : $decrypted;
    }

    /**
     * Produces a 32-byte encryption key derived from WordPress's auth salt.
     *
     * @return string A 32-byte binary string derived by hashing wp_salt('auth') with SHA-256.
     */
    private static function crypt_key(): string {
        return hash('sha256', wp_salt('auth'), true);
    }

    /**
     * Generate a 16-byte initialization vector derived from the WordPress auth salt.
     *
     * @return string A 16-byte binary initialization vector suitable for AES-256-CBC.
     */
    private static function crypt_iv(): string {
        return substr(hash('sha256', wp_salt('auth') . 'iv', true), 0, 16);
    }
}

<?php

class WPNS_Credential_Model {
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

    public static function delete(int $id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_credentials';
        $sql = $wpdb->prepare("DELETE FROM $table WHERE id=%d", $id);
        return $wpdb->query($sql) !== false;
    }

    public static function get(int $id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_credentials';
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id);
        $row = $wpdb->get_row($sql);
        return $row ? self::decrypt_row($row) : null;
    }

    public static function get_all(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpns_credentials';
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC") ?: [];
        return array_map([self::class, 'decrypt_row'], $rows);
    }

    private static function decrypt_row(object $row): object {
        $row->consumer_key = self::decrypt($row->consumer_key ?? '');
        $row->consumer_secret = self::decrypt($row->consumer_secret ?? '');
        $row->token_key = self::decrypt($row->token_key ?? '');
        $row->token_secret = self::decrypt($row->token_secret ?? '');
        return $row;
    }

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

    private static function crypt_key(): string {
        return hash('sha256', wp_salt('auth'), true);
    }

    private static function crypt_iv(): string {
        return substr(hash('sha256', wp_salt('auth') . 'iv', true), 0, 16);
    }
}

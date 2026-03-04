<?php

class WPNS_File_Uploader {
    private static array $allowed_mimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
    ];

    public static function upload_from_path(string $tmp_path, string $filename): string {
        if (!file_exists($tmp_path)) {
            return '';
        }

        $file_info = wp_check_filetype_and_ext($tmp_path, $filename);
        $mime = $file_info['type'] ?? '';
        if (!in_array($mime, self::$allowed_mimes, true)) {
            return '';
        }

        $file_bits = wp_upload_bits(basename($filename), null, file_get_contents($tmp_path));
        if (!empty($file_bits['error'])) {
            return '';
        }

        $attachment = [
            'post_mime_type' => $file_bits['type'],
            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $file_bits['file']);
        if (is_wp_error($attach_id)) {
            return '';
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_bits['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        $url = wp_get_attachment_url($attach_id);
        return $url ? $url : '';
    }
}

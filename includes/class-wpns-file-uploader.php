<?php

class WPNS_File_Uploader {
    /** Allowed MIME types for uploaded files. */
    private static array $allowed_mimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    /** Default maximum file size: 5 MB. Filterable via wpns_max_upload_size. */
    private static int $default_max_bytes = 5 * 1024 * 1024;

    /**
     * Upload a file from a temporary path into the WordPress media library.
     *
     * Validates existence, size, and MIME type before uploading.
     *
     * @param string $tmp_path Path to the temporary file.
     * @param string $filename Desired filename (used for type detection).
     * @return string Attachment URL on success, empty string on any failure.
     */
    public static function upload_from_path( string $tmp_path, string $filename ): string {
        if ( ! file_exists( $tmp_path ) ) {
            wpns_log( 'File upload: tmp file not found', [ 'path' => $tmp_path ] );
            return '';
        }

        // ── Size check ────────────────────────────────────────────────────
        $max_bytes = (int) apply_filters( 'wpns_max_upload_size', self::$default_max_bytes );
        $file_size = filesize( $tmp_path );
        if ( $file_size === false || $file_size > $max_bytes ) {
            wpns_log( 'File upload: file too large', [
                'file'     => $filename,
                'size'     => $file_size,
                'max'      => $max_bytes,
            ] );
            return '';
        }

        // ── MIME type check ───────────────────────────────────────────────
        $allowed_mimes = (array) apply_filters( 'wpns_allowed_upload_mimes', self::$allowed_mimes );
        $file_info     = wp_check_filetype_and_ext( $tmp_path, $filename );
        $mime          = $file_info['type'] ?? '';

        if ( ! in_array( $mime, $allowed_mimes, true ) ) {
            wpns_log( 'File upload: disallowed MIME type', [ 'file' => $filename, 'mime' => $mime ] );
            return '';
        }

        // ── Upload via WP ─────────────────────────────────────────────────
        $file_bits = wp_upload_bits( basename( $filename ), null, file_get_contents( $tmp_path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( ! empty( $file_bits['error'] ) ) {
            wpns_log( 'File upload: wp_upload_bits error', [ 'error' => $file_bits['error'] ] );
            return '';
        }

        $attachment = [
            'post_mime_type' => $file_bits['type'],
            'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment( $attachment, $file_bits['file'] );
        if ( is_wp_error( $attach_id ) ) {
            wpns_log( 'File upload: wp_insert_attachment error', [ 'error' => $attach_id->get_error_message() ] );
            return '';
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_bits['file'] );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        $url = wp_get_attachment_url( $attach_id );
        return $url ?: '';
    }
}

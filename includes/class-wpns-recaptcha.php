<?php

class WPNS_Recaptcha {
    /** Return true if a site key and secret key are both configured. */
    public static function is_enabled(): bool {
        return get_option( 'wpns_recaptcha_site_key', '' ) !== ''
            && get_option( 'wpns_recaptcha_secret_key', '' ) !== '';
    }

    public static function get_site_key(): string {
        return (string) get_option( 'wpns_recaptcha_site_key', '' );
    }

    /**
     * Verify a reCAPTCHA v3 token against Google's API.
     * Returns true if the score meets the configured threshold (default 0.5).
     * Fails open on network errors so valid users are never blocked by outages.
     */
    public static function verify( string $token ): bool {
        $secret = (string) get_option( 'wpns_recaptcha_secret_key', '' );
        if ( $secret === '' || $token === '' ) {
            return true;
        }

        $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 10,
            'body'    => [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            wpns_log( 'reCAPTCHA verify network error', [ 'error' => $response->get_error_message() ] );
            return true; // fail open
        }

        $data      = json_decode( wp_remote_retrieve_body( $response ), true );
        $threshold = (float) get_option( 'wpns_recaptcha_score_threshold', 0.5 );

        return ! empty( $data['success'] ) && ( (float) ( $data['score'] ?? 0 ) ) >= $threshold;
    }
}

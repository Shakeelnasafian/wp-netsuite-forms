<?php
/**
 * Zoho CRM connector.
 *
 * Uses OAuth 2.0 (refresh token grant) to obtain a short-lived access token,
 * then creates a record in the configured module.
 *
 * config_json keys: client_id, client_secret, refresh_token, data_center, module
 * data_center examples: com, eu, in, au, jp
 *
 * Payload passed to post() is a flat JSON object whose keys are Zoho field API names,
 * e.g. {"Last_Name":"Smith","Email":"smith@example.com"}.
 */
class WPNS_Zoho_CRM implements WPNS_CRM_Interface {
    private array $config;

    public function __construct( object $credential ) {
        $this->config = json_decode( $credential->config_json ?? '{}', true ) ?: [];
    }

    /** Exchange a refresh token for a fresh access token. */
    private function get_access_token(): string {
        $dc     = $this->config['data_center'] ?? 'com';
        $url    = "https://accounts.zoho.{$dc}/oauth/v2/token";
        $body   = [
            'grant_type'    => 'refresh_token',
            'client_id'     => $this->config['client_id']     ?? '',
            'client_secret' => $this->config['client_secret'] ?? '',
            'refresh_token' => $this->config['refresh_token'] ?? '',
        ];

        $response = wp_remote_post( $url, [
            'body'      => $body,
            'timeout'   => 20,
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['access_token'] ?? '';
    }

    public function post( string $payload ): array {
        $dc     = $this->config['data_center'] ?? 'com';
        $module = $this->config['module']      ?? 'Leads';

        $fields = json_decode( $payload, true ) ?: [];
        if ( empty( $fields ) ) {
            return [ 'success' => false, 'response' => 'Empty payload — nothing to send to Zoho.', 'http_code' => 0 ];
        }

        $access_token = $this->get_access_token();
        if ( ! $access_token ) {
            return [ 'success' => false, 'response' => 'Zoho token exchange failed. Check client credentials and refresh token.', 'http_code' => 401 ];
        }

        $url  = "https://www.zohoapis.{$dc}/crm/v2/{$module}";
        $body = wp_json_encode( [ 'data' => [ $fields ] ] );

        $response = wp_remote_post( $url, [
            'headers'   => [
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body'      => $body,
            'timeout'   => 20,
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'response' => $response->get_error_message(), 'http_code' => 0 ];
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $raw       = wp_remote_retrieve_body( $response );
        $data      = json_decode( $raw, true );
        // Zoho returns status "success" per record inside data[0].
        $success   = isset( $data['data'][0]['status'] ) && $data['data'][0]['status'] === 'success';

        return [ 'success' => $success, 'response' => $raw, 'http_code' => $http_code ];
    }

    public function test(): array {
        // A GET to /crm/v2/org validates the token without creating records.
        $dc           = $this->config['data_center'] ?? 'com';
        $access_token = $this->get_access_token();
        if ( ! $access_token ) {
            return [ 'success' => false, 'response' => 'Token exchange failed.', 'http_code' => 401 ];
        }

        $response = wp_remote_get( "https://www.zohoapis.{$dc}/crm/v2/org", [
            'headers'   => [ 'Authorization' => 'Zoho-oauthtoken ' . $access_token ],
            'timeout'   => 15,
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'response' => $response->get_error_message(), 'http_code' => 0 ];
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        return [
            'success'   => $http_code >= 200 && $http_code < 300,
            'response'  => wp_remote_retrieve_body( $response ),
            'http_code' => $http_code,
        ];
    }
}

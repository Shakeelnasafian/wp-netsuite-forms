<?php
/**
 * HubSpot CRM connector.
 *
 * Uses a Private App access token to create CRM objects.
 *
 * config_json keys: access_token, object_type
 * object_type examples: contacts, deals, companies, tickets
 *
 * Payload passed to post() is a flat JSON object whose keys are HubSpot
 * internal property names, e.g. {"email":"john@example.com","firstname":"John"}.
 */
class WPNS_HubSpot_CRM implements WPNS_CRM_Interface {
    private array $config;

    public function __construct( object $credential ) {
        $this->config = json_decode( $credential->config_json ?? '{}', true ) ?: [];
    }

    public function post( string $payload ): array {
        $access_token = $this->config['access_token'] ?? '';
        $object_type  = $this->config['object_type']  ?? 'contacts';

        if ( ! $access_token ) {
            return [ 'success' => false, 'response' => 'HubSpot access token is not configured.', 'http_code' => 0 ];
        }

        $fields = json_decode( $payload, true ) ?: [];
        if ( empty( $fields ) ) {
            return [ 'success' => false, 'response' => 'Empty payload — nothing to send to HubSpot.', 'http_code' => 0 ];
        }

        $url  = "https://api.hubapi.com/crm/v3/objects/{$object_type}";
        $body = wp_json_encode( [ 'properties' => $fields ] );

        $response = wp_remote_post( $url, [
            'headers'   => [
                'Authorization' => 'Bearer ' . $access_token,
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
        $success   = $http_code >= 200 && $http_code < 300;

        return [ 'success' => $success, 'response' => $raw, 'http_code' => $http_code ];
    }

    public function test(): array {
        $access_token = $this->config['access_token'] ?? '';
        if ( ! $access_token ) {
            return [ 'success' => false, 'response' => 'Access token not configured.', 'http_code' => 0 ];
        }

        // Use the account details endpoint as a lightweight connectivity check.
        $response = wp_remote_get( 'https://api.hubapi.com/account-info/v3/details', [
            'headers'   => [ 'Authorization' => 'Bearer ' . $access_token ],
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

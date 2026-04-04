<?php
/**
 * Odoo CRM connector.
 *
 * Uses Odoo's JSON-RPC API (v8+) to create records in any model.
 * config_json keys: url, database, username, api_key, model
 *
 * The payload passed to post() is expected to be a flat JSON object whose
 * keys are Odoo field names, e.g. {"name":"John","email":"john@example.com"}.
 */
class WPNS_Odoo_CRM implements WPNS_CRM_Interface {
    private array $config;

    public function __construct( object $credential ) {
        $this->config = json_decode( $credential->config_json ?? '{}', true ) ?: [];
    }

    /** Authenticate and return the Odoo user UID, or 0 on failure. */
    private function get_uid(): int {
        $url      = rtrim( $this->config['url'] ?? '', '/' );
        $database = $this->config['database']   ?? '';
        $username = $this->config['username']   ?? '';
        $api_key  = $this->config['api_key']    ?? '';

        $body = wp_json_encode( [
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'id'      => 1,
            'params'  => [
                'service'  => 'common',
                'method'   => 'login',
                'args'     => [ $database, $username, $api_key ],
            ],
        ] );

        $response = wp_remote_post( $url . '/jsonrpc', [
            'headers'     => [ 'Content-Type' => 'application/json' ],
            'body'        => $body,
            'timeout'     => 20,
            'sslverify'   => true,
        ] );

        if ( is_wp_error( $response ) ) {
            return 0;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return (int) ( $data['result'] ?? 0 );
    }

    public function post( string $payload ): array {
        $url      = rtrim( $this->config['url'] ?? '', '/' );
        $database = $this->config['database'] ?? '';
        $username = $this->config['username'] ?? '';
        $api_key  = $this->config['api_key']  ?? '';
        $model    = $this->config['model']    ?? 'crm.lead';

        if ( ! $url || ! $database || ! $username || ! $api_key ) {
            return [ 'success' => false, 'response' => 'Odoo credentials are incomplete.', 'http_code' => 0 ];
        }

        $uid = $this->get_uid();
        if ( ! $uid ) {
            return [ 'success' => false, 'response' => 'Odoo authentication failed.', 'http_code' => 401 ];
        }

        $fields  = json_decode( $payload, true ) ?: [];
        $body    = wp_json_encode( [
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'id'      => 2,
            'params'  => [
                'service' => 'object',
                'method'  => 'execute_kw',
                'args'    => [ $database, $uid, $api_key, $model, 'create', [ $fields ] ],
            ],
        ] );

        $response = wp_remote_post( $url . '/jsonrpc', [
            'headers'   => [ 'Content-Type' => 'application/json' ],
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
        $success   = isset( $data['result'] ) && ! isset( $data['error'] );

        return [ 'success' => $success, 'response' => $raw, 'http_code' => $http_code ];
    }

    public function test(): array {
        return $this->post( '{}' );
    }
}

<?php
/**
 * Generic webhook / REST API connector.
 *
 * Sends the form payload to any HTTP endpoint, supporting flexible auth.
 *
 * config_json keys:
 *   url         – full endpoint URL (required)
 *   method      – HTTP method: POST (default), PUT, PATCH
 *   headers_json – extra request headers as JSON object, e.g. {"X-Api-Key":"abc"}
 *   auth_type   – none | bearer | basic | api_key_header | api_key_query
 *   auth_value  – the token / "user:pass" / api-key value
 *   auth_param  – query parameter name when auth_type = api_key_query
 */
class WPNS_Webhook_CRM implements WPNS_CRM_Interface {
    private array $config;

    public function __construct( object $credential ) {
        $this->config = json_decode( $credential->config_json ?? '{}', true ) ?: [];
    }

    /** Build the request headers array. */
    private function build_headers(): array {
        $headers = [ 'Content-Type' => 'application/json' ];

        // Merge user-supplied extra headers.
        $extra = json_decode( $this->config['headers_json'] ?? '{}', true ) ?: [];
        $headers = array_merge( $headers, $extra );

        $auth_type  = $this->config['auth_type']  ?? 'none';
        $auth_value = $this->config['auth_value'] ?? '';

        switch ( $auth_type ) {
            case 'bearer':
                $headers['Authorization'] = 'Bearer ' . $auth_value;
                break;
            case 'basic':
                $headers['Authorization'] = 'Basic ' . base64_encode( $auth_value );
                break;
            case 'api_key_header':
                $param = $this->config['auth_param'] ?? 'X-Api-Key';
                $headers[ $param ] = $auth_value;
                break;
        }

        return $headers;
    }

    /** Build the target URL (optionally appending an API-key query param). */
    private function build_url(): string {
        $url       = $this->config['url']        ?? '';
        $auth_type = $this->config['auth_type']  ?? 'none';

        if ( $auth_type === 'api_key_query' ) {
            $param = urlencode( $this->config['auth_param']  ?? 'api_key' );
            $value = urlencode( $this->config['auth_value'] ?? '' );
            $sep   = strpos( $url, '?' ) !== false ? '&' : '?';
            $url  .= $sep . $param . '=' . $value;
        }

        return $url;
    }

    public function post( string $payload ): array {
        $url = $this->build_url();
        if ( ! $url ) {
            return [ 'success' => false, 'response' => 'Webhook URL is not configured.', 'http_code' => 0 ];
        }

        $method = strtoupper( $this->config['method'] ?? 'POST' );
        if ( ! in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
            $method = 'POST';
        }

        $args = [
            'method'    => $method,
            'headers'   => $this->build_headers(),
            'body'      => $payload,
            'timeout'   => 20,
            'sslverify' => true,
        ];

        $response = wp_remote_request( $url, $args );

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

    public function test(): array {
        $url = $this->build_url();
        if ( ! $url ) {
            return [ 'success' => false, 'response' => 'Webhook URL is not configured.', 'http_code' => 0 ];
        }

        // Send a HEAD request for test; fall back to POST with empty object.
        $response = wp_remote_request( $url, [
            'method'    => 'HEAD',
            'headers'   => $this->build_headers(),
            'timeout'   => 10,
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) ) {
            // Some endpoints don't support HEAD — try POST with empty body.
            return $this->post( '{}' );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        return [
            'success'   => $http_code >= 200 && $http_code < 400,
            'response'  => 'HEAD ' . $http_code,
            'http_code' => $http_code,
        ];
    }
}

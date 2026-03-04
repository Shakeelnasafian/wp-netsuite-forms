<?php

class WPNS_Netsuite_Auth {
    private string $account_id;
    private string $realm;
    private string $consumer_key;
    private string $consumer_secret;
    private string $token_key;
    private string $token_secret;

    /**
     * Initialize authentication credentials from a credential object.
     *
     * Constructs the authentication instance by reading account and OAuth credential fields
     * from the provided object; any missing fields are treated as empty strings.
     *
     * @param object $credential Object containing optional properties: `account_id`, `realm`,
     *                           `consumer_key`, `consumer_secret`, `token_key`, and `token_secret`.
     */
    public function __construct(object $credential) {
        $this->account_id = (string) ($credential->account_id ?? '');
        $this->realm = (string) ($credential->realm ?? '');
        $this->consumer_key = (string) ($credential->consumer_key ?? '');
        $this->consumer_secret = (string) ($credential->consumer_secret ?? '');
        $this->token_key = (string) ($credential->token_key ?? '');
        $this->token_secret = (string) ($credential->token_secret ?? '');
    }

    /**
     * Builds an OAuth-style Authorization header string for NetSuite requests.
     *
     * Constructs the header and signature (HMAC-SHA256) using the instance credentials,
     * the provided HTTP method and URL, and optional `script`/`deploy` parameters used
     * in signature generation.
     *
     * @param string $method The HTTP method (e.g., "GET", "POST").
     * @param string $url The full request URL.
     * @param array $url_params Associative array of URL parameters; recognizes optional keys:
     *                         - 'script' (string) — script id to include in the signature
     *                         - 'deploy' (string) — deployment id to include in the signature
     * @return string The complete Authorization header string ready for an HTTP request. 
     */
    public function build_auth_header(string $method, string $url, array $url_params): string {
        $timestamp = time();
        $nonce = uniqid(mt_rand(1, 1000));

        $script = $url_params['script'] ?? '';
        $deploy = $url_params['deploy'] ?? '';

        $base_string = $method . '&' . rawurlencode($url) . '&'
            . rawurlencode(
                'deploy=' . $deploy
                . '&oauth_consumer_key=' . rawurlencode($this->consumer_key)
                . '&oauth_nonce=' . rawurlencode($nonce)
                . '&oauth_signature_method=HMAC-SHA256'
                . '&oauth_timestamp=' . rawurlencode((string) $timestamp)
                . '&oauth_token=' . rawurlencode($this->token_key)
                . '&oauth_version=1.0'
                . '&script=' . rawurlencode($script)
            );

        $key = rawurlencode($this->consumer_secret) . '&' . rawurlencode($this->token_secret);
        $signature = rawurlencode(base64_encode(hash_hmac('sha256', $base_string, $key, true)));

        return 'Authorization: OAuth realm="' . $this->realm
            . '", oauth_consumer_key="' . $this->consumer_key
            . '", oauth_token="' . $this->token_key
            . '", oauth_timestamp="' . $timestamp
            . '", oauth_nonce="' . $nonce
            . '", oauth_signature_method="HMAC-SHA256"'
            . ', oauth_version="1.0"'
            . ', oauth_signature="' . $signature . '"';
    }
}

<?php

class WPNS_Netsuite_Client {
    private WPNS_Netsuite_Auth $auth;
    private object $credential;

    public function __construct(object $credential) {
        $this->credential = $credential;
        $this->auth = new WPNS_Netsuite_Auth($credential);
    }

    public function post(string $json_payload): array {
        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'response' => 'cURL is not available on this server.',
                'http_code' => 0,
            ];
        }

        $account_id = $this->credential->account_id ?? '';
        $script_id = $this->credential->script_id ?? '';
        $deploy_id = $this->credential->deploy_id ?? '1';

        $url = 'https://' . $account_id . '.restlets.api.netsuite.com/app/site/hosting/restlet.nl';
        $url_params = [
            'script' => $script_id,
            'deploy' => $deploy_id,
        ];

        $auth_header = $this->auth->build_auth_header('POST', $url, $url_params);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url . '?script=' . rawurlencode((string) $script_id) . '&deploy=' . rawurlencode((string) $deploy_id),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json_payload,
            CURLOPT_HTTPHEADER => [
                $auth_header,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $http_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            return [
                'success' => false,
                'response' => $error ?: 'Unknown cURL error.',
                'http_code' => $http_code,
            ];
        }

        $success = $http_code >= 200 && $http_code < 300;

        return [
            'success' => $success,
            'response' => $response,
            'http_code' => $http_code,
        ];
    }
}

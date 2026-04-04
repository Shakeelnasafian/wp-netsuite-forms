<?php
/**
 * CRM connector interface.
 *
 * Every CRM integration must implement this contract so that the form processor
 * and retry handler can work with any CRM without knowing its specifics.
 */
interface WPNS_CRM_Interface {
    /**
     * Send a JSON payload to the CRM and return a standardised result.
     *
     * @param string $payload JSON-encoded field data (flat key/value pairs or a
     *                        full template string produced by WPNS_Payload_Builder).
     * @return array{
     *     success: bool,
     *     response: string,
     *     http_code: int
     * }
     */
    public function post( string $payload ): array;

    /**
     * Perform a lightweight connectivity test (e.g. an empty/ping request).
     *
     * @return array Same shape as post().
     */
    public function test(): array;
}

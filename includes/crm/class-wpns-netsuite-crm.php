<?php
/**
 * NetSuite CRM connector — thin wrapper around the existing WPNS_Netsuite_Client.
 */
class WPNS_Netsuite_CRM implements WPNS_CRM_Interface {
    private WPNS_Netsuite_Client $client;

    public function __construct( object $credential ) {
        $this->client = new WPNS_Netsuite_Client( $credential );
    }

    public function post( string $payload ): array {
        return $this->client->post( $payload );
    }

    public function test(): array {
        return $this->client->post( '{}' );
    }
}

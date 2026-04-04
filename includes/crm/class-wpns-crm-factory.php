<?php
/**
 * Instantiates the correct CRM connector for a credential object.
 */
class WPNS_CRM_Factory {
    /**
     * Return a CRM connector that matches the credential's crm_type.
     *
     * @param object $credential Credential row (decrypted) from wpns_credentials.
     * @return WPNS_CRM_Interface
     */
    public static function make( object $credential ): WPNS_CRM_Interface {
        $type = $credential->crm_type ?? 'netsuite';

        switch ( $type ) {
            case 'odoo':
                return new WPNS_Odoo_CRM( $credential );
            case 'zoho':
                return new WPNS_Zoho_CRM( $credential );
            case 'hubspot':
                return new WPNS_HubSpot_CRM( $credential );
            case 'webhook':
                return new WPNS_Webhook_CRM( $credential );
            default:
                return new WPNS_Netsuite_CRM( $credential );
        }
    }
}

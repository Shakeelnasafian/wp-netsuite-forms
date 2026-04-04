<?php
/**
 * Plugin Name:       WP CRM Forms
 * Plugin URI:        https://example.com/wp-crm-forms
 * Description:       Build forms and sync submissions to any CRM — NetSuite, Odoo, Zoho, HubSpot, or any webhook. Includes conditional logic, spam protection, reCAPTCHA v3, CSV export, and visual field mapping.
 * Version:           1.2.0
 * Requires at least: 5.9
 * Requires PHP:      8.0
 * Author:            Shakeel Ahmad
 * Text Domain:       wp-netsuite-forms
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'WPNS_VERSION',     '1.2.0' );
define( 'WPNS_PLUGIN_FILE', __FILE__ );
define( 'WPNS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WPNS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// ── Debug-aware logger ────────────────────────────────────────────────────────
if ( ! function_exists( 'wpns_log' ) ) {
    function wpns_log( string $message, array $context = [] ): void {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }
        $line = '[WP NetSuite Forms] ' . $message;
        if ( $context ) {
            $line .= ' | ' . wp_json_encode( $context );
        }
        error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
}

// ── CRM interface (must be loaded before any CRM class is instantiated) ──────
require_once WPNS_PLUGIN_DIR . 'includes/crm/interface-wpns-crm.php';

// ── Autoloader ────────────────────────────────────────────────────────────────
spl_autoload_register( function ( string $class ): void {
    $map = [
        'WPNS_Activator'          => 'includes/class-wpns-activator.php',
        'WPNS_Loader'             => 'includes/class-wpns-loader.php',
        'WPNS_Form_Model'         => 'includes/class-wpns-form-model.php',
        'WPNS_Field_Model'        => 'includes/class-wpns-field-model.php',
        'WPNS_Credential_Model'   => 'includes/class-wpns-credential-model.php',
        'WPNS_Settings_Model'     => 'includes/class-wpns-settings-model.php',
        'WPNS_Submission_Model'   => 'includes/class-wpns-submission-model.php',
        'WPNS_Netsuite_Auth'      => 'includes/class-wpns-netsuite-auth.php',
        'WPNS_Netsuite_Client'    => 'includes/class-wpns-netsuite-client.php',
        'WPNS_Payload_Builder'    => 'includes/class-wpns-payload-builder.php',
        'WPNS_File_Uploader'      => 'includes/class-wpns-file-uploader.php',
        'WPNS_Email_Notifier'     => 'includes/class-wpns-email-notifier.php',
        'WPNS_Form_Processor'     => 'includes/class-wpns-form-processor.php',
        'WPNS_Shortcode'          => 'includes/class-wpns-shortcode.php',
        'WPNS_UTM_Tracker'        => 'includes/class-wpns-utm-tracker.php',
        'WPNS_Recaptcha'          => 'includes/class-wpns-recaptcha.php',
        'WPNS_CSV_Exporter'       => 'includes/class-wpns-csv-exporter.php',
        // CRM abstraction layer.
        'WPNS_CRM_Factory'        => 'includes/crm/class-wpns-crm-factory.php',
        'WPNS_Netsuite_CRM'       => 'includes/crm/class-wpns-netsuite-crm.php',
        'WPNS_Odoo_CRM'           => 'includes/crm/class-wpns-odoo-crm.php',
        'WPNS_Zoho_CRM'           => 'includes/crm/class-wpns-zoho-crm.php',
        'WPNS_HubSpot_CRM'        => 'includes/crm/class-wpns-hubspot-crm.php',
        'WPNS_Webhook_CRM'        => 'includes/crm/class-wpns-webhook-crm.php',
        'WPNS_Admin'              => 'admin/class-wpns-admin.php',
        'WPNS_Admin_Forms'        => 'admin/class-wpns-admin-forms.php',
        'WPNS_Admin_Form_Edit'    => 'admin/class-wpns-admin-form-edit.php',
        'WPNS_Admin_Credentials'  => 'admin/class-wpns-admin-credentials.php',
        'WPNS_Admin_Submissions'  => 'admin/class-wpns-admin-submissions.php',
    ];

    if ( isset( $map[ $class ] ) ) {
        require_once WPNS_PLUGIN_DIR . $map[ $class ];
    }
} );

// ── Activation / upgrade hooks ────────────────────────────────────────────────
register_activation_hook( __FILE__, [ 'WPNS_Activator', 'activate' ] );

add_action( 'plugins_loaded', function (): void {
    // Load translations.
    load_plugin_textdomain( 'wp-netsuite-forms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // Run DB migrations if the stored version is behind the current version.
    WPNS_Activator::maybe_upgrade();

    $loader = new WPNS_Loader();

    if ( is_admin() ) {
        $admin = new WPNS_Admin( $loader );
        $admin->init();
    }

    $shortcode = new WPNS_Shortcode( $loader );
    $shortcode->init();

    $utm = new WPNS_UTM_Tracker( $loader );
    $utm->init();

    $processor = new WPNS_Form_Processor();
    $loader->add_action( 'wp_ajax_wpns_submit_form',        $processor, 'handle_ajax' );
    $loader->add_action( 'wp_ajax_nopriv_wpns_submit_form', $processor, 'handle_ajax' );

    $loader->run();
} );

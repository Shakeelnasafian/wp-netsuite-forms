<?php
/**
 * Plugin Name: WP NetSuite Forms
 * Plugin URI:  https://example.com
 * Description: Build forms and sync submissions to NetSuite CRM with visual field mapping.
 * Version:     1.0.0
 * Author:      Shakeel Ahmad
 * Text Domain: wp-netsuite-forms
 */

defined('ABSPATH') || exit;

define('WPNS_VERSION', '1.0.0');
define('WPNS_PLUGIN_FILE', __FILE__);
define('WPNS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPNS_PLUGIN_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    $prefix = 'WPNS_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

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
        'WPNS_Admin'              => 'admin/class-wpns-admin.php',
        'WPNS_Admin_Forms'        => 'admin/class-wpns-admin-forms.php',
        'WPNS_Admin_Form_Edit'    => 'admin/class-wpns-admin-form-edit.php',
        'WPNS_Admin_Credentials'  => 'admin/class-wpns-admin-credentials.php',
        'WPNS_Admin_Submissions'  => 'admin/class-wpns-admin-submissions.php',
    ];

    if (isset($map[$class])) {
        require_once WPNS_PLUGIN_DIR . $map[$class];
    }
});

register_activation_hook(__FILE__, ['WPNS_Activator', 'activate']);

add_action('plugins_loaded', function () {
    $loader = new WPNS_Loader();

    if (is_admin()) {
        $admin = new WPNS_Admin($loader);
        $admin->init();
    }

    $shortcode = new WPNS_Shortcode($loader);
    $shortcode->init();

    $utm = new WPNS_UTM_Tracker($loader);
    $utm->init();

    $processor = new WPNS_Form_Processor();
    $loader->add_action('wp_ajax_wpns_submit_form', $processor, 'handle_ajax');
    $loader->add_action('wp_ajax_nopriv_wpns_submit_form', $processor, 'handle_ajax');

    $loader->run();
});

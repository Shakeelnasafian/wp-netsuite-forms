<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$tables = [
    $wpdb->prefix . 'wpns_forms',
    $wpdb->prefix . 'wpns_fields',
    $wpdb->prefix . 'wpns_credentials',
    $wpdb->prefix . 'wpns_form_settings',
    $wpdb->prefix . 'wpns_submissions',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

delete_option('wpns_version');

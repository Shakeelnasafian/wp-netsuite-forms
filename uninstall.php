<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
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

foreach ( $tables as $table ) {
    // Table names are plugin-generated constants, not user input — backtick-escape for safety.
    $wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

// Remove plugin options.
delete_option( 'wpns_version' );
delete_option( 'wpns_recaptcha_site_key' );
delete_option( 'wpns_recaptcha_secret_key' );
delete_option( 'wpns_recaptcha_score_threshold' );

<?php
/**
 * Debug System for Affiliate Plugin
 */

if (!defined('ABSPATH')) exit;

function cas_is_debug_enabled() {
    return (bool) get_option('cas_debug_enabled', false);
}

function cas_debug_log($message, $type = 'info') {
    if (!cas_is_debug_enabled()) {
        return;
    }
    
    global $wpdb;
    
    $table = $wpdb->prefix . 'affiliate_debug_log';
    
    // Create table if doesn't exist
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            type varchar(20) DEFAULT 'info',
            message text NOT NULL,
            user_id bigint(20) NULL,
            PRIMARY KEY (id),
            KEY type (type),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    $wpdb->insert(
        $table,
        array(
            'type' => $type,
            'message' => $message,
            'user_id' => get_current_user_id() ?: null
        ),
        array('%s', '%s', '%d')
    );
}

function cas_get_debug_logs($limit = 100) {
    global $wpdb;
    $table = $wpdb->prefix . 'affiliate_debug_log';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table ORDER BY timestamp DESC LIMIT %d",
        $limit
    ));
}

function cas_clear_debug_logs() {
    global $wpdb;
    $table = $wpdb->prefix . 'affiliate_debug_log';
    $wpdb->query("TRUNCATE TABLE $table");
}
<?php
/**
 * Fraud Detection System
 * Prevents self-referrals, duplicate accounts, and suspicious activity
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if order is a self-referral (affiliate buying with own code)
 *
 * @param int $order_id Order ID
 * @param int $affiliate_user_id Affiliate user ID
 * @return bool True if self-referral detected
 */
function cas_is_self_referral($order_id, $affiliate_user_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return false;
    }

    $customer_id = $order->get_customer_id();

    // Check if customer is the same as affiliate
    if ($customer_id && $customer_id == $affiliate_user_id) {
        cas_log_fraud_attempt($affiliate_user_id, 'self_referral', array(
            'order_id' => $order_id,
            'message' => 'Affiliate attempted to use own code'
        ));
        return true;
    }

    // Check by email if no customer ID
    $customer_email = $order->get_billing_email();
    $affiliate_user = get_userdata($affiliate_user_id);

    if ($customer_email && $affiliate_user && $customer_email === $affiliate_user->user_email) {
        cas_log_fraud_attempt($affiliate_user_id, 'self_referral', array(
            'order_id' => $order_id,
            'message' => 'Email match detected'
        ));
        return true;
    }

    return false;
}

/**
 * Check for duplicate accounts by IP, email, or device fingerprint
 *
 * @param int $user_id User ID to check
 * @return array Array of potential duplicate accounts
 */
function cas_detect_duplicate_accounts($user_id) {
    global $wpdb;

    $duplicates = array();
    $user = get_userdata($user_id);

    if (!$user) {
        return $duplicates;
    }

    // Check by email similarity
    $email_parts = explode('@', $user->user_email);
    $email_local = $email_parts[0];

    // Find similar emails (common tricks: +1, .dots, etc)
    $similar_emails = $wpdb->get_results($wpdb->prepare("
        SELECT u.ID, u.user_email
        FROM {$wpdb->users} u
        INNER JOIN {$wpdb->prefix}affiliates a ON u.ID = a.user_id
        WHERE u.ID != %d
        AND u.user_email LIKE %s
    ", $user_id, '%' . $wpdb->esc_like($email_parts[1])));

    if (!empty($similar_emails)) {
        foreach ($similar_emails as $similar) {
            $duplicates[] = array(
                'user_id' => $similar->ID,
                'email' => $similar->user_email,
                'reason' => 'Similar email domain'
            );
        }
    }

    // Check by IP address (stored in user meta)
    $current_ip = get_user_meta($user_id, 'cas_registration_ip', true);
    if ($current_ip) {
        $same_ip_users = $wpdb->get_results($wpdb->prepare("
            SELECT u.ID, u.user_email
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            INNER JOIN {$wpdb->prefix}affiliates a ON u.ID = a.user_id
            WHERE um.meta_key = 'cas_registration_ip'
            AND um.meta_value = %s
            AND u.ID != %d
        ", $current_ip, $user_id));

        if (!empty($same_ip_users)) {
            foreach ($same_ip_users as $ip_user) {
                $duplicates[] = array(
                    'user_id' => $ip_user->ID,
                    'email' => $ip_user->user_email,
                    'reason' => 'Same IP address: ' . $current_ip
                );
            }
        }
    }

    return $duplicates;
}

/**
 * Monitor suspicious activity patterns
 *
 * @param int $affiliate_id Affiliate ID
 * @return array Array of suspicious patterns detected
 */
function cas_check_suspicious_activity($affiliate_id) {
    global $wpdb;

    $alerts = array();

    // Check for unusual spike in referrals
    $recent_referrals = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->prefix}affiliate_referrals
        WHERE affiliate_id = %d
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOURS)
    ", $affiliate_id));

    $avg_referrals = $wpdb->get_var($wpdb->prepare("
        SELECT AVG(daily_count) FROM (
            SELECT COUNT(*) as daily_count
            FROM {$wpdb->prefix}affiliate_referrals
            WHERE affiliate_id = %d
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAYS)
            GROUP BY DATE(created_at)
        ) as daily_stats
    ", $affiliate_id));

    if ($recent_referrals > ($avg_referrals * 5)) {
        $alerts[] = array(
            'type' => 'spike',
            'severity' => 'high',
            'message' => "Unusual spike: {$recent_referrals} referrals in 24h (avg: {$avg_referrals})"
        );
    }

    // Check for multiple orders from same IP
    $ip_concentration = $wpdb->get_results($wpdb->prepare("
        SELECT om.meta_value as ip, COUNT(*) as count
        FROM {$wpdb->prefix}affiliate_referrals ar
        INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON ar.order_id = oi.order_id
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta om ON oi.order_item_id = om.order_item_id
        WHERE ar.affiliate_id = %d
        AND om.meta_key = '_customer_ip_address'
        AND ar.created_at > DATE_SUB(NOW(), INTERVAL 7 DAYS)
        GROUP BY om.meta_value
        HAVING count > 3
    ", $affiliate_id));

    if (!empty($ip_concentration)) {
        foreach ($ip_concentration as $ip_data) {
            $alerts[] = array(
                'type' => 'ip_concentration',
                'severity' => 'medium',
                'message' => "Multiple orders ({$ip_data->count}) from same IP: {$ip_data->ip}"
            );
        }
    }

    // Check for orders with same shipping address
    $address_duplicates = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT address_hash) as unique_addresses
        FROM (
            SELECT MD5(CONCAT(
                pm1.meta_value,
                pm2.meta_value,
                pm3.meta_value
            )) as address_hash
            FROM {$wpdb->prefix}affiliate_referrals ar
            INNER JOIN {$wpdb->postmeta} pm1 ON ar.order_id = pm1.post_id AND pm1.meta_key = '_shipping_address_1'
            INNER JOIN {$wpdb->postmeta} pm2 ON ar.order_id = pm2.post_id AND pm2.meta_key = '_shipping_city'
            INNER JOIN {$wpdb->postmeta} pm3 ON ar.order_id = pm3.post_id AND pm3.meta_key = '_shipping_postcode'
            WHERE ar.affiliate_id = %d
            AND ar.created_at > DATE_SUB(NOW(), INTERVAL 30 DAYS)
        ) as addresses
    ", $affiliate_id));

    $total_orders = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->prefix}affiliate_referrals
        WHERE affiliate_id = %d
        AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAYS)
    ", $affiliate_id));

    if ($total_orders > 5 && $address_duplicates == 1) {
        $alerts[] = array(
            'type' => 'address_duplicate',
            'severity' => 'high',
            'message' => "All {$total_orders} orders shipped to same address"
        );
    }

    return $alerts;
}

/**
 * Log fraud attempt to database
 *
 * @param int $user_id User ID who attempted fraud
 * @param string $type Type of fraud (self_referral, duplicate_account, etc)
 * @param array $data Additional data
 */
function cas_log_fraud_attempt($user_id, $type, $data = array()) {
    global $wpdb;

    $table = $wpdb->prefix . 'affiliate_fraud_log';

    // Create table if doesn't exist
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$table} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        affiliate_id bigint(20) NULL,
        fraud_type varchar(50) NOT NULL,
        severity varchar(20) DEFAULT 'medium',
        ip_address varchar(45) NULL,
        data text NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY fraud_type (fraud_type),
        KEY created_at (created_at)
    ) {$wpdb->get_charset_collate()}");

    // Get affiliate ID if exists
    $affiliate_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}affiliates WHERE user_id = %d",
        $user_id
    ));

    $wpdb->insert($table, array(
        'user_id' => $user_id,
        'affiliate_id' => $affiliate_id,
        'fraud_type' => $type,
        'severity' => $data['severity'] ?? 'medium',
        'ip_address' => cas_get_user_ip(),
        'data' => json_encode($data)
    ), array('%d', '%d', '%s', '%s', '%s', '%s'));

    // Send alert to admin if high severity
    if (isset($data['severity']) && $data['severity'] === 'high') {
        cas_send_fraud_alert_email($user_id, $type, $data);
    }
}

/**
 * Get user's IP address
 *
 * @return string IP address
 */
function cas_get_user_ip() {
    $ip = '';

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }

    return sanitize_text_field($ip);
}

/**
 * Store IP address when user registers
 *
 * @param int $user_id User ID
 */
function cas_store_registration_ip($user_id) {
    $ip = cas_get_user_ip();
    update_user_meta($user_id, 'cas_registration_ip', $ip);
    update_user_meta($user_id, 'cas_registration_date', current_time('mysql'));
}

/**
 * Send fraud alert email to admin
 *
 * @param int $user_id User ID
 * @param string $type Fraud type
 * @param array $data Additional data
 */
function cas_send_fraud_alert_email($user_id, $type, $data) {
    $user = get_userdata($user_id);
    if (!$user) return;

    $admin_email = cas_get_support_email();
    $subject = '🚨 Fraud Alert - Custom Affiliate System';

    $fraud_types = array(
        'self_referral' => 'Self-Referral Detected',
        'duplicate_account' => 'Duplicate Account Detected',
        'spike' => 'Unusual Activity Spike',
        'ip_concentration' => 'Multiple Orders from Same IP'
    );

    $fraud_title = $fraud_types[$type] ?? 'Suspicious Activity';

    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='background: #fee2e2; border-left: 4px solid #ef4444; padding: 20px; margin-bottom: 20px;'>
            <h2 style='color: #991b1b; margin: 0;'>🚨 Fraud Alert</h2>
        </div>

        <h3>{$fraud_title}</h3>

        <div style='background: #f9fafb; padding: 15px; border-radius: 6px; margin: 20px 0;'>
            <p style='margin: 5px 0;'><strong>User:</strong> {$user->display_name} ({$user->user_email})</p>
            <p style='margin: 5px 0;'><strong>User ID:</strong> {$user_id}</p>
            <p style='margin: 5px 0;'><strong>Fraud Type:</strong> {$type}</p>
            <p style='margin: 5px 0;'><strong>Time:</strong> " . current_time('Y-m-d H:i:s') . "</p>
            <p style='margin: 5px 0;'><strong>IP Address:</strong> " . cas_get_user_ip() . "</p>
        </div>

        <div style='background: #fff3cd; padding: 15px; border-radius: 6px; margin: 20px 0;'>
            <h4 style='margin: 0 0 10px 0;'>Details:</h4>
            <pre style='background: white; padding: 10px; border-radius: 4px;'>" . print_r($data, true) . "</pre>
        </div>

        <p style='margin: 30px 0;'>
            <a href='" . admin_url('admin.php?page=affiliate-system') . "' style='background: #ef4444; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>
                Review in Admin Panel →
            </a>
        </p>

        <p style='color: #666; font-size: 13px; margin-top: 30px;'>
            This is an automated alert from the Custom Affiliate System fraud detection system.
        </p>
    </body>
    </html>
    ";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($admin_email, $subject, $message, $headers);
}

/**
 * Get fraud statistics
 *
 * @return array Statistics
 */
function cas_get_fraud_stats() {
    global $wpdb;

    $table = $wpdb->prefix . 'affiliate_fraud_log';

    return array(
        'total_attempts' => $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
        'last_30_days' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAYS)"),
        'by_type' => $wpdb->get_results("
            SELECT fraud_type, COUNT(*) as count
            FROM {$table}
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAYS)
            GROUP BY fraud_type
        ", OBJECT_K),
        'high_severity' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE severity = 'high' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAYS)")
    );
}

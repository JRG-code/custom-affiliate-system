<?php
/**
 * Scheduled Automatic Payments
 * Uses WordPress Cron (100% FREE!) to automatically process payouts
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Schedule automatic payout processing
 * Runs on the schedule set by admin (e.g., 1st of month)
 */
function cas_schedule_automatic_payouts() {
    // Check if scheduled payouts are enabled
    if (!cas_is_auto_payouts_enabled()) {
        return;
    }

    $schedule = cas_get_payout_schedule();

    // If not already scheduled, schedule it
    if (!wp_next_scheduled('cas_process_automatic_payouts')) {
        wp_schedule_event(time(), $schedule, 'cas_process_automatic_payouts');
    }
}

/**
 * Process all approved payouts automatically
 * This is called by WP Cron
 */
function cas_process_automatic_payouts() {
    global $wpdb;

    cas_debug_log('Auto-payout: Starting automatic payout processing', 'info');

    // Get all pending payouts that are approved
    $payouts = $wpdb->get_results("
        SELECT p.*, a.user_id, u.user_email, u.display_name
        FROM {$wpdb->prefix}affiliate_payouts p
        INNER JOIN {$wpdb->prefix}affiliates a ON p.affiliate_id = a.id
        INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
        WHERE p.status = 'approved'
    ");

    if (empty($payouts)) {
        cas_debug_log('Auto-payout: No approved payouts found', 'info');
        return;
    }

    cas_debug_log('Auto-payout: Found ' . count($payouts) . ' approved payouts to process', 'info');

    $processed = 0;
    $failed = 0;

    foreach ($payouts as $payout) {
        // Mark as paid
        $success = $wpdb->update(
            $wpdb->prefix . 'affiliate_payouts',
            array(
                'status' => 'paid',
                'paid_date' => current_time('mysql')
            ),
            array('id' => $payout->id),
            array('%s', '%s'),
            array('%d')
        );

        if ($success) {
            // Update affiliate's paid commission
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}affiliates
                SET paid_commission = paid_commission + %f,
                    unpaid_commission = unpaid_commission - %f
                WHERE id = %d
            ", $payout->amount, $payout->amount, $payout->affiliate_id));

            // Send notification email
            cas_send_payout_processed_email($payout);

            cas_debug_log("Auto-payout: Processed payout #{$payout->id} for {$payout->display_name} - €{$payout->amount}", 'info');
            $processed++;
        } else {
            cas_debug_log("Auto-payout: Failed to process payout #{$payout->id}", 'error');
            $failed++;
        }
    }

    // Send summary to admin
    cas_send_admin_payout_summary($processed, $failed, $payouts);

    cas_debug_log("Auto-payout: Completed. Processed: {$processed}, Failed: {$failed}", 'info');
}

/**
 * Send email to affiliate when payout is processed
 */
function cas_send_payout_processed_email($payout) {
    $user = get_userdata($payout->user_id);
    if (!$user) return;

    $subject = 'Your Commission Payment Has Been Processed!';

    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 20px;'>
            <h2 style='margin: 0; color: white;'>✅ Payment Processed!</h2>
        </div>

        <p>Hello <strong>{$user->display_name}</strong>,</p>

        <p>Great news! Your commission payment has been processed and is on its way to you.</p>

        <div style='background: #f0fdf4; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3 style='margin: 0 0 15px 0; color: #065f46;'>Payment Details</h3>
            <p style='margin: 5px 0;'><strong>Amount:</strong> €" . number_format($payout->amount, 2) . "</p>
            <p style='margin: 5px 0;'><strong>Method:</strong> {$payout->method}</p>
            <p style='margin: 5px 0;'><strong>Processed Date:</strong> " . current_time('F j, Y') . "</p>
            <p style='margin: 5px 0;'><strong>Payout ID:</strong> #{$payout->id}</p>
        </div>

        <p>Your payment should arrive within 3-5 business days depending on your payment method.</p>

        <p style='margin: 30px 0;'>
            <a href='" . wc_get_account_endpoint_url('affiliate-dashboard') . "' style='background: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>
                View Dashboard →
            </a>
        </p>

        <p>Keep up the great work!</p>

        <p style='color: #666; font-size: 13px; margin-top: 30px;'>
            Questions? Contact us at " . cas_get_support_email() . "
        </p>
    </body>
    </html>
    ";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($user->user_email, $subject, $message, $headers);
}

/**
 * Send admin summary of automatic payouts
 */
function cas_send_admin_payout_summary($processed, $failed, $payouts) {
    $admin_email = cas_get_support_email();
    $subject = '📊 Automatic Payout Processing Summary - ' . current_time('F j, Y');

    $total_amount = array_sum(array_column($payouts, 'amount'));

    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Automatic Payout Processing Complete</h2>

        <div style='background: #f0fdf4; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3 style='margin: 0 0 15px 0;'>Summary</h3>
            <p style='margin: 5px 0;'><strong>Successfully Processed:</strong> {$processed} payouts</p>
            <p style='margin: 5px 0;'><strong>Failed:</strong> {$failed} payouts</p>
            <p style='margin: 5px 0;'><strong>Total Amount:</strong> €" . number_format($total_amount, 2) . "</p>
            <p style='margin: 5px 0;'><strong>Date:</strong> " . current_time('F j, Y H:i:s') . "</p>
        </div>

        <h3>Processed Payouts:</h3>
        <table style='width: 100%; border-collapse: collapse;'>
            <thead>
                <tr style='background: #f9fafb;'>
                    <th style='padding: 10px; text-align: left; border-bottom: 2px solid #e5e7eb;'>Affiliate</th>
                    <th style='padding: 10px; text-align: left; border-bottom: 2px solid #e5e7eb;'>Amount</th>
                    <th style='padding: 10px; text-align: left; border-bottom: 2px solid #e5e7eb;'>Method</th>
                </tr>
            </thead>
            <tbody>";

    foreach ($payouts as $payout) {
        $message .= "
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>{$payout->display_name}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>€" . number_format($payout->amount, 2) . "</td>
                    <td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>{$payout->method}</td>
                </tr>";
    }

    $message .= "
            </tbody>
        </table>

        <p style='margin: 30px 0;'>
            <a href='" . admin_url('admin.php?page=affiliate-payouts') . "' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>
                View All Payouts →
            </a>
        </p>
    </body>
    </html>
    ";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($admin_email, $subject, $message, $headers);
}

/**
 * Check if auto-payouts are enabled
 */
function cas_is_auto_payouts_enabled() {
    $options = get_option('cas_settings', array());
    return isset($options['general']['auto_payouts_enabled']) ? (bool) $options['general']['auto_payouts_enabled'] : false;
}

/**
 * Get payout schedule
 */
function cas_get_payout_schedule() {
    $options = get_option('cas_settings', array());
    $schedule = isset($options['general']['payout_schedule']) ? $options['general']['payout_schedule'] : 'monthly';

    // Return WP Cron schedule name
    $schedules = array(
        'weekly' => 'weekly',
        'biweekly' => 'twicemonthly',
        'monthly' => 'monthly'
    );

    return $schedules[$schedule] ?? 'monthly';
}

/**
 * Get next payout date
 */
function cas_get_next_payout_date() {
    $next_run = wp_next_scheduled('cas_process_automatic_payouts');

    if ($next_run) {
        return date('F j, Y', $next_run);
    }

    return 'Not scheduled';
}

/**
 * Manually trigger payout processing (for testing or manual run)
 */
function cas_manual_trigger_payouts() {
    cas_process_automatic_payouts();
}

/**
 * Add custom WP Cron schedules
 */
function cas_add_cron_schedules($schedules) {
    // Add biweekly schedule
    $schedules['twicemonthly'] = array(
        'interval' => 1209600, // 14 days
        'display' => __('Twice Monthly')
    );

    // Add monthly schedule (1st of each month)
    $schedules['monthly'] = array(
        'interval' => 2635200, // 30.5 days average
        'display' => __('Monthly')
    );

    return $schedules;
}
add_filter('cron_schedules', 'cas_add_cron_schedules');

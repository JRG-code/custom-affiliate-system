<?php
/**
 * Admin Payouts Page
 * Manage payout requests and approvals
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle payout approval
if (isset($_POST['approve_payout']) && check_admin_referer('approve_payout_nonce')) {
    $payout_id = intval($_POST['payout_id']);
    
    // Update payout status
    $wpdb->update(
        $wpdb->prefix . 'affiliate_payouts',
        array(
            'status' => 'paid',
            'paid_date' => current_time('mysql')
        ),
        array('id' => $payout_id),
        array('%s', '%s'),
        array('%d')
    );
    
    // Get payout details
    $payout = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}affiliate_payouts WHERE id = %d",
        $payout_id
    ));
    
    if ($payout) {
        // Update affiliate balances
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}affiliates 
            SET unpaid_commission = unpaid_commission - %f,
                paid_commission = paid_commission + %f
            WHERE id = %d",
            $payout->amount,
            $payout->amount,
            $payout->affiliate_id
        ));
        
        // Mark referrals as paid
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}affiliate_referrals 
            SET status = 'paid' 
            WHERE affiliate_id = %d AND status = 'unpaid'
            LIMIT 999999",
            $payout->affiliate_id
        ));
        
        // Get affiliate info for email
        $affiliate_info = $wpdb->get_row($wpdb->prepare(
            "SELECT u.user_email, u.display_name 
            FROM {$wpdb->prefix}affiliates a 
            JOIN {$wpdb->users} u ON a.user_id = u.ID 
            WHERE a.id = %d",
            $payout->affiliate_id
        ));
        
        // Send confirmation email
        if ($affiliate_info) {
            $to = $affiliate_info->user_email;
            $subject = '✅ Payout Processed!';
            $message = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2>Great News! 💰</h2>
                <p>Hi {$affiliate_info->display_name},</p>
                <p>Your payout has been processed successfully!</p>
                
                <div style='background: #d1fae5; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; color: #065f46;'><strong>Amount:</strong> $" . number_format($payout->amount, 2) . "</p>
                    <p style='margin: 10px 0 0 0; color: #065f46;'><strong>Method:</strong> " . ucfirst($payout->method) . "</p>
                </div>
                
                <p>The payment should arrive in your account within 1-3 business days.</p>
                
                <p><a href='" . home_url('/affiliate-dashboard/') . "'>View Your Dashboard</a></p>
                
                <p>Keep up the great work! 🚀</p>
            </body>
            </html>
            ";
            
            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($to, $subject, $message, $headers);
        }
    }
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Payout approved and processed. Affiliate has been notified via email.</p></div>';
}

// Handle payout rejection
if (isset($_POST['reject_payout']) && check_admin_referer('reject_payout_nonce')) {
    $payout_id = intval($_POST['payout_id']);
    
    $wpdb->update(
        $wpdb->prefix . 'affiliate_payouts',
        array('status' => 'rejected'),
        array('id' => $payout_id),
        array('%s'),
        array('%d')
    );
    
    echo '<div class="notice notice-warning is-dismissible"><p>Payout request rejected.</p></div>';
}

// Get all payout requests
$payouts = $wpdb->get_results("
    SELECT p.*, a.affiliate_code, u.display_name, u.user_email
    FROM {$wpdb->prefix}affiliate_payouts p
    LEFT JOIN {$wpdb->prefix}affiliates a ON p.affiliate_id = a.id
    LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
    ORDER BY 
        FIELD(p.status, 'pending', 'paid', 'rejected'),
        p.request_date DESC
");

// Count pending payouts
$pending_count = $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_payouts WHERE status = 'pending'
");

// Sum of pending amounts
$pending_total = $wpdb->get_var("
    SELECT SUM(amount) FROM {$wpdb->prefix}affiliate_payouts WHERE status = 'pending'
");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">💰 Payout Requests</h1>
    <a href="<?php echo admin_url('admin.php?page=affiliate-system'); ?>" class="page-title-action">Back to Overview</a>
    <hr class="wp-header-end">
    
    <?php if ($pending_count > 0): ?>
    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0; font-weight: 600; color: #92400e;">
            ⚠️ You have <strong><?php echo $pending_count; ?></strong> pending payout request<?php echo $pending_count > 1 ? 's' : ''; ?> totaling <strong>$<?php echo number_format($pending_total, 2); ?></strong>
        </p>
    </div>
    <?php endif; ?>
    
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 20px;">
        <?php if (empty($payouts)): ?>
        <div style="text-align: center; padding: 60px 20px;">
            <p style="font-size: 48px; margin: 0;">💳</p>
            <p style="font-size: 18px; color: #374151; margin: 15px 0 5px 0; font-weight: 600;">No payout requests yet</p>
            <p style="color: #6b7280; margin: 0;">Payout requests will appear here when affiliates request withdrawals.</p>
        </div>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 12%;">Request Date</th>
                    <th style="width: 20%;">Affiliate</th>
                    <th style="width: 10%;">Amount</th>
                    <th style="width: 10%;">Method</th>
                    <th style="width: 25%;">Payment Details</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 13%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payouts as $p): ?>
                <tr style="background: <?php echo $p->status == 'pending' ? '#fffbeb' : ($p->status == 'rejected' ? '#fef2f2' : ''); ?>;">
                    <td>
                        <?php echo date('M j, Y', strtotime($p->request_date)); ?><br>
                        <small style="color: #999;"><?php echo date('g:i A', strtotime($p->request_date)); ?></small>
                    </td>
                    <td>
                        <strong><?php echo esc_html($p->display_name); ?></strong><br>
                        <small style="color: #666;"><?php echo esc_html($p->user_email); ?></small><br>
                        <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 11px;"><?php echo $p->affiliate_code; ?></code>
                    </td>
                    <td>
                        <strong style="font-size: 18px; color: #10b981;">
                            $<?php echo number_format($p->amount, 2); ?>
                        </strong>
                    </td>
                    <td>
                        <span style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; background: #e0e7ff; color: #3730a3;">
                            <?php echo ucfirst($p->method); ?>
                        </span>
                    </td>
                    <td>
                        <div style="background: #f9fafb; padding: 10px; border-radius: 4px; font-size: 13px; max-height: 60px; overflow-y: auto;">
                            <?php echo nl2br(esc_html($p->notes)); ?>
                        </div>
                    </td>
                    <td>
                        <span style="padding: 6px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: <?php echo $p->status == 'paid' ? '#d1fae5' : ($p->status == 'pending' ? '#fef3c7' : '#fee2e2'); ?>; color: <?php echo $p->status == 'paid' ? '#065f46' : ($p->status == 'pending' ? '#92400e' : '#991b1b'); ?>;">
                            <?php 
                            if ($p->status == 'paid') {
                                echo '✓ Paid';
                            } elseif ($p->status == 'pending') {
                                echo '⏳ Pending';
                            } else {
                                echo '✗ Rejected';
                            }
                            ?>
                        </span>
                        <?php if ($p->status == 'paid' && $p->paid_date): ?>
                            <br><small style="color: #999;">on <?php echo date('M j', strtotime($p->paid_date)); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p->status == 'pending'): ?>
                        <form method="post" style="display: inline-block; margin-bottom: 5px;">
                            <?php wp_nonce_field('approve_payout_nonce'); ?>
                            <input type="hidden" name="payout_id" value="<?php echo $p->id; ?>">
                            <button type="submit" name="approve_payout" class="button button-primary button-small" onclick="return confirm('Approve this payout?\n\nMake sure you have processed the payment via <?php echo ucfirst($p->method); ?> before approving.');" style="width: 100%;">
                                ✅ Approve
                            </button>
                        </form>
                        <form method="post" style="display: inline-block;">
                            <?php wp_nonce_field('reject_payout_nonce'); ?>
                            <input type="hidden" name="payout_id" value="<?php echo $p->id; ?>">
                            <button type="submit" name="reject_payout" class="button button-small" onclick="return confirm('Reject this payout request?');" style="width: 100%;">
                                ✗ Reject
                            </button>
                        </form>
                        <?php else: ?>
                        <span style="color: #10b981; font-weight: 600;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; margin-top: 30px; border-left: 4px solid #0284c7;">
        <h3 style="margin: 0 0 10px 0; color: #0c4a6e;">💡 Payout Process Tips</h3>
        <ul style="margin: 10px 0; padding-left: 20px; color: #0c4a6e;">
            <li><strong>Before approving:</strong> Process the actual payment via PayPal, bank transfer, or selected method</li>
            <li><strong>Verify details:</strong> Double-check payment information before sending money</li>
            <li><strong>Keep records:</strong> Save transaction IDs and payment confirmations</li>
            <li><strong>Communication:</strong> Affiliates receive automatic email confirmation when approved</li>
            <li><strong>Tax reporting:</strong> Export payout data regularly for accounting purposes</li>
        </ul>
    </div>
</div>
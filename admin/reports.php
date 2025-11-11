<?php
/**
 * Admin Reports Page
 * Combined: Payouts, Code Change Requests, Analytics, and Data Exports
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// ========== PAYOUTS HANDLING ==========

// Handle payout approval
if (isset($_POST['approve_payout']) && check_admin_referer('approve_payout_nonce')) {
    $payout_id = intval($_POST['payout_id']);

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

    $payout = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}affiliate_payouts WHERE id = %d",
        $payout_id
    ));

    if ($payout) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}affiliates
            SET unpaid_commission = unpaid_commission - %f,
                paid_commission = paid_commission + %f
            WHERE id = %d",
            $payout->amount,
            $payout->amount,
            $payout->affiliate_id
        ));

        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}affiliate_referrals
            SET status = 'paid'
            WHERE affiliate_id = %d AND status = 'unpaid'
            LIMIT 999999",
            $payout->affiliate_id
        ));

        $affiliate_info = $wpdb->get_row($wpdb->prepare(
            "SELECT u.user_email, u.display_name
            FROM {$wpdb->prefix}affiliates a
            JOIN {$wpdb->users} u ON a.user_id = u.ID
            WHERE a.id = %d",
            $payout->affiliate_id
        ));

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

    echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Payout approved and processed.</p></div>';
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

// ========== CODE CHANGES HANDLING ==========

if (isset($_POST['action']) && isset($_POST['request_id'])) {
    check_admin_referer('cas_code_change_action');

    $request_id = intval($_POST['request_id']);
    $action = sanitize_text_field($_POST['action']);
    $admin_notes = isset($_POST['admin_notes']) ? sanitize_textarea_field($_POST['admin_notes']) : '';

    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}affiliate_code_changes WHERE id = %d",
        $request_id
    ));

    if ($request) {
        if ($action === 'approve') {
            $wpdb->update(
                $wpdb->prefix . 'affiliates',
                array('affiliate_code' => $request->new_code),
                array('id' => $request->affiliate_id),
                array('%s'),
                array('%d')
            );

            $coupon_code = strtolower($request->new_code);
            $old_coupon_code = strtolower($request->old_code);

            $old_coupon_id = wc_get_coupon_id_by_code($old_coupon_code);
            if ($old_coupon_id) {
                wp_update_post(array(
                    'ID' => $old_coupon_id,
                    'post_title' => $coupon_code,
                    'post_name' => $coupon_code
                ));
            }

            $wpdb->update(
                $wpdb->prefix . 'affiliate_code_changes',
                array(
                    'status' => 'approved',
                    'reviewed_at' => current_time('mysql'),
                    'reviewed_by' => get_current_user_id(),
                    'admin_notes' => $admin_notes
                ),
                array('id' => $request_id),
                array('%s', '%s', '%d', '%s'),
                array('%d')
            );

            echo '<div class="notice notice-success is-dismissible"><p>✅ Code change approved and applied!</p></div>';

        } elseif ($action === 'deny') {
            $wpdb->update(
                $wpdb->prefix . 'affiliate_code_changes',
                array(
                    'status' => 'denied',
                    'reviewed_at' => current_time('mysql'),
                    'reviewed_by' => get_current_user_id(),
                    'admin_notes' => $admin_notes
                ),
                array('id' => $request_id),
                array('%s', '%s', '%d', '%s'),
                array('%d')
            );

            echo '<div class="notice notice-success is-dismissible"><p>✅ Code change request denied.</p></div>';
        }
    }
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

$pending_payouts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_payouts WHERE status = 'pending'");
$pending_payout_total = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}affiliate_payouts WHERE status = 'pending'");

// Get all code change requests
$code_requests = $wpdb->get_results("
    SELECT
        ccr.*,
        a.affiliate_code as current_code,
        u.display_name,
        u.user_email,
        a.tier
    FROM {$wpdb->prefix}affiliate_code_changes ccr
    INNER JOIN {$wpdb->prefix}affiliates a ON ccr.affiliate_id = a.id
    INNER JOIN {$wpdb->prefix}users u ON a.user_id = u.ID
    ORDER BY
        CASE ccr.status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'denied' THEN 3
        END,
        ccr.requested_at DESC
");

$pending_code_changes = 0;
foreach ($code_requests as $req) {
    if ($req->status === 'pending') $pending_code_changes++;
}

?>

<div class="wrap">
    <h1 class="wp-heading-inline">📊 Reports & Management</h1>
    <a href="<?php echo admin_url('admin.php?page=affiliate-system'); ?>" class="page-title-action">Back to Overview</a>
    <hr class="wp-header-end">

    <div class="cas-reports-section">
        <h2>💰 Payout Requests
            <?php if ($pending_payouts > 0): ?>
                <span class="update-plugins count-<?php echo $pending_payouts; ?>"><span class="update-count"><?php echo $pending_payouts; ?></span></span>
            <?php endif; ?>
        </h2>

        <?php if ($pending_payouts > 0): ?>
        <div class="cas-reports-warning">
            <p>⚠️ You have <strong><?php echo $pending_payouts; ?></strong> pending request<?php echo $pending_payouts > 1 ? 's' : ''; ?> totaling <strong>€<?php echo number_format($pending_payout_total, 2); ?></strong></p>
        </div>
        <?php endif; ?>

        <?php if (empty($payouts)): ?>
        <div class="cas-reports-empty">
            <p>No payout requests yet.</p>
        </div>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Affiliate</th>
                    <th>Code</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Requested</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payouts as $payout):
                    $status_class = 'cas-status-badge ';
                    if ($payout->status === 'pending') $status_class .= 'cas-status-pending';
                    elseif ($payout->status === 'paid') $status_class .= 'cas-status-paid';
                    elseif ($payout->status === 'rejected') $status_class .= 'cas-status-rejected';
                    elseif ($payout->status === 'approved') $status_class .= 'cas-status-approved';
                ?>
                <tr class="<?php echo $payout->status === 'pending' ? 'cas-row-pending' : ''; ?>">
                    <td>
                        <strong><?php echo esc_html($payout->display_name); ?></strong><br>
                        <small><?php echo esc_html($payout->user_email); ?></small>
                    </td>
                    <td><code><?php echo esc_html($payout->affiliate_code); ?></code></td>
                    <td><strong>€<?php echo number_format($payout->amount, 2); ?></strong></td>
                    <td><?php echo esc_html(ucfirst($payout->method)); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($payout->request_date)); ?></td>
                    <td>
                        <span class="<?php echo $status_class; ?>">
                            <?php echo esc_html($payout->status); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($payout->status === 'pending'): ?>
                            <form method="post" class="cas-inline-form">
                                <?php wp_nonce_field('approve_payout_nonce'); ?>
                                <input type="hidden" name="payout_id" value="<?php echo $payout->id; ?>">
                                <button type="submit" name="approve_payout" class="button button-primary button-small">✓ Approve</button>
                            </form>
                            <form method="post" class="cas-inline-form">
                                <?php wp_nonce_field('reject_payout_nonce'); ?>
                                <input type="hidden" name="payout_id" value="<?php echo $payout->id; ?>">
                                <button type="submit" name="reject_payout" class="button button-small" onclick="return confirm('Reject this payout?')">✗ Reject</button>
                            </form>
                        <?php else: ?>
                            <span style="color: #9ca3af;">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="cas-reports-section">
        <h2>🔄 Code Change Requests
            <?php if ($pending_code_changes > 0): ?>
                <span class="update-plugins count-<?php echo $pending_code_changes; ?>"><span class="update-count"><?php echo $pending_code_changes; ?></span></span>
            <?php endif; ?>
        </h2>

        <?php if (empty($code_requests)): ?>
        <div class="cas-reports-empty">
            <p>No code change requests yet.</p>
        </div>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Affiliate</th>
                    <th>Current Code</th>
                    <th>Requested Code</th>
                    <th>Tier</th>
                    <th>Reason</th>
                    <th>Requested</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($code_requests as $request):
                    $status_class = 'cas-status-badge ';
                    if ($request->status === 'pending') $status_class .= 'cas-status-pending';
                    elseif ($request->status === 'approved') $status_class .= 'cas-status-approved';
                    elseif ($request->status === 'denied') $status_class .= 'cas-status-rejected';
                ?>
                <tr class="<?php echo $request->status === 'pending' ? 'cas-row-code-pending' : ''; ?>">
                    <td>
                        <strong><?php echo esc_html($request->display_name); ?></strong><br>
                        <small><?php echo esc_html($request->user_email); ?></small>
                    </td>
                    <td>
                        <code class="cas-code-old"><?php echo esc_html($request->old_code); ?></code>
                    </td>
                    <td>
                        <code class="cas-code-new"><?php echo esc_html($request->new_code); ?></code>
                    </td>
                    <td><?php echo esc_html(cas_get_tier_name($request->tier)); ?></td>
                    <td>
                        <details>
                            <summary style="cursor: pointer; color: #667eea;">View Reason</summary>
                            <p style="margin: 10px 0 0 0; padding: 10px; background: #f9fafb; border-radius: 4px;">
                                <?php echo nl2br(esc_html($request->reason)); ?>
                            </p>
                        </details>
                    </td>
                    <td><?php echo date('d/m/Y H:i', strtotime($request->requested_at)); ?></td>
                    <td>
                        <span class="<?php echo $status_class; ?>">
                            <?php echo esc_html($request->status); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($request->status === 'pending'): ?>
                            <button onclick="openReviewModal(<?php echo $request->id; ?>, 'approve', '<?php echo esc_js($request->new_code); ?>')" class="button button-primary button-small">✓ Approve</button>
                            <button onclick="openReviewModal(<?php echo $request->id; ?>, 'deny', '<?php echo esc_js($request->new_code); ?>')" class="button button-small">✗ Deny</button>
                        <?php else: ?>
                            <span style="color: #9ca3af;">Reviewed</span>
                            <?php if ($request->admin_notes): ?>
                                <br><small><?php echo esc_html($request->admin_notes); ?></small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<!-- Review Modal for Code Changes -->
<div id="reviewModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%;">
        <h2 id="modalTitle">Review Request</h2>
        <p id="modalMessage"></p>

        <form method="post">
            <?php wp_nonce_field('cas_code_change_action'); ?>
            <input type="hidden" name="request_id" id="modal_request_id">
            <input type="hidden" name="action" id="modal_action">

            <div style="margin: 20px 0;">
                <label for="admin_notes" style="display: block; margin-bottom: 8px; font-weight: 600;">Admin Notes (optional)</label>
                <textarea name="admin_notes" id="admin_notes" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Add any notes about this decision..."></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeReviewModal()" class="button">Cancel</button>
                <button type="submit" id="modalSubmitBtn" class="button button-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReviewModal(requestId, action, newCode) {
    const modal = document.getElementById('reviewModal');
    const title = document.getElementById('modalTitle');
    const message = document.getElementById('modalMessage');
    const submitBtn = document.getElementById('modalSubmitBtn');

    document.getElementById('modal_request_id').value = requestId;
    document.getElementById('modal_action').value = action;

    if (action === 'approve') {
        title.textContent = 'Approve Code Change';
        message.textContent = `Are you sure you want to approve the code change to "${newCode}"? The affiliate's current code and WooCommerce coupon will be updated.`;
        submitBtn.textContent = 'Approve Change';
        submitBtn.className = 'button button-primary';
    } else {
        title.textContent = 'Deny Code Change';
        message.textContent = 'Are you sure you want to deny this code change request?';
        submitBtn.textContent = 'Deny Request';
        submitBtn.className = 'button';
    }

    modal.style.display = 'flex';
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
    document.getElementById('admin_notes').value = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReviewModal();
    }
});

document.getElementById('reviewModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReviewModal();
    }
});
</script>

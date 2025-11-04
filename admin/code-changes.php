<?php
/**
 * Code Change Requests Admin Page
 * Review and approve/deny affiliate code change requests
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

// Handle approve/deny actions
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
            // Update affiliate code
            $wpdb->update(
                $wpdb->prefix . 'affiliates',
                array('affiliate_code' => $request->new_code),
                array('id' => $request->affiliate_id),
                array('%s'),
                array('%d')
            );

            // Update WooCommerce coupon
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

            // Update request status
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
            // Update request status
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

// Get all code change requests
$requests = $wpdb->get_results("
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

$pending_count = 0;
foreach ($requests as $req) {
    if ($req->status === 'pending') $pending_count++;
}

?>

<div class="wrap">
    <h1>Code Change Requests
        <?php if ($pending_count > 0): ?>
            <span class="update-plugins count-<?php echo $pending_count; ?>"><span class="update-count"><?php echo $pending_count; ?></span></span>
        <?php endif; ?>
    </h1>

    <?php if (empty($requests)): ?>
        <div style="background: white; padding: 60px; text-align: center; border-radius: 12px; margin-top: 20px;">
            <div style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;">📝</div>
            <h2>No Code Change Requests</h2>
            <p style="color: #666;">Affiliate code change requests will appear here.</p>
        </div>
    <?php else: ?>
        <div style="background: white; padding: 20px; border-radius: 12px; margin-top: 20px;">
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
                    <?php foreach ($requests as $request): ?>
                    <tr style="<?php echo $request->status === 'pending' ? 'background: #fff3cd;' : ''; ?>">
                        <td>
                            <strong><?php echo esc_html($request->display_name); ?></strong><br>
                            <small><?php echo esc_html($request->user_email); ?></small>
                        </td>
                        <td>
                            <code style="background: #e5e7eb; padding: 4px 8px; border-radius: 4px; font-weight: 600;">
                                <?php echo esc_html($request->old_code); ?>
                            </code>
                        </td>
                        <td>
                            <code style="background: #d1fae5; padding: 4px 8px; border-radius: 4px; font-weight: 600; color: #065f46;">
                                <?php echo esc_html($request->new_code); ?>
                            </code>
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
                            <?php
                            $status_colors = array(
                                'pending' => '#fbbf24',
                                'approved' => '#10b981',
                                'denied' => '#ef4444'
                            );
                            $color = $status_colors[$request->status] ?? '#6b7280';
                            ?>
                            <span style="background: <?php echo $color; ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
                                <?php echo esc_html($request->status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($request->status === 'pending'): ?>
                                <button onclick="openReviewModal(<?php echo $request->id; ?>, 'approve', '<?php echo esc_js($request->new_code); ?>')" class="button button-primary button-small">
                                    ✓ Approve
                                </button>
                                <button onclick="openReviewModal(<?php echo $request->id; ?>, 'deny', '<?php echo esc_js($request->new_code); ?>')" class="button button-small">
                                    ✗ Deny
                                </button>
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
        </div>
    <?php endif; ?>
</div>

<!-- Review Modal -->
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

// Close modal on ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReviewModal();
    }
});

// Close modal on backdrop click
document.getElementById('reviewModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReviewModal();
    }
});
</script>

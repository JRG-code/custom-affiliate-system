<?php
/**
 * Admin Overview Page
 * Shows all affiliates, stats, and management options
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle tier upgrade
if (isset($_POST['upgrade_affiliate']) && check_admin_referer('upgrade_affiliate_nonce')) {
    $user_id = intval($_POST['user_id']);
    $new_tier = sanitize_text_field($_POST['new_tier']);
    $rates = array('tier_1' => 10, 'tier_2' => 15, 'ambassador' => 20);
    
    // Update affiliate tier and rate
    $wpdb->update(
        $wpdb->prefix . 'affiliates',
        array(
            'tier' => $new_tier,
            'commission_rate' => $rates[$new_tier]
        ),
        array('user_id' => $user_id),
        array('%s', '%f'),
        array('%d')
    );
    
    // Update WooCommerce coupon discount amount
    $affiliate = $wpdb->get_row($wpdb->prepare(
        "SELECT affiliate_code FROM {$wpdb->prefix}affiliates WHERE user_id = %d",
        $user_id
    ));
    
    if ($affiliate) {
        $coupon = new WC_Coupon(strtolower($affiliate->affiliate_code));
        if ($coupon->get_id()) {
            update_post_meta($coupon->get_id(), 'coupon_amount', $rates[$new_tier]);
        }
    }
    
    // Update user role
    $user = new WP_User($user_id);
    $user->set_role('subscriber');
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Affiliate upgraded to ' . ucfirst(str_replace('_', ' ', $new_tier)) . ' (' . $rates[$new_tier] . '%).</p></div>';
}

// Get all affiliates
$affiliates = $wpdb->get_results("
    SELECT a.*, u.user_email, u.display_name, u.user_login
    FROM {$wpdb->prefix}affiliates a
    LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
    ORDER BY a.created_at DESC
");

// Get summary stats
$stats = $wpdb->get_row("
    SELECT 
        COUNT(*) as total_affiliates,
        SUM(total_sales) as total_revenue,
        SUM(total_commission) as total_commissions,
        SUM(unpaid_commission) as unpaid_commissions,
        SUM(paid_commission) as paid_commissions
    FROM {$wpdb->prefix}affiliates
");

// Get active affiliates count
$active_count = $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->prefix}affiliates WHERE status = 'active'
");

// Get total sales count
$total_sales_count = $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_referrals
");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">🎯 Affiliate System Overview</h1>
    <a href="<?php echo admin_url('admin.php?page=affiliate-payouts'); ?>" class="page-title-action">View Payouts</a>
    <a href="<?php echo admin_url('admin.php?page=affiliate-reports'); ?>" class="page-title-action">View Reports</a>
    <hr class="wp-header-end">
    
    <!-- Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin: 30px 0;">
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #667eea;">
            <h3 style="margin: 0; color: #666; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Total Affiliates</h3>
            <p style="font-size: 36px; font-weight: bold; margin: 10px 0 5px 0; color: #667eea;"><?php echo number_format($stats->total_affiliates); ?></p>
            <p style="margin: 0; font-size: 13px; color: #999;"><?php echo $active_count; ?> active</p>
        </div>
        
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #10b981;">
            <h3 style="margin: 0; color: #666; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Total Revenue</h3>
            <p style="font-size: 36px; font-weight: bold; margin: 10px 0 5px 0; color: #10b981;">$<?php echo number_format($stats->total_revenue, 2); ?></p>
            <p style="margin: 0; font-size: 13px; color: #999;"><?php echo $total_sales_count; ?> sales</p>
        </div>
        
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #f59e0b;">
            <h3 style="margin: 0; color: #666; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Total Commissions</h3>
            <p style="font-size: 36px; font-weight: bold; margin: 10px 0 5px 0; color: #f59e0b;">$<?php echo number_format($stats->total_commissions, 2); ?></p>
            <p style="margin: 0; font-size: 13px; color: #999;">All time</p>
        </div>
        
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #ef4444;">
            <h3 style="margin: 0; color: #666; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Unpaid Commissions</h3>
            <p style="font-size: 36px; font-weight: bold; margin: 10px 0 5px 0; color: #ef4444;">$<?php echo number_format($stats->unpaid_commissions, 2); ?></p>
            <p style="margin: 0; font-size: 13px; color: #999;">Paid: $<?php echo number_format($stats->paid_commissions, 2); ?></p>
        </div>
    </div>
    
    <h2>All Affiliates</h2>
    
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 20%;">Affiliate</th>
                    <th style="width: 12%;">Promo Code</th>
                    <th style="width: 12%;">Tier</th>
                    <th style="width: 8%;">Rate</th>
                    <th style="width: 8%;">Sales</th>
                    <th style="width: 12%;">Total Earned</th>
                    <th style="width: 12%;">Unpaid</th>
                    <th style="width: 8%;">Status</th>
                    <th style="width: 8%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($affiliates)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <p style="font-size: 48px; margin: 0;">📭</p>
                        <p style="margin: 10px 0 0 0; color: #666;">No affiliates yet. Share your registration page to get started!</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($affiliates as $aff): 
                        $sales_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_referrals WHERE affiliate_id = %d",
                            $aff->id
                        ));
                        
                        $tier_labels = array(
                            'tier_1' => 'Tier I',
                            'tier_2' => 'Tier II',
                            'ambassador' => 'Ambassador'
                        );
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($aff->display_name); ?></strong><br>
                            <small style="color: #666;"><?php echo esc_html($aff->user_email); ?></small>
                        </td>
                        <td>
                            <code style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px; font-weight: bold; color: #667eea;">
                                <?php echo esc_html($aff->affiliate_code); ?>
                            </code>
                        </td>
                        <td>
                            <span style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; background: <?php echo $aff->tier == 'tier_1' ? '#dbeafe' : ($aff->tier == 'tier_2' ? '#fef3c7' : '#fce7f3'); ?>; color: <?php echo $aff->tier == 'tier_1' ? '#1e40af' : ($aff->tier == 'tier_2' ? '#92400e' : '#831843'); ?>;">
                                <?php echo $tier_labels[$aff->tier]; ?>
                            </span>
                        </td>
                        <td><strong><?php echo $aff->commission_rate; ?>%</strong></td>
                        <td><?php echo $sales_count; ?></td>
                        <td><strong>$<?php echo number_format($aff->total_commission, 2); ?></strong></td>
                        <td style="color: #10b981; font-weight: bold;">$<?php echo number_format($aff->unpaid_commission, 2); ?></td>
                        <td>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: <?php echo $aff->status == 'active' ? '#d1fae5' : '#fee2e2'; ?>; color: <?php echo $aff->status == 'active' ? '#065f46' : '#991b1b'; ?>;">
                                <?php echo ucfirst($aff->status); ?>
                            </span>
                        </td>
                        <td>
                            <button onclick="showUpgradeModal(<?php echo $aff->user_id; ?>, '<?php echo esc_js($aff->display_name); ?>', '<?php echo $aff->tier; ?>')" class="button button-small">
                                Upgrade
                            </button>
                            <button onclick="toggleStatus(<?php echo $aff->id; ?>, '<?php echo $aff->status; ?>')" class="button button-small">
                                <?php echo $aff->status == 'active' ? 'Pause' : 'Activate'; ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Upgrade Modal -->
<div id="upgradeModal" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 999999; backdrop-filter: blur(4px);">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 12px; max-width: 450px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <h2 style="margin: 0 0 20px 0; color: #1e293b;">Upgrade Affiliate Tier</h2>
        <form method="post">
            <?php wp_nonce_field('upgrade_affiliate_nonce'); ?>
            <input type="hidden" name="user_id" id="upgrade_user_id">
            
            <p style="margin: 0 0 20px 0;">
                <strong>Affiliate:</strong> <span id="upgrade_affiliate_name" style="color: #667eea;"></span>
            </p>
            
            <p style="margin: 0 0 10px 0;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151;">Select New Tier:</label>
                <select name="new_tier" id="upgrade_tier_select" style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 15px;">
                    <option value="tier_1">Tier I - 10% Commission</option>
                    <option value="tier_2">Tier II Influencer - 15% Commission</option>
                    <option value="ambassador">Ambassador - 20% Commission</option>
                </select>
            </p>
            
            <div style="background: #f0f9ff; padding: 15px; border-radius: 6px; margin: 20px 0;">
                <p style="margin: 0; font-size: 13px; color: #0369a1;">
                    <strong>ℹ️ Note:</strong> This will update their commission rate and coupon discount amount.
                </p>
            </div>
            
            <p style="margin: 20px 0 0 0; display: flex; gap: 10px;">
                <button type="submit" name="upgrade_affiliate" class="button button-primary button-large" style="flex: 1;">
                    ✅ Upgrade Affiliate
                </button>
                <button type="button" onclick="closeUpgradeModal()" class="button button-large">
                    Cancel
                </button>
            </p>
        </form>
    </div>
</div>

<script>
function showUpgradeModal(userId, name, currentTier) {
    document.getElementById('upgrade_user_id').value = userId;
    document.getElementById('upgrade_affiliate_name').textContent = name;
    document.getElementById('upgrade_tier_select').value = currentTier;
    document.getElementById('upgradeModal').style.display = 'block';
}

function closeUpgradeModal() {
    document.getElementById('upgradeModal').style.display = 'none';
}

function toggleStatus(affId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'activate' : 'pause';
    
    if (!confirm(`Are you sure you want to ${action} this affiliate?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'toggle_affiliate_status');
    formData.append('affiliate_id', affId);
    formData.append('status', newStatus);
    
    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        location.reload();
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUpgradeModal();
    }
});

// Close modal on backdrop click
document.getElementById('upgradeModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeUpgradeModal();
    }
});
</script>
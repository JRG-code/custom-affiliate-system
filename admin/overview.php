<?php
/**
 * Admin Overview Page
 * Shows all affiliates, stats, and management options
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle Add Affiliate form submission
if (isset($_POST['add_affiliate']) && check_admin_referer('cas_add_affiliate')) {
    $user_id = intval($_POST['user_id']);
    $tier = sanitize_text_field($_POST['tier']);
    $custom_code = !empty($_POST['custom_code']) ? strtoupper(sanitize_text_field($_POST['custom_code'])) : '';

    $errors = array();

    // Validate user exists
    $user = get_userdata($user_id);
    if (!$user) {
        $errors[] = 'User not found';
    }

    // Check if user is already an affiliate
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}affiliates WHERE user_id = %d",
        $user_id
    ));

    if ($exists) {
        $errors[] = 'This user is already an affiliate';
    }

    // Validate tier
    $available_tiers = cas_get_available_tiers();
    if (!isset($available_tiers[$tier])) {
        $errors[] = 'Invalid tier selected';
    }

    if (empty($errors)) {
        // Generate affiliate code
        if (!empty($custom_code)) {
            $affiliate_code = $custom_code;

            // Check if code already exists
            $code_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}affiliates WHERE affiliate_code = %s",
                $affiliate_code
            ));

            if ($code_exists) {
                $errors[] = 'This affiliate code is already in use';
            }
        } else {
            // Auto-generate code
            $username = $user->user_login;

            if (preg_match('/^user\d+$/', $username)) {
                $display_name = $user->display_name;
                if (!empty($display_name)) {
                    $first_name = explode(' ', $display_name)[0];
                    $username = sanitize_user($first_name);
                }
            }

            $affiliate_code = strtoupper($username) . '5';
            $counter = 1;
            $original_code = $affiliate_code;

            while ($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}affiliates WHERE affiliate_code = %s",
                $affiliate_code
            ))) {
                $affiliate_code = $original_code . $counter;
                $counter++;
            }
        }

        if (empty($errors)) {
            // Get tier settings
            $commission_rate = cas_get_tier_setting($tier, 'commission');
            $coupon_discount = cas_get_tier_setting($tier, 'coupon_discount');
            $status = 'active';

            // Insert affiliate
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'affiliates',
                array(
                    'user_id' => $user_id,
                    'affiliate_code' => $affiliate_code,
                    'commission_rate' => $commission_rate,
                    'tier' => $tier,
                    'status' => $status,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%f', '%s', '%s', '%s')
            );

            if ($inserted) {
                // Create WooCommerce coupon
                $coupon = array(
                    'post_title' => strtolower($affiliate_code),
                    'post_content' => '',
                    'post_status' => 'publish',
                    'post_author' => 1,
                    'post_type' => 'shop_coupon'
                );

                $coupon_id = wp_insert_post($coupon);

                update_post_meta($coupon_id, 'discount_type', 'fixed_cart');
                update_post_meta($coupon_id, 'coupon_amount', $coupon_discount);
                update_post_meta($coupon_id, 'individual_use', 'yes');
                update_post_meta($coupon_id, 'usage_limit', '');
                update_post_meta($coupon_id, 'usage_limit_per_user', '1');
                update_post_meta($coupon_id, 'expiry_date', '');
                update_post_meta($coupon_id, 'free_shipping', 'no');
                update_post_meta($coupon_id, '_affiliate_user_id', $user_id);

                // Send welcome email
                $tier_name = cas_get_tier_name_v2($tier);
                $tier_badge = cas_get_tier_badge_v2($tier);

                $to = $user->user_email;
                $subject = 'Welcome to Our Affiliate Program!';
                $message = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Welcome to the Influencer Program!</h2>
                    <p>Hello <strong>{$user->display_name}</strong>,</p>
                    <p>You've been added to our affiliate program!</p>
                    <div style='background: #f0f0f0; padding: 20px; margin: 20px 0; text-align: center;'>
                        <h1 style='color: #667eea; font-size: 36px;'>{$affiliate_code}</h1>
                        <p>Your unique promotional code</p>
                    </div>
                    <div style='background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <p style='margin: 5px 0;'><strong>Your Tier:</strong> {$tier_badge} {$tier_name}</p>
                        <p style='margin: 5px 0;'><strong>Commission Rate:</strong> {$commission_rate}%</p>
                        <p style='margin: 5px 0;'><strong>Customer Discount:</strong> {$coupon_discount}€</p>
                    </div>
                    <p>You earn {$commission_rate}% commission on every sale made with your code!</p>
                    <p><a href='" . wc_get_account_endpoint_url('affiliate-dashboard') . "' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block;'>Go to Dashboard</a></p>
                </body>
                </html>
                ";

                $headers = array('Content-Type: text/html; charset=UTF-8');
                wp_mail($to, $subject, $message, $headers);

                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>✅ Success!</strong> ' . esc_html($user->display_name) . ' has been added as an affiliate.</p>';
                echo '<p><strong>Affiliate Code:</strong> ' . esc_html($affiliate_code) . '</p>';
                echo '<p><strong>Tier:</strong> ' . esc_html($tier_name) . ' (' . $commission_rate . '% commission)</p>';
                echo '<p>Welcome email sent to ' . esc_html($user->user_email) . '</p>';
                echo '</div>';
            } else {
                $errors[] = 'Database error: Could not insert affiliate';
            }
        }
    }

    if (!empty($errors)) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>❌ Errors:</strong></p><ul>';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }
}

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

// Get all users who are NOT affiliates yet (for Add Affiliate form)
$existing_affiliate_users = $wpdb->get_col("SELECT user_id FROM {$wpdb->prefix}affiliates");
$placeholder = implode(',', array_fill(0, count($existing_affiliate_users), '%d'));

if (count($existing_affiliate_users) > 0) {
    $query = $wpdb->prepare(
        "SELECT ID, user_login, user_email, display_name
        FROM {$wpdb->users}
        WHERE ID NOT IN ($placeholder)
        ORDER BY ID ASC",
        ...$existing_affiliate_users
    );
} else {
    $query = "SELECT ID, user_login, user_email, display_name
        FROM {$wpdb->users}
        ORDER BY ID ASC";
}

$non_affiliate_users = $wpdb->get_results($query);
$total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
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

    <!-- Add Affiliate Form -->
    <?php if (!empty($non_affiliate_users)): ?>
    <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 30px 0;">
        <h2 style="margin: 0 0 20px 0;">➕ Add New Affiliate</h2>

        <form method="post" action="">
            <?php wp_nonce_field('cas_add_affiliate'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="user_id">Select User *</label>
                    </th>
                    <td>
                        <select name="user_id" id="user_id" class="regular-text" required>
                            <option value="">Choose a user...</option>
                            <?php foreach ($non_affiliate_users as $user): ?>
                            <option value="<?php echo $user->ID; ?>">
                                <?php echo esc_html($user->display_name); ?>
                                (<?php echo esc_html($user->user_email); ?>)
                                - ID: <?php echo $user->ID; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php echo count($non_affiliate_users); ?> of <?php echo $total_users; ?> users available to add
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="tier">Affiliate Tier *</label>
                    </th>
                    <td>
                        <select name="tier" id="tier" class="regular-text" required>
                            <?php
                            $available_tiers = cas_get_available_tiers();
                            foreach ($available_tiers as $tier_id => $tier_data):
                                $settings = cas_get_all_tier_settings($tier_id);
                            ?>
                            <option value="<?php echo esc_attr($tier_id); ?>">
                                <?php echo esc_html($tier_data['badge']); ?>
                                <?php echo esc_html($tier_data['name']); ?>
                                (<?php echo $settings['commission']; ?>% commission)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Choose the tier for this affiliate</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="custom_code">Custom Affiliate Code</label>
                    </th>
                    <td>
                        <input type="text" name="custom_code" id="custom_code" class="regular-text" placeholder="Optional - auto-generated if empty" pattern="[A-Z0-9_]+" style="text-transform: uppercase;">
                        <p class="description">Leave empty to auto-generate. Must be uppercase letters/numbers only.</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="add_affiliate" class="button button-primary button-large">
                    ➕ Add as Affiliate
                </button>
            </p>
        </form>
    </div>
    <?php else: ?>
    <div style="background: #d1fae5; padding: 20px; border-radius: 8px; margin: 30px 0; border-left: 4px solid #10b981;">
        <p style="margin: 0;">✅ <strong>All users are already affiliates!</strong> Every WordPress user on this site has been added to the affiliate program.</p>
    </div>
    <?php endif; ?>

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
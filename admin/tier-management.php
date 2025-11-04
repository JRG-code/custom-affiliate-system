<?php
/**
 * Tier Management Page (Pro Only)
 * Create, edit, and delete custom affiliate tiers
 */

if (!defined('ABSPATH')) exit;

// Check if Pro is active
if (!cas_is_pro_active()) {
    echo cas_upgrade_notice('Custom Tier Management');
    return;
}

global $wpdb;

// Handle Create New Tier
if (isset($_POST['create_tier']) && check_admin_referer('cas_create_tier')) {
    try {
        $tier_id = sanitize_key($_POST['tier_id']);
        $tier_name = sanitize_text_field($_POST['tier_name']);
        $tier_badge = !empty($_POST['tier_badge']) ? sanitize_text_field($_POST['tier_badge']) : '⭐';
        $commission = floatval($_POST['commission']);
        $min_payout = floatval($_POST['min_payout']);
        $payment_days = intval($_POST['payment_days']);
        $coupon_discount = floatval($_POST['coupon_discount']);
        $allow_code_edit = isset($_POST['allow_code_edit']) ? 1 : 0;
        
        $errors = array();
        
        // Validation
        if (empty($tier_id)) {
            $errors[] = 'Tier ID is required';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $tier_id)) {
            $errors[] = 'Tier ID must be lowercase letters, numbers and underscores only';
        }
        
        if (empty($tier_name)) {
            $errors[] = 'Tier name is required';
        }
        
        if ($commission < 0 || $commission > 100) {
            $errors[] = 'Commission must be between 0-100%';
        }
        
        // Check if tier ID already exists (check both custom tiers and default tiers)
        $default_tiers = array('tier_1', 'tier_2', 'ambassador');
        if (in_array($tier_id, $default_tiers)) {
            $errors[] = 'Cannot use reserved tier ID. Please choose a different ID.';
        }
        
        $existing_tiers = get_option('cas_custom_tiers', array());
        if (isset($existing_tiers[$tier_id])) {
            $errors[] = 'Tier ID already exists. Please choose a different ID.';
        }
        
        if (empty($errors)) {
            // Create tier data
            $tier_data = array(
                'name' => $tier_name,
                'badge' => $tier_badge,
                'commission' => $commission,
                'min_payout' => $min_payout,
                'payment_days' => $payment_days,
                'coupon_discount' => $coupon_discount,
                'allow_code_edit' => $allow_code_edit,
                'created_at' => current_time('mysql')
            );
            
            // Add to custom tiers
            $existing_tiers[$tier_id] = $tier_data;
            update_option('cas_custom_tiers', $existing_tiers);
            
            // Also update cas_settings for compatibility
            $cas_settings = get_option('cas_settings', array());
            if (!is_array($cas_settings)) {
                $cas_settings = array();
            }
            
            $cas_settings[$tier_id] = array(
                'commission' => $commission,
                'min_payout' => $min_payout,
                'payment_days' => $payment_days,
                'coupon_discount' => $coupon_discount,
                'allow_code_edit' => $allow_code_edit
            );
            update_option('cas_settings', $cas_settings);
            
            echo '<div class="notice notice-success is-dismissible"><p>✅ Tier "' . esc_html($tier_name) . '" created successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p><strong>❌ Errors:</strong></p><ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
        }
    } catch (Exception $e) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>❌ Error creating tier:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
    }
}

// Handle Delete Tier
if (isset($_POST['delete_tier']) && check_admin_referer('cas_delete_tier')) {
    try {
        $tier_id = sanitize_key($_POST['tier_id']);
        
        // Prevent deleting default tiers
        $protected_tiers = array('tier_1', 'tier_2', 'ambassador');
        if (in_array($tier_id, $protected_tiers)) {
            echo '<div class="notice notice-error is-dismissible"><p>❌ Cannot delete default tier.</p></div>';
        } else {
            // Check if any affiliates are using this tier
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}affiliates WHERE tier = %s",
                $tier_id
            ));
            
            if ($count > 0) {
                echo '<div class="notice notice-error is-dismissible"><p>❌ Cannot delete tier. ' . $count . ' affiliate(s) are using this tier. Please move them to another tier first.</p></div>';
            } else {
                // Delete from custom tiers
                $custom_tiers = get_option('cas_custom_tiers', array());
                if (isset($custom_tiers[$tier_id])) {
                    $deleted_name = $custom_tiers[$tier_id]['name'];
                    unset($custom_tiers[$tier_id]);
                    update_option('cas_custom_tiers', $custom_tiers);
                    
                    // Remove from settings
                    $cas_settings = get_option('cas_settings', array());
                    if (is_array($cas_settings) && isset($cas_settings[$tier_id])) {
                        unset($cas_settings[$tier_id]);
                        update_option('cas_settings', $cas_settings);
                    }
                    
                    echo '<div class="notice notice-success is-dismissible"><p>✅ Tier "' . esc_html($deleted_name) . '" deleted successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>❌ Tier not found.</p></div>';
                }
            }
        }
    } catch (Exception $e) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>❌ Error deleting tier:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
    }
}

// Get all tiers (default + custom)
$all_tiers = cas_get_available_tiers();
$custom_tiers_only = get_option('cas_custom_tiers', array());

// Get usage count for each tier
$tier_usage = array();
foreach (array_keys($all_tiers) as $tier_id) {
    $tier_usage[$tier_id] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}affiliates WHERE tier = %s",
        $tier_id
    ));
}

?>

<div class="wrap">
    <h1>🎯 Tier Management <?php echo cas_pro_badge(); ?></h1>
    
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin: 20px 0;">
        <h2 style="margin: 0 0 10px 0; color: white;">Create Custom Tiers</h2>
        <p style="margin: 0; opacity: 0.9;">Design unlimited tiers with custom names, badges, and commission structures to match your affiliate program.</p>
    </div>
    
    <!-- Existing Tiers -->
    <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2>Existing Tiers</h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
            <?php foreach ($all_tiers as $tier_id => $tier_data): 
                $is_default = in_array($tier_id, array('tier_1', 'tier_2', 'ambassador'));
                $settings = cas_get_all_tier_settings($tier_id);
                $usage = $tier_usage[$tier_id] ?? 0;
            ?>
            <div style="border: 2px solid <?php echo cas_get_tier_color($tier_id); ?>; padding: 20px; border-radius: 12px; position: relative;">
                <?php if ($is_default): ?>
                <span style="position: absolute; top: 10px; right: 10px; background: #e5e7eb; color: #6b7280; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">DEFAULT</span>
                <?php endif; ?>
                
                <div style="font-size: 36px; margin-bottom: 10px;"><?php echo esc_html($tier_data['badge']); ?></div>
                <h3 style="margin: 0 0 10px 0; color: <?php echo cas_get_tier_color($tier_id); ?>;"><?php echo esc_html($tier_data['name']); ?></h3>
                
                <div style="margin: 15px 0; padding: 15px; background: #f9fafb; border-radius: 6px;">
                    <p style="margin: 5px 0; font-size: 13px;"><strong>Commission:</strong> <?php echo $settings['commission']; ?>%</p>
                    <p style="margin: 5px 0; font-size: 13px;"><strong>Min Payout:</strong> <?php echo $settings['min_payout'] > 0 ? $settings['min_payout'] . '€' : 'No minimum'; ?></p>
                    <p style="margin: 5px 0; font-size: 13px;"><strong>Payment:</strong> <?php echo $settings['payment_days']; ?> days</p>
                    <p style="margin: 5px 0; font-size: 13px;"><strong>Coupon:</strong> <?php echo $settings['coupon_discount']; ?>€</p>
                    <p style="margin: 5px 0; font-size: 13px;"><strong>Code Edit:</strong> <?php echo !empty($settings['allow_code_edit']) ? '✓ Allowed (1x/month)' : '✗ Not allowed'; ?></p>
                </div>
                
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                    <p style="margin: 0; font-size: 13px; color: #666;">
                        <strong><?php echo $usage; ?></strong> affiliate<?php echo $usage != 1 ? 's' : ''; ?> using this tier
                    </p>
                </div>
                
                <?php if (!$is_default): ?>
                <div style="margin-top: 15px;">
                    <form method="post" style="display: inline;" onsubmit="return confirm('Delete this tier? This cannot be undone.');">
                        <?php wp_nonce_field('cas_delete_tier'); ?>
                        <input type="hidden" name="tier_id" value="<?php echo esc_attr($tier_id); ?>">
                        <button type="submit" name="delete_tier" class="button" style="width: 100%;">
                            🗑️ Delete Tier
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <!-- Add New Tier Card -->
            <div style="border: 2px dashed #d1d5db; padding: 20px; border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 300px; cursor: pointer;" onclick="document.getElementById('createTierForm').scrollIntoView({behavior: 'smooth'});">
                <div style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;">➕</div>
                <h3 style="margin: 0; color: #6b7280;">Create New Tier</h3>
                <p style="margin: 10px 0 0 0; color: #9ca3af; font-size: 13px;">Click to add a custom tier</p>
            </div>
        </div>
    </div>
    
    <!-- Create New Tier Form -->
    <div id="createTierForm" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2>➕ Create New Tier</h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('cas_create_tier'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="tier_id">Tier ID *</label>
                    </th>
                    <td>
                        <input type="text" name="tier_id" id="tier_id" class="regular-text" required pattern="[a-z0-9_]+" placeholder="e.g., platinum">
                        <p class="description">Unique identifier (lowercase, no spaces). Example: platinum, diamond, vip</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="tier_name">Tier Name *</label>
                    </th>
                    <td>
                        <input type="text" name="tier_name" id="tier_name" class="regular-text" required placeholder="e.g., Platinum">
                        <p class="description">Display name shown to users</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="tier_badge">Badge/Emoji</label>
                    </th>
                    <td>
                        <input type="text" name="tier_badge" id="tier_badge" class="small-text" placeholder="💫" maxlength="5">
                        <p class="description">Emoji or icon to represent this tier (optional)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="commission">Commission Rate (%) *</label>
                    </th>
                    <td>
                        <input type="number" name="commission" id="commission" class="regular-text" required step="0.01" min="0" max="100" value="25">
                        <p class="description">Percentage commission on sales (0-100%)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="min_payout">Minimum Payout (€)</label>
                    </th>
                    <td>
                        <input type="number" name="min_payout" id="min_payout" class="regular-text" step="1" min="0" value="0">
                        <p class="description">Minimum amount required to request payout (0 = no minimum)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="payment_days">Payment Timeline (days)</label>
                    </th>
                    <td>
                        <input type="number" name="payment_days" id="payment_days" class="regular-text" step="1" min="1" value="3">
                        <p class="description">Number of days to process payment after approval</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="coupon_discount">Coupon Discount (€)</label>
                    </th>
                    <td>
                        <input type="number" name="coupon_discount" id="coupon_discount" class="regular-text" step="0.01" min="0" value="5">
                        <p class="description">Discount amount customer receives when using affiliate coupon</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="allow_code_edit">Allow Code Editing</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="allow_code_edit" id="allow_code_edit" value="1">
                            Allow affiliates to edit their code once per month
                        </label>
                        <p class="description">If enabled, affiliates in this tier can change their promotional code once every 30 days</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="create_tier" class="button button-primary button-large">
                    ✨ Create Tier
                </button>
            </p>
        </form>
    </div>
    
    <!-- Tips -->
    <div style="background: #f0f9ff; border-left: 4px solid #0284c7; padding: 20px; border-radius: 6px; margin: 20px 0;">
        <h3 style="margin: 0 0 10px 0; color: #0c4a6e;">💡 Tips for Creating Tiers</h3>
        <ul style="margin: 10px 0; padding-left: 20px; color: #0c4a6e;">
            <li><strong>Progressive Structure:</strong> Higher tiers should offer better commission rates and benefits</li>
            <li><strong>Clear Names:</strong> Use recognizable tier names like Platinum, Diamond, Elite, VIP</li>
            <li><strong>Meaningful Badges:</strong> Choose emojis that represent the tier level (💎 💫 🌟 👑 ⭐)</li>
            <li><strong>Strategic Minimums:</strong> Lower minimum payouts for higher tiers as a reward</li>
            <li><strong>Fast Processing:</strong> Reduce payment timelines for premium tiers</li>
            <li><strong>Migration Path:</strong> Plan how affiliates can progress from lower to higher tiers</li>
        </ul>
    </div>
    
    <!-- Tier ID Examples -->
    <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <h3 style="margin: 0 0 15px 0;">📋 Suggested Tier IDs & Names</h3>
        <table class="wp-list-table widefat">
            <thead>
                <tr>
                    <th>Tier ID</th>
                    <th>Display Name</th>
                    <th>Badge</th>
                    <th>Suggested Commission</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>platinum</code></td>
                    <td>Platinum</td>
                    <td>💫</td>
                    <td>25%</td>
                </tr>
                <tr>
                    <td><code>diamond</code></td>
                    <td>Diamond</td>
                    <td>💎</td>
                    <td>30%</td>
                </tr>
                <tr>
                    <td><code>elite</code></td>
                    <td>Elite</td>
                    <td>🌟</td>
                    <td>35%</td>
                </tr>
                <tr>
                    <td><code>vip</code></td>
                    <td>VIP</td>
                    <td>👑</td>
                    <td>40%</td>
                </tr>
                <tr>
                    <td><code>partner</code></td>
                    <td>Partner</td>
                    <td>🤝</td>
                    <td>50%</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
// Auto-format tier ID input
document.getElementById('tier_id').addEventListener('input', function() {
    this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
});

// Preview badge in real-time
document.getElementById('tier_badge').addEventListener('input', function() {
    console.log('Badge preview:', this.value);
});
</script>
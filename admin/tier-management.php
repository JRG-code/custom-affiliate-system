<?php
/**
 * Tier Management Page
 * Edit existing tiers (FREE) and create custom tiers (PRO)
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

// Display success message from transient (after save)
if (isset($_GET['saved']) && $_GET['saved'] == '1') {
    $saved_tier = get_transient('cas_tier_saved');
    if ($saved_tier) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Success!</strong> Tier "' . esc_html($saved_tier) . '" has been updated successfully.</p></div>';
        delete_transient('cas_tier_saved');
    }
}

// Display success message from transient (after create)
if (isset($_GET['created']) && $_GET['created'] == '1') {
    $created_tier = get_transient('cas_tier_created');
    if ($created_tier) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Success!</strong> Tier "' . esc_html($created_tier) . '" has been created successfully.</p></div>';
        delete_transient('cas_tier_created');
    }
}

// Display success message from transient (after delete)
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $deleted_tier = get_transient('cas_tier_deleted');
    if ($deleted_tier) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Success!</strong> Tier "' . esc_html($deleted_tier) . '" has been deleted successfully.</p></div>';
        delete_transient('cas_tier_deleted');
    }
}

// Handle Edit Tier
if (isset($_POST['edit_tier']) && check_admin_referer('cas_edit_tier')) {
    try {
        $tier_id = sanitize_key($_POST['tier_id']); // This is the tier being edited (read-only)
        $tier_name = sanitize_text_field($_POST['tier_name']);
        $tier_badge = !empty($_POST['tier_badge']) ? sanitize_text_field($_POST['tier_badge']) : '⭐';
        $commission = floatval($_POST['commission']);
        $min_payout = floatval($_POST['min_payout']);
        $payment_days = intval($_POST['payment_days']);
        $coupon_discount = floatval($_POST['coupon_discount']);
        $coupon_discount_type = isset($_POST['coupon_discount_type']) ? sanitize_text_field($_POST['coupon_discount_type']) : 'fixed_cart';
        $allow_code_edit = isset($_POST['allow_code_edit']) ? 1 : 0;
        $allow_self_referral = isset($_POST['allow_self_referral']) ? 1 : 0;

        $errors = array();

        // Validation
        if (empty($tier_name)) {
            $errors[] = 'Tier name is required';
        }

        if ($commission < 0 || $commission > 100) {
            $errors[] = 'Commission must be between 0-100%';
        }

        if (empty($errors)) {
            // Check if it's a default tier or custom tier
            $default_tiers = array('tier_1', 'tier_2', 'ambassador');
            $is_default = in_array($tier_id, $default_tiers);

            if ($is_default) {
                // Update default tier settings in cas_settings
                $cas_settings = get_option('cas_settings', array());
                if (!is_array($cas_settings)) {
                    $cas_settings = array();
                }

                $cas_settings[$tier_id] = array(
                    'commission' => $commission,
                    'min_payout' => $min_payout,
                    'payment_days' => $payment_days,
                    'coupon_discount' => $coupon_discount,
                    'coupon_discount_type' => $coupon_discount_type,
                    'allow_code_edit' => $allow_code_edit,
                    'allow_self_referral' => $allow_self_referral
                );
                update_option('cas_settings', $cas_settings);

            } else {
                // Update custom tier
                $custom_tiers = get_option('cas_custom_tiers', array());

                if (isset($custom_tiers[$tier_id])) {
                    $custom_tiers[$tier_id] = array(
                        'name' => $tier_name,
                        'badge' => $tier_badge,
                        'commission' => $commission,
                        'min_payout' => $min_payout,
                        'payment_days' => $payment_days,
                        'coupon_discount' => $coupon_discount,
                        'coupon_discount_type' => $coupon_discount_type,
                        'allow_code_edit' => $allow_code_edit,
                        'allow_self_referral' => $allow_self_referral,
                        'created_at' => $custom_tiers[$tier_id]['created_at'] ?? current_time('mysql')
                    );
                    update_option('cas_custom_tiers', $custom_tiers);

                    // Also update cas_settings
                    $cas_settings = get_option('cas_settings', array());
                    if (!is_array($cas_settings)) {
                        $cas_settings = array();
                    }

                    $cas_settings[$tier_id] = array(
                        'commission' => $commission,
                        'min_payout' => $min_payout,
                        'payment_days' => $payment_days,
                        'coupon_discount' => $coupon_discount,
                        'coupon_discount_type' => $coupon_discount_type,
                        'allow_code_edit' => $allow_code_edit,
                        'allow_self_referral' => $allow_self_referral
                    );
                    update_option('cas_settings', $cas_settings);
                }
            }

            // Set transient for success message (survives redirect)
            set_transient('cas_tier_saved', $tier_name, 10);

            // Redirect to clear form and show success message
            wp_redirect(add_query_arg(array('page' => 'affiliate-tiers', 'saved' => '1'), admin_url('admin.php')));
            exit;
        } else {
            echo '<div class="notice notice-error is-dismissible"><p><strong>❌ Errors:</strong></p><ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
        }
    } catch (Exception $e) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>❌ Error updating tier:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
    }
}

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
        $coupon_discount_type = isset($_POST['coupon_discount_type']) ? sanitize_text_field($_POST['coupon_discount_type']) : 'fixed_cart';
        $allow_code_edit = isset($_POST['allow_code_edit']) ? 1 : 0;
        $allow_self_referral = isset($_POST['allow_self_referral']) ? 1 : 0;

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
                'coupon_discount_type' => $coupon_discount_type,
                'allow_code_edit' => $allow_code_edit,
                'allow_self_referral' => $allow_self_referral,
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
                'coupon_discount_type' => $coupon_discount_type,
                'allow_code_edit' => $allow_code_edit,
                'allow_self_referral' => $allow_self_referral
            );
            update_option('cas_settings', $cas_settings);

            // Set transient for success message (survives redirect)
            set_transient('cas_tier_created', $tier_name, 10);

            // Redirect to clear form and show success message
            wp_redirect(add_query_arg(array('page' => 'affiliate-tiers', 'created' => '1'), admin_url('admin.php')));
            exit;
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

                    // Set transient for success message (survives redirect)
                    set_transient('cas_tier_deleted', $deleted_name, 10);

                    // Redirect to show success message
                    wp_redirect(add_query_arg(array('page' => 'affiliate-tiers', 'deleted' => '1'), admin_url('admin.php')));
                    exit;
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>❌ Tier not found.</p></div>';
                }
            }
        }
    } catch (Exception $e) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>❌ Error deleting tier:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
    }
}

// Handle restore backup
if (isset($_POST['restore_settings_backup']) && check_admin_referer('cas_restore_backup')) {
    if (cas_restore_settings_from_backup()) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Success!</strong> Settings have been restored from backup.</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p><strong>❌ Error:</strong> No backup found or backup is empty.</p></div>';
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

// Check if backup exists
$backup_info = cas_get_settings_backup_info();

?>

<div class="wrap">
    <?php cas_render_admin_navigation('affiliate-tiers'); ?>

    <h1>🎯 Tier Management <?php echo cas_pro_badge(); ?></h1>

    <?php if ($backup_info): ?>
    <div class="cas-backup-notice">
        <div class="cas-backup-notice-flex">
            <div>
                <strong style="color: #856404;">📦 Settings Backup Available</strong>
                <p style="margin: 5px 0 0 0; color: #856404; font-size: 13px;">
                    A backup of your tier settings was created on <?php echo date('F j, Y \a\t g:i a', strtotime($backup_info['date'])); ?>.
                    If your settings were accidentally changed, you can restore them.
                </p>
            </div>
            <form method="post" style="margin: 0;">
                <?php wp_nonce_field('cas_restore_backup'); ?>
                <button type="submit" name="restore_settings_backup" class="button" onclick="return confirm('Restore tier settings from backup? This will overwrite your current settings.');">⟲ Restore Backup</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="cas-settings-header">
        <h2 style="margin: 0 0 10px 0; color: white;">Create Custom Tiers</h2>
        <p style="margin: 0; opacity: 0.9;">Design unlimited tiers with custom names, badges, and commission structures to match your affiliate program.</p>
    </div>

    <div class="cas-white-box">
        <h2>Existing Tiers</h2>
        <div class="cas-tier-grid">
            <?php foreach ($all_tiers as $tier_id => $tier_data):
                $is_default = in_array($tier_id, array('tier_1', 'tier_2', 'ambassador'));
                $settings = cas_get_all_tier_settings($tier_id);
                $usage = $tier_usage[$tier_id] ?? 0;
            ?>
            <div class="cas-tier-card" style="border-color: <?php echo cas_get_tier_color($tier_id); ?>;">
                <?php if ($is_default): ?>
                <span class="cas-tier-badge-default">DEFAULT</span>
                <?php endif; ?>

                <div style="font-size: 36px; margin-bottom: 10px;"><?php echo esc_html($tier_data['badge']); ?></div>
                <h3 style="margin: 0 0 10px 0; color: <?php echo cas_get_tier_color($tier_id); ?>;"><?php echo esc_html($tier_data['name']); ?></h3>

                <div class="cas-tier-details">
                    <p><strong>Commission:</strong> <?php echo $settings['commission']; ?>%</p>
                    <p><strong>Min Payout:</strong> <?php echo $settings['min_payout'] > 0 ? $settings['min_payout'] . '€' : 'No minimum'; ?></p>
                    <p><strong>Payment:</strong> <?php echo $settings['payment_days']; ?> days</p>
                    <p><strong>Coupon:</strong> <?php
                        $discount_type = $settings['coupon_discount_type'] ?? 'fixed_cart';
                        echo $settings['coupon_discount'];
                        echo ($discount_type === 'percent') ? '%' : '€';
                    ?></p>
                    <p><strong>Code Edit:</strong> <?php echo !empty($settings['allow_code_edit']) ? '✓ Allowed (1x/month)' : '✗ Not allowed'; ?></p>
                    <p><strong>Self-Referral:</strong> <?php echo !empty($settings['allow_self_referral']) ? '✓ Allowed' : '✗ Blocked'; ?></p>
                </div>

                <div class="cas-tier-stats">
                    <p style="margin: 0; font-size: 13px; color: #666;">
                        <strong><?php echo $usage; ?></strong> affiliate<?php echo $usage != 1 ? 's' : ''; ?> using this tier
                    </p>
                </div>

                <div class="cas-tier-actions">
                    <a href="?page=affiliate-tiers&edit_tier=<?php echo esc_attr($tier_id); ?>#editTierForm" class="button" style="flex: 1; text-align: center;">⚙️ Edit</a>

                    <?php if (!$is_default): ?>
                    <form method="post" style="flex: 1; margin: 0;" onsubmit="return confirm('Delete this tier? This cannot be undone.');">
                        <?php wp_nonce_field('cas_delete_tier'); ?>
                        <input type="hidden" name="tier_id" value="<?php echo esc_attr($tier_id); ?>">
                        <button type="submit" name="delete_tier" class="button" style="width: 100%;">
                            🗑️ Delete
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="cas-tier-create" onclick="document.getElementById('createTierForm').scrollIntoView({behavior: 'smooth'});">
                <div style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;">➕</div>
                <h3 style="margin: 0; color: #6b7280;">Create New Tier</h3>
                <p style="margin: 10px 0 0 0; color: #9ca3af; font-size: 13px;">Click to add a custom tier</p>
            </div>
        </div>
    </div>

    <?php
    $editing_tier_id = isset($_GET['edit_tier']) ? sanitize_key($_GET['edit_tier']) : '';
    if (!empty($editing_tier_id) && isset($all_tiers[$editing_tier_id])):
        $editing_tier = $all_tiers[$editing_tier_id];
        $editing_settings = cas_get_all_tier_settings($editing_tier_id);
        $is_default_tier = in_array($editing_tier_id, array('tier_1', 'tier_2', 'ambassador'));
        $suggested_settings = cas_get_suggested_tier_settings($editing_tier_id);
    ?>

    <?php if ($suggested_settings): ?>
    <div class="cas-suggestion-box">
        <div class="cas-suggestion-header">
            <h3 style="margin: 0; color: #0c4a6e;">💡 Suggested Settings for <?php echo esc_html($editing_tier['name']); ?></h3>
            <button type="button" class="button button-primary" onclick="applySuggestedSettings('<?php echo esc_js($editing_tier_id); ?>')">✓ Accept Suggestion</button>
        </div>

        <div class="cas-suggestion-grid">
            <div class="cas-suggestion-item">
                <strong style="color: #0c4a6e;">Commission:</strong> <?php echo $suggested_settings['commission']; ?>%
            </div>
            <div class="cas-suggestion-item">
                <strong style="color: #0c4a6e;">Min Payout:</strong> <?php echo $suggested_settings['min_payout'] > 0 ? $suggested_settings['min_payout'] . '€' : 'No minimum'; ?>
            </div>
            <div class="cas-suggestion-item">
                <strong style="color: #0c4a6e;">Payment Days:</strong> <?php echo $suggested_settings['payment_days']; ?> days
            </div>
            <div class="cas-suggestion-item">
                <strong style="color: #0c4a6e;">Coupon Discount:</strong> <?php
                    $suggested_discount_type = $suggested_settings['coupon_discount_type'] ?? 'fixed_cart';
                    echo $suggested_settings['coupon_discount'];
                    echo ($suggested_discount_type === 'percent') ? '%' : '€';
                ?>
            </div>
            <div class="cas-suggestion-item">
                <strong style="color: #0c4a6e;">Code Edit:</strong> <?php echo $suggested_settings['allow_code_edit'] ? '✓ Allowed' : '✗ Not allowed'; ?>
            </div>
            <div class="cas-suggestion-item">
                <strong style="color: #0c4a6e;">Self-Referral:</strong> <?php echo $suggested_settings['allow_self_referral'] ? '✓ Allowed' : '✗ Blocked'; ?>
            </div>
        </div>
        <p style="margin: 15px 0 0 0; font-size: 13px; color: #0c4a6e;">
            <strong>Note:</strong> These are recommended best-practice settings. Click "Accept Suggestion" to apply them, or manually configure below.
        </p>
    </div>
    <?php endif; ?>

    <div id="editTierForm" class="cas-white-box-border">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">⚙️ Edit Tier: <?php echo esc_html($editing_tier['name']); ?></h2>
            <a href="?page=affiliate-tiers" class="button">✕ Cancel</a>
        </div>

        <form method="post" action="" id="editTierFormElement">
            <?php wp_nonce_field('cas_edit_tier'); ?>
            <input type="hidden" name="tier_id" value="<?php echo esc_attr($editing_tier_id); ?>">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label>Tier ID</label>
                    </th>
                    <td>
                        <input type="text" class="regular-text" value="<?php echo esc_attr($editing_tier_id); ?>" disabled>
                        <p class="description">Tier ID cannot be changed after creation</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="edit_tier_name">Tier Name *</label>
                    </th>
                    <td>
                        <?php if (cas_is_pro_active()): ?>
                            <input type="text" name="tier_name" id="edit_tier_name" class="regular-text" required value="<?php echo esc_attr($editing_tier['name']); ?>">
                            <p class="description">Display name shown to users</p>
                        <?php else: ?>
                            <input type="text" value="<?php echo esc_attr($editing_tier['name']); ?>" class="regular-text" disabled>
                            <input type="hidden" name="tier_name" value="<?php echo esc_attr($editing_tier['name']); ?>">
                            <p class="description">
                                <span style="color: #f59e0b;">⭐ PRO Feature:</span> Upgrade to Pro to customize tier names.
                                <a href="<?php echo esc_url(cas_get_upgrade_url()); ?>" target="_blank" style="font-weight: 600;">Upgrade Now →</a>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <?php if (!$is_default_tier): ?>
                <tr>
                    <th scope="row">
                        <label for="edit_tier_badge">Badge/Emoji</label>
                    </th>
                    <td>
                        <input type="text" name="tier_badge" id="edit_tier_badge" class="small-text" value="<?php echo esc_attr($editing_tier['badge']); ?>" maxlength="5">
                        <p class="description">Emoji or icon to represent this tier</p>
                    </td>
                </tr>
                <?php else: ?>
                <input type="hidden" name="tier_badge" value="<?php echo esc_attr($editing_tier['badge']); ?>">
                <?php endif; ?>

                <tr>
                    <th scope="row">
                        <label for="edit_commission">Commission Rate (%) *</label>
                    </th>
                    <td>
                        <input type="number" name="commission" id="edit_commission" class="regular-text" required step="0.01" min="0" max="100" value="<?php echo esc_attr($editing_settings['commission']); ?>">
                        <p class="description">Percentage commission on sales (0-100%)</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="edit_min_payout">Minimum Payout (€)</label>
                    </th>
                    <td>
                        <input type="number" name="min_payout" id="edit_min_payout" class="regular-text" step="1" min="0" value="<?php echo esc_attr($editing_settings['min_payout']); ?>">
                        <p class="description">Minimum amount required to request payout (0 = no minimum)</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="edit_payment_days">Payment Timeline (days)</label>
                    </th>
                    <td>
                        <input type="number" name="payment_days" id="edit_payment_days" class="regular-text" step="1" min="1" value="<?php echo esc_attr($editing_settings['payment_days']); ?>">
                        <p class="description">Number of days to process payment after approval</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="edit_coupon_discount">Coupon Discount</label>
                    </th>
                    <td>
                        <input type="number" name="coupon_discount" id="edit_coupon_discount" class="regular-text" step="0.01" min="0" value="<?php echo esc_attr($editing_settings['coupon_discount']); ?>">
                        <p class="description">Discount amount customer receives when using affiliate coupon</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="edit_coupon_discount_type">Discount Type</label>
                    </th>
                    <td>
                        <select name="coupon_discount_type" id="edit_coupon_discount_type" class="regular-text">
                            <option value="fixed_cart" <?php selected($editing_settings['coupon_discount_type'] ?? 'fixed_cart', 'fixed_cart'); ?>>Fixed Amount (€)</option>
                            <option value="percent" <?php selected($editing_settings['coupon_discount_type'] ?? 'fixed_cart', 'percent'); ?>>Percentage (%)</option>
                        </select>
                        <p class="description">Choose whether the discount is a fixed amount or a percentage</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="edit_allow_code_edit">Allow Code Editing</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="allow_code_edit" id="edit_allow_code_edit" value="1" <?php checked(!empty($editing_settings['allow_code_edit'])); ?>>
                            Allow affiliates to edit their code once per month
                        </label>
                        <p class="description">If enabled, affiliates in this tier can change their promotional code once every 30 days</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="edit_allow_self_referral">Allow Self-Referral</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="allow_self_referral" id="edit_allow_self_referral" value="1" <?php checked(!empty($editing_settings['allow_self_referral'])); ?>>
                            Allow affiliates to earn commission on their own purchases
                        </label>
                        <p class="description">If enabled, affiliates in this tier can use their own code when purchasing and still earn commission</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="edit_tier" class="button button-primary button-large">
                    💾 Save Changes
                </button>
                <a href="?page=affiliate-tiers" class="button button-large" style="margin-left: 10px;">Cancel</a>
            </p>
        </form>
    </div>
    <?php endif; ?>

    <div id="createTierForm" class="cas-white-box">
        <h2>➕ Create New Tier <?php if (!cas_is_pro_active()) echo cas_pro_badge(); ?></h2>

        <?php if (!cas_is_pro_active()): ?>
            <div class="cas-pro-upgrade">
                <div style="font-size: 64px; margin-bottom: 20px;">👑</div>
                <h3 class="cas-pro-upgrade h3">Create Custom Tiers with PRO</h3>
                <p style="margin: 0 0 25px 0; opacity: 0.95; font-size: 16px;">
                    Unlock the ability to create unlimited custom tiers with unique commission rates, badges, and settings.
                </p>
                <div class="cas-pro-features">
                    <span class="cas-pro-feature">✓ Unlimited Custom Tiers</span>
                    <span class="cas-pro-feature">✓ Custom Badges & Names</span>
                    <span class="cas-pro-feature">✓ Flexible Commission Rates</span>
                    <span class="cas-pro-feature">✓ Advanced Features</span>
                </div>
                <a href="<?php echo esc_url(cas_get_upgrade_url()); ?>" target="_blank" style="background: white; color: #667eea; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">Upgrade to PRO →</a>
            </div>
        <?php else: ?>

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
                        <label for="coupon_discount">Coupon Discount</label>
                    </th>
                    <td>
                        <input type="number" name="coupon_discount" id="coupon_discount" class="regular-text" step="0.01" min="0" value="5">
                        <p class="description">Discount amount customer receives when using affiliate coupon</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="coupon_discount_type">Discount Type</label>
                    </th>
                    <td>
                        <select name="coupon_discount_type" id="coupon_discount_type" class="regular-text">
                            <option value="fixed_cart">Fixed Amount (€)</option>
                            <option value="percent">Percentage (%)</option>
                        </select>
                        <p class="description">Choose whether the discount is a fixed amount or a percentage</p>
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

                <tr>
                    <th scope="row">
                        <label for="allow_self_referral">Allow Self-Referral</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="allow_self_referral" id="allow_self_referral" value="1">
                            Allow affiliates to earn commission on their own purchases
                        </label>
                        <p class="description">If enabled, affiliates in this tier can use their own code when purchasing and still earn commission</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="create_tier" class="button button-primary button-large">
                    ✨ Create Tier
                </button>
            </p>
        </form>

        <?php endif; ?>
    </div>
    
    <div class="cas-tips-box">
        <h3>💡 Tips for Creating Tiers</h3>
        <ul>
            <li><strong>Progressive Structure:</strong> Higher tiers should offer better commission rates and benefits</li>
            <li><strong>Clear Names:</strong> Use recognizable tier names like Platinum, Diamond, Elite, VIP</li>
            <li><strong>Meaningful Badges:</strong> Choose emojis that represent the tier level (💎 💫 🌟 👑 ⭐)</li>
            <li><strong>Strategic Minimums:</strong> Lower minimum payouts for higher tiers as a reward</li>
            <li><strong>Fast Processing:</strong> Reduce payment timelines for premium tiers</li>
            <li><strong>Migration Path:</strong> Plan how affiliates can progress from lower to higher tiers</li>
        </ul>
    </div>

    <div class="cas-white-box">
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
if (document.getElementById('tier_id')) {
    document.getElementById('tier_id').addEventListener('input', function() {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
    });
}

// Preview badge in real-time
if (document.getElementById('tier_badge')) {
    document.getElementById('tier_badge').addEventListener('input', function() {
        console.log('Badge preview:', this.value);
    });
}

// Apply suggested settings to form
function applySuggestedSettings(tierId) {
    // Get suggested settings data from PHP
    const suggestedSettings = <?php echo json_encode(cas_get_all_suggested_tier_settings()); ?>;

    if (!suggestedSettings[tierId]) {
        alert('No suggested settings found for this tier.');
        return;
    }

    const settings = suggestedSettings[tierId];

    // Apply to form fields
    if (document.getElementById('edit_commission')) {
        document.getElementById('edit_commission').value = settings.commission;
    }
    if (document.getElementById('edit_min_payout')) {
        document.getElementById('edit_min_payout').value = settings.min_payout;
    }
    if (document.getElementById('edit_payment_days')) {
        document.getElementById('edit_payment_days').value = settings.payment_days;
    }
    if (document.getElementById('edit_coupon_discount')) {
        document.getElementById('edit_coupon_discount').value = settings.coupon_discount;
    }
    if (document.getElementById('edit_coupon_discount_type')) {
        document.getElementById('edit_coupon_discount_type').value = settings.coupon_discount_type || 'fixed_cart';
    }
    if (document.getElementById('edit_allow_code_edit')) {
        document.getElementById('edit_allow_code_edit').checked = settings.allow_code_edit == 1;
    }
    if (document.getElementById('edit_allow_self_referral')) {
        document.getElementById('edit_allow_self_referral').checked = settings.allow_self_referral == 1;
    }

    // Visual feedback
    const form = document.getElementById('editTierFormElement');
    if (form) {
        form.style.border = '3px solid #10b981';
        setTimeout(function() {
            form.style.border = '1px solid #e5e7eb';
        }, 2000);
    }

    // Show success message
    alert('✓ Suggested settings have been applied! Click "Save Changes" to confirm.');

    // Scroll to form
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>
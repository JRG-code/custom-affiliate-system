<?php
/**
 * Admin Settings Page with Pro Version Support
 */

if (!defined('ABSPATH')) exit;

// CRITICAL FIX: Register settings IMMEDIATELY
add_action('admin_init', 'cas_register_settings_immediate', 5);

function cas_register_settings_immediate() {
    register_setting('cas_settings_group', 'cas_settings', 'cas_sanitize_settings');
    register_setting('cas_settings_group', 'cas_debug_enabled');
    register_setting('cas_settings_group', 'cas_pro_license_key');
    
    // License Section (only if not pro)
    if (!cas_is_pro_active()) {
        add_settings_section('cas_license_section', '🔐 Pro License', 'cas_license_section_callback', 'cas-settings');
        add_settings_field('pro_license_key', 'License Key', 'cas_license_key_field_callback', 'cas-settings', 'cas_license_section');
    }
    
    // Tier I Section
    add_settings_section('cas_tier1_section', '⭐ Tier I - Basic Affiliate Settings', 'cas_tier1_section_callback', 'cas-settings');
    add_settings_field('tier1_commission', 'Commission Rate (%)', 'cas_commission_field_callback', 'cas-settings', 'cas_tier1_section', ['tier' => 'tier_1']);
    add_settings_field('tier1_min_payout', 'Minimum Payout (€)', 'cas_min_payout_field_callback', 'cas-settings', 'cas_tier1_section', ['tier' => 'tier_1']);
    add_settings_field('tier1_payment_days', 'Payment Timeline (days)', 'cas_payment_days_field_callback', 'cas-settings', 'cas_tier1_section', ['tier' => 'tier_1']);
    add_settings_field('tier1_coupon_discount', 'Coupon Discount (€)', 'cas_coupon_discount_field_callback', 'cas-settings', 'cas_tier1_section', ['tier' => 'tier_1']);
    
    // Tier II Section
    add_settings_section('cas_tier2_section', '💎 Tier II - Influencer Settings', 'cas_tier2_section_callback', 'cas-settings');
    add_settings_field('tier2_commission', 'Commission Rate (%)', 'cas_commission_field_callback', 'cas-settings', 'cas_tier2_section', ['tier' => 'tier_2']);
    add_settings_field('tier2_min_payout', 'Minimum Payout (€)', 'cas_min_payout_field_callback', 'cas-settings', 'cas_tier2_section', ['tier' => 'tier_2']);
    add_settings_field('tier2_payment_days', 'Payment Timeline (days)', 'cas_payment_days_field_callback', 'cas-settings', 'cas_tier2_section', ['tier' => 'tier_2']);
    add_settings_field('tier2_coupon_discount', 'Coupon Discount (€)', 'cas_coupon_discount_field_callback', 'cas-settings', 'cas_tier2_section', ['tier' => 'tier_2']);
    
    // Pro Tiers (only if Pro is active)
    if (cas_is_pro_active()) {
        // Ambassador Section
        add_settings_section('cas_ambassador_section', '👑 Ambassador Settings ' . cas_pro_badge(), 'cas_ambassador_section_callback', 'cas-settings');
        add_settings_field('ambassador_commission', 'Commission Rate (%)', 'cas_commission_field_callback', 'cas-settings', 'cas_ambassador_section', ['tier' => 'ambassador']);
        add_settings_field('ambassador_min_payout', 'Minimum Payout (€)', 'cas_min_payout_field_callback', 'cas-settings', 'cas_ambassador_section', ['tier' => 'ambassador']);
        add_settings_field('ambassador_payment_days', 'Payment Timeline (days)', 'cas_payment_days_field_callback', 'cas-settings', 'cas_ambassador_section', ['tier' => 'ambassador']);
        add_settings_field('ambassador_coupon_discount', 'Coupon Discount (€)', 'cas_coupon_discount_field_callback', 'cas-settings', 'cas_ambassador_section', ['tier' => 'ambassador']);
    }
    
    // General Section
    add_settings_section('cas_general_section', '⚙️ General Settings', 'cas_general_section_callback', 'cas-settings');
    add_settings_field('currency_symbol', 'Currency Symbol', 'cas_currency_symbol_field_callback', 'cas-settings', 'cas_general_section');
    add_settings_field('support_email', 'Support Email', 'cas_support_email_field_callback', 'cas-settings', 'cas_general_section');
    add_settings_field('auto_approve', 'Auto-Approve New Affiliates', 'cas_auto_approve_field_callback', 'cas-settings', 'cas_general_section');
    add_settings_field('terms_page', 'Terms & Conditions Page', 'cas_terms_page_field_callback', 'cas-settings', 'cas_general_section');
    
    // Debug Section
    add_settings_section('cas_debug_section', '🐛 Debug Settings', 'cas_debug_section_callback', 'cas-settings');
    add_settings_field('debug_enabled', 'Enable Debug Mode', 'cas_debug_enabled_field_callback', 'cas-settings', 'cas_debug_section');
}

// License section callback
function cas_license_section_callback() {
    echo '<p>Enter your Pro license key to unlock premium features including Ambassador tier and unlimited custom tiers.</p>';
}

function cas_license_key_field_callback() {
    $license_key = get_option('cas_pro_license_key', '');
    $license_status = get_option('cas_pro_license_status', 'invalid');
    ?>
    <input type="text" name="cas_pro_license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX">
    
    <?php if (!empty($license_key)): ?>
        <?php if ($license_status === 'valid'): ?>
            <span style="color: #10b981; margin-left: 10px;">✓ Active</span>
        <?php else: ?>
            <span style="color: #ef4444; margin-left: 10px;">✗ Invalid</span>
        <?php endif; ?>
    <?php endif; ?>
    
    <p class="description">
        Don't have a license? 
        <a href="<?php echo esc_url(cas_get_upgrade_url()); ?>" target="_blank" style="font-weight: 600;">Get Pro License →</a>
    </p>
    <?php
}

// Section callbacks
function cas_tier1_section_callback() {
    echo '<p>Settings for basic tier affiliates (default for new registrations). <strong>Free Version</strong></p>';
}

function cas_tier2_section_callback() {
    echo '<p>Settings for Tier II influencers with better rates. <strong>Free Version</strong></p>';
}

function cas_ambassador_section_callback() {
    echo '<p>Settings for premium ambassadors with the best rates. <strong>Pro Version</strong></p>';
}

function cas_general_section_callback() {
    echo '<p>General system-wide settings.</p>';
}

function cas_debug_section_callback() {
    echo '<p>Debug options for troubleshooting issues.</p>';
}

// Field callbacks
function cas_commission_field_callback($args) {
    $tier = $args['tier'];
    $options = get_option('cas_settings', array());
    $value = isset($options[$tier]['commission']) ? $options[$tier]['commission'] : cas_get_tier_setting($tier, 'commission');
    ?>
    <input type="number" name="cas_settings[<?php echo $tier; ?>][commission]" value="<?php echo esc_attr($value); ?>" step="0.01" min="0" max="100" class="regular-text">
    <p class="description">Commission percentage per sale (e.g., 10 for 10%)</p>
    <?php
}

function cas_min_payout_field_callback($args) {
    $tier = $args['tier'];
    $options = get_option('cas_settings', array());
    $value = isset($options[$tier]['min_payout']) ? $options[$tier]['min_payout'] : cas_get_tier_setting($tier, 'min_payout');
    ?>
    <input type="number" name="cas_settings[<?php echo $tier; ?>][min_payout]" value="<?php echo esc_attr($value); ?>" step="1" min="0" class="regular-text">
    <p class="description">Minimum amount required to request payout (0 = no minimum)</p>
    <?php
}

function cas_payment_days_field_callback($args) {
    $tier = $args['tier'];
    $options = get_option('cas_settings', array());
    $value = isset($options[$tier]['payment_days']) ? $options[$tier]['payment_days'] : cas_get_tier_setting($tier, 'payment_days');
    ?>
    <input type="number" name="cas_settings[<?php echo $tier; ?>][payment_days]" value="<?php echo esc_attr($value); ?>" step="1" min="1" class="regular-text">
    <p class="description">Number of days to process payment after approval</p>
    <?php
}

function cas_coupon_discount_field_callback($args) {
    $tier = $args['tier'];
    $options = get_option('cas_settings', array());
    $value = isset($options[$tier]['coupon_discount']) ? $options[$tier]['coupon_discount'] : cas_get_tier_setting($tier, 'coupon_discount');
    ?>
    <input type="number" name="cas_settings[<?php echo $tier; ?>][coupon_discount]" value="<?php echo esc_attr($value); ?>" step="0.01" min="0" class="regular-text">
    <p class="description">Discount amount customer receives when using affiliate coupon</p>
    <?php
}

function cas_currency_symbol_field_callback() {
    $options = get_option('cas_settings', array());
    $value = isset($options['general']['currency_symbol']) ? $options['general']['currency_symbol'] : '€';
    ?>
    <input type="text" name="cas_settings[general][currency_symbol]" value="<?php echo esc_attr($value); ?>" maxlength="3" class="small-text">
    <p class="description">Currency symbol to display (e.g., €, $, £)</p>
    <?php
}

function cas_support_email_field_callback() {
    $options = get_option('cas_settings', array());
    $value = isset($options['general']['support_email']) ? $options['general']['support_email'] : get_option('admin_email');
    ?>
    <input type="email" name="cas_settings[general][support_email]" value="<?php echo esc_attr($value); ?>" class="regular-text">
    <p class="description">Email for affiliate support and payout notifications</p>
    <?php
}

function cas_auto_approve_field_callback() {
    $options = get_option('cas_settings', array());
    $value = isset($options['general']['auto_approve']) ? $options['general']['auto_approve'] : 1;
    ?>
    <label>
        <input type="checkbox" name="cas_settings[general][auto_approve]" value="1" <?php checked($value, 1); ?>>
        Automatically approve new affiliate registrations
    </label>
    <p class="description">If disabled, new affiliates will be pending manual approval</p>
    <?php
}

function cas_terms_page_field_callback() {
    $options = get_option('cas_settings', array());
    $value = isset($options['general']['terms_page']) ? $options['general']['terms_page'] : '';
    $pages = get_pages();
    ?>
    <select name="cas_settings[general][terms_page]" class="regular-text">
        <option value="">Select a page...</option>
        <?php foreach ($pages as $page): ?>
            <option value="<?php echo $page->ID; ?>" <?php selected($value, $page->ID); ?>>
                <?php echo esc_html($page->post_title); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description">Page containing Terms & Conditions for the affiliate program</p>
    <?php
}

function cas_debug_enabled_field_callback() {
    $value = get_option('cas_debug_enabled', false);
    ?>
    <label>
        <input type="checkbox" name="cas_debug_enabled" value="1" <?php checked($value, 1); ?>>
        Enable debug logging
    </label>
    <p class="description">Track affiliate system actions for troubleshooting (creates a Debug Log menu item)</p>
    <?php
}

// Sanitize settings
function cas_sanitize_settings($input) {
    $sanitized = array();
    
    // Get available tiers based on license
    $available_tiers = cas_get_available_tiers();
    $tier_codes = array_keys($available_tiers);
    
    foreach ($tier_codes as $tier) {
        if (isset($input[$tier])) {
            $sanitized[$tier]['commission'] = floatval($input[$tier]['commission']);
            $sanitized[$tier]['min_payout'] = floatval($input[$tier]['min_payout']);
            $sanitized[$tier]['payment_days'] = intval($input[$tier]['payment_days']);
            $sanitized[$tier]['coupon_discount'] = floatval($input[$tier]['coupon_discount']);
        }
    }
    
    if (isset($input['general'])) {
        $sanitized['general']['currency_symbol'] = sanitize_text_field($input['general']['currency_symbol']);
        $sanitized['general']['support_email'] = sanitize_email($input['general']['support_email']);
        $sanitized['general']['auto_approve'] = isset($input['general']['auto_approve']) ? 1 : 0;
        $sanitized['general']['terms_page'] = intval($input['general']['terms_page']);
    }
    
    cas_update_existing_coupons($sanitized);
    
    add_settings_error('cas_settings', 'cas_settings_updated', '✅ Settings saved successfully! Affiliate coupons and rates updated.', 'success');
    
    return $sanitized;
}

function cas_update_existing_coupons($new_settings) {
    global $wpdb;
    
    $available_tiers = cas_get_available_tiers();
    
    foreach (array_keys($available_tiers) as $tier) {
        if (!isset($new_settings[$tier])) continue;
        
        $commission = $new_settings[$tier]['commission'];
        $discount = $new_settings[$tier]['coupon_discount'];
        
        $wpdb->update(
            $wpdb->prefix . 'affiliates',
            ['commission_rate' => $commission],
            ['tier' => $tier],
            ['%f'],
            ['%s']
        );
        
        $affiliates = $wpdb->get_results($wpdb->prepare(
            "SELECT affiliate_code FROM {$wpdb->prefix}affiliates WHERE tier = %s",
            $tier
        ));
        
        foreach ($affiliates as $aff) {
            $coupon = new WC_Coupon(strtolower($aff->affiliate_code));
            if ($coupon->get_id()) {
                update_post_meta($coupon->get_id(), 'coupon_amount', $discount);
            }
        }
    }
}

// Handle debug setting separately
if (isset($_POST['cas_debug_enabled'])) {
    update_option('cas_debug_enabled', 1);
} elseif (isset($_POST['option_page']) && $_POST['option_page'] === 'cas_settings_group') {
    update_option('cas_debug_enabled', 0);
}

// Handle license activation
if (isset($_POST['cas_pro_license_key']) && !empty($_POST['cas_pro_license_key'])) {
    $new_key = sanitize_text_field($_POST['cas_pro_license_key']);
    $old_key = get_option('cas_pro_license_key', '');
    
    if ($new_key !== $old_key) {
        $result = cas_validate_license($new_key);
        
        if ($result['status'] === 'success') {
            add_settings_error('cas_settings', 'license_activated', '✅ ' . $result['message'], 'success');
        } else {
            add_settings_error('cas_settings', 'license_error', '❌ ' . $result['message'], 'error');
        }
    }
}

?>

<div class="wrap cas-settings-page">
    <h1>🎯 Affiliate System Settings</h1>
    
    <?php if (!cas_is_pro_active()): ?>
    <!-- Pro Upgrade Banner -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin: 20px 0; position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; right: 0; opacity: 0.1; font-size: 200px; line-height: 1;">👑</div>
        <div style="position: relative; z-index: 1;">
            <h2 style="margin: 0 0 10px 0; color: white; font-size: 28px;">🚀 Unlock Pro Features</h2>
            <p style="margin: 0 0 20px 0; opacity: 0.95; font-size: 16px;">
                Get access to Ambassador tier, unlimited custom tiers, advanced analytics, and priority support
            </p>
            <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">
                <span style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px; font-size: 14px;">✓ Unlimited Tiers</span>
                <span style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px; font-size: 14px;">✓ Custom Badges</span>
                <span style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px; font-size: 14px;">✓ Advanced Analytics</span>
                <span style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px; font-size: 14px;">✓ Priority Support</span>
            </div>
            <a href="<?php echo esc_url(cas_get_upgrade_url()); ?>" target="_blank" style="background: white; color: #667eea; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                View Pro Plans →
            </a>
        </div>
    </div>
    <?php else: ?>
    <!-- Pro Active Banner -->
    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 20px; border-radius: 12px; margin: 20px 0;">
        <h2 style="margin: 0 0 5px 0; color: white;">✓ Pro Version Active</h2>
        <p style="margin: 0; opacity: 0.9;">You have full access to all premium features including unlimited custom tiers.</p>
    </div>
    <?php endif; ?>
    
    <div class="cas-settings-header" style="background: white; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 8px;">
        <h2 style="margin: 0 0 10px 0;">Configure Your Affiliate Program</h2>
        <p style="margin: 0; color: #666;">Manage commission rates, payout minimums, and other settings for all affiliate tiers.</p>
    </div>
    
    <?php settings_errors('cas_settings'); ?>
    
    <?php if (!cas_is_pro_active()): ?>
    <div class="cas-settings-notice" style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; margin: 20px 0; border-radius: 6px;">
        <p style="margin: 0; color: #92400e;">
            <strong>ℹ️ Free Version:</strong> You have access to Tier I and Tier II. Upgrade to Pro to unlock Ambassador tier and create unlimited custom tiers.
        </p>
    </div>
    <?php endif; ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('cas_settings_group');
        do_settings_sections('cas-settings');
        submit_button('Save All Settings', 'primary large');
        ?>
    </form>
    
    <?php if (!cas_is_pro_active()): ?>
    <!-- Locked Ambassador Tier Preview -->
    <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0; opacity: 0.6; position: relative;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 10; text-align: center;">
            <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.2);">
                <span style="font-size: 48px; display: block; margin-bottom: 15px;">🔒</span>
                <h3 style="margin: 0 0 10px 0;">Ambassador Tier Locked</h3>
                <p style="margin: 0 0 20px 0; color: #666;">Upgrade to Pro to unlock this tier</p>
                <a href="<?php echo esc_url(cas_get_upgrade_url()); ?>" target="_blank" class="button button-primary">
                    Upgrade Now
                </a>
            </div>
        </div>
        <h2 style="margin: 0 0 20px 0;">👑 Ambassador Settings <?php echo cas_pro_badge(); ?></h2>
        <div style="filter: blur(4px); pointer-events: none;">
            <table class="form-table">
                <tr>
                    <th>Commission Rate (%)</th>
                    <td><input type="number" value="20" class="regular-text" disabled></td>
                </tr>
                <tr>
                    <th>Minimum Payout (€)</th>
                    <td><input type="number" value="0" class="regular-text" disabled></td>
                </tr>
                <tr>
                    <th>Payment Timeline (days)</th>
                    <td><input type="number" value="3" class="regular-text" disabled></td>
                </tr>
                <tr>
                    <th>Coupon Discount (€)</th>
                    <td><input type="number" value="5" class="regular-text" disabled></td>
                </tr>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="cas-settings-footer" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 30px;">
        <h3 style="margin: 0 0 15px 0;">📋 Tier Comparison</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div style="border: 2px solid #667eea; padding: 20px; border-radius: 8px;">
                <h4 style="margin: 0 0 10px 0; color: #667eea;">⭐ Tier I (Basic)</h4>
                <p style="margin: 5px 0; font-size: 14px; color: #666;">• Default tier for new affiliates</p>
                <p style="margin: 5px 0; font-size: 14px; color: #666;">• Standard commission rates</p>
                <p style="margin: 5px 0; font-size: 14px; color: #666;">• Minimum payout threshold</p>
                <span style="background: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; margin-top: 10px;">FREE</span>
            </div>
            <div style="border: 2px solid #f59e0b; padding: 20px; border-radius: 8px;">
                <h4 style="margin: 0 0 10px 0; color: #f59e0b;">💎 Tier II (Influencer)</h4>
                <p style="margin: 5px 0; font-size: 14px; color: #666;">• For proven performers</p>
                <p style="margin: 5px 0; font-size: 14px; color: #666;">• Higher commission rates</p>
                <p style="margin: 5px 0; font-size: 14px; color: #666;">• Faster payment processing</p>
                <span style="background: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; margin-top: 10px;">FREE</span>
            </div>
            <div style="border: 2px solid #ec4899; padding: 20px; border-radius: 8px; <?php echo !cas_is_pro_active() ? 'opacity: 0.5;' : ''; ?>">
                <h4 style="margin: 0 0 10px 0; color: #ec4899;">👑 Ambassador (Premium)</h4>
                <p style="margin: 5px 0; font-size: 14px; color: #666;">• For top affiliates</p>
                <p style="margin: 5px 0; font-size: 14px; color: #666;">• Maximum commission rates</p>
                <p style="margin: 5px 0; font-size: 14px; color: #666;">• Priority payment processing</p>
                <?php echo cas_pro_badge(); ?>
            </div>
        </div>
        
        <?php if (!cas_is_pro_active()): ?>
        <div style="margin-top: 30px; padding: 20px; background: #f0f9ff; border-radius: 8px; text-align: center;">
            <h4 style="margin: 0 0 10px 0; color: #0284c7;">💡 Want More Tiers?</h4>
            <p style="margin: 0 0 15px 0; color: #0c4a6e;">
                Pro version includes Ambassador tier plus ability to create unlimited custom tiers with your own names, badges, and settings.
            </p>
            <a href="<?php echo esc_url(cas_get_upgrade_url()); ?>" target="_blank" class="button button-primary">
                Learn More About Pro
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
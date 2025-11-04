<?php
/**
 * Admin Settings Page - General Settings Only
 * (Tier settings moved to Tier Management page)
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
    
    // General Section
    add_settings_section('cas_general_section', '⚙️ General Settings', 'cas_general_section_callback', 'cas-settings');
    add_settings_field('currency_symbol', 'Currency Symbol', 'cas_currency_symbol_field_callback', 'cas-settings', 'cas_general_section');
    add_settings_field('support_email', 'Support Email', 'cas_support_email_field_callback', 'cas-settings', 'cas_general_section');
    add_settings_field('auto_create_affiliate', 'Auto-Create Affiliates', 'cas_auto_create_affiliate_field_callback', 'cas-settings', 'cas_general_section');
    add_settings_field('auto_approve', 'Auto-Approve New Affiliates', 'cas_auto_approve_field_callback', 'cas-settings', 'cas_general_section');
    add_settings_field('send_welcome_email', 'Send Welcome Email', 'cas_send_welcome_email_field_callback', 'cas-settings', 'cas_general_section');
    add_settings_field('terms_page', 'Terms & Conditions Page', 'cas_terms_page_field_callback', 'cas-settings', 'cas_general_section');
    
    // Automatic Payouts Section
    add_settings_section('cas_payouts_section', '💰 Automatic Payouts', 'cas_payouts_section_callback', 'cas-settings');
    add_settings_field('auto_payouts_enabled', 'Enable Automatic Payouts', 'cas_auto_payouts_enabled_field_callback', 'cas-settings', 'cas_payouts_section');
    add_settings_field('payout_schedule', 'Payout Schedule', 'cas_payout_schedule_field_callback', 'cas-settings', 'cas_payouts_section');

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
function cas_general_section_callback() {
    echo '<p>General system-wide settings for your affiliate program.</p>';
}

function cas_payouts_section_callback() {
    echo '<p>Automate payout processing using WordPress Cron (100% free, no paid services required!).</p>';

    // Show next scheduled payout
    $next_run = wp_next_scheduled('cas_process_automatic_payouts');
    if ($next_run) {
        $next_date = date('F j, Y \a\t g:i A', $next_run);
        echo '<div style="background: #e0f2fe; border-left: 4px solid #0284c7; padding: 15px; margin: 15px 0; border-radius: 4px;">';
        echo '<p style="margin: 0;"><strong>ℹ️ Next Scheduled Run:</strong> ' . $next_date . '</p>';
        echo '</div>';
    }
}

function cas_debug_section_callback() {
    echo '<p>Debug options for troubleshooting issues.</p>';
}

// Field callbacks
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

function cas_auto_create_affiliate_field_callback() {
    $options = get_option('cas_settings', array());
    $value = isset($options['general']['auto_create_affiliate']) ? $options['general']['auto_create_affiliate'] : 1;
    ?>
    <label>
        <input type="checkbox" name="cas_settings[general][auto_create_affiliate]" value="1" <?php checked($value, 1); ?>>
        Automatically create affiliate account when user registers
    </label>
    <p class="description">When enabled, every new user becomes an affiliate automatically</p>
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

function cas_send_welcome_email_field_callback() {
    $options = get_option('cas_settings', array());
    $value = isset($options['general']['send_welcome_email']) ? $options['general']['send_welcome_email'] : 1;
    ?>
    <label>
        <input type="checkbox" name="cas_settings[general][send_welcome_email]" value="1" <?php checked($value, 1); ?>>
        Send welcome email when affiliate is created
    </label>
    <p class="description">Email will be sent after a 10-second delay to ensure account is fully set up</p>
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

function cas_auto_payouts_enabled_field_callback() {
    $options = get_option('cas_settings', array());
    $value = isset($options['general']['auto_payouts_enabled']) ? $options['general']['auto_payouts_enabled'] : 0;
    ?>
    <label>
        <input type="checkbox" name="cas_settings[general][auto_payouts_enabled]" value="1" <?php checked($value, 1); ?>>
        Automatically process approved payouts on schedule
    </label>
    <p class="description">When enabled, all "approved" payouts will be automatically marked as "paid" on the schedule below.</p>
    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 10px 0; border-radius: 4px;">
        <p style="margin: 0; font-size: 13px;"><strong>⚠️ Important:</strong> You still need to manually approve payouts. This only automates the processing of already-approved requests on the selected schedule.</p>
    </div>
    <?php
}

function cas_payout_schedule_field_callback() {
    $options = get_option('cas_settings', array());
    $value = isset($options['general']['payout_schedule']) ? $options['general']['payout_schedule'] : 'monthly';
    ?>
    <select name="cas_settings[general][payout_schedule]" class="regular-text">
        <option value="weekly" <?php selected($value, 'weekly'); ?>>Weekly (Every 7 days)</option>
        <option value="biweekly" <?php selected($value, 'biweekly'); ?>>Twice Monthly (Every 14 days)</option>
        <option value="monthly" <?php selected($value, 'monthly'); ?>>Monthly (Every 30 days)</option>
    </select>
    <p class="description">How often should automatic payouts be processed?</p>
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
    $sanitized = get_option('cas_settings', array());

    // Only update general settings (don't touch tier settings)
    if (isset($input['general'])) {
        $sanitized['general']['currency_symbol'] = sanitize_text_field($input['general']['currency_symbol']);
        $sanitized['general']['support_email'] = sanitize_email($input['general']['support_email']);
        $sanitized['general']['auto_create_affiliate'] = isset($input['general']['auto_create_affiliate']) ? 1 : 0;
        $sanitized['general']['auto_approve'] = isset($input['general']['auto_approve']) ? 1 : 0;
        $sanitized['general']['send_welcome_email'] = isset($input['general']['send_welcome_email']) ? 1 : 0;
        $sanitized['general']['terms_page'] = intval($input['general']['terms_page']);
        $sanitized['general']['auto_payouts_enabled'] = isset($input['general']['auto_payouts_enabled']) ? 1 : 0;
        $sanitized['general']['payout_schedule'] = sanitize_text_field($input['general']['payout_schedule'] ?? 'monthly');
    }

    // Reschedule automatic payouts if settings changed
    $timestamp = wp_next_scheduled('cas_process_automatic_payouts');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'cas_process_automatic_payouts');
    }
    cas_schedule_automatic_payouts();

    add_settings_error('cas_settings', 'cas_settings_updated', '✅ Settings saved successfully!', 'success');

    return $sanitized;
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
    <h1>⚙️ General Settings</h1>
    
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
    
    <div style="background: #f0f9ff; border-left: 4px solid #0284c7; padding: 20px; margin: 20px 0; border-radius: 6px;">
        <p style="margin: 0; color: #0c4a6e;">
            <strong>ℹ️ Note:</strong> Tier commission rates and payout settings are now managed in 
            <a href="<?php echo admin_url('admin.php?page=affiliate-tiers'); ?>">Tier Management</a> page.
        </p>
    </div>
    
    <?php settings_errors('cas_settings'); ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('cas_settings_group');
        do_settings_sections('cas-settings');
        submit_button('Save Settings', 'primary large');
        ?>
    </form>
    
    <div class="cas-settings-footer" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 30px;">
        <h3 style="margin: 0 0 15px 0;">🔗 Quick Links</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div style="padding: 15px; background: #f9fafb; border-radius: 8px;">
                <h4 style="margin: 0 0 10px 0; color: #667eea;">🎯 Tier Management</h4>
                <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">Configure commission rates, payouts, and create custom tiers</p>
                <a href="<?php echo admin_url('admin.php?page=affiliate-tiers'); ?>" class="button">Manage Tiers →</a>
            </div>
            
            <div style="padding: 15px; background: #f9fafb; border-radius: 8px;">
                <h4 style="margin: 0 0 10px 0; color: #10b981;">👥 Affiliates</h4>
                <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">View all affiliates, upgrade tiers, manage status</p>
                <a href="<?php echo admin_url('admin.php?page=affiliate-system'); ?>" class="button">View Affiliates →</a>
            </div>
            
            <div style="padding: 15px; background: #f9fafb; border-radius: 8px;">
                <h4 style="margin: 0 0 10px 0; color: #f59e0b;">📊 Reports</h4>
                <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">Analytics, top performers, and data exports</p>
                <a href="<?php echo admin_url('admin.php?page=affiliate-reports'); ?>" class="button">View Reports →</a>
            </div>
        </div>
    </div>
</div>
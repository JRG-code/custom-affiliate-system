<?php
/**
 * Pro Version License System
 * Add this to includes/pro-license.php
 */

if (!defined('ABSPATH')) exit;

/**
 * Check if Pro version is active
 * @return bool
 */
function cas_is_pro_active() {
    $license_key = get_option('cas_pro_license_key', '');
    
    // Allow localhost/development environments
    $allowed_domains = array(
        'localhost',
        '127.0.0.1',
        'thecouplesbrand.com',
        'www.thecouplesbrand.com'
    );
    
    $current_domain = $_SERVER['HTTP_HOST'];
    
    // Auto-activate for your domain
    if (in_array($current_domain, $allowed_domains)) {
        return true;
    }
    
    // Check license key
    if (empty($license_key)) {
        return false;
    }
    
    $license_status = get_option('cas_pro_license_status', 'invalid');
    return $license_status === 'valid';
}

/**
 * Validate license key with remote server
 */
function cas_validate_license($license_key) {
    // For now, simple validation
    // In production, this would call your license server API
    
    $response = wp_remote_post('https://yourlicenseserver.com/api/validate', array(
        'body' => array(
            'license_key' => $license_key,
            'domain' => $_SERVER['HTTP_HOST'],
            'product' => 'custom-affiliate-system-pro'
        ),
        'timeout' => 15
    ));
    
    if (is_wp_error($response)) {
        return array('status' => 'error', 'message' => 'Could not connect to license server');
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['valid']) && $body['valid'] === true) {
        update_option('cas_pro_license_status', 'valid');
        update_option('cas_pro_license_key', $license_key);
        return array('status' => 'success', 'message' => 'License activated successfully!');
    }
    
    return array('status' => 'error', 'message' => $body['message'] ?? 'Invalid license key');
}

/**
 * Deactivate license
 */
function cas_deactivate_license() {
    delete_option('cas_pro_license_key');
    delete_option('cas_pro_license_status');
}

/**
 * Get available tiers based on license
 */
function cas_get_available_tiers() {
    // Start with default tiers
    $tiers = array(
        'tier_1' => array(
            'name' => 'Tier I',
            'badge' => '⭐',
            'description' => 'Basic affiliate tier for new members',
            'pro' => false
        ),
        'tier_2' => array(
            'name' => 'Tier II',
            'badge' => '💎',
            'description' => 'Advanced tier for proven influencers',
            'pro' => false
        )
    );

    // Add Ambassador tier if Pro is active
    if (cas_is_pro_active()) {
        $tiers['ambassador'] = array(
            'name' => 'Ambassador',
            'badge' => '👑',
            'description' => 'Premium tier for top performers',
            'pro' => true
        );

        // Add custom tiers from database
        $custom_tiers = get_option('cas_custom_tiers', array());
        foreach ($custom_tiers as $tier_id => $tier_data) {
            $tiers[$tier_id] = array(
                'name' => $tier_data['name'],
                'badge' => $tier_data['badge'],
                'description' => $tier_data['description'] ?? '',
                'pro' => true
            );
        }
    }

    return $tiers;
}

/**
 * Check if tier is available in current license
 */
function cas_is_tier_available($tier) {
    $available_tiers = cas_get_available_tiers();
    return isset($available_tiers[$tier]);
}

/**
 * Get Pro upgrade URL
 */
function cas_get_upgrade_url() {
    return 'https://thecouplesbrand.com/affiliate-system-pro/';
}

/**
 * Display Pro badge/notice
 */
function cas_pro_badge() {
    return '<span style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin-left: 8px;">PRO</span>';
}

/**
 * Display upgrade notice for locked features
 */
function cas_upgrade_notice($feature_name = 'this feature') {
    if (cas_is_pro_active()) {
        return '';
    }
    
    ob_start();
    ?>
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
        <p style="margin: 0 0 10px 0; font-size: 18px; font-weight: 600;">
            🔒 Unlock <?php echo esc_html($feature_name); ?> with Pro
        </p>
        <p style="margin: 0 0 15px 0; opacity: 0.9;">
            Upgrade to Pro to access unlimited custom tiers, advanced analytics, and priority support
        </p>
        <a href="<?php echo esc_url(cas_get_upgrade_url()); ?>" target="_blank" class="button button-primary" style="background: white; color: #667eea; border: none; padding: 12px 24px; text-decoration: none; display: inline-block; border-radius: 6px; font-weight: 600;">
            Upgrade to Pro →
        </a>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Add Pro features list
 */
function cas_get_pro_features() {
    return array(
        'Unlimited custom tiers',
        'Custom tier names and badges',
        'Advanced commission structures',
        'Tier-based email templates',
        'Priority email support',
        'Advanced analytics dashboard',
        'Automated tier upgrades',
        'Performance-based rewards',
        'White-label options',
        'Lifetime updates'
    );
}
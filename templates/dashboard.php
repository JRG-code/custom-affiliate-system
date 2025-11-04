<?php

global $wpdb;
$user_id = get_current_user_id();
$user = wp_get_current_user();

$affiliate = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}affiliates WHERE user_id = %d",
    $user_id
));

if (!$affiliate) {
    echo '<p>Não és um afiliado ativo. Contacta o suporte.</p>';
    return;
}

$total_referrals = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_referrals WHERE affiliate_id = %d",
    $affiliate->id
));

$recent_referrals = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}affiliate_referrals 
    WHERE affiliate_id = %d 
    ORDER BY created_at DESC 
    LIMIT 5",
    $affiliate->id
));

// Use helper functions
$tier_name = cas_get_tier_name($affiliate->tier);
$tier_badge = cas_get_tier_badge($affiliate->tier);
$commission_rate = $affiliate->commission_rate;

// Get settings dynamically
$min_payout = cas_get_tier_setting($affiliate->tier, 'min_payout');
$payment_days = cas_get_payment_timeline_text($affiliate->tier);
$payout_check = cas_can_request_payout($affiliate);
$can_request = $payout_check['can_request'];

// Get currency and support email
$currency = cas_get_general_setting('currency_symbol');
$support_email = cas_get_support_email();

// Check for pending payout
$pending_payout = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}affiliate_payouts 
    WHERE affiliate_id = %d AND status = 'pending' 
    ORDER BY request_date DESC LIMIT 1",
    $affiliate->id
));
?>

<style>
/* Horizontal WooCommerce Account Menu */
.woocommerce-MyAccount-navigation {
    width: 100% !important;
    float: none !important;
    margin-bottom: 30px !important;
}

.woocommerce-MyAccount-navigation ul {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 10px !important;
    list-style: none !important;
    padding: 0 !important;
    margin: 0 !important;
    border: none !important;
    justify-content: center !important;
}

.woocommerce-MyAccount-navigation ul li {
    flex: 0 0 auto !important;
    margin: 0 !important;
    border: none !important;
}

.woocommerce-MyAccount-navigation ul li a {
    display: block !important;
    padding: 12px 24px !important;
    background: #f3f4f6 !important;
    color: #4b5563 !important;
    text-decoration: none !important;
    border-radius: 8px !important;
    transition: all 0.3s ease !important;
    font-weight: 500 !important;
    border: 2px solid transparent !important;
}

.woocommerce-MyAccount-navigation ul li a:hover {
    background: #e5e7eb !important;
    color: #1f2937 !important;
    transform: translateY(-2px) !important;
}

.woocommerce-MyAccount-navigation ul li.is-active a {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    border-color: #667eea !important;
    font-weight: 600 !important;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3) !important;
}

.woocommerce-MyAccount-content {
    width: 100% !important;
    float: none !important;
}

/* Responsive: Stack on mobile */
@media (max-width: 768px) {
    .woocommerce-MyAccount-navigation ul {
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .woocommerce-MyAccount-navigation ul li {
        width: 100% !important;
    }

    .woocommerce-MyAccount-navigation ul li a {
        text-align: center !important;
    }
}

/* Modern Affiliate Dashboard Styles */
.affiliate-dashboard-modern {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Header */
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 40px;
    margin-bottom: 30px;
    color: white;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.dashboard-header h1 {
    margin: 0 0 5px 0;
    font-size: 32px;
    font-weight: 700;
    color: white;
}

.dashboard-header .subtitle {
    margin: 0;
    opacity: 0.9;
    font-size: 16px;
}

.tier-badge {
    padding: 12px 24px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 50px;
    font-weight: 600;
    font-size: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.tier-badge-large {
    padding: 16px 32px;
    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(10px);
    border-radius: 50px;
    font-weight: 700;
    font-size: 20px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    margin-top: 15px;
    display: inline-block;
}

.header-left {
    flex: 1;
}

.header-right {
    text-align: right;
}

.share-quick {
    background: rgba(255, 255, 255, 0.15);
    padding: 15px 20px;
    border-radius: 12px;
    backdrop-filter: blur(10px);
}

.share-buttons-compact {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.share-btn-mini {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    text-decoration: none;
    color: white;
}

.share-btn-mini svg {
    fill: white;
}

.share-btn-mini:hover {
    transform: scale(1.1);
}

.share-btn-mini.share-twitter { background: #1DA1F2; }
.share-btn-mini.share-facebook { background: #4267B2; }
.share-btn-mini.share-whatsapp { background: #25D366; }
.share-btn-mini.share-instagram { 
    background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); 
}

.tier-badge.tier_2 {
    background: linear-gradient(135deg, #667eea, #43a8ff);
}

.tier-badge.ambassador {
    background: linear-gradient(135deg, #f093fb, #f5576c);
}

.tier-badge-large.tier_2 {
    background: linear-gradient(135deg, #667eea, #43a8ff);
}

.tier-badge-large.ambassador {
    background: linear-gradient(135deg, #f093fb, #f5576c);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s, box-shadow 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.stat-card.highlight {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.stat-card.success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.stat-content h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    opacity: 0.8;
}

.stat-number {
    font-size: 36px;
    font-weight: 700;
    margin: 10px 0;
    line-height: 1;
}

.stat-label {
    font-size: 13px;
    opacity: 0.7;
    margin: 5px 0 0 0;
}

/* Share Section */
.share-section {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.share-section h2 {
    margin-top: 0;
    color: #333;
}

.share-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 15px;
}

.share-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    color: white;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.share-btn:hover {
    transform: translateY(-2px);
    opacity: 0.9;
}

.share-twitter { background: #1DA1F2; }
.share-facebook { background: #4267B2; }
.share-whatsapp { background: #25D366; }
.share-instagram { 
    background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); 
}

/* Payout Section */
.payout-section {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.payout-section h2 {
    margin-top: 0;
    color: #333;
}

.balance-display {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.balance-label {
    font-size: 13px;
    color: #666;
    text-transform: uppercase;
    margin: 0 0 5px 0;
}

.balance-amount {
    font-size: 32px;
    font-weight: 700;
    color: #10b981;
    margin: 0;
}

.balance-info {
    text-align: right;
}

.info-item {
    margin: 5px 0;
    font-size: 14px;
    color: #666;
}

.payout-available {
    text-align: center;
    padding: 20px 0;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 16px 40px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

/* Alerts */
.alert {
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.alert-info {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    color: #1565c0;
}

.alert-warning {
    background: #fff3e0;
    border-left: 4px solid #ff9800;
    color: #e65100;
}

/* Recent Sales */
.recent-sales-section {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.recent-sales-section h2 {
    margin-top: 0;
}

.sales-table {
    overflow-x: auto;
}

.sales-table table {
    width: 100%;
    border-collapse: collapse;
}

.sales-table th {
    text-align: left;
    padding: 12px;
    border-bottom: 2px solid #e5e7eb;
    color: #666;
    font-size: 13px;
    text-transform: uppercase;
    font-weight: 600;
}

.sales-table td {
    padding: 12px;
    border-bottom: 1px solid #f3f4f6;
}

.sales-table tr:hover {
    background: #f9fafb;
}

.commission-amount {
    color: #10b981;
    font-weight: 600;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.status-paid {
    background: #d1fae5;
    color: #065f46;
}

.status-unpaid {
    background: #fef3c7;
    color: #92400e;
}

/* Conditions Section */
.conditions-section {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    margin-bottom: 30px;
}

.conditions-section h2 {
    margin-top: 0;
}

.tier-benefits {
    margin: 20px 0;
}

.benefit-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 15px;
    margin: 10px 0;
    background: #f8f9fa;
    border-radius: 8px;
}

.benefit-icon {
    font-size: 24px;
    flex-shrink: 0;
}

.tier-upgrade {
    margin-top: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #667eea15, #764ba215);
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.btn-secondary {
    background: white;
    color: #667eea;
    border: 2px solid #667eea;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
    margin-top: 10px;
}

.btn-secondary:hover {
    background: #667eea;
    color: white;
}

/* Disclaimer */
.disclaimer-box {
    background: #f0f9ff;
    border-left: 4px solid #0284c7;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.disclaimer-box p {
    margin: 5px 0;
    font-size: 14px;
    color: #0c4a6e;
}

.disclaimer-box a {
    color: #0284c7;
    font-weight: 600;
    text-decoration: underline;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    padding: 40px;
    border-radius: 16px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.close {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 32px;
    font-weight: 300;
    color: #999;
    cursor: pointer;
    line-height: 1;
}

.close:hover {
    color: #333;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 16px;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #667eea;
}

.form-group input[readonly] {
    background: #f8f9fa;
    cursor: not-allowed;
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 13px;
}

/* Responsive */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .header-left,
    .header-right {
        text-align: center;
    }
    
    .share-quick {
        text-align: center;
        margin-top: 20px;
    }
    
    .share-buttons-compact {
        justify-content: center;
    }
    
    .tier-badge-large {
        font-size: 16px;
        padding: 12px 24px;
    }
    
    .dashboard-header h1 {
        font-size: 24px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .balance-display {
        flex-direction: column;
        text-align: center;
    }
    
    .balance-info {
        text-align: center;
    }
    
    .share-buttons {
        flex-direction: column;
    }
    
    .share-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="affiliate-dashboard-modern">
    
    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="header-content">
            <div class="header-left">
                <h1>Welcome Back, <?php echo esc_html($user->display_name); ?>!</h1>
                <p class="subtitle">Your Affiliate Code: <strong style="font-size: 18px; letter-spacing: 1px;"><?php echo esc_html($affiliate->affiliate_code); ?></strong></p>
                <button onclick="copyCode()" style="background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.4); color: white; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; margin-top: 10px; transition: all 0.3s;">
                    Copy Code
                </button>

                <?php
                // Check if user can edit code
                $can_edit_code = cas_get_tier_setting($affiliate->tier, 'allow_code_edit');

                // Check for pending code change request
                $pending_request = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_code_changes
                    WHERE affiliate_id = %d AND status = 'pending'",
                    $affiliate->id
                ));

                // Check last code change (30-day limit)
                $last_change = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}affiliate_code_changes
                    WHERE affiliate_id = %d AND status = 'approved'
                    ORDER BY requested_at DESC LIMIT 1",
                    $affiliate->id
                ));

                $days_since_change = 999;
                if ($last_change) {
                    $days_since_change = floor((time() - strtotime($last_change->requested_at)) / 86400);
                }

                $can_request_change = $can_edit_code && !$pending_request && $days_since_change >= 30;
                ?>

                <?php if ($can_request_change): ?>
                    <a href="<?php echo wc_get_account_endpoint_url('affiliate-dashboard'); ?>#codeChangeSection" style="display: inline-block; background: rgba(245, 158, 11, 0.3); border: 2px solid rgba(245, 158, 11, 0.6); color: white; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; margin-top: 10px; margin-left: 10px; text-decoration: none; transition: all 0.3s;">
                        Edit Code
                    </a>
                <?php else: ?>
                    <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(239, 68, 68, 0.2); border: 2px solid rgba(239, 68, 68, 0.4); color: white; padding: 8px 16px; border-radius: 6px; margin-top: 10px; margin-left: 10px;">
                        <span style="font-size: 18px;">⚠</span>
                        <span style="font-size: 13px;">
                            <?php
                            if (!$can_edit_code) {
                                echo 'Code editing not available';
                            } elseif ($pending_request) {
                                echo 'Request pending approval';
                            } elseif ($days_since_change < 30) {
                                $days_remaining = 30 - $days_since_change;
                                echo "Available in {$days_remaining} days";
                            }
                            ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="tier-badge-large <?php echo esc_attr($affiliate->tier); ?>">
                    <?php echo $tier_name; ?> - <?php echo $commission_rate; ?>% Commission
                </div>
            </div>
            <div class="header-right">
                <div class="share-quick">
                    <p style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;">Share your code</p>
                    <div class="share-buttons-compact">
                        <a href="https://twitter.com/intent/tweet?text=Usa%20o%20meu%20código%20<?php echo $affiliate->affiliate_code; ?>%20e%20ganha%205€%20de%20desconto!%20<?php echo home_url(); ?>" target="_blank" class="share-btn-mini share-twitter" title="Twitter">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(home_url()); ?>" target="_blank" class="share-btn-mini share-facebook" title="Facebook">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 3.667h-3.533v7.98H9.101z"/></svg>
                        </a>
                        <a href="https://wa.me/?text=Usa%20o%20meu%20código%20<?php echo $affiliate->affiliate_code; ?>%20e%20ganha%205€%20de%20desconto!%20<?php echo home_url(); ?>" target="_blank" class="share-btn-mini share-whatsapp" title="WhatsApp">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                        </a>
                        <button onclick="copyShareText('<?php echo esc_js($affiliate->affiliate_code); ?>')" class="share-btn-mini share-instagram" title="Instagram">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8 1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Mini Chart -->
    <div style="background: white; border-radius: 12px; padding: 20px; margin: 20px 0; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);">
        <?php cas_render_performance_mini_chart($affiliate->id); ?>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <?php
        // Get total paid commissions
        $total_paid = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}affiliate_payouts
            WHERE affiliate_id = %d AND status = 'paid'",
            $affiliate->id
        ));

        // Get refund count
        $refund_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_fraud_log
            WHERE affiliate_id = %d AND fraud_type = 'self_referral_blocked'",
            $affiliate->id
        ));
        ?>

        <!-- Total Referrals -->
        <div class="stat-card">
            <div class="stat-content">
                <h3>Total Referrals</h3>
                <div class="stat-number"><?php echo number_format($total_referrals); ?></div>
                <p class="stat-label">all time uses</p>
            </div>
        </div>

        <!-- Total Sales -->
        <div class="stat-card">
            <div class="stat-content">
                <h3>Total Sales</h3>
                <div class="stat-number"><?php echo number_format($affiliate->total_sales, 2); ?>€</div>
                <p class="stat-label">generated by you</p>
            </div>
        </div>

        <!-- Unpaid Commission -->
        <div class="stat-card highlight">
            <div class="stat-content">
                <h3>Unpaid Balance</h3>
                <div class="stat-number"><?php echo cas_format_currency($affiliate->unpaid_commission); ?></div>
                <p class="stat-label"><?php echo $commission_rate; ?>% commission</p>
            </div>
        </div>

        <!-- Total Paid -->
        <div class="stat-card success">
            <div class="stat-content">
                <h3>Total Paid</h3>
                <div class="stat-number"><?php echo number_format($total_paid, 2); ?>€</div>
                <p class="stat-label">withdrawn successfully</p>
            </div>
        </div>

    </div>
    
    <!-- Remove Share Section - moved to header -->
    
    <!-- Payout Section -->
    <div class="payout-section">
        <h2>Commission Withdrawal</h2>
        
        <div class="balance-display">
            <div>
                <p class="balance-label">Available Balance</p>
                <p class="balance-amount"><?php echo cas_format_currency($affiliate->unpaid_commission); ?></p>
            </div>
            <div class="balance-info">
                <p class="info-item">Minimum: <?php echo $min_payout; ?>€</p>
                <p class="info-item">Timeline: <?php echo $payment_days; ?></p>
            </div>
        </div>
        
        <?php if ($pending_payout): ?>
            <div class="alert alert-info">
                <strong>Pending Request</strong><br>
                You requested a withdrawal of <strong><?php echo number_format($pending_payout->amount, 2); ?>€</strong> 
                on <?php echo date('d/m/Y', strtotime($pending_payout->request_date)); ?>.<br>
                <small>You will receive it within <?php echo $payment_days; ?>.</small>
            </div>
        <?php elseif ($can_request): ?>
            <div class="payout-available">
                <button class="btn-primary" onclick="openPayoutModal()">
                    Request Transfer
                </button>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <strong>Minimum Not Reached</strong><br>
                You need <?php echo $min_payout; ?>€ to request a withdrawal.<br>
                Missing <strong><?php echo number_format($min_payout - $affiliate->unpaid_commission, 2); ?>€</strong>.
            </div>
        <?php endif; ?>
        
        <div class="disclaimer-box">
            <p><strong>Important:</strong> Minimum withdrawal of <strong>20€ for Tier I</strong>. Other tiers have no minimum.</p>
            <?php 
            $terms_page = get_option('wp_page_for_privacy_policy'); // Try privacy policy first
            if (!$terms_page) {
                // Fallback: search for terms page
                $terms_page = get_page_by_path('terms-of-service');
                if (!$terms_page) {
                    $terms_page = get_page_by_path('terms-and-conditions');
                }
                if ($terms_page) {
                    $terms_page = $terms_page->ID;
                }
            }
            
            if ($terms_page): 
                $terms_url = get_permalink($terms_page);
            else:
                $terms_url = home_url('/terms-of-service/');
            endif;
            ?>
            <p>Check our <a href="<?php echo esc_url($terms_url); ?>" target="_blank">Terms and Conditions</a> for more information about the affiliate program.</p>
        </div>
    </div>

    <!-- Code Change Request Section -->
    <div id="codeChangeSection" class="payout-section">
        <h2>Affiliate Code Management</h2>

        <?php
        // Check if user can edit code (tier setting)
        $can_edit_code = cas_get_tier_setting($affiliate->tier, 'allow_code_edit');

        // Check for pending code change request
        $pending_code_change = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}affiliate_code_changes
            WHERE affiliate_id = %d AND status = 'pending'
            ORDER BY requested_at DESC LIMIT 1",
            $affiliate->id
        ));

        // Check last approved change date (for 30-day limit)
        $last_change = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}affiliate_code_changes
            WHERE affiliate_id = %d AND status = 'approved'
            ORDER BY requested_at DESC LIMIT 1",
            $affiliate->id
        ));

        $can_request = true;
        $reason = '';

        if (!$can_edit_code) {
            $can_request = false;
            $reason = 'Your tier does not allow code changes.';
        } elseif ($pending_code_change) {
            $can_request = false;
            $reason = 'You already have a pending code change request.';
        } elseif ($last_change) {
            $days_since_change = floor((time() - strtotime($last_change->requested_at)) / 86400);
            if ($days_since_change < 30) {
                $can_request = false;
                $days_remaining = 30 - $days_since_change;
                $reason = "You can request a code change again in {$days_remaining} days.";
            }
        }
        ?>

        <div class="balance-display">
            <div>
                <p class="balance-label">Current Code</p>
                <p class="balance-amount" style="color: #667eea; font-size: 28px;"><?php echo esc_html($affiliate->affiliate_code); ?></p>
            </div>
            <div class="balance-info">
                <p class="info-item">Tier: <?php echo esc_html($tier_name); ?></p>
                <p class="info-item">Code Changes: <?php echo $can_edit_code ? 'Allowed (1x/month)' : 'Not Allowed'; ?></p>
            </div>
        </div>

        <?php if ($pending_code_change): ?>
            <div class="alert alert-info">
                <strong>Pending Request</strong><br>
                You requested to change your code to <strong><?php echo esc_html($pending_code_change->new_code); ?></strong>
                on <?php echo date('d/m/Y', strtotime($pending_code_change->requested_at)); ?>.<br>
                <small>An administrator will review your request shortly.</small>
            </div>
        <?php elseif ($can_request): ?>
            <div class="payout-available">
                <button class="btn-primary" onclick="openCodeChangeModal()" style="background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);">
                    Request Code Change
                </button>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <strong>Code Change Not Available</strong><br>
                <?php echo $reason; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Sales -->
    <?php if (!empty($recent_referrals)): ?>
    <div class="recent-sales-section">
        <h2>Recent Sales</h2>
        <div class="sales-table">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Order</th>
                        <th>Total</th>
                        <th>Commission</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_referrals as $ref): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($ref->created_at)); ?></td>
                        <td><strong>#<?php echo $ref->order_id; ?></strong></td>
                        <td><?php echo number_format($ref->order_total, 2); ?>€</td>
                        <td class="commission-amount">+<?php echo number_format($ref->commission_amount, 2); ?>€</td>
                        <td>
                            <span class="status-badge status-<?php echo $ref->status; ?>">
                                <?php echo $ref->status == 'paid' ? 'Paid' : 'Pending'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Analytics Dashboard with Charts -->
    <?php cas_render_analytics_dashboard($affiliate->id); ?>

    <!-- Tier Info & Conditions -->
    <div class="conditions-section">
        <h2>Program Conditions</h2>
        
        <div class="tier-benefits">
            <div class="benefit-item">
                <span class="benefit-icon">✓</span>
                <div>
                    <strong>Commission:</strong> <?php echo $commission_rate; ?>% on all sales generated by your code
                </div>
            </div>
            
            <div class="benefit-item">
                <span class="benefit-icon">✓</span>
                <div>
                    <strong>Minimum withdrawal:</strong> 
                    <?php echo ($affiliate->tier === 'tier_1') ? '20€ for Tier I' : 'No minimum for your tier'; ?>
                </div>
            </div>
            
            <div class="benefit-item">
                <span class="benefit-icon">✓</span>
                <div>
                    <strong>Payment timeline:</strong> 
                    You will receive your money within <?php echo $payment_days; ?> after request
                </div>
            </div>
            
            <div class="benefit-item">
                <span class="benefit-icon">✓</span>
                <div>
                    <strong>Code discount:</strong> 5€ discount per use (each customer can use once)
                </div>
            </div>
            
            <div class="benefit-item">
                <span class="benefit-icon">✓</span>
                <div>
                    <strong>Validity:</strong> Your code is valid indefinitely and does not expire
                </div>
            </div>
        </div>
        
        <div class="tier-upgrade">
            <?php if ($affiliate->tier === 'tier_1'): ?>
                <p><strong>Want to upgrade your Tier?</strong></p>
                <p>Discover the benefits of Tier II (15% commission) or Ambassador (20% commission) with exclusive advantages!</p>
                <a href="https://thecouplesbrand.com/influencers-program/#tiers" class="btn-secondary">Learn More About Tiers</a>
            <?php else: ?>
                <p><strong>Congratulations!</strong> You are a <?php echo $tier_name; ?> member with premium benefits.</p>
            <?php endif; ?>
        </div>
        
        <div class="disclaimer-box" style="margin-top: 20px;">
            <p><strong>Terms and Conditions:</strong></p>
            <p>For complete information about the affiliate program, check our <a href="https://thecouplesbrand.com/terms-and-conditions" target="_blank">Terms and Conditions</a>.</p>
        </div>
        
        <!-- Support Contact -->
        <div style="margin-top: 30px; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; color: white; text-align: center;">
            <h3 style="margin: 0 0 10px 0;">Need Help?</h3>
            <p style="margin: 0; opacity: 0.9;">Have questions about your account or need support?</p>
            <p style="margin: 15px 0 0 0;">
                <a href="mailto:support@thecouplesbrand.com" style="color: white; text-decoration: underline; font-weight: 600;">support@thecouplesbrand.com</a>
            </p>
        </div>
    </div>
    
</div>

<!-- Payout Modal -->
<div id="payoutModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closePayoutModal()">&times;</span>
        <h2>Request Transfer</h2>
        
        <form id="payoutForm">
            <div class="form-group">
                <label>Amount to Transfer</label>
                <input type="text" value="<?php echo cas_format_currency($affiliate->unpaid_commission); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label>Payment Method *</label>
                <select name="payment_method" required>
                    <option value="">Select...</option>
                    <option value="bank_transfer">Bank Transfer (NIB)</option>
                    <option value="mbway">MB Way</option>
                    <option value="paypal">PayPal</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Payment Details *</label>
                <textarea name="payment_details" rows="4" placeholder="Ex: NIB (21 digits), MB Way phone number, or PayPal email" required></textarea>
                <small>Make sure the details are correct!</small>
            </div>
            
            <div class="form-group">
                <label>Additional Notes (optional)</label>
                <textarea name="notes" rows="2" placeholder="Any extra information you want to share..."></textarea>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%;">Confirm Request</button>
            <div id="payoutMessage" style="margin-top: 15px;"></div>
        </form>
    </div>
</div>

<!-- Code Change Modal -->
<div id="codeChangeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeCodeChangeModal()">&times;</span>
        <h2>Request Code Change</h2>

        <form id="codeChangeForm">
            <div class="form-group">
                <label>Current Code</label>
                <input type="text" value="<?php echo esc_attr($affiliate->affiliate_code); ?>" readonly>
            </div>

            <div class="form-group">
                <label>New Code *</label>
                <input type="text" name="new_code" id="new_code" pattern="[A-Z0-9]{5,15}" placeholder="MYNEWCODE" required maxlength="15" style="text-transform: uppercase;">
                <small>5-15 characters, uppercase letters and numbers only</small>
            </div>

            <div class="form-group">
                <label>Reason for Change *</label>
                <textarea name="reason" rows="3" placeholder="Why do you want to change your affiliate code?" required></textarea>
            </div>

            <div class="form-group" style="background: #fff3cd; padding: 15px; border-radius: 6px; border-left: 4px solid #ffc107;">
                <p style="margin: 0; font-size: 13px; color: #856404;">
                    <strong>Important:</strong> Code changes are limited to once every 30 days. Your new code must be unique and not already in use.
                </p>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);">Submit Request</button>
            <div id="codeChangeMessage" style="margin-top: 15px;"></div>
        </form>
    </div>
</div>

<script>
// Copy promo code
function copyCode() {
    const code = '<?php echo esc_js($affiliate->affiliate_code); ?>';
    navigator.clipboard.writeText(code).then(() => {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '✓ Copied!';
        btn.style.background = 'rgba(76, 175, 80, 0.5)';
        btn.style.borderColor = 'rgba(76, 175, 80, 0.7)';

        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '';
            btn.style.borderColor = '';
        }, 2000);
    }).catch(err => {
        alert('Code: ' + code + '\n\nPlease copy this code manually.');
    });
}

// Copy share text for Instagram
function copyShareText(code) {
    const text = `Usa o meu código ${code} e ganha 5€ de desconto!\n\nCompra agora em: <?php echo home_url(); ?>`;
    navigator.clipboard.writeText(text).then(() => {
        alert('✓ Texto copiado! Agora cola na tua publicação ou story do Instagram.');
    }).catch(err => {
        alert('Texto para partilhar:\n\n' + text);
    });
}

// Open/close payout modal
function openPayoutModal() {
    document.getElementById('payoutModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closePayoutModal() {
    document.getElementById('payoutModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close modal on ESC or backdrop click
window.onclick = function(event) {
    const payoutModal = document.getElementById('payoutModal');
    const codeChangeModal = document.getElementById('codeChangeModal');
    if (event.target === payoutModal) {
        closePayoutModal();
    }
    if (event.target === codeChangeModal) {
        closeCodeChangeModal();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePayoutModal();
        closeCodeChangeModal();
    }
});

// Handle payout form submission
document.getElementById('payoutForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ Enviando...';
    btn.disabled = true;
    
    const formData = new FormData(this);
    formData.append('action', 'request_affiliate_payout');
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        const msg = document.getElementById('payoutMessage');
        if (data.success) {
            msg.innerHTML = '<div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; border-radius: 6px; color: #065f46;">✓ ' + data.data + '</div>';
            setTimeout(() => location.reload(), 2000);
        } else {
            msg.innerHTML = '<div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 6px; color: #991b1b;">✗ ' + data.data + '</div>';
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(err => {
        const msg = document.getElementById('payoutMessage');
        msg.innerHTML = '<div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 6px; color: #991b1b;">✗ Erro de conexão. Tenta novamente.</div>';
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
});

// Open/close code change modal
function openCodeChangeModal() {
    document.getElementById('codeChangeModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeCodeChangeModal() {
    document.getElementById('codeChangeModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Handle code change form submission
document.getElementById('codeChangeForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ Submitting...';
    btn.disabled = true;

    const formData = new FormData(this);
    formData.append('action', 'request_code_change');

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        const msg = document.getElementById('codeChangeMessage');
        if (data.success) {
            msg.innerHTML = '<div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; border-radius: 6px; color: #065f46;">✓ ' + data.data + '</div>';
            setTimeout(() => location.reload(), 2000);
        } else {
            msg.innerHTML = '<div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 6px; color: #991b1b;">✗ ' + data.data + '</div>';
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(err => {
        const msg = document.getElementById('codeChangeMessage');
        msg.innerHTML = '<div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 6px; color: #991b1b;">✗ Connection error. Please try again.</div>';
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
});

// Auto-uppercase new code input
document.getElementById('new_code').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
});

// Update payment details placeholder based on method
document.querySelector('select[name="payment_method"]').addEventListener('change', function() {
    const method = this.value;
    const detailsField = document.querySelector('textarea[name="payment_details"]');
    
    if (method === 'bank_transfer') {
        detailsField.placeholder = 'Insere o teu NIB (21 dígitos)';
    } else if (method === 'mbway') {
        detailsField.placeholder = 'Insere o teu número de telemóvel';
    } else if (method === 'paypal') {
        detailsField.placeholder = 'Insere o teu email PayPal';
    }
});
</script>
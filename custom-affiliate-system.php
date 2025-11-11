<?php
/**
 * Plugin Name: Custom Affiliate System
 * Plugin URI: https://thecouplesbrand.com
 * Description: Complete affiliate system with auto-registration, coupon generation, commission tracking, and modern dashboard. Free: Tier I & II. Pro: Unlimited tiers.
 * Version: 2.0.0
 * Author: José Godinho
 * Author URI: https://thecouplesbrand.com
 * Text Domain: custom-affiliate
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Auto-update from GitHub
if (file_exists(plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php')) {
    require plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
    
    if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
        $myUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/JRG-code/custom-affiliate-system/',
            __FILE__,
            'custom-affiliate-system'
        );

        // SECURITY: GitHub token should be stored in wp-config.php or environment variable, not hardcoded
        // Define GITHUB_ACCESS_TOKEN in wp-config.php for private repository access
        if (defined('GITHUB_ACCESS_TOKEN') && GITHUB_ACCESS_TOKEN) {
            $myUpdateChecker->setAuthentication(GITHUB_ACCESS_TOKEN);
        }
        // For public repositories, authentication is not required
    }
}

// Define constants
define('CAS_VERSION', '2.0.0');
define('CAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CAS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include functions
require_once CAS_PLUGIN_DIR . 'includes/helpers.php';
require_once CAS_PLUGIN_DIR . 'includes/pro-license.php';
require_once CAS_PLUGIN_DIR . 'includes/fraud-detection.php';
require_once CAS_PLUGIN_DIR . 'includes/scheduled-payments.php';
require_once CAS_PLUGIN_DIR . 'includes/mobile-widgets.php';
require_once CAS_PLUGIN_DIR . 'includes/dashboard-charts.php';

class Custom_Affiliate_System {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
        
        // CRITICAL: Force register settings immediately
        add_action('admin_init', array($this, 'force_register_settings'), 1);
    }
    
    public function force_register_settings() {
        // Force register immediately to prevent "not in allowed options" error
        register_setting('cas_settings_group', 'cas_settings', 'cas_sanitize_settings');
        register_setting('cas_settings_group', 'cas_debug_enabled');
        register_setting('cas_settings_group', 'cas_pro_license_key');

        // Register settings sections and fields
        if (!cas_is_pro_active()) {
            add_settings_section('cas_license_section', '🔐 Pro License', 'cas_license_section_callback', 'cas-settings');
            add_settings_field('pro_license_key', 'License Key', 'cas_license_key_field_callback', 'cas-settings', 'cas_license_section');
        }

        // General Section
        add_settings_section('cas_general_section', '⚙️ General Settings', 'cas_general_section_callback', 'cas-settings');
        add_settings_field('currency_symbol', 'Currency Symbol', 'cas_currency_symbol_field_callback', 'cas-settings', 'cas_general_section');
        add_settings_field('support_email', 'Support Email', 'cas_support_email_field_callback', 'cas-settings', 'cas_general_section');
        add_settings_field('auto_create_affiliate', 'Auto-Create Affiliates', 'cas_auto_create_affiliate_field_callback', 'cas-settings', 'cas_general_section');
        add_settings_field('default_tier', 'Default Tier', 'cas_default_tier_field_callback', 'cas-settings', 'cas_general_section');
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
    
    public function activate() {
        $this->create_tables();
        $this->create_pages();

        // IMPORTANT: Only initialize settings on FIRST activation, never on updates
        $plugin_version = get_option('cas_plugin_version', false);

        if ($plugin_version === false) {
            // First time activation - initialize default settings
            cas_init_default_settings();
            update_option('cas_plugin_version', CAS_VERSION);
        } else {
            // Plugin update - preserve existing settings and just update version
            update_option('cas_plugin_version', CAS_VERSION);

            // Backup current settings (in case user needs to restore)
            $current_settings = get_option('cas_settings', array());
            if (!empty($current_settings)) {
                update_option('cas_settings_backup', $current_settings);
                update_option('cas_settings_backup_date', current_time('mysql'));
            }
        }

        // Schedule automatic payouts
        cas_schedule_automatic_payouts();

        flush_rewrite_rules();
    }

    public function deactivate() {
        // Clear scheduled payouts
        $timestamp = wp_next_scheduled('cas_process_automatic_payouts');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'cas_process_automatic_payouts');
        }

        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Affiliates table 
        $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affiliates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            affiliate_code varchar(50) NOT NULL UNIQUE,
            commission_rate decimal(5,2) DEFAULT 10.00,
            tier varchar(20) DEFAULT 'tier_1',
            status varchar(20) DEFAULT 'active',
            total_sales decimal(10,2) DEFAULT 0.00,
            total_commission decimal(10,2) DEFAULT 0.00,
            paid_commission decimal(10,2) DEFAULT 0.00,
            unpaid_commission decimal(10,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY affiliate_code (affiliate_code),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Referrals table
        $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affiliate_referrals (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            affiliate_id bigint(20) NOT NULL,
            order_id bigint(20) NOT NULL,
            coupon_code varchar(50) NOT NULL,
            order_total decimal(10,2) NOT NULL,
            commission_amount decimal(10,2) NOT NULL,
            commission_rate decimal(5,2) NOT NULL,
            status varchar(20) DEFAULT 'unpaid',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY order_id (order_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Payouts table
        $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affiliate_payouts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            affiliate_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            method varchar(50) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            request_date datetime DEFAULT CURRENT_TIMESTAMP,
            paid_date datetime NULL,
            notes text,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY status (status)
        ) $charset_collate;";

        // Code Change Requests table
        $sql4 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affiliate_code_changes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            affiliate_id bigint(20) NOT NULL,
            old_code varchar(50) NOT NULL,
            new_code varchar(50) NOT NULL,
            reason text NOT NULL,
            status varchar(20) DEFAULT 'pending',
            requested_at datetime DEFAULT CURRENT_TIMESTAMP,
            reviewed_at datetime NULL,
            reviewed_by bigint(20) NULL,
            admin_notes text NULL,
            PRIMARY KEY (id),
            KEY affiliate_id (affiliate_id),
            KEY status (status)
        ) $charset_collate;";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
    }
    
    private function create_pages() {
        // Create registration page
        $reg_page = get_page_by_path('become-an-affiliate');
        if (!$reg_page) {
            wp_insert_post(array(
                'post_title' => 'Become an Affiliate',
                'post_content' => '[affiliate_registration]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'become-an-affiliate'
            ));
        }
    }
    
    public function init() {
        // Register shortcodes
        add_shortcode('affiliate_registration', array($this, 'registration_shortcode'));

        // Hooks
        add_action('user_register', array($this, 'auto_create_affiliate'), 10, 1);
        add_action('woocommerce_created_customer', array($this, 'auto_create_affiliate'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this, 'track_commission'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'track_commission'), 10, 1);
        add_action('woocommerce_order_status_refunded', array($this, 'handle_refund'), 10, 1);
        add_action('woocommerce_order_status_cancelled', array($this, 'handle_refund'), 10, 1);
        add_action('woocommerce_order_fully_refunded', array($this, 'handle_refund'), 10, 1);
        add_action('init', array($this, 'process_registration'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('wp_ajax_request_affiliate_payout', array($this, 'handle_payout_request'));
        add_action('wp_ajax_request_code_change', array($this, 'handle_code_change_request'));
        add_action('wp_ajax_toggle_affiliate_status', array($this, 'toggle_status_ajax'));
        add_action('wp_ajax_export_affiliate_data', array($this, 'export_data'));
        add_action('wp_ajax_cas_send_test_welcome_email', array($this, 'send_test_welcome_email'));

        // Scheduled email hook (10-second delay)
        add_action('cas_send_welcome_email', array($this, 'send_welcome_email_scheduled'), 10, 2);

        // Fraud detection hooks
        add_action('user_register', 'cas_store_registration_ip', 10, 1);
        add_action('woocommerce_created_customer', 'cas_store_registration_ip', 10, 1);

        // Scheduled payments hook
        add_action('cas_process_automatic_payouts', 'cas_process_automatic_payouts');

        // WooCommerce My Account customization
        add_action('init', array($this, 'add_my_account_endpoints'));
        add_filter('woocommerce_account_menu_items', array($this, 'custom_my_account_menu'), 999);
        add_action('woocommerce_account_affiliate-dashboard_endpoint', array($this, 'affiliate_dashboard_endpoint_content'));
        add_action('woocommerce_account_dashboard_endpoint', array($this, 'dashboard_endpoint_content'));
        
        // Redirects
        add_action('template_redirect', array($this, 'handle_my_account_redirects'));
        add_filter('login_redirect', array($this, 'redirect_after_login'), 10, 3);
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    // === MY ACCOUNT CUSTOMIZATION ===
    
    public function add_my_account_endpoints() {
        add_rewrite_endpoint('affiliate-dashboard', EP_ROOT | EP_PAGES);
        flush_rewrite_rules();
    }
    
    public function custom_my_account_menu($items) {
        unset($items['downloads']);
        unset($items['dashboard']);
        
        $new_items = array();
        $new_items['affiliate-dashboard'] = __('Influencer Dashboard', 'custom-affiliate');
        $new_items['orders'] = __('Orders', 'woocommerce');
        $new_items['edit-address'] = __('Addresses', 'woocommerce');
        $new_items['edit-account'] = __('Account Details', 'woocommerce');
        $new_items['customer-logout'] = __('Logout', 'woocommerce');
        
        return $new_items;
    }
    
    public function dashboard_endpoint_content() {
        global $wpdb;
        $user = wp_get_current_user();
        
        $affiliate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}affiliates WHERE user_id = %d",
            get_current_user_id()
        ));
        
        $total_orders = wc_get_customer_order_count(get_current_user_id());
        
        ?>
        <div class="modern-dashboard-overview">
            <h2>Hello, <?php echo esc_html($user->display_name); ?>! 👋</h2>
            <p>Welcome to your account. Here you can manage your orders and details.</p>
            
            <?php if ($affiliate): ?>
            <div class="quick-stats">
                <div class="stat-box">
                    <span class="stat-icon">🎟️</span>
                    <div>
                        <strong>Your Code</strong>
                        <p class="stat-value"><?php echo esc_html($affiliate->affiliate_code); ?></p>
                    </div>
                </div>
                
                <div class="stat-box">
                    <span class="stat-icon">💰</span>
                    <div>
                        <strong>Commissions to Receive</strong>
                        <p class="stat-value"><?php echo number_format($affiliate->unpaid_commission, 2); ?>€</p>
                    </div>
                </div>
                
                <div class="stat-box">
                    <span class="stat-icon">📦</span>
                    <div>
                        <strong>Orders</strong>
                        <p class="stat-value"><?php echo $total_orders; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-actions">
                <a href="<?php echo wc_get_account_endpoint_url('orders'); ?>" class="button">View Orders</a>
                <a href="<?php echo wc_get_account_endpoint_url('affiliate-dashboard'); ?>" class="button button-primary">Influencer Dashboard</a>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .modern-dashboard-overview { padding: 20px 0; }
        .modern-dashboard-overview h2 { margin-bottom: 10px; }
        .quick-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
        .stat-box { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 15px; }
        .stat-icon { font-size: 32px; }
        .stat-value { font-size: 24px; font-weight: bold; color: #667eea; margin: 5px 0 0 0; }
        .dashboard-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .dashboard-actions .button { padding: 12px 24px; text-decoration: none; }
        </style>
        <?php
    }
    
    public function affiliate_dashboard_endpoint_content() {
        $template_file = CAS_PLUGIN_DIR . 'templates/dashboard.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            echo '<p>Dashboard template not found.</p>';
        }
    }
    
    public function handle_my_account_redirects() {
        if (!is_user_logged_in() && is_account_page()) {
            wp_redirect(wp_login_url(wc_get_page_permalink('myaccount')));
            exit;
        }
    }
    
    public function redirect_after_login($redirect_to, $request, $user) {
        if (isset($user->roles) && is_array($user->roles)) {
            return wc_get_page_permalink('myaccount');
        }
        return $redirect_to;
    }
    
    public function enqueue_frontend_assets() {
        if (is_account_page()) {
            wp_enqueue_style('cas-my-account', CAS_PLUGIN_URL . 'assets/my-account.css', array(), CAS_VERSION);
            wp_enqueue_script('cas-my-account', CAS_PLUGIN_URL . 'assets/my-account.js', array('jquery'), CAS_VERSION, true);
            
            wp_localize_script('cas-my-account', 'casMyAccount', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('payout_request')
            ));
        }
    }
    
    // === AUTO-CREATE AFFILIATE ===
    
    public function auto_create_affiliate($user_id) {
        global $wpdb;

        // Check if auto-create is enabled
        if (!cas_is_auto_create_affiliate_enabled()) {
            return;
        }

        // Check if affiliate already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}affiliates WHERE user_id = %d",
            $user_id
        ));

        if ($existing) {
            return; // Affiliate already exists
        }

        // FRAUD DETECTION: Check for duplicate accounts
        $duplicates = cas_detect_duplicate_accounts($user_id);
        if (!empty($duplicates)) {
            // Log but don't block - admin will review
            cas_log_fraud_attempt($user_id, 'duplicate_account', array(
                'severity' => 'medium',
                'duplicates_found' => count($duplicates),
                'details' => $duplicates
            ));
        }

        $user = get_userdata($user_id);
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

        $commission_rate = cas_get_tier_setting('tier_1', 'commission');
        $status = cas_is_auto_approve_enabled() ? 'active' : 'pending';

        $wpdb->insert(
            $wpdb->prefix . 'affiliates',
            array(
                'user_id' => $user_id,
                'affiliate_code' => $affiliate_code,
                'commission_rate' => $commission_rate,
                'tier' => 'tier_1',
                'status' => $status
            ),
            array('%d', '%s', '%f', '%s', '%s')
        );

        $coupon_discount = cas_get_tier_setting('tier_1', 'coupon_discount');
        $this->create_coupon($affiliate_code, $user_id, $coupon_discount);

        // Schedule welcome email with 10-second delay if enabled
        if (cas_is_send_welcome_email_enabled()) {
            wp_schedule_single_event(time() + 10, 'cas_send_welcome_email', array($user_id, $affiliate_code));
        }

        $this->notify_admin_new_affiliate($user, $affiliate_code);
    }
    
    private function create_coupon($code, $user_id, $discount = 5) {
        $coupon = array(
            'post_title' => strtolower($code),
            'post_content' => '',
            'post_status' => 'publish',
            'post_author' => 1,
            'post_type' => 'shop_coupon'
        );
        
        $coupon_id = wp_insert_post($coupon);
        
        update_post_meta($coupon_id, 'discount_type', 'fixed_cart');
        update_post_meta($coupon_id, 'coupon_amount', $discount);
        update_post_meta($coupon_id, 'individual_use', 'yes');
        update_post_meta($coupon_id, 'usage_limit', '');
        update_post_meta($coupon_id, 'usage_limit_per_user', '1');
        update_post_meta($coupon_id, 'expiry_date', '');
        update_post_meta($coupon_id, 'free_shipping', 'no');
        update_post_meta($coupon_id, '_affiliate_user_id', $user_id);
        
        return $coupon_id;
    }
    
    /**
     * Scheduled welcome email (called by wp_cron after 10-second delay)
     */
    public function send_welcome_email_scheduled($user_id, $code) {
        $user = get_userdata($user_id);
        if ($user) {
            $this->send_welcome_email($user, $code);
        }
    }

    private function send_welcome_email($user, $code) {
        $to = $user->user_email;
        $subject = 'Your Affiliate Code is Ready!';
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Welcome to the Influencer Program!</h2>
            <p>Hello <strong>{$user->display_name}</strong>,</p>
            <div style='background: #f0f0f0; padding: 20px; margin: 20px 0; text-align: center;'>
                <h1 style='color: #667eea; font-size: 36px;'>{$code}</h1>
                <p>Your unique promotional code</p>
            </div>
            <p>You earn 10% commission on each sale!</p>
            <p><a href='" . wc_get_account_endpoint_url('affiliate-dashboard') . "' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block;'>Go to Dashboard</a></p>
        </body>
        </html>
        ";

        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);
    }
    
    private function notify_admin_new_affiliate($user, $code) {
        $admin_email = cas_get_support_email();
        $subject = 'New Affiliate Registered!';
        $message = "
        <h2>New Affiliate!</h2>
        <p><strong>Name:</strong> {$user->display_name}</p>
        <p><strong>Email:</strong> {$user->user_email}</p>
        <p><strong>Code:</strong> {$code}</p>
        <p><a href='" . admin_url('admin.php?page=affiliate-system') . "'>View Dashboard</a></p>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);
    }
    
    // === TRACK COMMISSIONS ===
    
    public function track_commission($order_id) {
        global $wpdb;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $coupons = $order->get_coupon_codes();

        if (empty($coupons)) {
            return;
        }

        foreach ($coupons as $coupon_code) {
            $coupon = new WC_Coupon($coupon_code);
            $affiliate_user_id = get_post_meta($coupon->get_id(), '_affiliate_user_id', true);

            if (!$affiliate_user_id) {
                continue;
            }

            $affiliate = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}affiliates WHERE user_id = %d AND status = 'active'",
                $affiliate_user_id
            ));

            if (!$affiliate) {
                continue;
            }

            // FRAUD DETECTION: Check for self-referral based on tier settings
            if (cas_is_self_referral($order_id, $affiliate_user_id, $affiliate->tier)) {
                // Self-referral blocked for this tier
                continue;
            }
            
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}affiliate_referrals WHERE order_id = %d",
                $order_id
            ));
            
            if ($exists) {
                continue;
            }
            
            $order_total = $order->get_total();
            $commission_rate = $affiliate->commission_rate;
            $commission_amount = ($order_total * $commission_rate) / 100;
            
            $wpdb->insert(
                $wpdb->prefix . 'affiliate_referrals',
                array(
                    'affiliate_id' => $affiliate->id,
                    'order_id' => $order_id,
                    'coupon_code' => $coupon_code,
                    'order_total' => $order_total,
                    'commission_amount' => $commission_amount,
                    'commission_rate' => $commission_rate,
                    'status' => 'unpaid'
                ),
                array('%d', '%d', '%s', '%f', '%f', '%f', '%s')
            );
            
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}affiliates 
                SET total_sales = total_sales + %f,
                    total_commission = total_commission + %f,
                    unpaid_commission = unpaid_commission + %f
                WHERE id = %d",
                $order_total,
                $commission_amount,
                $commission_amount,
                $affiliate->id
            ));
            
            $this->send_commission_email($affiliate_user_id, $coupon_code, $order_total, $commission_amount);
        }
    }

    // === HANDLE REFUNDS/CANCELLATIONS ===

    public function handle_refund($order_id) {
        global $wpdb;

        // Check if this order had commission tracked
        $referrals = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}affiliate_referrals
            WHERE order_id = %d
        ", $order_id));

        if (empty($referrals)) {
            return; // No commission to refund
        }

        foreach ($referrals as $referral) {
            // Only process if commission hasn't been paid yet
            if ($referral->status === 'unpaid') {
                // Deduct commission from affiliate's unpaid balance
                $wpdb->query($wpdb->prepare("
                    UPDATE {$wpdb->prefix}affiliates
                    SET total_commission = total_commission - %f,
                        unpaid_commission = unpaid_commission - %f,
                        total_sales = total_sales - %f
                    WHERE id = %d
                ", $referral->commission_amount, $referral->commission_amount, $referral->order_total, $referral->affiliate_id));

                // Delete the referral record
                $wpdb->delete(
                    $wpdb->prefix . 'affiliate_referrals',
                    array('id' => $referral->id),
                    array('%d')
                );

                // Send notification email to affiliate
                $affiliate = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}affiliates WHERE id = %d",
                    $referral->affiliate_id
                ));

                if ($affiliate) {
                    $this->send_refund_notification_email($affiliate->user_id, $referral->commission_amount, $order_id);
                }

                cas_debug_log("Refund processed: Order #{$order_id}, Commission deducted: €{$referral->commission_amount}, Affiliate ID: {$referral->affiliate_id}", 'info');
            } else {
                // Commission was already paid - log as warning
                cas_debug_log("WARNING: Order #{$order_id} refunded but commission was already paid to affiliate #{$referral->affiliate_id}. Manual intervention required.", 'warning');

                // Send alert to admin
                $this->send_paid_commission_refund_alert($referral, $order_id);
            }
        }
    }

    private function send_refund_notification_email($user_id, $commission_amount, $order_id) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $to = $user->user_email;
        $subject = 'Order Refunded - Commission Adjusted';
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2 style='color: #ef4444;'>Order Refunded</h2>
            <p>Hello <strong>{$user->display_name}</strong>,</p>
            <p>An order that generated commission for you has been refunded or cancelled.</p>
            <div style='background: #fee2e2; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong>Order ID:</strong> #{$order_id}</p>
                <p style='margin: 5px 0;'><strong>Commission Deducted:</strong> €" . number_format($commission_amount, 2) . "</p>
            </div>
            <p>Your unpaid commission balance has been adjusted accordingly.</p>
            <p><a href='" . wc_get_account_endpoint_url('affiliate-dashboard') . "' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>View Dashboard →</a></p>
        </body>
        </html>
        ";

        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);
    }

    private function send_paid_commission_refund_alert($referral, $order_id) {
        $admin_email = cas_get_support_email();
        $subject = '⚠️ Refund Alert: Commission Already Paid';

        $message = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <div style='background: #fee2e2; border-left: 4px solid #ef4444; padding: 20px; margin-bottom: 20px;'>
                <h2 style='color: #991b1b; margin: 0;'>⚠️ Manual Intervention Required</h2>
            </div>

            <p>An order has been refunded, but the commission was already paid to the affiliate.</p>

            <div style='background: #f9fafb; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong>Order ID:</strong> #{$order_id}</p>
                <p style='margin: 5px 0;'><strong>Affiliate ID:</strong> {$referral->affiliate_id}</p>
                <p style='margin: 5px 0;'><strong>Commission Amount:</strong> €" . number_format($referral->commission_amount, 2) . "</p>
                <p style='margin: 5px 0;'><strong>Status:</strong> Already Paid</p>
            </div>

            <p><strong>Action Needed:</strong> You may need to request the commission back from the affiliate or adjust their next payout.</p>

            <p style='margin: 30px 0;'>
                <a href='" . admin_url('admin.php?page=affiliate-system') . "' style='background: #ef4444; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>
                    Review in Admin Panel →
                </a>
            </p>
        </body>
        </html>
        ";

        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);
    }

    private function send_commission_email($user_id, $code, $total, $commission) {
        $user = get_userdata($user_id);
        $to = $user->user_email;
        $subject = 'New Commission Earned!';
        $message = "
        <h2>Congratulations!</h2>
        <p>Someone used your code <strong>{$code}</strong></p>
        <p><strong>Order Total:</strong> " . number_format($total, 2) . "€</p>
        <p><strong>Your Commission:</strong> " . number_format($commission, 2) . "€</p>
        <p><a href='" . wc_get_account_endpoint_url('affiliate-dashboard') . "'>View Dashboard</a></p>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);
    }
    
    // === PAYOUT REQUEST ===
    
    public function handle_payout_request() {
        global $wpdb;
        
        check_ajax_referer('payout_request', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Not authenticated');
        }
        
        $affiliate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}affiliates WHERE user_id = %d",
            $user_id
        ));
        
        if (!$affiliate) {
            wp_send_json_error('Affiliate not found');
        }
        
        $min_payout = cas_get_tier_setting($affiliate->tier, 'min_payout');
        if ($affiliate->unpaid_commission < $min_payout) {
            wp_send_json_error('Minimum amount not reached');
        }
        
        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_payouts 
            WHERE affiliate_id = %d AND status = 'pending'",
            $affiliate->id
        ));
        
        if ($pending > 0) {
            wp_send_json_error('You already have a pending request');
        }
        
        $payment_method = sanitize_text_field($_POST['payment_method']);
        $payment_details = sanitize_textarea_field($_POST['payment_details']);
        $notes = sanitize_textarea_field($_POST['notes']);
        $amount = $affiliate->unpaid_commission;
        
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_payouts',
            array(
                'affiliate_id' => $affiliate->id,
                'amount' => $amount,
                'method' => $payment_method,
                'status' => 'pending',
                'notes' => "Method: {$payment_method}\nDetails: {$payment_details}\n" . (!empty($notes) ? "Notes: {$notes}" : "")
            ),
            array('%d', '%f', '%s', '%s', '%s')
        );
        
        $user = get_userdata($user_id);
        $admin_email = cas_get_support_email();
        
        $tier_names = array('tier_1' => 'Tier I', 'tier_2' => 'Tier II', 'ambassador' => 'Ambassador');
        $tier_name = $tier_names[$affiliate->tier];
        $payment_days = cas_get_payment_timeline_text($affiliate->tier);
        
        $subject = 'New Payout Request - ' . $user->display_name;
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
        <h2>New Payout Request</h2>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <p style='margin: 5px 0;'><strong>Affiliate:</strong> {$user->display_name}</p>
            <p style='margin: 5px 0;'><strong>Customer ID:</strong> {$user_id}</p>
            <p style='margin: 5px 0;'><strong>Email:</strong> {$user->user_email}</p>
            <p style='margin: 5px 0;'><strong>Code:</strong> {$affiliate->affiliate_code}</p>
            <p style='margin: 5px 0;'><strong>Tier:</strong> {$tier_name}</p>
        </div>
        
        <hr>
        
        <div style='background: #d1fae5; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <p style='margin: 5px 0; font-size: 18px;'><strong>Amount:</strong> " . number_format($amount, 2) . "€</p>
            <p style='margin: 5px 0;'><strong>Method:</strong> {$payment_method}</p>
            <p style='margin: 5px 0;'><strong>Payment Details:</strong></p>
            <p style='margin: 5px 0; background: white; padding: 10px; border-radius: 4px;'>{$payment_details}</p>
            " . (!empty($notes) ? "<p style='margin: 10px 0 5px 0;'><strong>Notes:</strong></p><p style='margin: 0; background: white; padding: 10px; border-radius: 4px;'>{$notes}</p>" : "") . "
        </div>
        
        <p style='background: #fef3c7; padding: 15px; border-radius: 6px;'>
            <strong>Timeline:</strong> This affiliate should receive payment within <strong>{$payment_days}</strong>.
        </p>
        
        <p style='margin: 30px 0;'>
            <a href='" . admin_url('admin.php?page=affiliate-payouts') . "' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>View Pending Requests</a>
        </p>
        </body>
        </html>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);
        
        wp_send_json_success('Request sent successfully! You will receive a response soon.');
    }

    public function handle_code_change_request() {
        global $wpdb;

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Not authenticated');
        }

        $affiliate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}affiliates WHERE user_id = %d",
            $user_id
        ));

        if (!$affiliate) {
            wp_send_json_error('Affiliate not found');
        }

        // Check if tier allows code changes
        $can_edit_code = cas_get_tier_setting($affiliate->tier, 'allow_code_edit');
        if (!$can_edit_code) {
            wp_send_json_error('Your tier does not allow code changes');
        }

        // Check for pending request
        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_code_changes
            WHERE affiliate_id = %d AND status = 'pending'",
            $affiliate->id
        ));

        if ($pending > 0) {
            wp_send_json_error('You already have a pending code change request');
        }

        // Check 30-day limit
        $last_change = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}affiliate_code_changes
            WHERE affiliate_id = %d AND status = 'approved'
            ORDER BY requested_at DESC LIMIT 1",
            $affiliate->id
        ));

        if ($last_change) {
            $days_since_change = floor((time() - strtotime($last_change->requested_at)) / 86400);
            if ($days_since_change < 30) {
                $days_remaining = 30 - $days_since_change;
                wp_send_json_error("You can request a code change again in {$days_remaining} days");
            }
        }

        $new_code = strtoupper(sanitize_text_field($_POST['new_code']));
        $reason = sanitize_textarea_field($_POST['reason']);

        // Validate new code
        if (empty($new_code) || strlen($new_code) < 5 || strlen($new_code) > 15) {
            wp_send_json_error('Code must be between 5-15 characters');
        }

        if (!preg_match('/^[A-Z0-9]+$/', $new_code)) {
            wp_send_json_error('Code must contain only uppercase letters and numbers');
        }

        // Check if code is already in use
        $code_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliates WHERE affiliate_code = %s",
            $new_code
        ));

        if ($code_exists > 0) {
            wp_send_json_error('This code is already in use. Please choose another one');
        }

        // Insert request
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_code_changes',
            array(
                'affiliate_id' => $affiliate->id,
                'old_code' => $affiliate->affiliate_code,
                'new_code' => $new_code,
                'reason' => $reason,
                'status' => 'pending'
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        // Notify admin
        $user = get_userdata($user_id);
        $admin_email = cas_get_support_email();

        $subject = 'Code Change Request - ' . $user->display_name;
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
        <h2>Code Change Request</h2>

        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <p style='margin: 5px 0;'><strong>Affiliate:</strong> {$user->display_name}</p>
            <p style='margin: 5px 0;'><strong>Email:</strong> {$user->user_email}</p>
            <p style='margin: 5px 0;'><strong>Current Code:</strong> {$affiliate->affiliate_code}</p>
            <p style='margin: 5px 0;'><strong>Requested Code:</strong> {$new_code}</p>
        </div>

        <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <p style='margin: 0 0 10px 0;'><strong>Reason:</strong></p>
            <p style='margin: 0; background: white; padding: 10px; border-radius: 4px;'>{$reason}</p>
        </div>

        <p style='margin: 30px 0;'>
            <a href='" . admin_url('admin.php?page=affiliate-code-changes') . "' style='background: #f59e0b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Review Request</a>
        </p>
        </body>
        </html>
        ";

        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);

        wp_send_json_success('Request submitted successfully! An administrator will review it shortly.');
    }

    public function send_test_welcome_email() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $admin_email = get_option('admin_email');
        $user = wp_get_current_user();

        // Get custom template or use defaults
        $template = get_option('cas_welcome_email_template', array(
            'subject' => 'Welcome to Our Affiliate Program!',
            'heading' => 'Welcome to the Influencer Program!',
            'message' => "<p>Hello <strong>{affiliate_name}</strong>,</p>\n<p>You've been added to our affiliate program!</p>\n<p>You earn <strong>{commission_rate}%</strong> commission on every sale made with your code!</p>",
            'button_text' => 'Go to Dashboard',
            'footer_text' => '<p style="font-size: 12px; color: #666; margin-top: 30px;">Questions? Contact us at {support_email}</p>'
        ));

        // Replace variables with test data
        $vars = array(
            '{affiliate_name}' => $user->display_name,
            '{affiliate_code}' => 'TESTCODE123',
            '{commission_rate}' => '15',
            '{tier_name}' => 'Tier II',
            '{tier_badge}' => '💎',
            '{coupon_discount}' => '5',
            '{support_email}' => cas_get_support_email(),
            '{dashboard_url}' => wc_get_page_permalink('myaccount')
        );

        $subject = str_replace(array_keys($vars), array_values($vars), $template['subject']);
        $heading = str_replace(array_keys($vars), array_values($vars), $template['heading']);
        $message = str_replace(array_keys($vars), array_values($vars), $template['message']);
        $button_text = str_replace(array_keys($vars), array_values($vars), $template['button_text']);
        $footer = str_replace(array_keys($vars), array_values($vars), $template['footer_text']);

        $email_content = "
        <html>
        <head>
            <meta charset='UTF-8'>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: #f9fafb; padding: 30px; border-radius: 8px;'>
                <h2 style='color: #667eea; margin: 0 0 20px 0;'>{$heading}</h2>

                <div style='background: #f0f0f0; padding: 20px; margin: 20px 0; text-align: center; border-radius: 8px;'>
                    <h1 style='color: #667eea; font-size: 36px; margin: 0;'>TESTCODE123</h1>
                    <p style='margin: 10px 0 0 0;'>Your unique promotional code (TEST)</p>
                </div>

                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    {$message}
                </div>

                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$vars['{dashboard_url}']}' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>{$button_text}</a>
                </p>

                <div style='border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 30px;'>
                    {$footer}
                </div>

                <p style='background: #fef3c7; padding: 15px; border-radius: 6px; margin-top: 20px; font-size: 13px; color: #92400e;'>
                    <strong>This is a test email.</strong> In a real email, variables will be replaced with actual affiliate data.
                </p>
            </div>
        </body>
        </html>
        ";

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($admin_email, '[TEST] ' . $subject, $email_content, $headers);

        if ($sent) {
            wp_send_json_success('Test email sent to ' . $admin_email);
        } else {
            wp_send_json_error('Failed to send email. Please check your server email configuration.');
        }
    }

    // === REGISTRATION FORM ===
    
    public function registration_shortcode() {
        if (is_user_logged_in()) {
            return '<p>You are already registered. <a href="' . wc_get_page_permalink('myaccount') . '">Go to Dashboard</a></p>';
        }
        
        $template_file = CAS_PLUGIN_DIR . 'templates/registration-form.php';
        
        if (file_exists($template_file)) {
            ob_start();
            include $template_file;
            return ob_get_clean();
        }
        
        return $this->get_inline_registration_form();
    }
    
    private function get_inline_registration_form() {
        ob_start();
        ?>
        <style>
        .affiliate-reg-form {
            max-width: 500px;
            margin: 40px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .affiliate-reg-form h2 {
            text-align: center;
            color: #667eea;
            margin-bottom: 30px;
        }
        .affiliate-reg-form input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .affiliate-reg-form button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
        }
        </style>
        
        <div class="affiliate-reg-form">
            <h2>Join the Influencer Program</h2>
            <p style="text-align: center; color: #666; margin-bottom: 30px;">
                Earn 10% commission on every sale!
            </p>
            
            <form method="post" action="">
                <?php wp_nonce_field('affiliate_registration', 'affiliate_reg_nonce'); ?>
                
                <input type="text" name="full_name" placeholder="Full Name" required>
                <input type="email" name="user_email" placeholder="Email" required>
                <input type="text" name="username" placeholder="Username (for your code)" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="text" name="whatsapp" placeholder="WhatsApp (optional)">
                <input type="text" name="instagram" placeholder="Instagram (optional)">
                
                <label style="display: block; margin: 20px 0;">
                    <input type="checkbox" name="terms" required>
                    I accept the terms and conditions
                </label>
                
                <button type="submit" name="register_affiliate">Create Account and Get Code</button>
            </form>
            
            <p style="text-align: center; margin-top: 20px;">
                Already have an account? <a href="<?php echo wp_login_url(wc_get_page_permalink('myaccount')); ?>">Login</a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function process_registration() {
        if (!isset($_POST['register_affiliate'])) {
            return;
        }
        
        if (!isset($_POST['affiliate_reg_nonce']) || !wp_verify_nonce($_POST['affiliate_reg_nonce'], 'affiliate_registration')) {
            wp_redirect(add_query_arg(array('registration' => 'failed', 'error' => 'Security check failed'), wp_get_referer()));
            exit;
        }
        
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['user_email']);
        $password = $_POST['password'];
        $full_name = sanitize_text_field($_POST['full_name']);
        
        if (!validate_username($username)) {
            wp_redirect(add_query_arg(array('registration' => 'failed', 'error' => 'Invalid username'), wp_get_referer()));
            exit;
        }
        
        if (username_exists($username)) {
            wp_redirect(add_query_arg(array('registration' => 'failed', 'error' => 'Username already taken'), wp_get_referer()));
            exit;
        }
        
        if (email_exists($email)) {
            wp_redirect(add_query_arg(array('registration' => 'failed', 'error' => 'Email already registered'), wp_get_referer()));
            exit;
        }
        
        if (!is_email($email)) {
            wp_redirect(add_query_arg(array('registration' => 'failed', 'error' => 'Invalid email'), wp_get_referer()));
            exit;
        }
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            $error_message = $user_id->get_error_message();
            wp_redirect(add_query_arg(array('registration' => 'failed', 'error' => urlencode($error_message)), wp_get_referer()));
            exit;
        }
        
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $full_name,
            'first_name' => $full_name,
            'role' => 'subscriber'
        ));
        
        if (!empty($_POST['whatsapp'])) {
            update_user_meta($user_id, 'whatsapp', sanitize_text_field($_POST['whatsapp']));
        }
        
        if (!empty($_POST['instagram'])) {
            update_user_meta($user_id, 'instagram', sanitize_text_field($_POST['instagram']));
        }
        
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        do_action('wp_login', $username, get_userdata($user_id));
        
        wp_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }
    
    // === ADMIN MENU ===
    public function admin_menu() {
    add_menu_page(
        'Affiliates',
        'Affiliates',
        'manage_options',
        'affiliate-system',
        array($this, 'admin_overview_page'),
        'dashicons-groups',
        30
    );

    add_submenu_page(
        'affiliate-system',
        'Reports',
        'Reports',
        'manage_options',
        'affiliate-reports',
        array($this, 'admin_reports_page')
    );

    add_submenu_page(
        'affiliate-system',
        'Settings',
        'Settings',
        'manage_options',
        'affiliate-settings',
        array($this, 'admin_settings_page')
    );

    // Tier Management (Edit Tier I & II is FREE, Create new tiers is PRO)
    add_submenu_page(
        'affiliate-system',
        'Tier Management',
        'Tier Management',
        'manage_options',
        'affiliate-tiers',
        array($this, 'admin_tier_management_page')
    );

    // Advanced Features (PRO only)
    if (cas_is_pro_active()) {
        add_submenu_page(
            'affiliate-system',
            'Advanced Features',
            'Advanced Features ' . cas_pro_badge(),
            'manage_options',
            'affiliate-advanced',
            array($this, 'admin_advanced_features_page')
        );
    }

    add_submenu_page(
        'affiliate-system',
        'Email Affiliates',
        'Email Affiliates',
        'manage_options',
        'affiliate-email',
        array($this, 'admin_email_page')
    );

    if (cas_is_debug_enabled()) {
        add_submenu_page(
            'affiliate-system', 
            'Debug Log', 
            'Debug Log', 
            'manage_options', 
            'affiliate-debug', 
            array($this, 'admin_debug_page')
        );
    }
    
    // Pro upgrade menu item (only if not pro)
    if (!cas_is_pro_active()) {
        add_submenu_page(
            'affiliate-system',
            'Upgrade to Pro',
            '<span style="color: #f59e0b;">⭐ Upgrade to Pro</span>',
            'manage_options',
            cas_get_upgrade_url()
        );
    }
}

    
    public function admin_overview_page() {
        $file = CAS_PLUGIN_DIR . 'admin/overview.php';
        if (file_exists($file)) {
            include $file;
        }
    }
    
    public function admin_payouts_page() {
        $file = CAS_PLUGIN_DIR . 'admin/payouts.php';
        if (file_exists($file)) {
            include $file;
        }
    }
    
    public function admin_reports_page() {
        $file = CAS_PLUGIN_DIR . 'admin/reports.php';
        if (file_exists($file)) {
            include $file;
        }
    }

    public function admin_code_changes_page() {
        $file = CAS_PLUGIN_DIR . 'admin/code-changes.php';
        if (file_exists($file)) {
            include $file;
        }
    }

    public function admin_settings_page() {
        $file = CAS_PLUGIN_DIR . 'admin/settings.php';
        if (file_exists($file)) {
            include $file;
        }
    }
    
    public function admin_email_page() {
        $file = CAS_PLUGIN_DIR . 'admin/email-affiliates.php';
        if (file_exists($file)) {
            include $file;
        }
    }

    public function admin_debug_page() {
        include CAS_PLUGIN_DIR . 'admin/debug.php';
    }

    public function admin_add_affiliate_page() {
        $file = CAS_PLUGIN_DIR . 'admin/add-affiliate.php';
        if (file_exists($file)) {
            include $file;
        }
    }   

    public function admin_tier_management_page() {
        $file = CAS_PLUGIN_DIR . 'admin/tier-management.php';
        if (file_exists($file)) {
            include $file;
        }
    }

    public function admin_advanced_features_page() {
        $file = CAS_PLUGIN_DIR . 'admin/advanced-features.php';
        if (file_exists($file)) {
            include $file;
        }
    }


    // === AJAX HANDLERS ===
    
    public function toggle_status_ajax() {
        global $wpdb;
        
        $affiliate_id = intval($_POST['affiliate_id']);
        $status = sanitize_text_field($_POST['status']);
        
        $wpdb->update(
            $wpdb->prefix . 'affiliates',
            array('status' => $status),
            array('id' => $affiliate_id)
        );
        
        wp_send_json_success();
    }
    
    public function export_data() {
        global $wpdb;
        
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'affiliates';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $type . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        if ($type == 'affiliates') {
            fputcsv($output, array('ID', 'Name', 'Email', 'Code', 'Tier', 'Rate', 'Total Sales', 'Total Commission', 'Unpaid', 'Status', 'Created'));
            
            $data = $wpdb->get_results("
                SELECT a.*, u.user_email, u.display_name
                FROM {$wpdb->prefix}affiliates a
                LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
            ");
            
            foreach ($data as $row) {
                fputcsv($output, array(
                    $row->id,
                    $row->display_name,
                    $row->user_email,
                    $row->affiliate_code,
                    $row->tier,
                    $row->commission_rate . '%',
                    $row->total_sales,
                    $row->total_commission,
                    $row->unpaid_commission,
                    $row->status,
                    $row->created_at
                ));
            }
        }
        
        fclose($output);
        exit;
    }
    
    public function admin_notices() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'affiliate') === false) return;
        if ($screen->id === 'affiliates_page_affiliate-settings') return;
        
        // Pro upgrade notice
        if (!cas_is_pro_active()) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p><strong>🚀 Upgrade to Pro</strong> - Unlock Ambassador tier, unlimited custom tiers, and advanced features! 
                <a href="<?php echo esc_url(cas_get_upgrade_url()); ?>" target="_blank">Learn More →</a></p>
            </div>
            <?php
        }
        
        $status = cas_check_settings_status();
        if (!$status['configured']) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><strong>⚠️ Affiliate System Setup Required</strong></p>
                <p>Some settings are not configured yet.</p>
                <?php if (!empty($status['missing'])): ?>
                <ul style="margin-left: 20px;">
                    <?php foreach ($status['missing'] as $item): ?>
                        <li><?php echo esc_html($item); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <p><a href="<?php echo admin_url('admin.php?page=affiliate-settings'); ?>" class="button button-primary">Configure Now</a></p>
            </div>
            <?php
        }
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'affiliate') === false) return;
        wp_enqueue_style('cas-admin', CAS_PLUGIN_URL . 'assets/admin.css', array(), CAS_VERSION);
    }
}

// Initialize plugin
Custom_Affiliate_System::get_instance();

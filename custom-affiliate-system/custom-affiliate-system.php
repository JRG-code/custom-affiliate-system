<?php
/**
 * Plugin Name: Custom Affiliate System
 * Plugin URI: https://thecouplesbrand.com
 * Description: Complete affiliate system with auto-registration, coupon generation, commission tracking, and modern dashboard
 * Version: 1.0.6.1
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
        $myUpdateChecker->setAuthentication('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'); // Replace with token
    }
}

// Define constants
define('CAS_VERSION', '1.0.6.1');
define('CAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CAS_PLUGIN_URL', plugin_dir_url(__FILE__));

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
    }
    
    public function activate() {
        $this->create_tables();
        $this->create_pages();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Affiliates table - FORCE CREATE
        $sql1 = "CREATE TABLE {$wpdb->prefix}affiliates (
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
        
        // Referrals table - keep IF NOT EXISTS
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
        
        // Payouts table - keep IF NOT EXISTS
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
        
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
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
        add_action('init', array($this, 'process_registration'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_ajax_request_affiliate_payout', array($this, 'handle_payout_request'));
        add_action('wp_ajax_toggle_affiliate_status', array($this, 'toggle_status_ajax'));
        add_action('wp_ajax_export_affiliate_data', array($this, 'export_data'));
        
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
    }
    
    // === MY ACCOUNT CUSTOMIZATION ===
    
    public function add_my_account_endpoints() {
        add_rewrite_endpoint('affiliate-dashboard', EP_ROOT | EP_PAGES);
        flush_rewrite_rules();
    }
    
    public function custom_my_account_menu($items) {
        // Remove default items
        unset($items['downloads']);
        
        // Reorder and customize
        $new_items = array();
        $new_items['dashboard'] = __('Dashboard', 'woocommerce');
        $new_items['orders'] = __('Encomendas', 'woocommerce');
        $new_items['affiliate-dashboard'] = __('Dashboard Influencer', 'custom-affiliate');
        $new_items['edit-address'] = __('Endereços', 'woocommerce');
        $new_items['edit-account'] = __('Detalhes da Conta', 'woocommerce');
        $new_items['customer-logout'] = __('Sair', 'woocommerce');
        
        return $new_items;
    }
    
    public function dashboard_endpoint_content() {
        global $wpdb;
        $user = wp_get_current_user();
        
        // Get affiliate data
        $affiliate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}affiliates WHERE user_id = %d",
            get_current_user_id()
        ));
        
        $total_orders = wc_get_customer_order_count(get_current_user_id());
        
        ?>
        <div class="modern-dashboard-overview">
            <h2>Olá, <?php echo esc_html($user->display_name); ?>! 👋</h2>
            <p>Bem-vindo à tua conta. Aqui podes gerir as tuas encomendas e dados.</p>
            
            <?php if ($affiliate): ?>
            <div class="quick-stats">
                <div class="stat-box">
                    <span class="stat-icon">🎟️</span>
                    <div>
                        <strong>O Teu Código</strong>
                        <p class="stat-value"><?php echo esc_html($affiliate->affiliate_code); ?></p>
                    </div>
                </div>
                
                <div class="stat-box">
                    <span class="stat-icon">💰</span>
                    <div>
                        <strong>Comissões a Receber</strong>
                        <p class="stat-value"><?php echo number_format($affiliate->unpaid_commission, 2); ?>€</p>
                    </div>
                </div>
                
                <div class="stat-box">
                    <span class="stat-icon">📦</span>
                    <div>
                        <strong>Encomendas</strong>
                        <p class="stat-value"><?php echo $total_orders; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-actions">
                <a href="<?php echo wc_get_account_endpoint_url('orders'); ?>" class="button">Ver Encomendas</a>
                <a href="<?php echo wc_get_account_endpoint_url('affiliate-dashboard'); ?>" class="button button-primary">Dashboard Influencer</a>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .modern-dashboard-overview {
            padding: 20px 0;
        }
        .modern-dashboard-overview h2 {
            margin-bottom: 10px;
        }
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stat-icon {
            font-size: 32px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin: 5px 0 0 0;
        }
        .dashboard-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .dashboard-actions .button {
            padding: 12px 24px;
            text-decoration: none;
        }
        </style>
        <?php
    }
    
    public function affiliate_dashboard_endpoint_content() {
        // Load the modern affiliate dashboard template
        $template_file = CAS_PLUGIN_DIR . 'templates/dashboard.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            echo '<p>Dashboard template not found.</p>';
        }
    }
    
    public function handle_my_account_redirects() {
        // If not logged in and tries to access my-account -> redirect to login
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
        
        $user = get_userdata($user_id);
        $username = $user->user_login;
        
        // Generate unique code
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
        
        // Insert affiliate
        $wpdb->insert(
            $wpdb->prefix . 'affiliates',
            array(
                'user_id' => $user_id,
                'affiliate_code' => $affiliate_code,
                'commission_rate' => 10.00,
                'tier' => 'tier_1',
                'status' => 'active'
            ),
            array('%d', '%s', '%f', '%s', '%s')
        );
        
        // Create WooCommerce coupon
        $this->create_coupon($affiliate_code, $user_id, 10);
        
        // Send welcome email
        $this->send_welcome_email($user, $affiliate_code);
        
        // Notify admin
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
        update_post_meta($coupon_id, 'coupon_amount', '5');
        update_post_meta($coupon_id, 'individual_use', 'yes'); // FIXED: Cannot combine with other coupons
        update_post_meta($coupon_id, 'usage_limit', '');
        update_post_meta($coupon_id, 'usage_limit_per_user', '1');
        update_post_meta($coupon_id, 'expiry_date', '');
        update_post_meta($coupon_id, 'free_shipping', 'no');
        update_post_meta($coupon_id, '_affiliate_user_id', $user_id);
        
        return $coupon_id;
    }
    
    private function send_welcome_email($user, $code) {
        $to = $user->user_email;
        $subject = 'O Teu Código de Afiliado está Pronto!';
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Bem-vindo ao Programa de Influencers!</h2>
            <p>Olá <strong>{$user->display_name}</strong>,</p>
            <div style='background: #f0f0f0; padding: 20px; margin: 20px 0; text-align: center;'>
                <h1 style='color: #667eea; font-size: 36px;'>{$code}</h1>
                <p>O teu código único promocional</p>
            </div>
            <p>Ganhas 10% de comissão em cada venda!</p>
            <p><a href='" . wc_get_account_endpoint_url('affiliate-dashboard') . "' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block;'>Ir para Dashboard</a></p>
        </body>
        </html>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);
    }
    
    private function notify_admin_new_affiliate($user, $code) {
        $admin_email = get_option('admin_email');
        $subject = 'Novo Afiliado Registado!';
        $message = "
        <h2>Novo Afiliado!</h2>
        <p><strong>Nome:</strong> {$user->display_name}</p>
        <p><strong>Email:</strong> {$user->user_email}</p>
        <p><strong>Código:</strong> {$code}</p>
        <p><a href='" . admin_url('admin.php?page=affiliate-system') . "'>Ver Dashboard</a></p>
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
            
            // Check if already tracked
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}affiliate_referrals WHERE order_id = %d",
                $order_id
            ));
            
            if ($exists) {
                continue;
            }
            
            // Calculate commission
            $order_total = $order->get_total();
            $commission_rate = $affiliate->commission_rate;
            $commission_amount = ($order_total * $commission_rate) / 100;
            
            // Insert referral
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
            
            // Update affiliate totals
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
            
            // Send notification
            $this->send_commission_email($affiliate_user_id, $coupon_code, $order_total, $commission_amount);
        }
    }
    
    private function send_commission_email($user_id, $code, $total, $commission) {
        $user = get_userdata($user_id);
        $to = $user->user_email;
        $subject = 'Nova Comissão Ganha!';
        $message = "
        <h2>Parabéns!</h2>
        <p>Alguém usou o teu código <strong>{$code}</strong></p>
        <p><strong>Total da Encomenda:</strong> " . number_format($total, 2) . "€</p>
        <p><strong>A Tua Comissão:</strong> " . number_format($commission, 2) . "€</p>
        <p><a href='" . wc_get_account_endpoint_url('affiliate-dashboard') . "'>Ver Dashboard</a></p>
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
            wp_send_json_error('Não autenticado');
        }
        
        $affiliate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}affiliates WHERE user_id = %d",
            $user_id
        ));
        
        if (!$affiliate) {
            wp_send_json_error('Afiliado não encontrado');
        }
        
        // Check minimum
        $min_payout = ($affiliate->tier === 'tier_1') ? 20 : 0;
        if ($affiliate->unpaid_commission < $min_payout) {
            wp_send_json_error('Valor mínimo não atingido');
        }
        
        // Check for pending payout
        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_payouts 
            WHERE affiliate_id = %d AND status = 'pending'",
            $affiliate->id
        ));
        
        if ($pending > 0) {
            wp_send_json_error('Já tens um pedido pendente');
        }
        
        $payment_method = sanitize_text_field($_POST['payment_method']);
        $payment_details = sanitize_textarea_field($_POST['payment_details']);
        $notes = sanitize_textarea_field($_POST['notes']);
        $amount = $affiliate->unpaid_commission;
        
        // Insert payout request
        $wpdb->insert(
            $wpdb->prefix . 'affiliate_payouts',
            array(
                'affiliate_id' => $affiliate->id,
                'amount' => $amount,
                'method' => $payment_method,
                'status' => 'pending',
                'notes' => "Método: {$payment_method}\nDetalhes: {$payment_details}\n" . (!empty($notes) ? "Notas: {$notes}" : "")
            ),
            array('%d', '%f', '%s', '%s', '%s')
        );
        
        // Send email to admin
        $user = get_userdata($user_id);
        $admin_email = 'support@thecouplesbrand.com';
        
        $tier_names = array('tier_1' => 'Tier I', 'tier_2' => 'Tier II', 'ambassador' => 'Embaixador');
        $tier_name = $tier_names[$affiliate->tier];
        $payment_days = ($affiliate->tier === 'tier_1') ? '30 dias' : '3 dias';
        
        $subject = 'Novo Pedido de Levantamento - ' . $user->display_name;
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
        <h2>Novo Pedido de Transferência</h2>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <p style='margin: 5px 0;'><strong>Afiliado:</strong> {$user->display_name}</p>
            <p style='margin: 5px 0;'><strong>ID Cliente:</strong> {$user_id}</p>
            <p style='margin: 5px 0;'><strong>Email:</strong> {$user->user_email}</p>
            <p style='margin: 5px 0;'><strong>Código:</strong> {$affiliate->affiliate_code}</p>
            <p style='margin: 5px 0;'><strong>Tier:</strong> {$tier_name}</p>
        </div>
        
        <hr>
        
        <div style='background: #d1fae5; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <p style='margin: 5px 0; font-size: 18px;'><strong>Valor:</strong> " . number_format($amount, 2) . "€</p>
            <p style='margin: 5px 0;'><strong>Método:</strong> {$payment_method}</p>
            <p style='margin: 5px 0;'><strong>Detalhes de Pagamento:</strong></p>
            <p style='margin: 5px 0; background: white; padding: 10px; border-radius: 4px;'>{$payment_details}</p>
            " . (!empty($notes) ? "<p style='margin: 10px 0 5px 0;'><strong>Notas:</strong></p><p style='margin: 0; background: white; padding: 10px; border-radius: 4px;'>{$notes}</p>" : "") . "
        </div>
        
        <p style='background: #fef3c7; padding: 15px; border-radius: 6px;'>
            <strong>Prazo:</strong> Este afiliado deve receber o pagamento em até <strong>{$payment_days}</strong>.
        </p>
        
        <p style='margin: 30px 0;'>
            <a href='" . admin_url('admin.php?page=affiliate-payouts') . "' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Ver Pedidos Pendentes</a>
        </p>
        </body>
        </html>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);
        
        wp_send_json_success('Pedido enviado com sucesso! Receberás uma resposta em breve.');
    }
    
    // === REGISTRATION FORM ===
    
    public function registration_shortcode() {
        if (is_user_logged_in()) {
            return '<p>Já estás registado. <a href="' . wc_get_page_permalink('myaccount') . '">Ir para Dashboard</a></p>';
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
            <h2>Junta-te ao Programa de Influencers</h2>
            <p style="text-align: center; color: #666; margin-bottom: 30px;">
                Ganha 10% de comissão em cada venda!
            </p>
            
            <form method="post" action="">
                <?php wp_nonce_field('affiliate_registration', 'affiliate_reg_nonce'); ?>
                
                <input type="text" name="full_name" placeholder="Nome Completo" required>
                <input type="email" name="user_email" placeholder="Email" required>
                <input type="text" name="username" placeholder="Username (para o teu código)" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="text" name="whatsapp" placeholder="WhatsApp (opcional)">
                <input type="text" name="instagram" placeholder="Instagram (opcional)">
                
                <label style="display: block; margin: 20px 0;">
                    <input type="checkbox" name="terms" required>
                    Aceito os termos e condições
                </label>
                
                <button type="submit" name="register_affiliate">Criar Conta e Receber Código</button>
            </form>
            
            <p style="text-align: center; margin-top: 20px;">
                Já tens conta? <a href="<?php echo wp_login_url(wc_get_page_permalink('myaccount')); ?>">Login</a>
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
            'Payouts',
            'Payouts',
            'manage_options',
            'affiliate-payouts',
            array($this, 'admin_payouts_page')
        );
        
        add_submenu_page(
            'affiliate-system',
            'Reports',
            'Reports',
            'manage_options',
            'affiliate-reports',
            array($this, 'admin_reports_page')
        );
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
}

// Initialize plugin
Custom_Affiliate_System::get_instance();
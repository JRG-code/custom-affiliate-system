<?php
/**
 * Helper Functions
 * Utility functions for accessing settings and formatting data
 * 
 * Este ficheiro contém todas as funções auxiliares que permitem
 * aceder às configurações do plugin de forma consistente
 */

if (!defined('ABSPATH')) {
    exit; // Segurança: impede acesso direto ao ficheiro
}

/**
 * Obter configuração de um tier específico
 * 
 * @param string $tier Nome do tier (tier_1, tier_2, ambassador)
 * @param string $field Campo desejado (commission, min_payout, payment_days, coupon_discount)
 * @return mixed Valor da configuração
 */
function cas_get_tier_setting($tier, $field) {
    // Buscar as configurações guardadas no WordPress
    $options = get_option('cas_settings', array());
    
    // Valores padrão caso não existam configurações
    // Estes valores são usados quando o plugin é instalado pela primeira vez
    $defaults = array(
        'tier_1' => array(
            'commission' => 10,      // 10% de comissão
            'min_payout' => 20,      // Mínimo 20€ para levantar
            'payment_days' => 30,    // Pagamento em 30 dias
            'coupon_discount' => 5   // 5€ de desconto no cupão
        ),
        'tier_2' => array(
            'commission' => 15,      // 15% de comissão
            'min_payout' => 0,       // Sem mínimo
            'payment_days' => 3,     // Pagamento em 3 dias
            'coupon_discount' => 5   // 5€ de desconto
        ),
        'ambassador' => array(
            'commission' => 20,      // 20% de comissão
            'min_payout' => 0,       // Sem mínimo
            'payment_days' => 3,     // Pagamento em 3 dias
            'coupon_discount' => 5   // 5€ de desconto
        )
    );
    
    // Retornar o valor das configurações ou o valor padrão
    if (isset($options[$tier][$field])) {
        return $options[$tier][$field];
    }
    
    return isset($defaults[$tier][$field]) ? $defaults[$tier][$field] : 0;
}

/**
 * Obter configuração geral do sistema
 * 
 * @param string $field Nome do campo
 * @return mixed Valor da configuração
 */
function cas_get_general_setting($field) {
    $options = get_option('cas_settings', array());
    
    // Valores padrão para configurações gerais
    $defaults = array(
        'currency_symbol' => '€',                      // Símbolo da moeda
        'support_email' => get_option('admin_email'),  // Email do admin por padrão
        'auto_approve' => 1,                           // Auto-aprovar novos afiliados
        'terms_page' => 0                              // Página de termos (nenhuma por padrão)
    );
    
    if (isset($options['general'][$field])) {
        return $options['general'][$field];
    }
    
    return isset($defaults[$field]) ? $defaults[$field] : '';
}

/**
 * Obter todas as configurações de um tier
 * 
 * @param string $tier Nome do tier
 * @return array Array com todas as configurações
 */
function cas_get_all_tier_settings($tier) {
    return array(
        'commission' => cas_get_tier_setting($tier, 'commission'),
        'min_payout' => cas_get_tier_setting($tier, 'min_payout'),
        'payment_days' => cas_get_tier_setting($tier, 'payment_days'),
        'coupon_discount' => cas_get_tier_setting($tier, 'coupon_discount')
    );
}

/**
 * Obter nome de apresentação do tier
 * 
 * @param string $tier Código do tier
 * @return string Nome para mostrar
 */
function cas_get_tier_name($tier) {
    $names = array(
        'tier_1' => 'Tier I',
        'tier_2' => 'Tier II',
        'ambassador' => 'Embaixador'
    );
    
    return isset($names[$tier]) ? $names[$tier] : $tier;
}

/**
 * Obter emoji/badge do tier
 * 
 * @param string $tier Código do tier
 * @return string Emoji
 */
function cas_get_tier_badge($tier) {
    $badges = array(
        'tier_1' => '⭐',
        'tier_2' => '💎',
        'ambassador' => '👑'
    );
    
    return isset($badges[$tier]) ? $badges[$tier] : '⭐';
}

/**
 * Formatar valor monetário
 * 
 * @param float $amount Valor a formatar
 * @param bool $include_symbol Incluir símbolo da moeda?
 * @return string Valor formatado
 */
function cas_format_currency($amount, $include_symbol = true) {
    $formatted = number_format($amount, 2);
    
    if ($include_symbol) {
        $symbol = cas_get_general_setting('currency_symbol');
        return $formatted . $symbol;
    }
    
    return $formatted;
}

/**
 * Obter URL da página de termos
 * 
 * @return string URL ou fallback
 */
function cas_get_terms_url() {
    $page_id = cas_get_general_setting('terms_page');
    
    if ($page_id) {
        return get_permalink($page_id);
    }
    
    // Tentar encontrar página por slug como fallback
    $terms_page = get_page_by_path('terms-and-conditions');
    if (!$terms_page) {
        $terms_page = get_page_by_path('terms-of-service');
    }
    
    if ($terms_page) {
        return get_permalink($terms_page);
    }
    
    return home_url('/terms-of-service/');
}

/**
 * Obter email de suporte
 * 
 * @return string Email
 */
function cas_get_support_email() {
    return cas_get_general_setting('support_email');
}

/**
 * Verificar se auto-aprovação está ativa
 * 
 * @return bool
 */
function cas_is_auto_approve_enabled() {
    return (bool) cas_get_general_setting('auto_approve');
}

/**
 * Obter texto formatado do prazo de pagamento
 * 
 * @param string $tier Nome do tier
 * @return string Texto formatado (ex: "30 dias")
 */
function cas_get_payment_timeline_text($tier) {
    $days = cas_get_tier_setting($tier, 'payment_days');
    return $days . ' dias';
}

/**
 * Verificar se as configurações estão completas
 * 
 * @return array Status e lista de itens em falta
 */
function cas_check_settings_status() {
    $status = array(
        'configured' => true,
        'missing' => array()
    );
    
    $options = get_option('cas_settings', array());
    
    // Verificar se existem configurações
    if (empty($options)) {
        $status['configured'] = false;
        $status['missing'][] = 'Nenhuma configuração definida ainda';
        return $status;
    }
    
    // Verificar configurações de cada tier
    $tiers = array('tier_1', 'tier_2', 'ambassador');
    foreach ($tiers as $tier) {
        if (!isset($options[$tier])) {
            $status['configured'] = false;
            $status['missing'][] = 'Falta configurar ' . cas_get_tier_name($tier);
        }
    }
    
    // Verificar configurações gerais
    if (!isset($options['general'])) {
        $status['configured'] = false;
        $status['missing'][] = 'Faltam configurações gerais';
    }
    
    // Verificar email de suporte
    $support_email = cas_get_general_setting('support_email');
    if (empty($support_email) || !is_email($support_email)) {
        $status['configured'] = false;
        $status['missing'][] = 'Email de suporte inválido ou em falta';
    }
    
    return $status;
}

/**
 * Inicializar configurações padrão se não existirem
 * 
 * Esta função é chamada quando o plugin é ativado
 */
function cas_init_default_settings() {
    $existing = get_option('cas_settings');
    
    // Só criar se não existirem configurações
    if (empty($existing)) {
        $defaults = array(
            'tier_1' => array(
                'commission' => 10,
                'min_payout' => 20,
                'payment_days' => 30,
                'coupon_discount' => 5
            ),
            'tier_2' => array(
                'commission' => 15,
                'min_payout' => 0,
                'payment_days' => 3,
                'coupon_discount' => 5
            ),
            'ambassador' => array(
                'commission' => 20,
                'min_payout' => 0,
                'payment_days' => 3,
                'coupon_discount' => 5
            ),
            'general' => array(
                'currency_symbol' => '€',
                'support_email' => get_option('admin_email'),
                'auto_approve' => 1,
                'terms_page' => 0
            )
        );
        
        update_option('cas_settings', $defaults);
        return true;
    }
    
    return false;
}

/**
 * Obter cor do tier para usar em CSS
 * 
 * @param string $tier Código do tier
 * @return string Código hexadecimal da cor
 */
function cas_get_tier_color($tier) {
    $colors = array(
        'tier_1' => '#667eea',
        'tier_2' => '#f59e0b',
        'ambassador' => '#ec4899'
    );
    
    return isset($colors[$tier]) ? $colors[$tier] : '#667eea';
}

/**
 * Obter texto do valor mínimo de levantamento
 * 
 * @param string $tier Nome do tier
 * @return string Texto formatado
 */
function cas_get_min_payout_text($tier) {
    $min = cas_get_tier_setting($tier, 'min_payout');
    
    if ($min == 0) {
        return 'Sem mínimo';
    }
    
    return cas_format_currency($min);
}

/**
 * Verificar se um afiliado pode pedir levantamento
 * 
 * @param object $affiliate Objeto do afiliado da base de dados
 * @return array Array com 'can_request' (bool) e 'message' (string)
 */
function cas_can_request_payout($affiliate) {
    $min_payout = cas_get_tier_setting($affiliate->tier, 'min_payout');
    
    if ($affiliate->unpaid_commission >= $min_payout) {
        return array(
            'can_request' => true,
            'message' => ''
        );
    }
    
    $missing = $min_payout - $affiliate->unpaid_commission;
    
    return array(
        'can_request' => false,
        'message' => sprintf(
            'Precisas de %s para pedir levantamento. Faltam %s.',
            cas_format_currency($min_payout),
            cas_format_currency($missing)
        )
    );
}

/**
 * Obter contagem de afiliados por tier
 * 
 * @return array Array associativo com contagens
 */
function cas_get_affiliate_counts_by_tier() {
    global $wpdb;
    
    $counts = $wpdb->get_results("
        SELECT tier, COUNT(*) as count
        FROM {$wpdb->prefix}affiliates
        GROUP BY tier
    ", OBJECT_K);
    
    $result = array(
        'tier_1' => 0,
        'tier_2' => 0,
        'ambassador' => 0
    );
    
    foreach ($counts as $tier => $data) {
        $result[$tier] = (int) $data->count;
    }
    
    return $result;
}

/**
 * Obter todos os emails de afiliados (para envio em massa)
 * 
 * @param string $tier Tier específico (opcional)
 * @param string $status Status específico (opcional)
 * @return array Array de emails
 */
function cas_get_affiliate_emails($tier = '', $status = 'active') {
    global $wpdb;
    
    $where = array();
    $params = array();
    
    if (!empty($status)) {
        $where[] = "a.status = %s";
        $params[] = $status;
    }
    
    if (!empty($tier)) {
        $where[] = "a.tier = %s";
        $params[] = $tier;
    }
    
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $query = "
        SELECT DISTINCT u.user_email, u.display_name, a.tier, a.affiliate_code
        FROM {$wpdb->prefix}affiliates a
        LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
        {$where_clause}
    ";
    
    if (!empty($params)) {
        $query = $wpdb->prepare($query, $params);
    }
    
    return $wpdb->get_results($query);
}

/**
 * Check if debug is enabled
 */
function cas_is_debug_enabled() {
    return (bool) get_option('cas_debug_enabled', false);
}

/**
 * Debug log function
 */
function cas_debug_log($message, $type = 'info') {
    if (!cas_is_debug_enabled()) {
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'affiliate_debug_log';
    
    // Create table if doesn't exist
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            type varchar(20) DEFAULT 'info',
            message text NOT NULL,
            user_id bigint(20) NULL,
            PRIMARY KEY (id),
            KEY type (type),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    $wpdb->insert(
        $table,
        array(
            'type' => $type,
            'message' => $message,
            'user_id' => get_current_user_id() ?: null
        ),
        array('%s', '%s', '%d')
    );
}

function cas_get_debug_logs($limit = 100) {
    global $wpdb;
    $table = $wpdb->prefix . 'affiliate_debug_log';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table ORDER BY timestamp DESC LIMIT %d",
        $limit
    ));
}

function cas_clear_debug_logs() {
    global $wpdb;
    $table = $wpdb->prefix . 'affiliate_debug_log';
    $wpdb->query("TRUNCATE TABLE $table");
}

// ========================================
// PRO VERSION FUNCTIONS
// ========================================
/**
 * Update tier name and badge functions to support custom tiers
 */
function cas_get_tier_name_v2($tier) {
    $available_tiers = cas_get_available_tiers();
    
    if (isset($available_tiers[$tier]['name'])) {
        return $available_tiers[$tier]['name'];
    }
    
    // Fallback to old function
    return cas_get_tier_name($tier);
}

function cas_get_tier_badge_v2($tier) {
    $available_tiers = cas_get_available_tiers();
    
    if (isset($available_tiers[$tier]['badge'])) {
        return $available_tiers[$tier]['badge'];
    }
    
    // Fallback to old function
    return cas_get_tier_badge($tier);
}

/**
 * Update tier color to support custom tiers
 */
function cas_get_tier_color_v2($tier) {
    // Default colors
    $colors = array(
        'tier_1' => '#667eea',
        'tier_2' => '#f59e0b',
        'ambassador' => '#ec4899',
        'platinum' => '#a78bfa',
        'diamond' => '#06b6d4',
        'elite' => '#f43f5e',
        'vip' => '#eab308',
        'partner' => '#10b981'
    );
    
    // Check if custom color is set
    $custom_tiers = get_option('cas_custom_tiers', array());
    if (isset($custom_tiers[$tier]['color'])) {
        return $custom_tiers[$tier]['color'];
    }
    
    return isset($colors[$tier]) ? $colors[$tier] : '#667eea';
}

/**
 * Get tier setting with support for custom tiers
 */
function cas_get_tier_setting_v2($tier, $field) {
    $options = get_option('cas_settings', array());
    
    // Check if setting exists in cas_settings
    if (isset($options[$tier][$field])) {
        return $options[$tier][$field];
    }
    
    // Check custom tiers
    $custom_tiers = get_option('cas_custom_tiers', array());
    if (isset($custom_tiers[$tier][$field])) {
        return $custom_tiers[$tier][$field];
    }
    
    // Fallback to original function
    return cas_get_tier_setting($tier, $field);
}

/**
 * Update affiliate counts to include all tiers
 */
function cas_get_affiliate_counts_by_tier_v2() {
    global $wpdb;
    
    $counts = $wpdb->get_results("
        SELECT tier, COUNT(*) as count
        FROM {$wpdb->prefix}affiliates
        GROUP BY tier
    ", OBJECT_K);
    
    $result = array();
    $available_tiers = cas_get_available_tiers();
    
    // Initialize all available tiers with 0
    foreach (array_keys($available_tiers) as $tier) {
        $result[$tier] = 0;
    }
    
    // Fill with actual counts
    foreach ($counts as $tier => $data) {
        $result[$tier] = (int) $data->count;
    }
    
    return $result;
}

/**
 * Validate tier ID format
 */
function cas_validate_tier_id($tier_id) {
    // Must be lowercase, alphanumeric + underscore only
    if (!preg_match('/^[a-z0-9_]+$/', $tier_id)) {
        return false;
    }
    
    // Must not be a protected name
    $protected = array('tier_1', 'tier_2', 'ambassador');
    if (in_array($tier_id, $protected)) {
        return false;
    }
    
    return true;
}

/**
 * Check if tier can be deleted
 */
function cas_can_delete_tier($tier_id) {
    global $wpdb;
    
    // Cannot delete default tiers
    $protected = array('tier_1', 'tier_2', 'ambassador');
    if (in_array($tier_id, $protected)) {
        return false;
    }
    
    // Check if any affiliates are using this tier
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}affiliates WHERE tier = %s",
        $tier_id
    ));
    
    return $count == 0;
}

/**
 * Delete custom tier
 */
function cas_delete_custom_tier($tier_id) {
    if (!cas_can_delete_tier($tier_id)) {
        return false;
    }
    
    // Remove from custom tiers
    $custom_tiers = get_option('cas_custom_tiers', array());
    unset($custom_tiers[$tier_id]);
    update_option('cas_custom_tiers', $custom_tiers);
    
    // Remove from settings
    $cas_settings = get_option('cas_settings', array());
    unset($cas_settings[$tier_id]);
    update_option('cas_settings', $cas_settings);
    
    return true;
}

/**
 * Create custom tier
 */
function cas_create_custom_tier($tier_id, $data) {
    // Validate tier ID
    if (!cas_validate_tier_id($tier_id)) {
        return array('success' => false, 'message' => 'Invalid tier ID format');
    }
    
    // Check if already exists
    $existing_tiers = get_option('cas_custom_tiers', array());
    if (isset($existing_tiers[$tier_id])) {
        return array('success' => false, 'message' => 'Tier ID already exists');
    }
    
    // Validate required fields
    if (empty($data['name'])) {
        return array('success' => false, 'message' => 'Tier name is required');
    }
    
    // Add to custom tiers
    $existing_tiers[$tier_id] = array(
        'name' => sanitize_text_field($data['name']),
        'badge' => sanitize_text_field($data['badge'] ?? '⭐'),
        'description' => sanitize_text_field($data['description'] ?? ''),
        'commission' => floatval($data['commission'] ?? 25),
        'min_payout' => floatval($data['min_payout'] ?? 0),
        'payment_days' => intval($data['payment_days'] ?? 3),
        'coupon_discount' => floatval($data['coupon_discount'] ?? 5),
        'color' => sanitize_hex_color($data['color'] ?? '#667eea'),
        'created_at' => current_time('mysql')
    );
    
    update_option('cas_custom_tiers', $existing_tiers);
    
    // Also add to cas_settings for compatibility
    $cas_settings = get_option('cas_settings', array());
    $cas_settings[$tier_id] = array(
        'commission' => $existing_tiers[$tier_id]['commission'],
        'min_payout' => $existing_tiers[$tier_id]['min_payout'],
        'payment_days' => $existing_tiers[$tier_id]['payment_days'],
        'coupon_discount' => $existing_tiers[$tier_id]['coupon_discount']
    );
    update_option('cas_settings', $cas_settings);
    
    return array('success' => true, 'message' => 'Tier created successfully');
}

/**
 * Update custom tier
 */
function cas_update_custom_tier($tier_id, $data) {
    $custom_tiers = get_option('cas_custom_tiers', array());
    
    if (!isset($custom_tiers[$tier_id])) {
        return array('success' => false, 'message' => 'Tier not found');
    }
    
    // Update fields
    if (isset($data['name'])) {
        $custom_tiers[$tier_id]['name'] = sanitize_text_field($data['name']);
    }
    if (isset($data['badge'])) {
        $custom_tiers[$tier_id]['badge'] = sanitize_text_field($data['badge']);
    }
    if (isset($data['commission'])) {
        $custom_tiers[$tier_id]['commission'] = floatval($data['commission']);
    }
    if (isset($data['min_payout'])) {
        $custom_tiers[$tier_id]['min_payout'] = floatval($data['min_payout']);
    }
    if (isset($data['payment_days'])) {
        $custom_tiers[$tier_id]['payment_days'] = intval($data['payment_days']);
    }
    if (isset($data['coupon_discount'])) {
        $custom_tiers[$tier_id]['coupon_discount'] = floatval($data['coupon_discount']);
    }
    if (isset($data['color'])) {
        $custom_tiers[$tier_id]['color'] = sanitize_hex_color($data['color']);
    }
    
    update_option('cas_custom_tiers', $custom_tiers);
    
    // Update cas_settings too
    $cas_settings = get_option('cas_settings', array());
    $cas_settings[$tier_id] = array(
        'commission' => $custom_tiers[$tier_id]['commission'],
        'min_payout' => $custom_tiers[$tier_id]['min_payout'],
        'payment_days' => $custom_tiers[$tier_id]['payment_days'],
        'coupon_discount' => $custom_tiers[$tier_id]['coupon_discount']
    );
    update_option('cas_settings', $cas_settings);
    
    return array('success' => true, 'message' => 'Tier updated successfully');
}

/**
 * Get all custom tiers
 */
function cas_get_custom_tiers() {
    return get_option('cas_custom_tiers', array());
}

/**
 * Count total custom tiers
 */
function cas_count_custom_tiers() {
    $custom_tiers = get_option('cas_custom_tiers', array());
    return count($custom_tiers);
}
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
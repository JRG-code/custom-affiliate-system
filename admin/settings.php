<?php
/**
 * Admin Settings Page
 * Manage all affiliate system configurations
 * 
 * Esta página permite configurar todas as opções do sistema de afiliados
 * sem precisar editar código. Todas as mudanças aplicam-se automaticamente.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Registar todas as configurações do plugin
function cas_register_settings() {
    // Registar o grupo de configurações
    register_setting('cas_settings_group', 'cas_settings', 'cas_sanitize_settings');
    
    // === SECÇÃO TIER 1 ===
    add_settings_section(
        'cas_tier1_section',
        '⭐ Tier I - Configurações Afiliados Básicos',
        'cas_tier1_section_callback',
        'cas-settings'
    );
    
    // === SECÇÃO TIER 2 ===
    add_settings_section(
        'cas_tier2_section',
        '💎 Tier II - Configurações Influencers',
        'cas_tier2_section_callback',
        'cas-settings'
    );
    
    // === SECÇÃO AMBASSADOR ===
    add_settings_section(
        'cas_ambassador_section',
        '👑 Configurações Embaixadores',
        'cas_ambassador_section_callback',
        'cas-settings'
    );
    
    // === SECÇÃO GERAL ===
    add_settings_section(
        'cas_general_section',
        '⚙️ Configurações Gerais',
        'cas_general_section_callback',
        'cas-settings'
    );
    
    // CAMPOS TIER 1
    add_settings_field('tier1_commission', 'Taxa de Comissão (%)', 'cas_commission_field_callback', 'cas-settings', 'cas_tier1_section', ['tier' => 'tier_1']);
    add_settings_field('tier1_min_payout', 'Mínimo para Levantamento (€)', 'cas_min_payout_field_callback', 'cas-settings', 'cas_tier1_section', ['tier' => 'tier_1']);
    add_settings_field('tier1_payment_days', 'Prazo de Pagamento (dias)', 'cas_payment_days_field_callback', 'cas-settings', 'cas_tier1_section', ['tier' => 'tier_1']);
    add_settings_field('tier1_coupon_discount', 'Desconto do Cupão (€)', 'cas_coupon_discount_field_callback', 'cas-settings', 'cas_tier1_section', ['tier' => 'tier_1']);
    
    // CAMPOS TIER 2
    add_settings_field('tier2_commission', 'Taxa de Comissão (%)', 'cas_commission_field_callback', 'cas-settings', 'cas_tier2_section', ['tier' => 'tier_2']);
    add_settings_field('tier2_min_payout', 'Mínimo para Levantamento (€)', 'cas_min_payout_field_callback', 'cas-settings', 'cas_tier2_section', ['tier' => 'tier_2']);
    add_settings_field('tier2_payment_days', 'Prazo de Pagamento (dias)', 'cas_payment_days_field_callback', 'cas-settings', 'cas_tier2_section', ['tier' => 'tier_2']);
    add_settings_field('tier2_coupon_discount', 'Desconto do Cupão (€)', 'cas_coupon_discount_field_callback', 'cas-settings', 'cas_tier2_section', ['tier' => 'tier_2']);
    
    // CAMPOS AMBASSADOR
    add_settings_field('ambassador_commission', 'Taxa de Comissão (%)', 'cas_commission_field_callback', 'cas-settings', 'cas_ambassador_section', ['tier' => 'ambassador']);
    add_settings_field('ambassador_min_payout', 'Mínimo para Levantamento (€)', 'cas_min_payout_field_callback', 'cas-settings', 'cas_ambassador_section', ['tier' => 'ambassador']);
    add_settings_field('ambassador_payment_days', 'Prazo de Pagamento (dias)', 'cas_payment_days_field_callback', 'cas-settings', 'cas_ambassador_section', ['tier' => 'ambassador']);
    add_settings_field('ambassador_coupon_discount', 'Desconto do Cupão (€)', 'cas_coupon_discount_field_callback', 'cas-settings', 'cas_ambassador_section', ['tier' => 'ambassador']);
    
    // CAMPOS GERAIS
    add_settings_field('currency_symbol', 'Símbolo da Moeda', 'cas_currency_symbol_field_callback', 'cas-settings', 'cas_general_section');
    add_settings_field('support_email', 'Email de Suporte', 'cas_support_email_field_callback', 'cas-settings', 'cas_general_section');
    add_settings_field('auto_approve', 'Auto-Aprovar Novos Afiliados', 'cas_auto_approve_field_callback', 'cas-settings', 'cas_general_section');
    add_settings_field('terms_page', 'Página de Termos e Condições', 'cas_terms_page_field_callback', 'cas-settings', 'cas_general_section');
}
add_action('admin_init', 'cas_register_settings');

// === CALLBACKS DAS SECÇÕES (Descrições) ===

function cas_tier1_section_callback() {
    echo '<p>Configurações para afiliados básicos (tier padrão para novos registos).</p>';
}

function cas_tier2_section_callback() {
    echo '<p>Configurações para influencers Tier II com taxas melhores.</p>';
}

function cas_ambassador_section_callback() {
    echo '<p>Configurações para embaixadores premium com as melhores taxas.</p>';
}

function cas_general_section_callback() {
    echo '<p>Configurações gerais do sistema.</p>';
}

// === CALLBACKS DOS CAMPOS (Inputs) ===

function cas_commission_field_callback($args) {
    $tier = $args['tier'];
    $options = get_option('cas_settings');
    $value = isset($options[$tier]['commission']) ? $options[$tier]['commission'] : cas_get_tier_setting($tier, 'commission');
    ?>
    <input type="number" 
           name="cas_settings[<?php echo $tier; ?>][commission]" 
           value="<?php echo esc_attr($value); ?>" 
           step="0.01" 
           min="0" 
           max="100"
           class="regular-text">
    <p class="description">Percentagem de comissão em cada venda (ex: 10 para 10%)</p>
    <?php
}

function cas_min_payout_field_callback($args) {
    $tier = $args['tier'];
    $options = get_option('cas_settings');
    $value = isset($options[$tier]['min_payout']) ? $options[$tier]['min_payout'] : cas_get_tier_setting($tier, 'min_payout');
    ?>
    <input type="number" 
           name="cas_settings[<?php echo $tier; ?>][min_payout]" 
           value="<?php echo esc_attr($value); ?>" 
           step="1" 
           min="0"
           class="regular-text">
    <p class="description">Valor mínimo necessário para pedir levantamento (0 = sem mínimo)</p>
    <?php
}

function cas_payment_days_field_callback($args) {
    $tier = $args['tier'];
    $options = get_option('cas_settings');
    $value = isset($options[$tier]['payment_days']) ? $options[$tier]['payment_days'] : cas_get_tier_setting($tier, 'payment_days');
    ?>
    <input type="number" 
           name="cas_settings[<?php echo $tier; ?>][payment_days]" 
           value="<?php echo esc_attr($value); ?>" 
           step="1" 
           min="1"
           class="regular-text">
    <p class="description">Número de dias para processar pagamento após aprovação</p>
    <?php
}

function cas_coupon_discount_field_callback($args) {
    $tier = $args['tier'];
    $options = get_option('cas_settings');
    $value = isset($options[$tier]['coupon_discount']) ? $options[$tier]['coupon_discount'] : cas_get_tier_setting($tier, 'coupon_discount');
    ?>
    <input type="number" 
           name="cas_settings[<?php echo $tier; ?>][coupon_discount]" 
           value="<?php echo esc_attr($value); ?>" 
           step="0.01" 
           min="0"
           class="regular-text">
    <p class="description">Valor de desconto que o cliente recebe ao usar o cupão do afiliado</p>
    <?php
}

function cas_currency_symbol_field_callback() {
    $options = get_option('cas_settings');
    $value = isset($options['general']['currency_symbol']) ? $options['general']['currency_symbol'] : '€';
    ?>
    <input type="text" 
           name="cas_settings[general][currency_symbol]" 
           value="<?php echo esc_attr($value); ?>" 
           maxlength="3"
           class="small-text">
    <p class="description">Símbolo da moeda a mostrar (ex: €, $, £)</p>
    <?php
}

function cas_support_email_field_callback() {
    $options = get_option('cas_settings');
    $value = isset($options['general']['support_email']) ? $options['general']['support_email'] : get_option('admin_email');
    ?>
    <input type="email" 
           name="cas_settings[general][support_email]" 
           value="<?php echo esc_attr($value); ?>" 
           class="regular-text">
    <p class="description">Email para suporte a afiliados e notificações de levantamentos</p>
    <?php
}

function cas_auto_approve_field_callback() {
    $options = get_option('cas_settings');
    $value = isset($options['general']['auto_approve']) ? $options['general']['auto_approve'] : 1;
    ?>
    <label>
        <input type="checkbox" 
               name="cas_settings[general][auto_approve]" 
               value="1" 
               <?php checked($value, 1); ?>>
        Aprovar automaticamente novos registos de afiliados
    </label>
    <p class="description">Se desativado, novos afiliados ficam pendentes de aprovação manual</p>
    <?php
}

function cas_terms_page_field_callback() {
    $options = get_option('cas_settings');
    $value = isset($options['general']['terms_page']) ? $options['general']['terms_page'] : '';
    
    $pages = get_pages();
    ?>
    <select name="cas_settings[general][terms_page]" class="regular-text">
        <option value="">Selecionar página...</option>
        <?php foreach ($pages as $page): ?>
            <option value="<?php echo $page->ID; ?>" <?php selected($value, $page->ID); ?>>
                <?php echo esc_html($page->post_title); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description">Página com os Termos e Condições do programa de afiliados</p>
    <?php
}

// === SANITIZAÇÃO E VALIDAÇÃO ===

function cas_sanitize_settings($input) {
    $sanitized = array();
    
    // Sanitizar configurações de cada tier
    $tiers = ['tier_1', 'tier_2', 'ambassador'];
    foreach ($tiers as $tier) {
        if (isset($input[$tier])) {
            $sanitized[$tier]['commission'] = floatval($input[$tier]['commission']);
            $sanitized[$tier]['min_payout'] = floatval($input[$tier]['min_payout']);
            $sanitized[$tier]['payment_days'] = intval($input[$tier]['payment_days']);
            $sanitized[$tier]['coupon_discount'] = floatval($input[$tier]['coupon_discount']);
        }
    }
    
    // Sanitizar configurações gerais
    if (isset($input['general'])) {
        $sanitized['general']['currency_symbol'] = sanitize_text_field($input['general']['currency_symbol']);
        $sanitized['general']['support_email'] = sanitize_email($input['general']['support_email']);
        $sanitized['general']['auto_approve'] = isset($input['general']['auto_approve']) ? 1 : 0;
        $sanitized['general']['terms_page'] = intval($input['general']['terms_page']);
    }
    
    // Atualizar cupões e afiliados existentes
    cas_update_existing_coupons($sanitized);
    
    // Mensagem de sucesso
    add_settings_error(
        'cas_settings',
        'cas_settings_updated',
        '✅ Configurações guardadas com sucesso! Cupões e taxas de afiliados foram atualizados.',
        'success'
    );
    
    return $sanitized;
}

// Atualizar cupões existentes quando as configurações mudam
function cas_update_existing_coupons($new_settings) {
    global $wpdb;
    
    $tiers = ['tier_1', 'tier_2', 'ambassador'];
    
    foreach ($tiers as $tier) {
        if (!isset($new_settings[$tier])) continue;
        
        $commission = $new_settings[$tier]['commission'];
        $discount = $new_settings[$tier]['coupon_discount'];
        
        // Atualizar taxa de comissão dos afiliados deste tier
        $wpdb->update(
            $wpdb->prefix . 'affiliates',
            ['commission_rate' => $commission],
            ['tier' => $tier],
            ['%f'],
            ['%s']
        );
        
        // Obter todos os afiliados deste tier
        $affiliates = $wpdb->get_results($wpdb->prepare(
            "SELECT affiliate_code FROM {$wpdb->prefix}affiliates WHERE tier = %s",
            $tier
        ));
        
        // Atualizar cada cupão WooCommerce
        foreach ($affiliates as $aff) {
            $coupon = new WC_Coupon(strtolower($aff->affiliate_code));
            if ($coupon->get_id()) {
                update_post_meta($coupon->get_id(), 'coupon_amount', $discount);
            }
        }
    }
}

// === INTERFACE DA PÁGINA ===
?>

<div class="wrap cas-settings-page">
    <h1>🎯 Configurações do Sistema de Afiliados</h1>
    
    <!-- Cabeçalho -->
    <div class="cas-settings-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin: 20px 0;">
        <h2 style="margin: 0 0 10px 0; color: white;">Configura o Teu Programa de Afiliados</h2>
        <p style="margin: 0; opacity: 0.9;">Gere taxas de comissão, mínimos de levantamento e outras configurações para todos os tiers de afiliados.</p>
    </div>
    
    <?php settings_errors('cas_settings'); ?>
    
    <!-- Aviso Importante -->
    <div class="cas-settings-notice" style="background: #f0f9ff; border-left: 4px solid #0284c7; padding: 20px; margin: 20px 0; border-radius: 6px;">
        <p style="margin: 0; color: #0c4a6e;">
            <strong>ℹ️ Importante:</strong> Ao mudar estas configurações, TODOS os afiliados existentes em cada tier e os seus cupões serão atualizados automaticamente. Isto garante consistência em todo o programa.
        </p>
    </div>
    
    <!-- Formulário de Configurações -->
    <form method="post" action="options.php">
        <?php
        settings_fields('cas_settings_group');
        do_settings_sections('cas-settings');
        submit_button('Guardar Todas as Configurações', 'primary large');
        ?>
    </form>
    
    <!-- Rodapé com Referência Rápida -->
    <div class="cas-settings-footer" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 30px;">
        <h3 style="margin: 0 0 15px 0;">📋 Referência Rápida</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div>
                <h4 style="margin: 0 0 10px 0; color: #667eea;">Tier I (Básico)</h4>
                <p style="margin: 5px 0; font-size: 14px;">• Tier padrão para novos afiliados</p>
                <p style="margin: 5px 0; font-size: 14px;">• Taxas de comissão standard</p>
                <p style="margin: 5px 0; font-size: 14px;">• Com valor mínimo de levantamento</p>
            </div>
            <div>
                <h4 style="margin: 0 0 10px 0; color: #f59e0b;">Tier II (Influencer)</h4>
                <p style="margin: 5px 0; font-size: 14px;">• Para afiliados comprovados</p>
                <p style="margin: 5px 0; font-size: 14px;">• Taxas de comissão mais altas</p>
                <p style="margin: 5px 0; font-size: 14px;">• Processamento de pagamento mais rápido</p>
            </div>
            <div>
                <h4 style="margin: 0 0 10px 0; color: #ec4899;">Ambassador (Premium)</h4>
                <p style="margin: 5px 0; font-size: 14px;">• Para os melhores afiliados</p>
                <p style="margin: 5px 0; font-size: 14px;">• Taxas de comissão máximas</p>
                <p style="margin: 5px 0; font-size: 14px;">• Processamento de pagamento prioritário</p>
            </div>
        </div>
    </div>
</div>
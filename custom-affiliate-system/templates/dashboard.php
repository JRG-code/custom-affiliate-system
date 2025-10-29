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

$tier_names = array(
    'tier_1' => 'Tier I',
    'tier_2' => 'Tier II',
    'ambassador' => 'Embaixador'
);

$tier_badges = array(
    'tier_1' => '⭐',
    'tier_2' => '💎',
    'ambassador' => '👑'
);

$tier_name = $tier_names[$affiliate->tier];
$tier_badge = $tier_badges[$affiliate->tier];
$commission_rate = $affiliate->commission_rate;

// Minimum payout and payment days based on tier
$min_payout = ($affiliate->tier === 'tier_1') ? 20 : 0;
$payment_days = ($affiliate->tier === 'tier_1') ? '30 dias' : '3 dias';
$can_request = $affiliate->unpaid_commission >= $min_payout;

// Check for pending payout
$pending_payout = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}affiliate_payouts 
    WHERE affiliate_id = %d AND status = 'pending' 
    ORDER BY request_date DESC LIMIT 1",
    $affiliate->id
));
?>

<style>
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
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    text-decoration: none;
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

.stat-card.promo-card {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.stat-icon {
    font-size: 48px;
    margin-bottom: 15px;
    display: block;
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

.promo-code {
    font-size: 28px;
    font-weight: 700;
    letter-spacing: 2px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    text-align: center;
    margin: 15px 0;
    border: 2px dashed rgba(255, 255, 255, 0.5);
}

.copy-btn {
    width: 100%;
    padding: 12px;
    background: rgba(255, 255, 255, 0.3);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.5);
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.copy-btn:hover {
    background: rgba(255, 255, 255, 0.5);
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
                <p class="subtitle">Bem-vindo ao teu Dashboard Influencer</p>
                <div class="tier-badge-large <?php echo esc_attr($affiliate->tier); ?>">
                    <?php echo $tier_badge . ' ' . $tier_name; ?> - <?php echo $commission_rate; ?>% Comissão
                </div>
            </div>
            <div class="header-right">
                <div class="share-quick">
                    <p style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;">Partilha o teu código</p>
                    <div class="share-buttons-compact">
                        <a href="https://twitter.com/intent/tweet?text=Usa%20o%20meu%20código%20<?php echo $affiliate->affiliate_code; ?>%20e%20ganha%205€%20de%20desconto!%20<?php echo home_url(); ?>" target="_blank" class="share-btn-mini share-twitter" title="Twitter">🐦</a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(home_url()); ?>" target="_blank" class="share-btn-mini share-facebook" title="Facebook">📘</a>
                        <a href="https://wa.me/?text=Usa%20o%20meu%20código%20<?php echo $affiliate->affiliate_code; ?>%20e%20ganha%205€%20de%20desconto!%20<?php echo home_url(); ?>" target="_blank" class="share-btn-mini share-whatsapp" title="WhatsApp">💬</a>
                        <button onclick="copyShareText('<?php echo esc_js($affiliate->affiliate_code); ?>')" class="share-btn-mini share-instagram" title="Instagram">📸</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        
        <!-- Promo Code Card -->
        <div class="stat-card promo-card">
            <div class="stat-icon">🎟️</div>
            <div class="stat-content">
                <h3>O Teu Código</h3>
                <div class="promo-code" id="promoCode"><?php echo esc_html($affiliate->affiliate_code); ?></div>
                <button class="copy-btn" onclick="copyCode()">
                    Copiar Código
                </button>
            </div>
        </div>
        
        <!-- Total Uses -->
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <h3>Pessoas que Usaram</h3>
                <div class="stat-number"><?php echo number_format($total_referrals); ?></div>
                <p class="stat-label">utilizações totais</p>
            </div>
        </div>
        
        <!-- Total Sales -->
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <h3>Vendas Totais</h3>
                <div class="stat-number"><?php echo number_format($affiliate->total_sales, 2); ?>€</div>
                <p class="stat-label">geradas por ti</p>
            </div>
        </div>
        
        <!-- Commission Earned -->
        <div class="stat-card highlight">
            <div class="stat-icon">💵</div>
            <div class="stat-content">
                <h3>A Receber</h3>
                <div class="stat-number"><?php echo number_format($affiliate->unpaid_commission, 2); ?>€</div>
                <p class="stat-label">comissão <?php echo $commission_rate; ?>%</p>
            </div>
        </div>
        
    </div>
    
    <!-- Remove Share Section - moved to header -->
    
    <!-- Payout Section -->
    <div class="payout-section">
        <h2>💳 Levantamento de Comissões</h2>
        
        <div class="balance-display">
            <div>
                <p class="balance-label">Saldo Disponível</p>
                <p class="balance-amount"><?php echo number_format($affiliate->unpaid_commission, 2); ?>€</p>
            </div>
            <div class="balance-info">
                <p class="info-item">Mínimo: <?php echo $min_payout; ?>€</p>
                <p class="info-item">Prazo: <?php echo $payment_days; ?></p>
            </div>
        </div>
        
        <?php if ($pending_payout): ?>
            <div class="alert alert-info">
                <strong>Pedido Pendente</strong><br>
                Pediste um levantamento de <strong><?php echo number_format($pending_payout->amount, 2); ?>€</strong> 
                em <?php echo date('d/m/Y', strtotime($pending_payout->request_date)); ?>.<br>
                <small>Receberás em até <?php echo $payment_days; ?>.</small>
            </div>
        <?php elseif ($can_request): ?>
            <div class="payout-available">
                <button class="btn-primary" onclick="openPayoutModal()">
                    Pedir Transferência
                </button>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <strong>Mínimo não atingido</strong><br>
                Precisas de <?php echo $min_payout; ?>€ para pedir um levantamento.<br>
                Faltam <strong><?php echo number_format($min_payout - $affiliate->unpaid_commission, 2); ?>€</strong>.
            </div>
        <?php endif; ?>
        
        <div class="disclaimer-box">
            <p><strong>Importante:</strong> Levantamentos mínimos de <strong>20€ para Tier I</strong>. Outros tiers não têm mínimo.</p>
            <p>Consulta os <a href="https://thecouplesbrand.com/terms-and-conditions" target="_blank">Termos e Condições</a> para mais informações sobre o programa de afiliados.</p>
        </div>
    </div>
    
    <!-- Recent Sales -->
    <?php if (!empty($recent_referrals)): ?>
    <div class="recent-sales-section">
        <h2>Vendas Recentes</h2>
        <div class="sales-table">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Encomenda</th>
                        <th>Total</th>
                        <th>Comissão</th>
                        <th>Estado</th>
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
                                <?php echo $ref->status == 'paid' ? 'Pago' : 'Pendente'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tier Info & Conditions -->
    <div class="conditions-section">
        <h2>Condições do Programa</h2>
        
        <div class="tier-benefits">
            <div class="benefit-item">
                <span class="benefit-icon">✓</span>
                <div>
                    <strong>Comissão:</strong> <?php echo $commission_rate; ?>% em todas as vendas geradas pelo teu código
                </div>
            </div>
            
            <div class="benefit-item">
                <span class="benefit-icon">✓</span>
                <div>
                    <strong>Levantamento mínimo:</strong> 
                    <?php echo ($affiliate->tier === 'tier_1') ? '20€ para Tier I' : 'Sem mínimo para o teu tier'; ?>
                </div>
            </div>
            
            <div class="benefit-item">
                <span class="benefit-icon">✓</span>
                <div>
                    <strong>Prazo de pagamento:</strong> 
                    Receberás o teu dinheiro em até <?php echo $payment_days; ?> após pedido
                </div>
            </div>
            
            <div class="benefit-item">
                <span class="benefit-icon">✓</span>
                <div>
                    <strong>Desconto do código:</strong> 5€ de desconto por utilização (cada cliente pode usar 1 vez)
                </div>
            </div>
            
            <div class="benefit-item">
                <span class="benefit-icon">✓</span>
                <div>
                    <strong>Validade:</strong> O teu código é válido indefinidamente e não expira
                </div>
            </div>
        </div>
        
        <div class="tier-upgrade">
            <?php if ($affiliate->tier === 'tier_1'): ?>
                <p><strong>Queres subir de Tier?</strong></p>
                <p>Descobre os benefícios de Tier II (15% comissão) ou Embaixador (20% comissão) com vantagens exclusivas!</p>
                <a href="https://thecouplesbrand.com/influencers-program/#tiers" class="btn-secondary">Saber Mais sobre Tiers</a>
            <?php else: ?>
                <p><strong>Parabéns!</strong> És um membro <?php echo $tier_name; ?> com benefícios premium.</p>
            <?php endif; ?>
        </div>
        
        <div class="disclaimer-box" style="margin-top: 20px;">
            <p><strong>Termos e Condições:</strong></p>
            <p>Para informações completas sobre o programa de afiliados, consulta os nossos <a href="https://thecouplesbrand.com/terms-and-conditions" target="_blank">Termos e Condições</a>.</p>
        </div>
        
        <!-- Support Contact -->
        <div style="margin-top: 30px; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; color: white; text-align: center;">
            <h3 style="margin: 0 0 10px 0;">Precisas de Ajuda?</h3>
            <p style="margin: 0; opacity: 0.9;">Tens questões sobre a tua conta ou precisas de suporte?</p>
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
        <h2>💸 Pedir Transferência</h2>
        
        <form id="payoutForm">
            <div class="form-group">
                <label>Valor a Transferir</label>
                <input type="text" value="<?php echo number_format($affiliate->unpaid_commission, 2); ?>€" readonly>
            </div>
            
            <div class="form-group">
                <label>Método de Pagamento *</label>
                <select name="payment_method" required>
                    <option value="">Seleciona...</option>
                    <option value="bank_transfer">Transferência Bancária (NIB)</option>
                    <option value="mbway">MB Way</option>
                    <option value="paypal">PayPal</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Dados para Transferência *</label>
                <textarea name="payment_details" rows="4" placeholder="Ex: NIB (21 dígitos), número de telefone MB Way, ou email PayPal" required></textarea>
                <small>⚠️ Certifica-te que os dados estão corretos!</small>
            </div>
            
            <div class="form-group">
                <label>Notas Adicionais (opcional)</label>
                <textarea name="notes" rows="2" placeholder="Alguma informação extra que queiras partilhar..."></textarea>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%;">Confirmar Pedido</button>
            <div id="payoutMessage" style="margin-top: 15px;"></div>
        </form>
    </div>
</div>

<script>
// Copy promo code
function copyCode() {
    const code = document.getElementById('promoCode').textContent;
    navigator.clipboard.writeText(code).then(() => {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '✓ Copiado!';
        btn.style.background = 'rgba(76, 175, 80, 0.5)';
        btn.style.borderColor = 'rgba(76, 175, 80, 0.7)';
        
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '';
            btn.style.borderColor = '';
        }, 2000);
    }).catch(err => {
        alert('Código: ' + code + '\n\nCopia manualmente este código.');
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
    const modal = document.getElementById('payoutModal');
    if (event.target === modal) {
        closePayoutModal();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePayoutModal();
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
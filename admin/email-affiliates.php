<?php
/**
 * Email Affiliates Page
 * Send bulk emails to affiliates
 * 
 * Esta página permite enviar e-mails em massa para os afiliados.
 * Podes escolher enviar para todos ou apenas para um tier específico.
 * Perfeito para avisar sobre mudanças no programa ou promoções.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Processar envio de e-mail
if (isset($_POST['send_email']) && check_admin_referer('send_affiliate_email_nonce')) {
    
    $tier = sanitize_text_field($_POST['target_tier']);
    $subject = sanitize_text_field($_POST['email_subject']);
    $message = wp_kses_post($_POST['email_message']);
    $include_tier_info = isset($_POST['include_tier_info']);
    
    // Validação básica
    $errors = array();
    if (empty($subject)) {
        $errors[] = 'O assunto do e-mail é obrigatório';
    }
    if (empty($message)) {
        $errors[] = 'A mensagem do e-mail é obrigatória';
    }
    
    if (empty($errors)) {
        // Obter emails dos afiliados
        $affiliates = cas_get_affiliate_emails($tier, 'active');
        
        if (empty($affiliates)) {
            $errors[] = 'Não foram encontrados afiliados ativos para enviar e-mail';
        } else {
            // Contadores
            $sent_count = 0;
            $failed_count = 0;
            
            // Cabeçalhos do e-mail
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . cas_get_support_email() . '>'
            );
            
            // Enviar para cada afiliado
            foreach ($affiliates as $affiliate) {
                // Personalizar mensagem se solicitado
                $personalized_message = $message;
                
                if ($include_tier_info) {
                    $tier_name = cas_get_tier_name($affiliate->tier);
                    $tier_badge = cas_get_tier_badge($affiliate->tier);
                    $commission = cas_get_tier_setting($affiliate->tier, 'commission');
                    
                    $tier_info = "<div style='background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
                    $tier_info .= "<p style='margin: 5px 0;'><strong>O Teu Tier:</strong> {$tier_badge} {$tier_name}</p>";
                    $tier_info .= "<p style='margin: 5px 0;'><strong>O Teu Código:</strong> {$affiliate->affiliate_code}</p>";
                    $tier_info .= "<p style='margin: 5px 0;'><strong>Taxa de Comissão:</strong> {$commission}%</p>";
                    $tier_info .= "</div>";
                    
                    $personalized_message .= $tier_info;
                }
                
                // Template HTML do e-mail
                $email_html = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
                        .content { background: white; padding: 30px; }
                        .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 12px 12px; }
                        a { color: #667eea; text-decoration: none; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1 style='margin: 0; color: white;'>".get_bloginfo('name')."</h1>
                            <p style='margin: 10px 0 0 0; opacity: 0.9;'>Programa de Afiliados</p>
                        </div>
                        <div class='content'>
                            <p>Olá <strong>".esc_html($affiliate->display_name)."</strong>,</p>
                            {$personalized_message}
                            <p style='margin-top: 30px;'>
                                <a href='".wc_get_account_endpoint_url('affiliate-dashboard')."' style='background: #667eea; color: white; padding: 12px 24px; border-radius: 6px; display: inline-block; text-decoration: none;'>Ver Dashboard</a>
                            </p>
                        </div>
                        <div class='footer'>
                            <p>Tens dúvidas? Contacta-nos: <a href='mailto:".cas_get_support_email()."'>".cas_get_support_email()."</a></p>
                            <p>&copy; ".date('Y')." ".get_bloginfo('name').". Todos os direitos reservados.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                // Enviar e-mail
                if (wp_mail($affiliate->user_email, $subject, $email_html, $headers)) {
                    $sent_count++;
                } else {
                    $failed_count++;
                }
            }
            
            // Mensagem de resultado
            if ($sent_count > 0) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>✅ Sucesso!</strong> E-mail enviado para '.$sent_count.' afiliado(s).</p>';
                if ($failed_count > 0) {
                    echo '<p>⚠️ '.$failed_count.' e-mail(s) falharam. Verifica as configurações SMTP do WordPress.</p>';
                }
                echo '</div>';
            } else {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>❌ Erro:</strong> Não foi possível enviar nenhum e-mail.</p>';
                echo '</div>';
            }
        }
    }
    
    // Mostrar erros se existirem
    if (!empty($errors)) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>❌ Erros encontrados:</strong></p><ul>';
        foreach ($errors as $error) {
            echo '<li>'.esc_html($error).'</li>';
        }
        echo '</ul></div>';
    }
}

// Obter contagens por tier para mostrar na interface
$tier_counts = cas_get_affiliate_counts_by_tier();
$total_affiliates = array_sum($tier_counts);

?>

<div class="wrap cas-email-page">
    <h1>📧 Enviar E-mail para Afiliados</h1>
    
    <!-- Cabeçalho -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin: 20px 0;">
        <h2 style="margin: 0 0 10px 0; color: white;">Comunicação em Massa</h2>
        <p style="margin: 0; opacity: 0.9;">Envia e-mails para todos os afiliados ou apenas para um tier específico. Perfeito para avisar sobre mudanças importantes, promoções ou atualizações do programa.</p>
    </div>
    
    <!-- Estatísticas Rápidas -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 36px; font-weight: bold; color: #667eea;"><?php echo $total_affiliates; ?></div>
            <div style="color: #666; margin-top: 5px;">Total de Afiliados</div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 36px; font-weight: bold; color: #667eea;"><?php echo $tier_counts['tier_1']; ?></div>
            <div style="color: #666; margin-top: 5px;">⭐ Tier I</div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 36px; font-weight: bold; color: #f59e0b;"><?php echo $tier_counts['tier_2']; ?></div>
            <div style="color: #666; margin-top: 5px;">💎 Tier II</div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 36px; font-weight: bold; color: #ec4899;"><?php echo $tier_counts['ambassador']; ?></div>
            <div style="color: #666; margin-top: 5px;">👑 Ambassadors</div>
        </div>
    </div>
    
    <!-- Formulário de E-mail -->
    <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <form method="post" action="">
            <?php wp_nonce_field('send_affiliate_email_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="target_tier">Enviar Para</label>
                    </th>
                    <td>
                        <select name="target_tier" id="target_tier" class="regular-text">
                            <option value="">Todos os Afiliados Ativos (<?php echo $total_affiliates; ?>)</option>
                            <option value="tier_1">⭐ Apenas Tier I (<?php echo $tier_counts['tier_1']; ?>)</option>
                            <option value="tier_2">💎 Apenas Tier II (<?php echo $tier_counts['tier_2']; ?>)</option>
                            <option value="ambassador">👑 Apenas Ambassadors (<?php echo $tier_counts['ambassador']; ?>)</option>
                        </select>
                        <p class="description">Escolhe quem vai receber este e-mail</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="email_subject">Assunto *</label>
                    </th>
                    <td>
                        <input type="text" 
                               name="email_subject" 
                               id="email_subject" 
                               class="large-text" 
                               placeholder="Ex: Atualizações Importantes do Programa de Afiliados"
                               required>
                        <p class="description">Assunto do e-mail (obrigatório)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="email_message">Mensagem *</label>
                    </th>
                    <td>
                        <?php
                        wp_editor('', 'email_message', array(
                            'textarea_name' => 'email_message',
                            'textarea_rows' => 12,
                            'media_buttons' => false,
                            'teeny' => false,
                            'quicktags' => true,
                            'tinymce' => array(
                                'toolbar1' => 'bold,italic,underline,link,bullist,numlist,blockquote',
                                'toolbar2' => ''
                            )
                        ));
                        ?>
                        <p class="description">Escreve a mensagem que queres enviar. Podes usar formatação HTML básica.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        Opções
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="include_tier_info" value="1" checked>
                            Incluir informação do tier e código de cada afiliado
                        </label>
                        <p class="description">Se ativo, o e-mail mostrará automaticamente o tier, código e taxa de comissão de cada afiliado</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="send_email" class="button button-primary button-large">
                    📧 Enviar E-mail Agora
                </button>
            </p>
        </form>
    </div>
    
    <!-- Dicas e Avisos -->
    <div style="background: #f0f9ff; border-left: 4px solid #0284c7; padding: 20px; border-radius: 6px; margin: 20px 0;">
        <h3 style="margin: 0 0 10px 0; color: #0c4a6e;">💡 Dicas para E-mails Eficazes</h3>
        <ul style="margin: 10px 0; padding-left: 20px; color: #0c4a6e;">
            <li><strong>Assunto claro:</strong> Usa um assunto que explique imediatamente o propósito do e-mail</li>
            <li><strong>Seja breve:</strong> Afiliados preferem mensagens diretas e concisas</li>
            <li><strong>Call-to-action:</strong> Inclui sempre o que queres que façam (ex: "Usa o teu código hoje")</li>
            <li><strong>Personalização:</strong> Ativa a opção de incluir info do tier para mensagens mais personalizadas</li>
            <li><strong>Testa primeiro:</strong> Considera enviar para um tier pequeno primeiro para testar</li>
        </ul>
    </div>
    
    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; border-radius: 6px; margin: 20px 0;">
        <h3 style="margin: 0 0 10px 0; color: #92400e;">⚠️ Importante</h3>
        <ul style="margin: 10px 0; padding-left: 20px; color: #92400e;">
            <li>Os e-mails são enviados imediatamente - não há forma de desfazer</li>
            <li>Se tens muitos afiliados, o envio pode demorar alguns minutos</li>
            <li>Certifica-te que o WordPress está configurado para enviar e-mails (usa um plugin SMTP se necessário)</li>
            <li>Respeita as leis de proteção de dados (RGPD) ao enviar e-mails em massa</li>
        </ul>
    </div>
    
    <!-- Sugestões de E-mails -->
    <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 30px;">
        <h3 style="margin: 0 0 15px 0;">📝 Exemplos de E-mails que Podes Enviar</h3>
        <div style="display: grid; gap: 15px;">
            <div style="padding: 15px; background: #f9fafb; border-left: 4px solid #667eea; border-radius: 4px;">
                <strong>Mudanças nas Taxas de Comissão</strong>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Avisa sobre aumentos ou mudanças nas taxas, explicando os benefícios</p>
            </div>
            <div style="padding: 15px; background: #f9fafb; border-left: 4px solid #10b981; border-radius: 4px;">
                <strong>Promoção Especial</strong>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Informa sobre promoções onde os afiliados podem ganhar mais</p>
            </div>
            <div style="padding: 15px; background: #f9fafb; border-left: 4px solid #f59e0b; border-radius: 4px;">
                <strong>Dicas de Marketing</strong>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Partilha estratégias para ajudar os afiliados a vender mais</p>
            </div>
            <div style="padding: 15px; background: #f9fafb; border-left: 4px solid #ec4899; border-radius: 4px;">
                <strong>Novos Produtos</strong>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Anuncia novos produtos que podem promover</p>
            </div>
        </div>
    </div>
</div>

<style>
.cas-email-page .button-primary.button-large {
    padding: 12px 32px;
    font-size: 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    transition: all 0.3s;
    height: auto;
}

.cas-email-page .button-primary.button-large:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

.cas-email-page .form-table th {
    font-weight: 600;
    color: #1e293b;
    width: 200px;
}

.cas-email-page .form-table td {
    padding: 15px 10px;
}
</style>
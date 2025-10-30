// Custom My Account JavaScript for Affiliate System

jQuery(document).ready(function($) {
    
    // Smooth scroll for affiliate dashboard
    if (window.location.hash === '#affiliate-dashboard') {
        $('html, body').animate({
            scrollTop: $('.woocommerce-MyAccount-content').offset().top - 100
        }, 500);
    }
    
});

// Copy promo code to clipboard
function copyCode() {
    const code = document.getElementById('promoCode').textContent;
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
        alert('Code: ' + code + '\n\nCopy this code manually.');
    });
}

// Copy share text for Instagram
function copyShareText(code) {
    const text = `Use my code ${code} and get 5€ off!\n\nShop now at: ${window.location.origin}`;
    navigator.clipboard.writeText(text).then(() => {
        alert('✓ Text copied! Now paste it in your Instagram post or story.');
    }).catch(err => {
        alert('Text to share:\n\n' + text);
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
if (document.getElementById('payoutModal')) {
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
}

// Handle payout form submission
jQuery(document).ready(function($) {
    
    $('#payoutForm').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $(this).find('button[type="submit"]');
        const originalText = btn.html();
        btn.prop('disabled', true).html('Enviando...');
        
        $.ajax({
            url: casMyAccount.ajax_url,
            type: 'POST',
            data: {
                action: 'request_affiliate_payout',
                nonce: casMyAccount.nonce,
                payment_method: $('select[name="payment_method"]').val(),
                payment_details: $('textarea[name="payment_details"]').val(),
                notes: $('textarea[name="notes"]').val()
            },
            success: function(response) {
                const msgDiv = $('#payoutMessage');
                
                if (response.success) {
                    msgDiv.html('<div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; border-radius: 6px; color: #065f46;">✓ ' + response.data + '</div>');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    msgDiv.html('<div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 6px; color: #991b1b;">✗ Erro: ' + response.data + '</div>');
                    btn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                const msgDiv = $('#payoutMessage');
                msgDiv.html('<div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 6px; color: #991b1b;">✗ Erro de conexão. Tenta novamente.</div>');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Update payment details placeholder based on method
    $('select[name="payment_method"]').on('change', function() {
        const method = $(this).val();
        const detailsField = $('textarea[name="payment_details"]');
        
        if (method === 'bank_transfer') {
            detailsField.attr('placeholder', 'Insere o teu NIB (21 dígitos)');
        } else if (method === 'mbway') {
            detailsField.attr('placeholder', 'Insere o teu número de telemóvel');
        } else if (method === 'paypal') {
            detailsField.attr('placeholder', 'Insere o teu email PayPal');
        }
    });
    
});
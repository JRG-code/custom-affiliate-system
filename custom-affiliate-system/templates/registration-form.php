<?php
// Check if form was submitted and show errors
$registration_error = '';
$registration_success = false;

if (isset($_GET['registration']) && $_GET['registration'] == 'failed') {
    $registration_error = isset($_GET['error']) ? urldecode($_GET['error']) : 'Registration failed. Please try again.';
}

if (isset($_GET['registration']) && $_GET['registration'] == 'success') {
    $registration_success = true;
}
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
.affiliate-reg-form input, .affiliate-reg-form textarea {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border: 2px solid #e5e7eb;
    border-radius: 6px;
    font-size: 16px;
    box-sizing: border-box;
}
.affiliate-reg-form input:focus {
    outline: none;
    border-color: #667eea;
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
    transition: opacity 0.2s;
}
.affiliate-reg-form button:hover {
    opacity: 0.9;
}
.affiliate-reg-form button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.error-message {
    background: #fee2e2;
    border-left: 4px solid #ef4444;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 6px;
    color: #991b1b;
}
.success-message {
    background: #d1fae5;
    border-left: 4px solid #10b981;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 6px;
    color: #065f46;
    text-align: center;
}
.password-strength {
    font-size: 12px;
    margin-top: -10px;
    margin-bottom: 15px;
}
</style>

<div class="affiliate-reg-form">
    <?php if ($registration_success): ?>
        <div class="success-message">
            <h3 style="margin: 0 0 10px 0;">✅ Registration Successful!</h3>
            <p style="margin: 0;">You're being redirected to your dashboard...</p>
        </div>
        <script>
            setTimeout(function() {
                window.location.href = '<?php echo home_url('/affiliate-dashboard/'); ?>';
            }, 2000);
        </script>
    <?php else: ?>
        <h2>🚀 Join Our Affiliate Program</h2>
        <p style="text-align: center; color: #666; margin-bottom: 30px;">
            Earn 10% commission on every sale!
        </p>
        
        <?php if ($registration_error): ?>
            <div class="error-message">
                <strong>❌ Error:</strong> <?php echo esc_html($registration_error); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="" id="affiliateRegForm">
            <?php wp_nonce_field('affiliate_registration', 'affiliate_reg_nonce'); ?>
            
            <input 
                type="text" 
                name="full_name" 
                id="full_name"
                placeholder="Full Name" 
                required
                minlength="3"
            >
            
            <input 
                type="email" 
                name="user_email" 
                id="user_email"
                placeholder="Email Address" 
                required
            >
            
            <input 
                type="text" 
                name="username" 
                id="username"
                placeholder="Username (for your promo code)" 
                required
                minlength="3"
                pattern="[a-zA-Z0-9_]+"
                title="Only letters, numbers and underscores"
            >
            <small style="display: block; margin-top: -10px; margin-bottom: 15px; color: #666; font-size: 12px;">
                Only letters, numbers and underscores. This will be your promo code base.
            </small>
            
            <input 
                type="password" 
                name="password" 
                id="password"
                placeholder="Password" 
                required
                minlength="6"
            >
            <div id="password-strength" class="password-strength"></div>
            
            <input 
                type="text" 
                name="whatsapp" 
                id="whatsapp"
                placeholder="WhatsApp (optional)"
            >
            
            <input 
                type="text" 
                name="instagram" 
                id="instagram"
                placeholder="Instagram Handle (optional)"
            >
            
            <label style="display: block; margin: 20px 0;">
                <input type="checkbox" name="terms" id="terms" required>
                I accept the <a href="/terms" target="_blank">terms and conditions</a>
            </label>
            
            <button type="submit" name="register_affiliate" id="submitBtn">
                Create Account & Get My Code
            </button>
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            Already have an account? <a href="<?php echo wp_login_url(home_url('/affiliate-dashboard/')); ?>">Login</a>
        </p>
    <?php endif; ?>
</div>

<script>
// Password strength indicator
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.getElementById('password-strength');
    let strength = 0;
    
    if (password.length >= 6) strength++;
    if (password.length >= 10) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    const messages = ['', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    const colors = ['', '#ef4444', '#f59e0b', '#3b82f6', '#10b981', '#059669'];
    
    if (password.length > 0) {
        strengthDiv.textContent = 'Password strength: ' + messages[strength];
        strengthDiv.style.color = colors[strength];
    } else {
        strengthDiv.textContent = '';
    }
});

// Form validation
document.getElementById('affiliateRegForm').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    const username = document.getElementById('username').value;
    
    // Check username format
    if (!/^[a-zA-Z0-9_]+$/.test(username)) {
        e.preventDefault();
        alert('Username can only contain letters, numbers and underscores.');
        return false;
    }
    
    // Disable button to prevent double submission
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating Account...';
    
    // Re-enable after 5 seconds in case of error
    setTimeout(function() {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create Account & Get My Code';
    }, 5000);
});

// Username validation - convert to lowercase and remove spaces
document.getElementById('username').addEventListener('input', function() {
    this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
});
</script>
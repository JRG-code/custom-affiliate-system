<?php
/**
 * Advanced Features Page (PRO Only)
 * Email customization and other advanced settings
 */

if (!defined('ABSPATH')) exit;

// Check if Pro is active
if (!cas_is_pro_active()) {
    echo cas_upgrade_notice('Advanced Features');
    return;
}

// Handle email template save
if (isset($_POST['save_email_template']) && check_admin_referer('cas_save_email_template')) {
    $email_template = array(
        'subject' => sanitize_text_field($_POST['email_subject']),
        'heading' => sanitize_text_field($_POST['email_heading']),
        'message' => wp_kses_post($_POST['email_message']),
        'button_text' => sanitize_text_field($_POST['email_button_text']),
        'footer_text' => wp_kses_post($_POST['email_footer'])
    );

    update_option('cas_welcome_email_template', $email_template);

    // Set transient for success message
    set_transient('cas_email_template_saved', true, 10);
    wp_redirect(add_query_arg(array('page' => 'affiliate-advanced', 'saved' => '1'), admin_url('admin.php')));
    exit;
}

// Display success message
if (isset($_GET['saved']) && $_GET['saved'] == '1') {
    if (get_transient('cas_email_template_saved')) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Success!</strong> Email template has been saved successfully.</p></div>';
        delete_transient('cas_email_template_saved');
    }
}

// Get current email template or defaults
$template = get_option('cas_welcome_email_template', array(
    'subject' => 'Welcome to Our Affiliate Program!',
    'heading' => 'Welcome to the Influencer Program!',
    'message' => "<p>Hello <strong>{affiliate_name}</strong>,</p>\n<p>You've been added to our affiliate program!</p>\n<p>You earn <strong>{commission_rate}%</strong> commission on every sale made with your code!</p>",
    'button_text' => 'Go to Dashboard',
    'footer_text' => '<p style="font-size: 12px; color: #666; margin-top: 30px;">Questions? Contact us at {support_email}</p>'
));

?>

<div class="wrap">
    <h1 class="wp-heading-inline">🚀 Advanced Features</h1>
    <a href="<?php echo admin_url('admin.php?page=affiliate-system'); ?>" class="page-title-action">Back to Overview</a>
    <hr class="wp-header-end">

    <!-- PRO Active Banner -->
    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 20px; border-radius: 12px; margin: 20px 0;">
        <h2 style="margin: 0 0 5px 0; color: white;">✓ PRO Features Unlocked</h2>
        <p style="margin: 0; opacity: 0.9;">You have access to all advanced customization options.</p>
    </div>

    <!-- Welcome Email Customization -->
    <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2 style="margin: 0 0 10px 0;">📧 Welcome Email Template</h2>
        <p style="color: #666; margin: 0 0 20px 0;">Customize the email that customers receive when they become affiliates.</p>

        <form method="post" action="">
            <?php wp_nonce_field('cas_save_email_template'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="email_subject">Email Subject</label>
                    </th>
                    <td>
                        <input type="text" name="email_subject" id="email_subject" class="regular-text" value="<?php echo esc_attr($template['subject']); ?>" required>
                        <p class="description">The subject line of the welcome email</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="email_heading">Email Heading</label>
                    </th>
                    <td>
                        <input type="text" name="email_heading" id="email_heading" class="regular-text" value="<?php echo esc_attr($template['heading']); ?>" required>
                        <p class="description">Main heading at the top of the email</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="email_message">Email Message</label>
                    </th>
                    <td>
                        <?php
                        wp_editor($template['message'], 'email_message', array(
                            'textarea_name' => 'email_message',
                            'textarea_rows' => 10,
                            'media_buttons' => false,
                            'teeny' => true,
                            'quicktags' => true
                        ));
                        ?>
                        <p class="description">Main content of the email. You can use HTML.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="email_button_text">Button Text</label>
                    </th>
                    <td>
                        <input type="text" name="email_button_text" id="email_button_text" class="regular-text" value="<?php echo esc_attr($template['button_text']); ?>" required>
                        <p class="description">Text for the call-to-action button</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="email_footer">Email Footer</label>
                    </th>
                    <td>
                        <textarea name="email_footer" id="email_footer" rows="3" class="large-text" style="font-family: monospace;"><?php echo esc_textarea($template['footer_text']); ?></textarea>
                        <p class="description">Footer text at the bottom of the email (can include HTML)</p>
                    </td>
                </tr>
            </table>

            <!-- Available Variables -->
            <div style="background: #f0f9ff; border-left: 4px solid #0284c7; padding: 20px; border-radius: 6px; margin: 20px 0;">
                <h3 style="margin: 0 0 15px 0; color: #0c4a6e;">📋 Available Variables</h3>
                <p style="margin: 0 0 10px 0; color: #0c4a6e;">You can use these placeholders in your email content:</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 15px;">
                    <code style="background: white; padding: 8px 12px; border-radius: 4px; display: block;">{affiliate_name}</code>
                    <code style="background: white; padding: 8px 12px; border-radius: 4px; display: block;">{affiliate_code}</code>
                    <code style="background: white; padding: 8px 12px; border-radius: 4px; display: block;">{commission_rate}</code>
                    <code style="background: white; padding: 8px 12px; border-radius: 4px; display: block;">{tier_name}</code>
                    <code style="background: white; padding: 8px 12px; border-radius: 4px; display: block;">{tier_badge}</code>
                    <code style="background: white; padding: 8px 12px; border-radius: 4px; display: block;">{coupon_discount}</code>
                    <code style="background: white; padding: 8px 12px; border-radius: 4px; display: block;">{support_email}</code>
                    <code style="background: white; padding: 8px 12px; border-radius: 4px; display: block;">{dashboard_url}</code>
                </div>
            </div>

            <p class="submit">
                <button type="submit" name="save_email_template" class="button button-primary button-large">
                    💾 Save Email Template
                </button>
                <button type="button" onclick="sendTestEmail()" class="button button-large" style="margin-left: 10px;">
                    📨 Send Test Email
                </button>
            </p>
        </form>
    </div>

    <!-- Email Preview -->
    <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2 style="margin: 0 0 20px 0;">👁️ Email Preview</h2>

        <div style="background: #f9fafb; padding: 30px; border-radius: 8px; font-family: Arial, sans-serif;">
            <h2 style="color: #667eea; margin: 0 0 20px 0;"><?php echo esc_html($template['heading']); ?></h2>

            <div style="background: #f0f0f0; padding: 20px; margin: 20px 0; text-align: center; border-radius: 8px;">
                <h1 style="color: #667eea; font-size: 36px; margin: 0;">EXAMPLECODE</h1>
                <p style="margin: 10px 0 0 0;">Your unique promotional code</p>
            </div>

            <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <?php echo wpautop($template['message']); ?>
            </div>

            <p style="text-align: center; margin: 30px 0;">
                <a href="#" style="background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">
                    <?php echo esc_html($template['button_text']); ?>
                </a>
            </p>

            <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 30px;">
                <?php echo wpautop($template['footer_text']); ?>
            </div>
        </div>

        <p style="color: #666; font-size: 13px; margin-top: 15px;"><em>Note: This is a preview. Variables like {affiliate_name} will be replaced with actual data in the real email.</em></p>
    </div>

    <!-- More Features Coming Soon -->
    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; border-radius: 6px; margin: 20px 0;">
        <h3 style="margin: 0 0 10px 0; color: #92400e;">🚧 More Advanced Features Coming Soon!</h3>
        <p style="margin: 0; color: #92400e;">We're working on additional customization options for PRO users. Stay tuned!</p>
    </div>
</div>

<script>
function sendTestEmail() {
    if (!confirm('Send a test welcome email to your admin email address?')) {
        return;
    }

    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ Sending...';
    btn.disabled = true;

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=cas_send_test_welcome_email'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ Test email sent successfully! Check your inbox.');
        } else {
            alert('❌ Failed to send test email: ' + (data.data || 'Unknown error'));
        }
        btn.innerHTML = originalText;
        btn.disabled = false;
    })
    .catch(err => {
        alert('❌ Error sending test email. Please try again.');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
</script>

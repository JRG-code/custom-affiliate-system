<?php
/**
 * Advanced Features Page
 * Includes: Welcome Email customization and Send Affiliates Email
 */

if (!defined('ABSPATH')) exit;

// Handle welcome email template save
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

// Handle send email to affiliates
if (isset($_POST['send_email']) && check_admin_referer('send_affiliate_email_nonce')) {

    $tier = sanitize_text_field($_POST['target_tier']);
    $subject = sanitize_text_field($_POST['email_subject']);
    $message = wp_kses_post($_POST['email_message']);
    $include_tier_info = isset($_POST['include_tier_info']);

    $errors = array();
    if (empty($subject)) $errors[] = 'Email subject is required';
    if (empty($message)) $errors[] = 'Email message is required';

    if (empty($errors)) {
        $affiliates = cas_get_affiliate_emails($tier, 'active');

        if (empty($affiliates)) {
            $errors[] = 'No active affiliates found to send email';
        } else {
            $sent_count = 0;
            $failed_count = 0;

            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . cas_get_support_email() . '>'
            );

            foreach ($affiliates as $affiliate) {
                $personalized_message = $message;

                if ($include_tier_info) {
                    $tier_name = cas_get_tier_name($affiliate->tier);
                    $tier_badge = cas_get_tier_badge($affiliate->tier);
                    $commission = cas_get_tier_setting($affiliate->tier, 'commission');

                    $tier_info = "<div style='background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
                    $tier_info .= "<p style='margin: 5px 0;'><strong>Your Tier:</strong> {$tier_badge} {$tier_name}</p>";
                    $tier_info .= "<p style='margin: 5px 0;'><strong>Your Code:</strong> {$affiliate->affiliate_code}</p>";
                    $tier_info .= "<p style='margin: 5px 0;'><strong>Commission Rate:</strong> {$commission}%</p>";
                    $tier_info .= "</div>";

                    $personalized_message .= $tier_info;
                }

                $email_html = "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto;'>
                        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0;'>
                            <h1 style='margin: 0; color: white;'>".get_bloginfo('name')."</h1>
                            <p style='margin: 10px 0 0 0; opacity: 0.9;'>Affiliate Program</p>
                        </div>
                        <div style='background: white; padding: 30px;'>
                            <p>Hello <strong>".esc_html($affiliate->display_name)."</strong>,</p>
                            {$personalized_message}
                            <p style='margin-top: 30px;'>
                                <a href='".wc_get_account_endpoint_url('affiliate-dashboard')."' style='background: #667eea; color: white; padding: 12px 24px; border-radius: 6px; display: inline-block; text-decoration: none;'>View Dashboard</a>
                            </p>
                        </div>
                        <div style='background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 12px 12px;'>
                            <p>Questions? Contact us: <a href='mailto:".cas_get_support_email()."'>".cas_get_support_email()."</a></p>
                            <p>&copy; ".date('Y')." ".get_bloginfo('name').". All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";

                if (wp_mail($affiliate->user_email, $subject, $email_html, $headers)) {
                    $sent_count++;
                } else {
                    $failed_count++;
                }
            }

            if ($sent_count > 0) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>Success!</strong> Email sent to '.$sent_count.' affiliate(s).</p>';
                if ($failed_count > 0) {
                    echo '<p>Warning: '.$failed_count.' email(s) failed. Check WordPress SMTP settings.</p>';
                }
                echo '</div>';
            } else {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Error:</strong> Could not send any emails.</p>';
                echo '</div>';
            }
        }
    }

    if (!empty($errors)) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>Errors found:</strong></p><ul>';
        foreach ($errors as $error) {
            echo '<li>'.esc_html($error).'</li>';
        }
        echo '</ul></div>';
    }
}

// Display success message for template save
if (isset($_GET['saved']) && $_GET['saved'] == '1') {
    if (get_transient('cas_email_template_saved')) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Email template has been saved successfully.</p></div>';
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

$tier_counts = cas_get_affiliate_counts_by_tier();
$total_affiliates = array_sum($tier_counts);

?>

<div class="wrap">
    <?php cas_render_admin_navigation('affiliate-advanced'); ?>

    <h1 class="wp-heading-inline">Advanced Features</h1>
    <a href="<?php echo admin_url('admin.php?page=affiliate-system'); ?>" class="page-title-action">Back to Overview</a>
    <hr class="wp-header-end">

    <?php if (cas_is_pro_active()): ?>
    <div class="cas-pro-active-banner">
        <h2>PRO Features Unlocked</h2>
        <p>You have access to all advanced customization options.</p>
    </div>
    <?php endif; ?>

    <style>
    .cas-accordion {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin: 20px 0;
        overflow: hidden;
    }

    .cas-accordion-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 30px;
        cursor: pointer;
        background: white;
        border: none;
        width: 100%;
        text-align: left;
        transition: background 0.3s ease;
    }

    .cas-accordion-header:hover {
        background: #f9fafb;
    }

    .cas-accordion-title {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }

    .cas-accordion-arrow {
        font-size: 20px;
        color: #667eea;
        transition: transform 0.3s ease;
        font-weight: bold;
    }

    .cas-accordion.active .cas-accordion-arrow {
        transform: rotate(180deg);
    }

    .cas-accordion-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s ease, padding 0.4s ease;
        padding: 0 30px;
    }

    .cas-accordion.active .cas-accordion-content {
        max-height: 5000px;
        padding: 0 30px 30px 30px;
    }

    .cas-accordion-description {
        color: #6b7280;
        font-size: 14px;
        margin: 0 0 10px 0;
    }
    </style>

    <!-- Welcome Email Section -->
    <div class="cas-accordion">
        <button class="cas-accordion-header" onclick="toggleAccordion(this)">
            <div>
                <h2 class="cas-accordion-title">Welcome Email Template</h2>
                <p class="cas-accordion-description">Customize the email that customers receive when they become affiliates</p>
            </div>
            <span class="cas-accordion-arrow">▼</span>
        </button>
        <div class="cas-accordion-content">

        <?php if (!cas_is_pro_active()): ?>
        <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0;">
            <p style="margin: 0;"><strong>PRO Feature:</strong> Upgrade to customize welcome emails. <a href="<?php echo esc_url(cas_get_upgrade_url()); ?>" target="_blank">Learn More</a></p>
        </div>
        <?php endif; ?>

        <form method="post" action="" <?php echo !cas_is_pro_active() ? 'style="opacity: 0.6; pointer-events: none;"' : ''; ?>>
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

            <div class="cas-variables-box" style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>Available Variables</h3>
                <p>You can use these placeholders in your email content:</p>
                <div class="cas-variables-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                    <code>{affiliate_name}</code>
                    <code>{affiliate_code}</code>
                    <code>{commission_rate}</code>
                    <code>{tier_name}</code>
                    <code>{tier_badge}</code>
                    <code>{coupon_discount}</code>
                    <code>{support_email}</code>
                    <code>{dashboard_url}</code>
                </div>
            </div>

            <p class="submit">
                <button type="submit" name="save_email_template" class="button button-primary button-large">
                    Save Email Template
                </button>
                <button type="button" onclick="sendTestEmail()" class="button button-large" style="margin-left: 10px;">
                    Send Test Email
                </button>
            </p>
        </form>

        </div>
    </div>

    <!-- Send Affiliates Email Section -->
    <div class="cas-accordion">
        <button class="cas-accordion-header" onclick="toggleAccordion(this)">
            <div>
                <h2 class="cas-accordion-title">Send Affiliates Email</h2>
                <p class="cas-accordion-description">Send emails to all affiliates or specific tiers - perfect for important updates and promotions</p>
            </div>
            <span class="cas-accordion-arrow">▼</span>
        </button>
        <div class="cas-accordion-content">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
            <div style="background: #f9fafb; padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; color: #667eea;"><?php echo $total_affiliates; ?></div>
                <div style="color: #666; margin-top: 5px;">Total Affiliates</div>
            </div>
            <div style="background: #f9fafb; padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; color: #667eea;"><?php echo $tier_counts['tier_1']; ?></div>
                <div style="color: #666; margin-top: 5px;">Tier I</div>
            </div>
            <div style="background: #f9fafb; padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; color: #f59e0b;"><?php echo $tier_counts['tier_2']; ?></div>
                <div style="color: #666; margin-top: 5px;">Tier II</div>
            </div>
            <div style="background: #f9fafb; padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; color: #ec4899;"><?php echo $tier_counts['ambassador']; ?></div>
                <div style="color: #666; margin-top: 5px;">Ambassadors</div>
            </div>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field('send_affiliate_email_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="target_tier">Send To</label></th>
                    <td>
                        <select name="target_tier" id="target_tier" class="regular-text">
                            <option value="">All Active Affiliates (<?php echo $total_affiliates; ?>)</option>
                            <option value="tier_1">Tier I Only (<?php echo $tier_counts['tier_1']; ?>)</option>
                            <option value="tier_2">Tier II Only (<?php echo $tier_counts['tier_2']; ?>)</option>
                            <option value="ambassador">Ambassadors Only (<?php echo $tier_counts['ambassador']; ?>)</option>
                        </select>
                        <p class="description">Choose who will receive this email</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="email_subject">Subject</label></th>
                    <td>
                        <input type="text" name="email_subject" id="email_subject" class="large-text" placeholder="e.g., Important Affiliate Program Updates" required>
                        <p class="description">Email subject (required)</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="email_message">Message</label></th>
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
                        <p class="description">Write your message. You can use basic HTML formatting.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Options</th>
                    <td>
                        <label>
                            <input type="checkbox" name="include_tier_info" value="1" checked>
                            Include tier information and affiliate code
                        </label>
                        <p class="description">If enabled, email will automatically show each affiliate's tier, code, and commission rate</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="send_email" class="button button-primary button-large">
                    Send Email Now
                </button>
            </p>
        </form>

        <div style="background: #f0f9ff; border-left: 4px solid #0284c7; padding: 20px; border-radius: 6px; margin: 20px 0;">
            <h3 style="margin: 0 0 10px 0; color: #0c4a6e;">Tips for Effective Emails</h3>
            <ul style="margin: 10px 0; padding-left: 20px; color: #0c4a6e;">
                <li><strong>Clear subject:</strong> Use a subject that immediately explains the email's purpose</li>
                <li><strong>Be concise:</strong> Affiliates prefer direct, brief messages</li>
                <li><strong>Call-to-action:</strong> Always include what you want them to do</li>
                <li><strong>Personalization:</strong> Enable tier info option for more personalized messages</li>
                <li><strong>Test first:</strong> Consider sending to a small tier first to test</li>
            </ul>
        </div>

        </div>
    </div>
</div>

<script>
// Toggle accordion sections
function toggleAccordion(button) {
    const accordion = button.parentElement;
    accordion.classList.toggle('active');
}

function sendTestEmail() {
    if (!confirm('Send a test welcome email to your admin email address?')) {
        return;
    }

    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = 'Sending...';
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
            alert('Test email sent successfully! Check your inbox.');
        } else {
            alert('Failed to send test email: ' + (data.data || 'Unknown error'));
        }
        btn.innerHTML = originalText;
        btn.disabled = false;
    })
    .catch(err => {
        alert('Error sending test email. Please try again.');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
</script>

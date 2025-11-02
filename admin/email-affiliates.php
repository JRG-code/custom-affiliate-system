<?php
/**
 * Email Affiliates Page
 */

if (!defined('ABSPATH')) exit;

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
                echo '<p><strong>✅ Success!</strong> Email sent to '.$sent_count.' affiliate(s).</p>';
                if ($failed_count > 0) {
                    echo '<p>⚠️ '.$failed_count.' email(s) failed. Check WordPress SMTP settings.</p>';
                }
                echo '</div>';
            } else {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>❌ Error:</strong> Could not send any emails.</p>';
                echo '</div>';
            }
        }
    }
    
    if (!empty($errors)) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>❌ Errors found:</strong></p><ul>';
        foreach ($errors as $error) {
            echo '<li>'.esc_html($error).'</li>';
        }
        echo '</ul></div>';
    }
}

$tier_counts = cas_get_affiliate_counts_by_tier();
$total_affiliates = array_sum($tier_counts);

?>

<div class="wrap cas-email-page">
    <h1>📧 Email Affiliates</h1>
    
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin: 20px 0;">
        <h2 style="margin: 0 0 10px 0; color: white;">Mass Communication</h2>
        <p style="margin: 0; opacity: 0.9;">Send emails to all affiliates or specific tier. Perfect for important updates, promotions, or program changes.</p>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 36px; font-weight: bold; color: #667eea;"><?php echo $total_affiliates; ?></div>
            <div style="color: #666; margin-top: 5px;">Total Affiliates</div>
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
    
    <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <form method="post" action="">
            <?php wp_nonce_field('send_affiliate_email_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="target_tier">Send To</label></th>
                    <td>
                        <select name="target_tier" id="target_tier" class="regular-text">
                            <option value="">All Active Affiliates (<?php echo $total_affiliates; ?>)</option>
                            <option value="tier_1">⭐ Tier I Only (<?php echo $tier_counts['tier_1']; ?>)</option>
                            <option value="tier_2">💎 Tier II Only (<?php echo $tier_counts['tier_2']; ?>)</option>
                            <option value="ambassador">👑 Ambassadors Only (<?php echo $tier_counts['ambassador']; ?>)</option>
                        </select>
                        <p class="description">Choose who will receive this email</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="email_subject">Subject *</label></th>
                    <td>
                        <input type="text" name="email_subject" id="email_subject" class="large-text" placeholder="e.g., Important Affiliate Program Updates" required>
                        <p class="description">Email subject (required)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="email_message">Message *</label></th>
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
                    📧 Send Email Now
                </button>
            </p>
        </form>
    </div>
    
    <div style="background: #f0f9ff; border-left: 4px solid #0284c7; padding: 20px; border-radius: 6px; margin: 20px 0;">
        <h3 style="margin: 0 0 10px 0; color: #0c4a6e;">💡 Tips for Effective Emails</h3>
        <ul style="margin: 10px 0; padding-left: 20px; color: #0c4a6e;">
            <li><strong>Clear subject:</strong> Use a subject that immediately explains the email's purpose</li>
            <li><strong>Be concise:</strong> Affiliates prefer direct, brief messages</li>
            <li><strong>Call-to-action:</strong> Always include what you want them to do</li>
            <li><strong>Personalization:</strong> Enable tier info option for more personalized messages</li>
            <li><strong>Test first:</strong> Consider sending to a small tier first to test</li>
        </ul>
    </div>
</div>
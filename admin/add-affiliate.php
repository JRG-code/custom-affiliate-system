<?php
/**
 * Manually Add Existing Users as Affiliates
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

// Handle form submission
if (isset($_POST['add_affiliate']) && check_admin_referer('cas_add_affiliate')) {
    $user_id = intval($_POST['user_id']);
    $tier = sanitize_text_field($_POST['tier']);
    $custom_code = !empty($_POST['custom_code']) ? strtoupper(sanitize_text_field($_POST['custom_code'])) : '';
    
    $errors = array();
    
    // Validate user exists
    $user = get_userdata($user_id);
    if (!$user) {
        $errors[] = 'User not found';
    }
    
    // Check if user is already an affiliate
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}affiliates WHERE user_id = %d",
        $user_id
    ));
    
    if ($exists) {
        $errors[] = 'This user is already an affiliate';
    }
    
    // Validate tier
    $available_tiers = cas_get_available_tiers();
    if (!isset($available_tiers[$tier])) {
        $errors[] = 'Invalid tier selected';
    }
    
    if (empty($errors)) {
        // Generate affiliate code
        if (!empty($custom_code)) {
            $affiliate_code = $custom_code;
            
            // Check if code already exists
            $code_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}affiliates WHERE affiliate_code = %s",
                $affiliate_code
            ));
            
            if ($code_exists) {
                $errors[] = 'This affiliate code is already in use';
            }
        } else {
            // Auto-generate code
            $username = $user->user_login;
            
            if (preg_match('/^user\d+$/', $username)) {
                $display_name = $user->display_name;
                if (!empty($display_name)) {
                    $first_name = explode(' ', $display_name)[0];
                    $username = sanitize_user($first_name);
                }
            }

            $affiliate_code = strtoupper($username) . '5';
            $counter = 1;
            $original_code = $affiliate_code;
            
            while ($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}affiliates WHERE affiliate_code = %s",
                $affiliate_code
            ))) {
                $affiliate_code = $original_code . $counter;
                $counter++;
            }
        }
        
        if (empty($errors)) {
            // Get tier settings
            $commission_rate = cas_get_tier_setting($tier, 'commission');
            $coupon_discount = cas_get_tier_setting($tier, 'coupon_discount');
            $status = 'active';

            // Insert affiliate
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'affiliates',
                array(
                    'user_id' => $user_id,
                    'affiliate_code' => $affiliate_code,
                    'commission_rate' => $commission_rate,
                    'tier' => $tier,
                    'status' => $status,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%f', '%s', '%s', '%s')
            );
            
            if ($inserted) {
                // Create WooCommerce coupon
                $coupon = array(
                    'post_title' => strtolower($affiliate_code),
                    'post_content' => '',
                    'post_status' => 'publish',
                    'post_author' => 1,
                    'post_type' => 'shop_coupon'
                );
                
                $coupon_id = wp_insert_post($coupon);
                
                update_post_meta($coupon_id, 'discount_type', 'fixed_cart');
                update_post_meta($coupon_id, 'coupon_amount', $coupon_discount);
                update_post_meta($coupon_id, 'individual_use', 'yes');
                update_post_meta($coupon_id, 'usage_limit', '');
                update_post_meta($coupon_id, 'usage_limit_per_user', '1');
                update_post_meta($coupon_id, 'expiry_date', '');
                update_post_meta($coupon_id, 'free_shipping', 'no');
                update_post_meta($coupon_id, '_affiliate_user_id', $user_id);
                
                // Send welcome email
                $tier_name = cas_get_tier_name_v2($tier);
                $tier_badge = cas_get_tier_badge_v2($tier);
                
                $to = $user->user_email;
                $subject = 'Welcome to Our Affiliate Program!';
                $message = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Welcome to the Influencer Program!</h2>
                    <p>Hello <strong>{$user->display_name}</strong>,</p>
                    <p>You've been added to our affiliate program!</p>
                    <div style='background: #f0f0f0; padding: 20px; margin: 20px 0; text-align: center;'>
                        <h1 style='color: #667eea; font-size: 36px;'>{$affiliate_code}</h1>
                        <p>Your unique promotional code</p>
                    </div>
                    <div style='background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <p style='margin: 5px 0;'><strong>Your Tier:</strong> {$tier_badge} {$tier_name}</p>
                        <p style='margin: 5px 0;'><strong>Commission Rate:</strong> {$commission_rate}%</p>
                        <p style='margin: 5px 0;'><strong>Customer Discount:</strong> {$coupon_discount}€</p>
                    </div>
                    <p>You earn {$commission_rate}% commission on every sale made with your code!</p>
                    <p><a href='" . wc_get_account_endpoint_url('affiliate-dashboard') . "' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block;'>Go to Dashboard</a></p>
                </body>
                </html>
                ";
                
                $headers = array('Content-Type: text/html; charset=UTF-8');
                wp_mail($to, $subject, $message, $headers);
                
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>✅ Success!</strong> ' . esc_html($user->display_name) . ' has been added as an affiliate.</p>';
                echo '<p><strong>Affiliate Code:</strong> ' . esc_html($affiliate_code) . '</p>';
                echo '<p><strong>Tier:</strong> ' . esc_html($tier_name) . ' (' . $commission_rate . '% commission)</p>';
                echo '<p>Welcome email sent to ' . esc_html($user->user_email) . '</p>';
                echo '</div>';
            } else {
                $errors[] = 'Database error: Could not insert affiliate';
            }
        }
    }
    
    if (!empty($errors)) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>❌ Errors:</strong></p><ul>';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }
}

// Get all users who are NOT affiliates yet
$existing_affiliate_users = $wpdb->get_col("SELECT user_id FROM {$wpdb->prefix}affiliates");
$placeholder = implode(',', array_fill(0, count($existing_affiliate_users), '%d'));

if (count($existing_affiliate_users) > 0) {
    $query = $wpdb->prepare(
        "SELECT ID, user_login, user_email, display_name 
        FROM {$wpdb->users} 
        WHERE ID NOT IN ($placeholder)
        ORDER BY ID ASC",
        ...$existing_affiliate_users
    );
} else {
    $query = "SELECT ID, user_login, user_email, display_name 
        FROM {$wpdb->users} 
        ORDER BY ID ASC";
}

$non_affiliate_users = $wpdb->get_results($query);

// Get all affiliates for reference
$total_affiliates = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}affiliates");
$total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");

?>

<div class="wrap">
    <h1>➕ Add Affiliate Manually</h1>
    
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin: 20px 0;">
        <h2 style="margin: 0 0 10px 0; color: white;">Add Existing Users to Affiliate Program</h2>
        <p style="margin: 0; opacity: 0.9;">Convert existing WordPress users into affiliates with custom codes and tier selection.</p>
    </div>
    
    <!-- Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 36px; font-weight: bold; color: #667eea;"><?php echo $total_users; ?></div>
            <div style="color: #666; margin-top: 5px;">Total Users</div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 36px; font-weight: bold; color: #10b981;"><?php echo $total_affiliates; ?></div>
            <div style="color: #666; margin-top: 5px;">Current Affiliates</div>
        </div>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 36px; font-weight: bold; color: #f59e0b;"><?php echo count($non_affiliate_users); ?></div>
            <div style="color: #666; margin-top: 5px;">Available to Add</div>
        </div>
    </div>
    
    <?php if (empty($non_affiliate_users)): ?>
        <div style="background: white; padding: 40px; border-radius: 8px; text-align: center; margin: 20px 0;">
            <p style="font-size: 48px; margin: 0;">✅</p>
            <h3 style="margin: 15px 0 5px 0;">All Users Are Already Affiliates</h3>
            <p style="color: #666; margin: 0;">Every WordPress user on this site has been added to the affiliate program.</p>
            <p style="margin-top: 20px;">
                <a href="<?php echo admin_url('admin.php?page=affiliate-system'); ?>" class="button button-primary">View All Affiliates</a>
            </p>
        </div>
    <?php else: ?>
    
    <!-- Add Affiliate Form -->
    <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2>Add User as Affiliate</h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('cas_add_affiliate'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="user_id">Select User *</label>
                    </th>
                    <td>
                        <select name="user_id" id="user_id" class="regular-text" required>
                            <option value="">Choose a user...</option>
                            <?php foreach ($non_affiliate_users as $user): ?>
                            <option value="<?php echo $user->ID; ?>">
                                <?php echo esc_html($user->display_name); ?> 
                                (<?php echo esc_html($user->user_email); ?>) 
                                - ID: <?php echo $user->ID; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Select an existing WordPress user to convert into an affiliate</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="tier">Affiliate Tier *</label>
                    </th>
                    <td>
                        <select name="tier" id="tier" class="regular-text" required>
                            <?php 
                            $available_tiers = cas_get_available_tiers();
                            foreach ($available_tiers as $tier_id => $tier_data): 
                                $settings = cas_get_all_tier_settings($tier_id);
                            ?>
                            <option value="<?php echo esc_attr($tier_id); ?>">
                                <?php echo esc_html($tier_data['badge']); ?> 
                                <?php echo esc_html($tier_data['name']); ?> 
                                (<?php echo $settings['commission']; ?>% commission)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Choose the tier for this affiliate</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="custom_code">Custom Affiliate Code</label>
                    </th>
                    <td>
                        <input type="text" name="custom_code" id="custom_code" class="regular-text" placeholder="Optional - auto-generated if empty" pattern="[A-Z0-9_]+" style="text-transform: uppercase;">
                        <p class="description">Leave empty to auto-generate. Must be uppercase letters/numbers only.</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="add_affiliate" class="button button-primary button-large">
                    ➕ Add as Affiliate
                </button>
            </p>
        </form>
    </div>
    
    <!-- Available Users List -->
    <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2>Users Not Yet in Affiliate Program (<?php echo count($non_affiliate_users); ?>)</h2>
        
        <?php if (count($non_affiliate_users) > 10): ?>
        <p style="color: #666;">Showing first 10 users. Use the form above to add any user by ID.</p>
        <?php endif; ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 8%;">User ID</th>
                    <th style="width: 20%;">Name</th>
                    <th style="width: 25%;">Email</th>
                    <th style="width: 15%;">Username</th>
                    <th style="width: 32%;">Quick Add</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $display_users = array_slice($non_affiliate_users, 0, 10);
                foreach ($display_users as $user): 
                ?>
                <tr>
                    <td><strong><?php echo $user->ID; ?></strong></td>
                    <td><?php echo esc_html($user->display_name); ?></td>
                    <td><?php echo esc_html($user->user_email); ?></td>
                    <td><code><?php echo esc_html($user->user_login); ?></code></td>
                    <td>
                        <button class="button button-small" onclick="document.getElementById('user_id').value='<?php echo $user->ID; ?>'; document.getElementById('user_id').scrollIntoView({behavior: 'smooth', block: 'center'});">
                            Select This User
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php endif; ?>
    
    <!-- Tips -->
    <div style="background: #f0f9ff; border-left: 4px solid #0284c7; padding: 20px; border-radius: 6px; margin: 20px 0;">
        <h3 style="margin: 0 0 10px 0; color: #0c4a6e;">💡 Tips</h3>
        <ul style="margin: 10px 0; padding-left: 20px; color: #0c4a6e;">
            <li><strong>Automatic:</strong> New users are automatically added as affiliates (Tier I)</li>
            <li><strong>Manual:</strong> Use this page to add existing users who registered before the plugin was installed</li>
            <li><strong>Custom Codes:</strong> You can specify custom affiliate codes or let the system auto-generate them</li>
            <li><strong>Welcome Email:</strong> Users will receive a welcome email with their affiliate code and dashboard link</li>
            <li><strong>WooCommerce Coupon:</strong> A coupon is automatically created for each affiliate</li>
        </ul>
    </div>
</div>

<script>
// Auto-format custom code input to uppercase
document.getElementById('custom_code').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
});

// Preview selected user
document.getElementById('user_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption.value) {
        console.log('Selected user:', selectedOption.text);
    }
});
</script>
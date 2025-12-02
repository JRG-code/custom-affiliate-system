<?php
/**
 * Diagnostics Page - Debug affiliate tracking issues
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

// Get recent orders (HPOS compatible)
$recent_orders = wc_get_orders(array(
    'limit' => 20,
    'orderby' => 'date',
    'order' => 'DESC',
    'return' => 'ids'
));

// Get all affiliate coupons
$affiliate_coupons = $wpdb->get_results("
    SELECT p.ID, p.post_title, pm.meta_value as affiliate_user_id
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_affiliate_user_id'
    WHERE p.post_type = 'shop_coupon'
    AND pm.meta_value IS NOT NULL
    ORDER BY p.post_date DESC
    LIMIT 50
");

// Get recent referrals
$recent_referrals = $wpdb->get_results("
    SELECT r.*, a.affiliate_code
    FROM {$wpdb->prefix}affiliate_referrals r
    LEFT JOIN {$wpdb->prefix}affiliates a ON r.affiliate_id = a.id
    ORDER BY r.created_at DESC
    LIMIT 10
");

?>

<div class="wrap">
    <h1>🔍 Affiliate System Diagnostics</h1>

    <!-- Manual Order Check -->
    <div class="card" style="max-width: 800px; margin: 20px 0;">
        <h2>Manual Order Check</h2>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th><label for="order_id">Order ID:</label></th>
                    <td>
                        <input type="number" name="order_id" id="order_id" class="regular-text" required>
                        <button type="submit" name="check_order" class="button button-primary">Check Order</button>
                    </td>
                </tr>
            </table>
        </form>

        <?php
        if (isset($_POST['check_order']) && isset($_POST['order_id'])) {
            $order_id = intval($_POST['order_id']);
            $order = wc_get_order($order_id);

            echo '<div style="background: #f0f9ff; border-left: 4px solid #0284c7; padding: 15px; margin: 15px 0;">';
            echo '<h3>Order #' . $order_id . ' Details:</h3>';

            if ($order) {
                echo '<p><strong>Status:</strong> ' . $order->get_status() . '</p>';
                echo '<p><strong>Total:</strong> ' . $order->get_total() . '</p>';
                echo '<p><strong>Date:</strong> ' . $order->get_date_created()->format('Y-m-d H:i:s') . '</p>';

                $coupons = $order->get_coupon_codes();
                echo '<p><strong>Coupons Used:</strong> ' . (empty($coupons) ? 'None' : implode(', ', $coupons)) . '</p>';

                if (!empty($coupons)) {
                    foreach ($coupons as $coupon_code) {
                        $coupon = new WC_Coupon($coupon_code);
                        $coupon_id = $coupon->get_id();
                        $affiliate_user_id = get_post_meta($coupon_id, '_affiliate_user_id', true);

                        echo '<hr>';
                        echo '<p><strong>Coupon:</strong> ' . $coupon_code . ' (ID: ' . $coupon_id . ')</p>';
                        echo '<p><strong>Affiliate User ID:</strong> ' . ($affiliate_user_id ? $affiliate_user_id : '<span style="color: red;">NOT SET</span>') . '</p>';

                        if ($affiliate_user_id) {
                            $affiliate = $wpdb->get_row($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}affiliates WHERE user_id = %d",
                                $affiliate_user_id
                            ));

                            if ($affiliate) {
                                echo '<p><strong>Affiliate:</strong> ' . $affiliate->affiliate_code . ' (ID: ' . $affiliate->id . ', Status: ' . $affiliate->status . ', Tier: ' . $affiliate->tier . ')</p>';
                                echo '<p><strong>Commission Rate:</strong> ' . $affiliate->commission_rate . '%</p>';

                                // Check if self-referral
                                $allow_self = cas_get_tier_setting($affiliate->tier, 'allow_self_referral');
                                $order_user_id = $order->get_user_id();
                                $is_self = ($order_user_id == $affiliate_user_id);

                                echo '<p><strong>Order User ID:</strong> ' . $order_user_id . '</p>';
                                echo '<p><strong>Is Self-Referral:</strong> ' . ($is_self ? 'YES' : 'NO') . '</p>';
                                echo '<p><strong>Tier Allows Self-Referral:</strong> ' . ($allow_self ? 'YES' : 'NO') . '</p>';

                                if ($is_self && !$allow_self) {
                                    echo '<p style="color: red;"><strong>⚠️ BLOCKED:</strong> Self-referral not allowed for this tier</p>';
                                }
                            } else {
                                echo '<p style="color: red;"><strong>⚠️ ERROR:</strong> No affiliate found for user ID ' . $affiliate_user_id . '</p>';
                            }
                        }
                    }
                }

                // Check if referral exists
                $referral = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}affiliate_referrals WHERE order_id = %d",
                    $order_id
                ));

                echo '<hr>';
                if ($referral) {
                    echo '<p style="color: green;"><strong>✓ Referral Tracked:</strong> Yes</p>';
                    echo '<p><strong>Affiliate ID:</strong> ' . $referral->affiliate_id . '</p>';
                    echo '<p><strong>Commission:</strong> ' . $referral->commission_amount . ' (' . $referral->commission_rate . '%)</p>';
                    echo '<p><strong>Status:</strong> ' . $referral->status . '</p>';
                } else {
                    echo '<p style="color: red;"><strong>✗ Referral Tracked:</strong> No</p>';

                    // Try to track it now
                    echo '<form method="post" action="" style="margin-top: 15px;">';
                    echo '<input type="hidden" name="force_track_order" value="' . $order_id . '">';
                    echo '<button type="submit" class="button button-secondary">Force Track Commission Now</button>';
                    echo '</form>';
                }
            } else {
                echo '<p style="color: red;">Order not found!</p>';
            }

            echo '</div>';
        }

        // Handle force tracking
        if (isset($_POST['force_track_order'])) {
            $order_id = intval($_POST['force_track_order']);
            echo '<div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin: 15px 0;">';
            echo '<h3>Forcing Commission Tracking...</h3>';

            $plugin = Custom_Affiliate_System::get_instance();
            $plugin->track_commission($order_id);

            echo '<p>Check the logs above or refresh to see if tracking was successful.</p>';
            echo '</div>';
        }
        ?>
    </div>

    <!-- Affiliate Coupons Status -->
    <div class="card" style="max-width: 100%; margin: 20px 0;">
        <h2>Affiliate Coupons (<?php echo count($affiliate_coupons); ?>)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Coupon ID</th>
                    <th>Coupon Code</th>
                    <th>Affiliate User ID</th>
                    <th>Usage Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($affiliate_coupons as $coupon): ?>
                <tr>
                    <td><?php echo $coupon->ID; ?></td>
                    <td><strong><?php echo $coupon->post_title; ?></strong></td>
                    <td><?php echo $coupon->affiliate_user_id; ?></td>
                    <td><?php echo get_post_meta($coupon->ID, 'usage_count', true) ?: 0; ?></td>
                    <td>
                        <a href="<?php echo admin_url('post.php?post=' . $coupon->ID . '&action=edit'); ?>" class="button button-small">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Orders -->
    <div class="card" style="max-width: 100%; margin: 20px 0;">
        <h2>Recent Orders (Last 20)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Coupons</th>
                    <th>Tracked</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($recent_orders)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #999;">No orders found</td>
                </tr>
                <?php else:
                    foreach ($recent_orders as $order_id):
                        $order = wc_get_order($order_id);
                        if (!$order) continue;

                        $coupons = $order->get_coupon_codes();
                        $has_tracking = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_referrals WHERE order_id = %d",
                            $order_id
                        ));
                    ?>
                    <tr>
                        <td><a href="<?php echo esc_url($order->get_edit_order_url()); ?>">#<?php echo $order_id; ?></a></td>
                        <td><?php echo $order->get_date_created()->date('Y-m-d H:i'); ?></td>
                        <td><?php echo $order->get_status(); ?></td>
                        <td><?php echo empty($coupons) ? '-' : implode(', ', $coupons); ?></td>
                        <td><?php echo $has_tracking ? '<span style="color: green;">✓</span>' : '<span style="color: red;">✗</span>'; ?></td>
                    </tr>
                    <?php endforeach;
                endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Referrals -->
    <div class="card" style="max-width: 100%; margin: 20px 0;">
        <h2>Recent Referrals (Last 10)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Order</th>
                    <th>Affiliate</th>
                    <th>Coupon</th>
                    <th>Commission</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_referrals)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: #999;">No referrals found</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($recent_referrals as $ref): ?>
                    <tr>
                        <td><?php echo $ref->id; ?></td>
                        <td><a href="<?php echo admin_url('post.php?post=' . $ref->order_id . '&action=edit'); ?>">#<?php echo $ref->order_id; ?></a></td>
                        <td><?php echo $ref->affiliate_code; ?></td>
                        <td><?php echo $ref->coupon_code; ?></td>
                        <td><?php echo number_format($ref->commission_amount, 2); ?>€ (<?php echo $ref->commission_rate; ?>%)</td>
                        <td><?php echo $ref->status; ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($ref->created_at)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.card {
    background: white;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    padding: 20px;
    margin-bottom: 20px;
}
</style>

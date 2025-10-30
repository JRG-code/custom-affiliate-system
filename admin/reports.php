<?php
/**
 * Admin Reports Page
 * Analytics, top performers, and data exports
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get date range from GET parameters
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');

// Get top performers
$top_affiliates = $wpdb->get_results($wpdb->prepare("
    SELECT a.*, u.display_name, u.user_email,
        (SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_referrals WHERE affiliate_id = a.id) as sales_count,
        (SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_referrals WHERE affiliate_id = a.id AND created_at >= %s AND created_at <= %s) as period_sales,
        (SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_referrals WHERE affiliate_id = a.id AND created_at >= %s AND created_at <= %s) as period_commission
    FROM {$wpdb->prefix}affiliates a
    LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
    WHERE a.total_sales > 0
    ORDER BY a.total_commission DESC
    LIMIT 20
", $date_from, $date_to . ' 23:59:59', $date_from, $date_to . ' 23:59:59'));

// Get tier distribution
$tier_stats = $wpdb->get_results("
    SELECT 
        tier,
        COUNT(*) as count,
        SUM(total_commission) as total_commission,
        AVG(commission_rate) as avg_rate
    FROM {$wpdb->prefix}affiliates
    GROUP BY tier
    ORDER BY avg_rate ASC
");

// Get recent activity
$recent_referrals = $wpdb->get_results("
    SELECT r.*, a.affiliate_code, u.display_name
    FROM {$wpdb->prefix}affiliate_referrals r
    LEFT JOIN {$wpdb->prefix}affiliates a ON r.affiliate_id = a.id
    LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
    ORDER BY r.created_at DESC
    LIMIT 10
");

// Monthly stats for chart
$monthly_stats = $wpdb->get_results("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as sales,
        SUM(commission_amount) as commissions,
        SUM(order_total) as revenue
    FROM {$wpdb->prefix}affiliate_referrals
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month ASC
");

// Summary stats
$summary = $wpdb->get_row("
    SELECT 
        COUNT(DISTINCT a.id) as total_affiliates,
        COUNT(r.id) as total_sales,
        SUM(r.order_total) as total_revenue,
        SUM(r.commission_amount) as total_commissions,
        AVG(r.commission_amount) as avg_commission
    FROM {$wpdb->prefix}affiliates a
    LEFT JOIN {$wpdb->prefix}affiliate_referrals r ON a.id = r.affiliate_id
");

?>

<div class="wrap">
    <h1 class="wp-heading-inline">📊 Affiliate Reports & Analytics</h1>
    <a href="<?php echo admin_url('admin.php?page=affiliate-system'); ?>" class="page-title-action">Back to Overview</a>
    <hr class="wp-header-end">
    
    <!-- Date Filter -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <form method="get" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <input type="hidden" name="page" value="affiliate-reports">
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">From Date:</label>
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" style="padding: 8px; border: 2px solid #e5e7eb; border-radius: 6px;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">To Date:</label>
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" style="padding: 8px; border: 2px solid #e5e7eb; border-radius: 6px;">
            </div>
            
            <button type="submit" class="button button-primary">Apply Filter</button>
            <a href="<?php echo admin_url('admin.php?page=affiliate-reports'); ?>" class="button">Reset</a>
        </form>
    </div>
    
    <!-- Summary Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin: 20px 0;">
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h3 style="margin: 0; color: #666; font-size: 13px; text-transform: uppercase;">Total Affiliates</h3>
            <p style="font-size: 36px; font-weight: bold; margin: 10px 0; color: #667eea;"><?php echo number_format($summary->total_affiliates); ?></p>
        </div>
        
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h3 style="margin: 0; color: #666; font-size: 13px; text-transform: uppercase;">Total Sales</h3>
            <p style="font-size: 36px; font-weight: bold; margin: 10px 0; color: #10b981;"><?php echo number_format($summary->total_sales); ?></p>
        </div>
        
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h3 style="margin: 0; color: #666; font-size: 13px; text-transform: uppercase;">Total Revenue</h3>
            <p style="font-size: 36px; font-weight: bold; margin: 10px 0; color: #f59e0b;">$<?php echo number_format($summary->total_revenue, 2); ?></p>
        </div>
        
        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h3 style="margin: 0; color: #666; font-size: 13px; text-transform: uppercase;">Avg Commission</h3>
            <p style="font-size: 36px; font-weight: bold; margin: 10px 0; color: #8b5cf6;">$<?php echo number_format($summary->avg_commission, 2); ?></p>
        </div>
    </div>
    
    <!-- Tier Distribution -->
    <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2 style="margin: 0 0 20px 0;">Distribution by Tier</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <?php foreach ($tier_stats as $tier): 
                $tier_names = array(
                    'tier_1' => array('name' => 'Tier I', 'color' => '#667eea'),
                    'tier_2' => array('name' => 'Tier II', 'color' => '#f59e0b'),
                    'ambassador' => array('name' => 'Ambassador', 'color' => '#ec4899')
                );
                $tier_info = $tier_names[$tier->tier];
            ?>
            <div style="border: 2px solid <?php echo $tier_info['color']; ?>; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 10px 0; color: <?php echo $tier_info['color']; ?>;"><?php echo $tier_info['name']; ?></h3>
                <p style="margin: 5px 0; font-size: 14px;"><strong>Affiliates:</strong> <?php echo $tier->count; ?></p>
                <p style="margin: 5px 0; font-size: 14px;"><strong>Total Earned:</strong> $<?php echo number_format($tier->total_commission, 2); ?></p>
                <p style="margin: 5px 0; font-size: 14px;"><strong>Commission Rate:</strong> <?php echo number_format($tier->avg_rate, 0); ?>%</p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Top Performers -->
    <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2 style="margin: 0 0 20px 0;">🏆 Top Performing Affiliates</h2>
        
        <?php if (empty($top_affiliates)): ?>
        <div style="text-align: center; padding: 40px;">
            <p style="font-size: 48px; margin: 0;">📈</p>
            <p style="color: #666; margin: 10px 0 0 0;">No sales data yet</p>
        </div>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 5%;">Rank</th>
                    <th style="width: 20%;">Affiliate</th>
                    <th style="width: 12%;">Code</th>
                    <th style="width: 8%;">Tier</th>
                    <th style="width: 10%;">All-Time Sales</th>
                    <th style="width: 12%;">All-Time Revenue</th>
                    <th style="width: 13%;">All-Time Commission</th>
                    <th style="width: 10%;">Period Sales</th>
                    <th style="width: 10%;">Period Commission</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                foreach ($top_affiliates as $aff): 
                    $medal = '';
                    if ($rank == 1) $medal = '🥇';
                    elseif ($rank == 2) $medal = '🥈';
                    elseif ($rank == 3) $medal = '🥉';
                    
                    $tier_labels = array(
                        'tier_1' => 'Tier I',
                        'tier_2' => 'Tier II',
                        'ambassador' => 'Ambassador'
                    );
                ?>
                <tr>
                    <td style="text-align: center; font-size: 20px; font-weight: bold;">
                        <?php echo $medal ?: $rank; ?>
                    </td>
                    <td>
                        <strong><?php echo esc_html($aff->display_name); ?></strong><br>
                        <small style="color: #666;"><?php echo esc_html($aff->user_email); ?></small>
                    </td>
                    <td>
                        <code style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px; font-weight: bold;">
                            <?php echo $aff->affiliate_code; ?>
                        </code>
                    </td>
                    <td><?php echo $tier_labels[$aff->tier]; ?></td>
                    <td><strong><?php echo number_format($aff->sales_count); ?></strong></td>
                    <td><strong>$<?php echo number_format($aff->total_sales, 2); ?></strong></td>
                    <td style="color: #10b981; font-weight: bold; font-size: 16px;">
                        $<?php echo number_format($aff->total_commission, 2); ?>
                    </td>
                    <td><?php echo number_format($aff->period_sales ?: 0); ?></td>
                    <td style="color: #f59e0b; font-weight: bold;">
                        $<?php echo number_format($aff->period_commission ?: 0, 2); ?>
                    </td>
                </tr>
                <?php 
                $rank++;
                endforeach; 
                ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Recent Activity -->
    <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2 style="margin: 0 0 20px 0;">🕐 Recent Sales Activity</h2>
        
        <?php if (empty($recent_referrals)): ?>
        <div style="text-align: center; padding: 40px;">
            <p style="font-size: 48px; margin: 0;">📭</p>
            <p style="color: #666; margin: 10px 0 0 0;">No sales yet</p>
        </div>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Affiliate</th>
                    <th>Code Used</th>
                    <th>Order ID</th>
                    <th>Order Total</th>
                    <th>Commission</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_referrals as $ref): ?>
                <tr>
                    <td>
                        <?php echo date('M j, Y', strtotime($ref->created_at)); ?><br>
                        <small style="color: #999;"><?php echo date('g:i A', strtotime($ref->created_at)); ?></small>
                    </td>
                    <td><?php echo esc_html($ref->display_name); ?></td>
                    <td>
                        <code style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px;">
                            <?php echo strtoupper($ref->coupon_code); ?>
                        </code>
                    </td>
                    <td><strong>#<?php echo $ref->order_id; ?></strong></td>
                    <td><strong>$<?php echo number_format($ref->order_total, 2); ?></strong></td>
                    <td style="color: #10b981; font-weight: bold;">
                        $<?php echo number_format($ref->commission_amount, 2); ?>
                    </td>
                    <td>
                        <span style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; background: <?php echo $ref->status == 'paid' ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $ref->status == 'paid' ? '#065f46' : '#92400e'; ?>;">
                            <?php echo ucfirst($ref->status); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Monthly Performance Chart Data -->
    <?php if (!empty($monthly_stats)): ?>
    <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2 style="margin: 0 0 20px 0;">📈 Monthly Performance (Last 12 Months)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Sales</th>
                    <th>Revenue</th>
                    <th>Commissions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly_stats as $stat): ?>
                <tr>
                    <td><strong><?php echo date('F Y', strtotime($stat->month . '-01')); ?></strong></td>
                    <td><?php echo number_format($stat->sales); ?></td>
                    <td>$<?php echo number_format($stat->revenue, 2); ?></td>
                    <td style="color: #10b981; font-weight: bold;">$<?php echo number_format($stat->commissions, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Export Section -->
    <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2 style="margin: 0 0 15px 0;">📥 Export Data</h2>
        <p style="color: #666; margin: 0 0 20px 0;">Download CSV files for accounting and analysis</p>
        
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin-ajax.php?action=export_affiliate_data&type=affiliates'); ?>" class="button button-primary">
                📊 Export All Affiliates
            </a>
            <a href="<?php echo admin_url('admin-ajax.php?action=export_affiliate_data&type=referrals'); ?>" class="button button-primary">
                📈 Export All Sales/Referrals
            </a>
            <a href="<?php echo admin_url('admin-ajax.php?action=export_affiliate_data&type=payouts'); ?>" class="button button-primary">
                💰 Export All Payouts
            </a>
        </div>
    </div>
    
    <!-- Tips -->
    <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; margin-top: 30px; border-left: 4px solid #0284c7;">
        <h3 style="margin: 0 0 10px 0; color: #0c4a6e;">💡 Performance Tips</h3>
        <ul style="margin: 10px 0; padding-left: 20px; color: #0c4a6e;">
            <li><strong>Reward top performers:</strong> Upgrade your best affiliates to higher tiers to motivate them</li>
            <li><strong>Re-engage inactive:</strong> Reach out to affiliates with 0 sales after 30 days</li>
            <li><strong>Seasonal patterns:</strong> Look for monthly trends to plan promotions</li>
            <li><strong>Commission optimization:</strong> Test different rates to find the sweet spot</li>
            <li><strong>Regular exports:</strong> Download data monthly for tax and accounting records</li>
        </ul>
    </div>
</div>

<style>
@media print {
    .wrap > h1, .page-title-action { display: none; }
    table { page-break-inside: auto; }
    tr { page-break-inside: avoid; page-break-after: auto; }
}
</style>
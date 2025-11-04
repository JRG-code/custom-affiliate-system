<?php
/**
 * Mobile Widgets & Quick Stats
 * Beautiful, responsive widgets optimized for mobile viewing
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display mobile-optimized quick stats widget
 */
function cas_render_mobile_quick_stats($affiliate) {
    global $wpdb;

    // Get stats
    $total_referrals = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_referrals WHERE affiliate_id = %d",
        $affiliate->id
    ));

    $this_month_sales = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(order_total), 0)
        FROM {$wpdb->prefix}affiliate_referrals
        WHERE affiliate_id = %d
        AND MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())",
        $affiliate->id
    ));

    $this_month_commission = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(commission_amount), 0)
        FROM {$wpdb->prefix}affiliate_referrals
        WHERE affiliate_id = %d
        AND MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())",
        $affiliate->id
    ));

    $pending_payout = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_payouts
        WHERE affiliate_id = %d AND status = 'pending'",
        $affiliate->id
    ));

    ?>
    <div class="cas-mobile-widgets">
        <!-- Swipeable Quick Stats Cards -->
        <div class="cas-widget-scroll">

            <!-- Widget: Promo Code -->
            <div class="cas-widget cas-widget-gradient-purple">
                <div class="cas-widget-icon">🎟️</div>
                <div class="cas-widget-content">
                    <div class="cas-widget-label">Your Code</div>
                    <div class="cas-widget-value"><?php echo esc_html($affiliate->affiliate_code); ?></div>
                    <button class="cas-widget-action" onclick="copyCode()">
                        <span class="icon">📋</span> Copy
                    </button>
                </div>
            </div>

            <!-- Widget: To Receive -->
            <div class="cas-widget cas-widget-gradient-green">
                <div class="cas-widget-icon">💰</div>
                <div class="cas-widget-content">
                    <div class="cas-widget-label">To Receive</div>
                    <div class="cas-widget-value"><?php echo number_format($affiliate->unpaid_commission, 2); ?>€</div>
                    <div class="cas-widget-sub">Unpaid commission</div>
                </div>
            </div>

            <!-- Widget: This Month -->
            <div class="cas-widget cas-widget-gradient-blue">
                <div class="cas-widget-icon">📊</div>
                <div class="cas-widget-content">
                    <div class="cas-widget-label">This Month</div>
                    <div class="cas-widget-value"><?php echo number_format($this_month_commission, 2); ?>€</div>
                    <div class="cas-widget-sub"><?php echo number_format($this_month_sales, 2); ?>€ in sales</div>
                </div>
            </div>

            <!-- Widget: Total Uses -->
            <div class="cas-widget cas-widget-gradient-orange">
                <div class="cas-widget-icon">👥</div>
                <div class="cas-widget-content">
                    <div class="cas-widget-label">Code Uses</div>
                    <div class="cas-widget-value"><?php echo number_format($total_referrals); ?></div>
                    <div class="cas-widget-sub">All time referrals</div>
                </div>
            </div>

            <!-- Widget: Pending Payouts -->
            <?php if ($pending_payout > 0): ?>
            <div class="cas-widget cas-widget-gradient-yellow">
                <div class="cas-widget-icon">⏳</div>
                <div class="cas-widget-content">
                    <div class="cas-widget-label">Pending</div>
                    <div class="cas-widget-value"><?php echo $pending_payout; ?></div>
                    <div class="cas-widget-sub">Payout request<?php echo $pending_payout > 1 ? 's' : ''; ?></div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Scroll Indicator -->
        <div class="cas-scroll-indicator">
            <span class="dot active"></span>
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="dot"></span>
            <?php if ($pending_payout > 0): ?>
            <span class="dot"></span>
            <?php endif; ?>
        </div>
    </div>

    <style>
    .cas-mobile-widgets {
        margin: 20px -20px;
        padding: 0;
    }

    .cas-widget-scroll {
        display: flex;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        gap: 15px;
        padding: 20px;
        scrollbar-width: none; /* Firefox */
    }

    .cas-widget-scroll::-webkit-scrollbar {
        display: none; /* Chrome, Safari */
    }

    .cas-widget {
        flex: 0 0 280px;
        scroll-snap-align: start;
        border-radius: 20px;
        padding: 25px;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        transition: transform 0.3s ease;
    }

    .cas-widget:active {
        transform: scale(0.98);
    }

    /* Gradient Backgrounds */
    .cas-widget-gradient-purple {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .cas-widget-gradient-green {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .cas-widget-gradient-blue {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    }

    .cas-widget-gradient-orange {
        background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
    }

    .cas-widget-gradient-yellow {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    }

    .cas-widget-icon {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.9;
    }

    .cas-widget-content {
        position: relative;
        z-index: 1;
    }

    .cas-widget-label {
        font-size: 13px;
        opacity: 0.9;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .cas-widget-value {
        font-size: 32px;
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 5px;
    }

    .cas-widget-sub {
        font-size: 14px;
        opacity: 0.8;
    }

    .cas-widget-action {
        margin-top: 15px;
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .cas-widget-action:active {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(0.95);
    }

    .cas-scroll-indicator {
        display: flex;
        justify-content: center;
        gap: 8px;
        padding: 15px 0;
    }

    .cas-scroll-indicator .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #cbd5e1;
        transition: all 0.3s ease;
    }

    .cas-scroll-indicator .dot.active {
        width: 24px;
        border-radius: 4px;
        background: #667eea;
    }

    /* Desktop: Show as grid */
    @media (min-width: 768px) {
        .cas-mobile-widgets {
            margin: 20px 0;
        }

        .cas-widget-scroll {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            overflow-x: visible;
            gap: 20px;
        }

        .cas-widget {
            flex: none;
        }

        .cas-scroll-indicator {
            display: none;
        }
    }
    </style>

    <script>
    // Update scroll indicator on scroll
    const widgetScroll = document.querySelector('.cas-widget-scroll');
    const dots = document.querySelectorAll('.cas-scroll-indicator .dot');

    if (widgetScroll && dots.length > 0) {
        widgetScroll.addEventListener('scroll', function() {
            const scrollLeft = this.scrollLeft;
            const cardWidth = this.querySelector('.cas-widget').offsetWidth + 15; // card width + gap
            const activeIndex = Math.round(scrollLeft / cardWidth);

            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === activeIndex);
            });
        });
    }
    </script>
    <?php
}

/**
 * Render mini performance chart widget
 */
function cas_render_performance_mini_chart($affiliate_id) {
    global $wpdb;

    // Get last 7 days performance
    $last_7_days = $wpdb->get_results($wpdb->prepare("
        SELECT
            DATE(created_at) as date,
            COUNT(*) as sales,
            SUM(commission_amount) as commission
        FROM {$wpdb->prefix}affiliate_referrals
        WHERE affiliate_id = %d
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ", $affiliate_id));

    $dates = array();
    $commissions = array();

    // Fill in missing days with 0
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $dates[] = date('M j', strtotime($date));

        $found = false;
        foreach ($last_7_days as $day) {
            if ($day->date === $date) {
                $commissions[] = floatval($day->commission);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $commissions[] = 0;
        }
    }

    $has_mini_data = array_sum($commissions) > 0;
    ?>
    <div class="cas-mini-chart-widget">
        <h3 style="margin: 0 0 15px 0; font-size: 16px; font-weight: 600;">📈 Last 7 Days</h3>

        <?php if (!$has_mini_data): ?>
        <div class="cas-mini-no-data">
            <div style="text-align: center; padding: 40px 20px; color: #9ca3af;">
                <div style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;">📊</div>
                <p style="margin: 0; font-size: 14px;">No data yet</p>
            </div>
        </div>
        <?php else: ?>
        <div style="height: 200px; position: relative;">
            <canvas id="casMiniChart"></canvas>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php if ($has_mini_data): ?>
    <script>
    const ctx = document.getElementById('casMiniChart');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($dates); ?>,
            datasets: [{
                label: 'Commission (€)',
                data: <?php echo json_encode($commissions); ?>,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#667eea'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function(context) {
                            return '€' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '€' + value;
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    </script>
    <?php endif; ?>
    <?php
}

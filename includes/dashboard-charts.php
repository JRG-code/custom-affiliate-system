<?php
/**
 * Dashboard Charts & Analytics
 * Beautiful Chart.js visualizations for affiliate dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render comprehensive analytics dashboard with multiple charts
 */
function cas_render_analytics_dashboard($affiliate_id) {
    global $wpdb;

    // Get monthly performance (last 12 months)
    $monthly_data = $wpdb->get_results($wpdb->prepare("
        SELECT
            DATE_FORMAT(created_at, '%%Y-%%m') as month,
            COUNT(*) as sales_count,
            SUM(order_total) as total_sales,
            SUM(commission_amount) as total_commission
        FROM {$wpdb->prefix}affiliate_referrals
        WHERE affiliate_id = %d
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
        ORDER BY month ASC
    ", $affiliate_id));

    // Prepare data arrays
    $months = array();
    $sales_counts = array();
    $revenue = array();
    $commissions = array();

    $has_data = !empty($monthly_data);

    foreach ($monthly_data as $data) {
        $months[] = date('M Y', strtotime($data->month . '-01'));
        $sales_counts[] = intval($data->sales_count);
        $revenue[] = floatval($data->total_sales);
        $commissions[] = floatval($data->total_commission);
    }

    // Get conversion data
    $conversion_stats = $wpdb->get_row($wpdb->prepare("
        SELECT
            COUNT(DISTINCT order_id) as total_orders,
            COUNT(DISTINCT CASE WHEN status = 'paid' THEN order_id END) as completed_orders
        FROM {$wpdb->prefix}affiliate_referrals
        WHERE affiliate_id = %d
    ", $affiliate_id));

    $conversion_rate = $conversion_stats->total_orders > 0
        ? round(($conversion_stats->completed_orders / $conversion_stats->total_orders) * 100, 1)
        : 0;

    // Get top performing days of week
    $day_performance = $wpdb->get_results($wpdb->prepare("
        SELECT
            DAYNAME(created_at) as day_name,
            DAYOFWEEK(created_at) as day_num,
            COUNT(*) as sales,
            SUM(commission_amount) as commission
        FROM {$wpdb->prefix}affiliate_referrals
        WHERE affiliate_id = %d
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY day_name, day_num
        ORDER BY day_num
    ", $affiliate_id));

    $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
    $day_sales = array_fill(0, 7, 0);

    foreach ($day_performance as $day) {
        $day_sales[$day->day_num - 1] = intval($day->sales);
    }

    ?>
    <!-- Load Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <div class="cas-analytics-dashboard">
        <h2 style="margin: 0 0 20px 0; font-size: 24px; font-weight: 700;">📊 Performance Analytics</h2>

        <?php if (!$has_data): ?>
        <!-- No Data Message -->
        <div class="cas-no-data-message">
            <div class="cas-no-data-icon">📊</div>
            <h3>No data yet</h3>
            <p>Start generating sales to see your performance analytics here!</p>
        </div>
        <?php else: ?>
        <!-- Charts Grid -->
        <div class="cas-charts-grid">

            <!-- Monthly Performance Chart -->
            <div class="cas-chart-card cas-chart-large">
                <h3 class="cas-chart-title">Monthly Performance (Last 12 Months)</h3>
                <div class="cas-chart-container" style="height: 300px;">
                    <canvas id="casMonthlyChart"></canvas>
                </div>
            </div>

            <!-- Revenue vs Commission -->
            <div class="cas-chart-card">
                <h3 class="cas-chart-title">Revenue vs Commission</h3>
                <div class="cas-chart-container" style="height: 250px;">
                    <canvas id="casRevenueChart"></canvas>
                </div>
            </div>

            <!-- Days of Week Performance -->
            <div class="cas-chart-card">
                <h3 class="cas-chart-title">Best Days of the Week</h3>
                <div class="cas-chart-container" style="height: 250px;">
                    <canvas id="casDaysChart"></canvas>
                </div>
            </div>

            <!-- Conversion Rate Gauge -->
            <div class="cas-chart-card cas-gauge-card">
                <h3 class="cas-chart-title">Conversion Rate</h3>
                <div class="cas-gauge-container">
                    <div class="cas-gauge-value"><?php echo $conversion_rate; ?>%</div>
                    <div class="cas-gauge-label"><?php echo $conversion_stats->completed_orders; ?> of <?php echo $conversion_stats->total_orders; ?> completed</div>
                </div>
                <div class="cas-chart-container" style="height: 200px;">
                    <canvas id="casConversionGauge"></canvas>
                </div>
            </div>

        </div>
        <?php endif; ?>
    </div>

    <style>
    .cas-analytics-dashboard {
        background: white;
        border-radius: 12px;
        padding: 30px;
        margin: 30px 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .cas-no-data-message {
        text-align: center;
        padding: 60px 20px;
        background: #f9fafb;
        border-radius: 12px;
        border: 2px dashed #e5e7eb;
    }

    .cas-no-data-icon {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .cas-no-data-message h3 {
        margin: 0 0 10px 0;
        font-size: 20px;
        color: #6b7280;
    }

    .cas-no-data-message p {
        margin: 0;
        color: #9ca3af;
        font-size: 14px;
    }

    .cas-charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    .cas-chart-card {
        background: #f9fafb;
        border-radius: 12px;
        padding: 20px;
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }

    .cas-chart-card:hover {
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .cas-chart-large {
        grid-column: span 2;
    }

    .cas-chart-title {
        margin: 0 0 15px 0;
        font-size: 16px;
        font-weight: 600;
        color: #374151;
    }

    .cas-chart-container {
        position: relative;
        width: 100%;
    }

    .cas-chart-container canvas {
        display: block;
        width: 100% !important;
    }

    .cas-gauge-card {
        text-align: center;
    }

    .cas-gauge-container {
        margin-bottom: 15px;
    }

    .cas-gauge-value {
        font-size: 48px;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 5px;
    }

    .cas-gauge-label {
        font-size: 14px;
        color: #6b7280;
    }

    @media (max-width: 768px) {
        .cas-analytics-dashboard {
            padding: 20px;
        }

        .cas-chart-large {
            grid-column: span 1;
        }

        .cas-charts-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <?php if ($has_data): ?>
    <script>
    // Chart.js global configuration
    Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    Chart.defaults.plugins.legend.display = true;
    Chart.defaults.plugins.legend.position = 'bottom';

    // Monthly Performance Chart (Line + Bar)
    const ctxMonthly = document.getElementById('casMonthlyChart');
    new Chart(ctxMonthly, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [
                {
                    type: 'line',
                    label: 'Commission (€)',
                    data: <?php echo json_encode($commissions); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1',
                    order: 0
                },
                {
                    type: 'bar',
                    label: 'Sales Count',
                    data: <?php echo json_encode($sales_counts); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.6)',
                    borderColor: '#10b981',
                    borderWidth: 2,
                    yAxisID: 'y',
                    order: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Number of Sales'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Commission (€)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // Revenue vs Commission Chart (Doughnut)
    const ctxRevenue = document.getElementById('casRevenueChart');
    const totalRevenue = <?php echo array_sum($revenue); ?>;
    const totalCommission = <?php echo array_sum($commissions); ?>;
    new Chart(ctxRevenue, {
        type: 'doughnut',
        data: {
            labels: ['Your Commission', 'Store Revenue'],
            datasets: [{
                data: [totalCommission, totalRevenue - totalCommission],
                backgroundColor: [
                    'rgba(102, 126, 234, 0.8)',
                    'rgba(229, 231, 235, 0.8)'
                ],
                borderColor: [
                    '#667eea',
                    '#e5e7eb'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const percentage = totalRevenue > 0 ? ((value / totalRevenue) * 100).toFixed(1) : 0;
                            return label + ': €' + value.toFixed(2) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // Days of Week Chart (Bar)
    const ctxDays = document.getElementById('casDaysChart');
    new Chart(ctxDays, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($days); ?>,
            datasets: [{
                label: 'Sales',
                data: <?php echo json_encode($day_sales); ?>,
                backgroundColor: function(context) {
                    const value = context.parsed.y;
                    const max = Math.max(...<?php echo json_encode($day_sales); ?>);
                    const alpha = value / max;
                    return `rgba(245, 158, 11, ${0.3 + alpha * 0.5})`;
                },
                borderColor: '#f59e0b',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Conversion Rate Gauge (Doughnut)
    const ctxConversion = document.getElementById('casConversionGauge');
    const conversionRate = <?php echo $conversion_rate; ?>;
    new Chart(ctxConversion, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [conversionRate, 100 - conversionRate],
                backgroundColor: [
                    conversionRate >= 75 ? '#10b981' : conversionRate >= 50 ? '#f59e0b' : '#ef4444',
                    '#e5e7eb'
                ],
                borderWidth: 0,
                circumference: 180,
                rotation: 270
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
                    enabled: false
                }
            }
        }
    });
    </script>
    <?php endif; ?>
    <?php
}

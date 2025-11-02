<?php
/**
 * Debug Log Page
 */

if (!defined('ABSPATH')) exit;

if (isset($_POST['clear_logs']) && check_admin_referer('cas_clear_logs')) {
    cas_clear_debug_logs();
    echo '<div class="notice notice-success is-dismissible"><p>✅ Debug logs cleared successfully.</p></div>';
}

$logs = cas_get_debug_logs(200);

?>

<div class="wrap">
    <h1>🐛 Debug Log</h1>
    
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin: 20px 0;">
        <h2 style="margin: 0 0 10px 0; color: white;">System Debug Information</h2>
        <p style="margin: 0; opacity: 0.9;">Track all affiliate system actions for troubleshooting. Last 200 entries shown.</p>
    </div>
    
    <?php if (empty($logs)): ?>
        <div style="background: white; padding: 40px; border-radius: 8px; text-align: center;">
            <p style="font-size: 48px; margin: 0;">📝</p>
            <p style="margin: 10px 0 0 0; color: #666;">No debug logs yet. Debug mode is active and will start recording actions.</p>
        </div>
    <?php else: ?>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin: 20px 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">Recent Activity (<?php echo count($logs); ?> entries)</h3>
                <form method="post" style="margin: 0;">
                    <?php wp_nonce_field('cas_clear_logs'); ?>
                    <button type="submit" name="clear_logs" class="button" onclick="return confirm('Clear all debug logs?');">
                        🗑️ Clear Logs
                    </button>
                </form>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 15%;">Timestamp</th>
                        <th style="width: 10%;">Type</th>
                        <th style="width: 60%;">Message</th>
                        <th style="width: 15%;">User ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        $type_colors = array(
                            'info' => '#0284c7',
                            'success' => '#10b981',
                            'warning' => '#f59e0b',
                            'error' => '#ef4444'
                        );
                        $color = isset($type_colors[$log->type]) ? $type_colors[$log->type] : '#666';
                    ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($log->timestamp)); ?></td>
                        <td>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                <?php echo strtoupper($log->type); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log->message); ?></td>
                        <td><?php echo $log->user_id ? $log->user_id : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; border-radius: 6px; margin: 20px 0;">
        <h3 style="margin: 0 0 10px 0; color: #92400e;">⚠️ Debug Mode Active</h3>
        <p style="margin: 0; color: #92400e;">Debug mode is currently enabled. All affiliate actions are being logged. Remember to disable it in Settings when you're done troubleshooting to improve performance.</p>
    </div>
</div>
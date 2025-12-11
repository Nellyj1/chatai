<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get API monitoring stats
$api_monitor = AIPC_API_Monitor::getInstance();
$stats_7_days = $api_monitor->get_api_stats(7);
$stats_30_days = $api_monitor->get_api_stats(30);
$recent_errors = $api_monitor->get_recent_errors(10);
$hourly_stats = $api_monitor->get_hourly_stats(24);

// Get current settings
$rate_limit = get_option('aipc_rate_limit_per_minute', 10);
$retention_days = get_option('aipc_monitoring_retention_days', 30);
?>

<div class="wrap">
    <h1><?php esc_html_e('API Monitoring', 'ai-product-chatbot'); ?></h1>
    
    <div class="aipc-dashboard-grid">
        <!-- Statistics Overview -->
        <div class="aipc-card">
            <h2><?php esc_html_e('API Statistics (Last 7 Days)', 'ai-product-chatbot'); ?></h2>
            <table class="widefat">
                <tr>
                    <td><strong><?php esc_html_e('Total Requests', 'ai-product-chatbot'); ?></strong></td>
                    <td><?php echo number_format($stats_7_days['total_requests']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Successful Requests', 'ai-product-chatbot'); ?></strong></td>
                    <td><?php echo number_format($stats_7_days['successful_requests']); ?> (<?php echo $stats_7_days['success_rate']; ?>%)</td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Rate Limited', 'ai-product-chatbot'); ?></strong></td>
                    <td><?php echo number_format($stats_7_days['rate_limited']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Errors', 'ai-product-chatbot'); ?></strong></td>
                    <td><?php echo number_format($stats_7_days['errors']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Average Response Time', 'ai-product-chatbot'); ?></strong></td>
                    <td><?php echo $stats_7_days['avg_response_time_ms']; ?> ms</td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Total Cost', 'ai-product-chatbot'); ?></strong></td>
                    <td>$<?php echo number_format($stats_7_days['total_cost'], 4); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Total Tokens', 'ai-product-chatbot'); ?></strong></td>
                    <td><?php echo number_format($stats_7_days['total_tokens']); ?></td>
                </tr>
            </table>
        </div>

        <!-- 30-Day Comparison -->
        <div class="aipc-card">
            <h2><?php esc_html_e('Monthly Statistics (Last 30 Days)', 'ai-product-chatbot'); ?></h2>
            <table class="widefat">
                <tr>
                    <td><strong><?php esc_html_e('Total Requests', 'ai-product-chatbot'); ?></strong></td>
                    <td><?php echo number_format($stats_30_days['total_requests']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Success Rate', 'ai-product-chatbot'); ?></strong></td>
                    <td><?php echo $stats_30_days['success_rate']; ?>%</td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Rate Limited', 'ai-product-chatbot'); ?></strong></td>
                    <td><?php echo number_format($stats_30_days['rate_limited']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Total Cost', 'ai-product-chatbot'); ?></strong></td>
                    <td>$<?php echo number_format($stats_30_days['total_cost'], 4); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Daily Average', 'ai-product-chatbot'); ?></strong></td>
                    <td><?php echo number_format($stats_30_days['total_requests'] / 30, 1); ?> requests/day</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Rate Limiting Settings -->
    <div class="aipc-card">
        <h2><?php esc_html_e('Rate Limiting Configuration', 'ai-product-chatbot'); ?></h2>
        <form method="post" action="options.php">
            <?php settings_fields('aipc_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="aipc_rate_limit_per_minute"><?php esc_html_e('Rate Limit (per minute)', 'ai-product-chatbot'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="aipc_rate_limit_per_minute" 
                               name="aipc_rate_limit_per_minute" 
                               value="<?php echo esc_attr($rate_limit); ?>" 
                               min="1" 
                               max="100" 
                               step="1" />
                        <p class="description"><?php esc_html_e('Maximum number of API requests per minute per IP address', 'ai-product-chatbot'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="aipc_monitoring_retention_days"><?php esc_html_e('Data Retention (days)', 'ai-product-chatbot'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="aipc_monitoring_retention_days" 
                               name="aipc_monitoring_retention_days" 
                               value="<?php echo esc_attr($retention_days); ?>" 
                               min="1" 
                               max="365" 
                               step="1" />
                        <p class="description"><?php esc_html_e('How long to keep monitoring data before automatic cleanup', 'ai-product-chatbot'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>

    <!-- Recent Errors -->
    <?php if (!empty($recent_errors)): ?>
    <div class="aipc-card">
        <h2><?php esc_html_e('Recent Errors', 'ai-product-chatbot'); ?></h2>
        <div class="tablenav top">
            <div class="alignleft">
                <p><?php printf(esc_html__('Showing last %d errors', 'ai-product-chatbot'), count($recent_errors)); ?></p>
            </div>
        </div>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date/Time', 'ai-product-chatbot'); ?></th>
                    <th><?php esc_html_e('Status', 'ai-product-chatbot'); ?></th>
                    <th><?php esc_html_e('Provider', 'ai-product-chatbot'); ?></th>
                    <th><?php esc_html_e('Model', 'ai-product-chatbot'); ?></th>
                    <th><?php esc_html_e('Error', 'ai-product-chatbot'); ?></th>
                    <th><?php esc_html_e('Response Time', 'ai-product-chatbot'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_errors as $error): ?>
                <tr>
                    <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($error->created_at))); ?></td>
                    <td>
                        <span class="aipc-status-badge aipc-status-<?php echo esc_attr($error->status); ?>">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $error->status))); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($error->provider ?: 'N/A'); ?></td>
                    <td><?php echo esc_html($error->model ?: 'N/A'); ?></td>
                    <td>
                        <span title="<?php echo esc_attr($error->error_message); ?>">
                            <?php echo esc_html(wp_trim_words($error->error_message, 8, '...')); ?>
                        </span>
                    </td>
                    <td><?php echo $error->response_time_ms ? esc_html($error->response_time_ms . ' ms') : 'N/A'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Hourly Chart Data -->
    <?php if (!empty($hourly_stats)): ?>
    <div class="aipc-card">
        <h2><?php esc_html_e('API Usage (Last 24 Hours)', 'ai-product-chatbot'); ?></h2>
        <div style="position: relative; height: 300px; margin: 20px 0;">
            <canvas id="apiUsageChart" width="800" height="300"></canvas>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('apiUsageChart').getContext('2d');
        const hourlyData = <?php echo json_encode($hourly_stats); ?>;
        
        const labels = hourlyData.map(item => {
            const date = new Date(item.hour);
            return date.toLocaleTimeString('nl-NL', {hour: '2-digit', minute: '2-digit'});
        });
        
        // Simple canvas-based chart (fallback if Chart.js not available)
        if (typeof Chart === 'undefined') {
            // Draw simple bar chart
            const canvas = document.getElementById('apiUsageChart');
            const ctx = canvas.getContext('2d');
            const maxRequests = Math.max(...hourlyData.map(item => parseInt(item.total_requests)));
            const barWidth = canvas.width / hourlyData.length;
            const maxHeight = canvas.height - 40;
            
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#007cba';
            
            hourlyData.forEach((item, index) => {
                const barHeight = (parseInt(item.total_requests) / maxRequests) * maxHeight;
                const x = index * barWidth;
                const y = canvas.height - barHeight - 20;
                
                ctx.fillRect(x + 5, y, barWidth - 10, barHeight);
                
                // Draw labels
                ctx.fillStyle = '#333';
                ctx.font = '10px Arial';
                ctx.textAlign = 'center';
                ctx.fillText(labels[index], x + barWidth/2, canvas.height - 5);
                ctx.fillText(item.total_requests, x + barWidth/2, y - 5);
                ctx.fillStyle = '#007cba';
            });
        }
    });
    </script>
    <?php endif; ?>

    <!-- Actions -->
    <div class="aipc-card">
        <h2><?php esc_html_e('Actions', 'ai-product-chatbot'); ?></h2>
        <p>
            <button type="button" class="button" onclick="cleanupOldData()">
                <?php esc_html_e('Clean Up Old Monitoring Data', 'ai-product-chatbot'); ?>
            </button>
            <span class="description"><?php esc_html_e('Remove monitoring data older than retention period', 'ai-product-chatbot'); ?></span>
        </p>
        
        <script>
        function cleanupOldData() {
            if (!confirm('<?php esc_js_e('Are you sure you want to clean up old monitoring data? This cannot be undone.', 'ai-product-chatbot'); ?>')) {
                return;
            }
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'aipc_cleanup_monitoring',
                    nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('<?php esc_js_e('Cleanup completed successfully.', 'ai-product-chatbot'); ?> ' + data.data.message);
                    location.reload();
                } else {
                    alert('<?php esc_js_e('Error:', 'ai-product-chatbot'); ?> ' + data.data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('<?php esc_js_e('An error occurred during cleanup.', 'ai-product-chatbot'); ?>');
            });
        }
        </script>
    </div>
</div>

<style>
.aipc-dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.aipc-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
    margin-bottom: 20px;
}

.aipc-card h2 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.aipc-status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    color: white;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.aipc-status-success { background-color: #46b450; }
.aipc-status-error { background-color: #dc3232; }
.aipc-status-rate-limit { background-color: #ffb900; }
.aipc-status-timeout { background-color: #826eb4; }
.aipc-status-quota-exceeded { background-color: #d63638; }

@media (max-width: 768px) {
    .aipc-dashboard-grid {
        grid-template-columns: 1fr;
    }
}
</style>
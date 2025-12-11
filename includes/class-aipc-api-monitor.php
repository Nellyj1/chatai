<?php

class AIPC_API_Monitor {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Private constructor to prevent multiple instances
    }
    
    /**
     * Check if user/IP is rate limited
     */
    public function is_rate_limited($user_ip = null) {
        if (!$user_ip) {
            $user_ip = $this->get_user_ip();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_api_monitoring';
        
        // Check requests in last minute
        $minute_ago = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $requests_last_minute = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE user_ip = %s 
             AND created_at >= %s 
             AND request_type = 'chat'",
            $user_ip,
            $minute_ago
        ));
        
        // Get rate limit from settings (default: 10 requests per minute)
        $rate_limit = get_option('aipc_rate_limit_per_minute', 10);
        
        return intval($requests_last_minute) >= $rate_limit;
    }
    
    /**
     * Get user IP address safely (with optional anonymization for GDPR)
     */
    private function get_user_ip() {
        $ip_fields = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                      'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        $ip = '0.0.0.0'; // Default fallback
        
        foreach ($ip_fields as $field) {
            if (!empty($_SERVER[$field])) {
                $candidate_ip = sanitize_text_field($_SERVER[$field]);
                // Handle comma-separated IPs (take first one)
                if (strpos($candidate_ip, ',') !== false) {
                    $candidate_ip = explode(',', $candidate_ip)[0];
                }
                $candidate_ip = trim($candidate_ip);
                if (filter_var($candidate_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $ip = $candidate_ip;
                    break;
                } elseif (filter_var($candidate_ip, FILTER_VALIDATE_IP)) {
                    // Accept private IPs as fallback
                    $ip = $candidate_ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR if no valid IP found
        if ($ip === '0.0.0.0' && isset($_SERVER['REMOTE_ADDR'])) {
            $remote_ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
            if (filter_var($remote_ip, FILTER_VALIDATE_IP)) {
                $ip = $remote_ip;
            }
        }
        
        // Apply GDPR anonymization if enabled
        if (get_option('aipc_anonymize_monitoring_ips', false)) {
            $ip = $this->anonymize_ip($ip);
        }
        
        return $ip;
    }
    
    /**
     * Anonymize IP address for GDPR compliance
     * IPv4: 192.168.1.123 -> 192.168.1.0
     * IPv6: 2001:0db8:85a3:0000:0000:8a2e:0370:7334 -> 2001:0db8:85a3:0000::
     */
    private function anonymize_ip($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '0.0.0.0';
        }
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: Mask last octet
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = '0';
                return implode('.', $parts);
            }
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: Keep first 4 groups, zero the rest
            $expanded = inet_pton($ip);
            if ($expanded !== false) {
                // Zero out the last 8 bytes (64 bits)
                for ($i = 8; $i < 16; $i++) {
                    $expanded[$i] = "\0";
                }
                return inet_ntop($expanded);
            }
        }
        
        return $ip; // Return original if anonymization fails
    }
    
    /**
     * Log API request
     */
    public function log_api_request($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_api_monitoring';
        
        $default_data = [
            'conversation_id' => null,
            'user_ip' => $this->get_user_ip(),
            'provider' => null,
            'model' => null,
            'request_type' => 'chat',
            'status' => 'success',
            'http_status' => null,
            'error_code' => null,
            'error_message' => null,
            'response_time_ms' => null,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cost_estimate' => 0.0,
            'retry_attempt' => 0,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : null
        ];
        
        $log_data = array_merge($default_data, $data);
        
        return $wpdb->insert($table, $log_data);
    }
    
    /**
     * Get API statistics
     */
    public function get_api_stats($days = 7) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_api_monitoring';
        
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Total requests
        $total_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
            $since
        ));
        
        // Success rate
        $successful_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND status = 'success'",
            $since
        ));
        
        // Rate limit hits
        $rate_limited = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND status = 'rate_limit'",
            $since
        ));
        
        // Errors
        $errors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND status IN ('error', 'timeout', 'quota_exceeded')",
            $since
        ));
        
        // Average response time
        $avg_response_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(response_time_ms) FROM {$table} WHERE created_at >= %s AND response_time_ms IS NOT NULL",
            $since
        ));
        
        // Total cost
        $total_cost = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(cost_estimate) FROM {$table} WHERE created_at >= %s",
            $since
        ));
        
        // Total tokens
        $total_tokens = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_tokens) FROM {$table} WHERE created_at >= %s",
            $since
        ));
        
        return [
            'total_requests' => intval($total_requests),
            'successful_requests' => intval($successful_requests),
            'success_rate' => $total_requests > 0 ? round(($successful_requests / $total_requests) * 100, 2) : 0,
            'rate_limited' => intval($rate_limited),
            'errors' => intval($errors),
            'avg_response_time_ms' => round(floatval($avg_response_time), 2),
            'total_cost' => round(floatval($total_cost), 6),
            'total_tokens' => intval($total_tokens),
            'period_days' => $days
        ];
    }
    
    /**
     * Get recent errors for debugging
     */
    public function get_recent_errors($limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_api_monitoring';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE status IN ('error', 'timeout', 'quota_exceeded', 'rate_limit') 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Clean up old monitoring data
     */
    public function cleanup_old_data($days_to_keep = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_api_monitoring';
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Get hourly stats for charts
     */
    public function get_hourly_stats($hours = 24) {
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_api_monitoring';
        
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(created_at, '%%Y-%%m-%%d %%H:00:00') as hour,
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_requests,
                SUM(CASE WHEN status = 'rate_limit' THEN 1 ELSE 0 END) as rate_limited,
                SUM(CASE WHEN status IN ('error', 'timeout', 'quota_exceeded') THEN 1 ELSE 0 END) as errors,
                AVG(response_time_ms) as avg_response_time,
                SUM(cost_estimate) as total_cost,
                SUM(total_tokens) as total_tokens
             FROM {$table} 
             WHERE created_at >= %s 
             GROUP BY DATE_FORMAT(created_at, '%%Y-%%m-%%d %%H:00:00')
             ORDER BY hour ASC",
            $since
        ));
    }
}
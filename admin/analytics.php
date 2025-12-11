<?php
if (!defined('ABSPATH')) { exit; }

global $wpdb;
$table_an = $wpdb->prefix . 'aipc_analytics';
$table_conv = $wpdb->prefix . 'aipc_conversations';

// License gating: Advanced analytics only for Enterprise
$show_advanced_analytics = true;
$upgrade_url = '';
if (class_exists('AIPC_License')) {
    $lic = AIPC_License::getInstance();
    $show_advanced_analytics = ($lic->is_active() && $lic->has_feature('advanced_analytics'));
    $upgrade_url = $lic->generate_upgrade_url('enterprise');
}

$total_conversations = (int) $wpdb->get_var("SELECT COUNT(DISTINCT conversation_id) FROM $table_conv");
$total_messages = (int) $wpdb->get_var("SELECT SUM(message_count) FROM $table_an");
$last7 = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT conversation_id) FROM $table_conv WHERE created_at >= %s", gmdate('Y-m-d H:i:s', time()-7*DAY_IN_SECONDS)));

$top_rules = [];
$skin_latest = get_transient('aipc_skin_latest_rules');
if (is_array($skin_latest)) { $top_rules = $skin_latest; }
?>
<div class="wrap">
    <h1><?php _e('Analytics', 'ai-product-chatbot'); ?></h1>
    
    <?php if (!$show_advanced_analytics): ?>
        <div class="notice notice-info">
            <p>
                <?php _e('🔒 Geavanceerde analytics beschikbaar in Enterprise tier. Basis overzicht hieronder.', 'ai-product-chatbot'); ?>
                <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-secondary" style="margin-left:10px;">
                    <?php _e('Upgrade naar Enterprise', 'ai-product-chatbot'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <div class="aipc-analytics-grid">
        <div class="aipc-card">
            <h3><?php _e('Totaal gesprekken', 'ai-product-chatbot'); ?></h3>
            <div class="aipc-metric"><?php echo number_format_i18n($total_conversations); ?></div>
        </div>
        <div class="aipc-card">
            <h3><?php _e('Berichten (geteld)', 'ai-product-chatbot'); ?></h3>
            <div class="aipc-metric"><?php echo number_format_i18n($total_messages); ?></div>
        </div>
        <div class="aipc-card">
            <h3><?php _e('Nieuwe gesprekken (7 dagen)', 'ai-product-chatbot'); ?></h3>
            <div class="aipc-metric"><?php echo number_format_i18n($last7); ?></div>
        </div>
        <?php if ($show_advanced_analytics): ?>
            <?php 
            $table_tokens = $wpdb->prefix . 'aipc_token_usage';
            $usage_30 = $wpdb->get_row($wpdb->prepare("SELECT SUM(prompt_tokens) p, SUM(completion_tokens) c, SUM(total_tokens) t, SUM(cost_estimate) cost FROM $table_tokens WHERE created_at >= %s", gmdate('Y-m-d H:i:s', time()-30*DAY_IN_SECONDS)), ARRAY_A);
            $p = intval($usage_30['p'] ?? 0);
            $c = intval($usage_30['c'] ?? 0);
            $t = intval($usage_30['t'] ?? 0);
            $cost = floatval($usage_30['cost'] ?? 0);
            ?>
            <div class="aipc-card">
                <h3><?php _e('Tokens (30 dagen)', 'ai-product-chatbot'); ?></h3>
                <div class="aipc-metric" title="<?php echo esc_attr(sprintf('Prompt %d, Completion %d', $p, $c)); ?>"><?php echo number_format_i18n($t); ?></div>
            </div>
            <div class="aipc-card">
                <h3><?php _e('Kosten (schatting, 30 dagen)', 'ai-product-chatbot'); ?></h3>
                <div class="aipc-metric">€<?php echo number_format_i18n($cost, 2); ?></div>
            </div>
        <?php else: ?>
            <div class="aipc-card aipc-card-locked">
                <h3><?php _e('Tokens (30 dagen)', 'ai-product-chatbot'); ?> <span class="dashicons dashicons-lock" style="font-size:16px;color:#d63638;"></span></h3>
                <div class="aipc-metric" style="color:#999;">---</div>
                <p class="description" style="margin:0;color:#666;font-size:12px;"><?php _e('Enterprise feature', 'ai-product-chatbot'); ?></p>
            </div>
            <div class="aipc-card aipc-card-locked">
                <h3><?php _e('Kosten (schatting, 30 dagen)', 'ai-product-chatbot'); ?> <span class="dashicons dashicons-lock" style="font-size:16px;color:#d63638;"></span></h3>
                <div class="aipc-metric" style="color:#999;">---</div>
                <p class="description" style="margin:0;color:#666;font-size:12px;"><?php _e('Enterprise feature', 'ai-product-chatbot'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="aipc-section">
        <h2><?php _e('Huidtest – recent gematchte regels', 'ai-product-chatbot'); ?></h2>
        <?php if (!empty($top_rules)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th><?php _e('Label', 'ai-product-chatbot'); ?></th><th><?php _e('Samenvatting', 'ai-product-chatbot'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($top_rules as $r): ?>
                    <tr>
                        <td><?php echo esc_html($r['label'] ?? '-'); ?></td>
                        <td><?php echo esc_html($r['summary'] ?? '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('Nog geen huidtest data.', 'ai-product-chatbot'); ?></p>
        <?php endif; ?>
    </div>

    <?php if ($show_advanced_analytics): ?>
        <div class="aipc-section">
            <h2><?php _e('Recente token usage', 'ai-product-chatbot'); ?></h2>
            <?php 
            $table_tokens = $wpdb->prefix . 'aipc_token_usage';
            $recent = $wpdb->get_results("SELECT created_at, provider, model, prompt_tokens, completion_tokens, total_tokens, cost_estimate FROM $table_tokens ORDER BY created_at DESC LIMIT 50", ARRAY_A);
            if (!empty($recent)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Datum/Tijd', 'ai-product-chatbot'); ?></th>
                            <th><?php _e('Provider', 'ai-product-chatbot'); ?></th>
                            <th><?php _e('Model', 'ai-product-chatbot'); ?></th>
                            <th><?php _e('Prompt', 'ai-product-chatbot'); ?></th>
                            <th><?php _e('Completion', 'ai-product-chatbot'); ?></th>
                            <th><?php _e('Totaal', 'ai-product-chatbot'); ?></th>
                            <th><?php _e('Kosten (€)', 'ai-product-chatbot'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $row): ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n('Y-m-d H:i', strtotime($row['created_at'])) ); ?></td>
                                <td><?php echo esc_html( $row['provider'] ); ?></td>
                                <td><?php echo esc_html( $row['model'] ); ?></td>
                                <td><?php echo number_format_i18n( intval($row['prompt_tokens']) ); ?></td>
                                <td><?php echo number_format_i18n( intval($row['completion_tokens']) ); ?></td>
                                <td><?php echo number_format_i18n( intval($row['total_tokens']) ); ?></td>
                                <td><?php echo '€' . number_format_i18n( floatval($row['cost_estimate']), 4 ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('Nog geen usage data.', 'ai-product-chatbot'); ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="aipc-section aipc-section-locked">
            <h2>
                <?php _e('Recente token usage', 'ai-product-chatbot'); ?>
                <span class="dashicons dashicons-lock" style="font-size:18px;color:#d63638;margin-left:8px;"></span>
            </h2>
            <div class="aipc-locked-content">
                <p><?php _e('🔒 Gedetailleerde token usage en kosten tracking is beschikbaar in Enterprise tier.', 'ai-product-chatbot'); ?></p>
                <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-primary">
                    <?php _e('Upgrade naar Enterprise', 'ai-product-chatbot'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.aipc-analytics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:16px 0}
.aipc-card{background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:16px}
.aipc-card-locked{background:#f9f9f9;border:1px solid #ddd;opacity:0.7}
.aipc-metric{font-size:28px;font-weight:700;color:#0073aa}
.aipc-section{margin-top:24px;background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:16px}
.aipc-section-locked{background:#f9f9f9;border:1px solid #ddd;opacity:0.8}
.aipc-locked-content{text-align:center;padding:20px;color:#666}
</style>

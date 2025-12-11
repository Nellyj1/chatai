<?php
/**
 * Compact Pricing Table Component
 * Voor gebruik op admin pagina's met upgrade prompts
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current license info
$current_license = null;
$current_tier = 'none';

if (class_exists('AIPC_License')) {
    $license = AIPC_License::getInstance();
    $current_license = $license;
    $current_tier = $license->is_active() ? $license->get_current_tier() : 'none';
}

// Default style - can be overridden
$style = isset($style) ? $style : 'cards'; // 'cards' or 'table'
$show_current_badge = isset($show_current_badge) ? $show_current_badge : true;
?>

<?php if ($style === 'cards'): ?>
<!-- Card Style Pricing Table -->
<div class="aipc-pricing-compact-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin: 20px 0;">
    
    <!-- Basic Tier -->
    <div class="aipc-tier-compact <?php echo $current_tier === 'basic' ? 'aipc-current' : ''; ?>" style="background: white; border: 2px solid <?php echo $current_tier === 'basic' ? '#2271b1' : '#e0e0e0'; ?>; border-radius: 8px; padding: 20px; position: relative;">
        <?php if ($show_current_badge && $current_tier === 'basic'): ?>
            <div style="position: absolute; top: -8px; right: 16px; background: #2271b1; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold;">
                <?php _e('HUIDIG', 'ai-product-chatbot'); ?>
            </div>
        <?php endif; ?>
        <h4 style="margin: 0 0 8px 0; color: #2271b1;">💼 <?php _e('Basic', 'ai-product-chatbot'); ?></h4>
        <div style="margin-bottom: 12px;">
            <span style="font-size: 24px; font-weight: bold; color: #2271b1;">€99</span>
            <span style="color: #666; font-size: 14px;"><?php _e('/jaar', 'ai-product-chatbot'); ?></span>
        </div>
        <ul style="list-style: none; padding: 0; margin: 0; font-size: 13px;">
            <li style="padding: 2px 0; color: #2d7c47;">✅ <?php _e('Volledige AI Chatbot', 'ai-product-chatbot'); ?></li>
            <li style="padding: 2px 0; color: #2d7c47;">✅ <?php _e('WooCommerce Basis', 'ai-product-chatbot'); ?></li>
            <li style="padding: 2px 0; color: #2d7c47;">✅ <?php _e('FAQ (20 items)', 'ai-product-chatbot'); ?></li>
            <li style="padding: 2px 0; color: #666;">❌ <?php _e('Geen kenmerken beheer', 'ai-product-chatbot'); ?></li>
            <li style="padding: 2px 0; color: #666;">❌ <?php _e('Geen Product Quiz', 'ai-product-chatbot'); ?></li>
        </ul>
        <?php if ($current_tier !== 'basic' && $current_tier !== 'business' && $current_tier !== 'enterprise'): ?>
            <a href="<?php echo esc_url($current_license ? $current_license->generate_upgrade_url('basic') : '#'); ?>" class="button button-primary" style="width: 100%; margin-top: 12px; text-align: center;">
                <?php _e('Kies Basic', 'ai-product-chatbot'); ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- Business Tier -->
    <div class="aipc-tier-compact aipc-popular <?php echo $current_tier === 'business' ? 'aipc-current' : ''; ?>" style="background: white; border: 2px solid <?php echo $current_tier === 'business' ? '#2271b1' : '#00a32a'; ?>; border-radius: 8px; padding: 20px; position: relative; transform: scale(1.02);">
        <?php if (!$show_current_badge || $current_tier !== 'business'): ?>
            <div style="position: absolute; top: -8px; left: 50%; transform: translateX(-50%); background: #00a32a; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold;">
                <?php _e('POPULAIR', 'ai-product-chatbot'); ?>
            </div>
        <?php else: ?>
            <div style="position: absolute; top: -8px; right: 16px; background: #2271b1; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold;">
                <?php _e('HUIDIG', 'ai-product-chatbot'); ?>
            </div>
        <?php endif; ?>
        <h4 style="margin: 0 0 8px 0; color: #00a32a;">🚀 <?php _e('Business', 'ai-product-chatbot'); ?></h4>
        <div style="margin-bottom: 12px;">
            <span style="font-size: 24px; font-weight: bold; color: #00a32a;">€299</span>
            <span style="color: #666; font-size: 14px;"><?php _e('/jaar', 'ai-product-chatbot'); ?></span>
        </div>
        <ul style="list-style: none; padding: 0; margin: 0; font-size: 13px;">
            <li style="padding: 2px 0; color: #2d7c47;">✅ <?php _e('Alle Basic functies', 'ai-product-chatbot'); ?></li>
            <li style="padding: 2px 0; color: #2d7c47;">✅ <?php _e('Product Quiz/Skin Test', 'ai-product-chatbot'); ?></li>
            <li style="padding: 2px 0; color: #2d7c47;">✅ <?php _e('Documenten Upload', 'ai-product-chatbot'); ?></li>
            <li style="padding: 2px 0; color: #2d7c47;">✅ <?php _e('Custom Post Types', 'ai-product-chatbot'); ?></li>
            <li style="padding: 2px 0; color: #2d7c47;">✅ <?php _e('Volledig kenmerken beheer', 'ai-product-chatbot'); ?></li>
        </ul>
        <?php if (in_array($current_tier, ['none', 'basic'])): ?>
            <a href="<?php echo esc_url($current_license ? $current_license->generate_upgrade_url('business') : '#'); ?>" class="button button-primary" style="width: 100%; margin-top: 12px; text-align: center; background: #00a32a; border-color: #00a32a;">
                <?php _e($current_tier === 'none' ? 'Kies Business' : 'Upgrade', 'ai-product-chatbot'); ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- Enterprise Tier -->
    <div class="aipc-tier-compact <?php echo $current_tier === 'enterprise' ? 'aipc-current' : ''; ?>" style="background: white; border: 2px solid <?php echo $current_tier === 'enterprise' ? '#2271b1' : '#e0e0e0'; ?>; border-radius: 8px; padding: 20px; position: relative;">
        <?php if ($show_current_badge && $current_tier === 'enterprise'): ?>
            <div style="position: absolute; top: -8px; right: 16px; background: #2271b1; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold;">
                <?php _e('HUIDIG', 'ai-product-chatbot'); ?>
            </div>
        <?php endif; ?>
        <h4 style="margin: 0 0 8px 0; color: #8b5cf6;">🏆 <?php _e('Enterprise', 'ai-product-chatbot'); ?></h4>
        <div style="margin-bottom: 12px;">
            <span style="font-size: 24px; font-weight: bold; color: #8b5cf6;">€599</span>
            <span style="color: #666; font-size: 14px;"><?php _e('/jaar', 'ai-product-chatbot'); ?></span>
        </div>
        <ul style="list-style: none; padding: 0; margin: 0; font-size: 13px;">
            <li style="padding: 2px 0; color: #2d7c47;">✅ <?php _e('Alle Business functies', 'ai-product-chatbot'); ?></li>
            <li style="padding: 2px 0; color: #2d7c47;">✅ <?php _e('Onbeperkte FAQ', 'ai-product-chatbot'); ?></li>
            <li style="padding: 2px 0; color: #2d7c47;">✅ <?php _e('Onbeperkte Documenten', 'ai-product-chatbot'); ?></li>
            <li style="padding: 2px 0; color: #2d7c47;">✅ <?php _e('Advanced Analytics', 'ai-product-chatbot'); ?></li>
        </ul>
        <?php if ($current_tier !== 'enterprise'): ?>
            <a href="<?php echo esc_url($current_license ? $current_license->generate_upgrade_url('enterprise') : '#'); ?>" class="button button-secondary" style="width: 100%; margin-top: 12px; text-align: center;">
                <?php _e($current_tier === 'none' ? 'Kies Enterprise' : 'Upgrade', 'ai-product-chatbot'); ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- Table Style Pricing -->
<div class="aipc-pricing-compact-table" style="overflow-x: auto; margin: 20px 0;">
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 30%;"><?php _e('Functie', 'ai-product-chatbot'); ?></th>
                <th style="width: 23%; text-align: center; background: #e3f2fd;"><?php _e('Basic €99/jr', 'ai-product-chatbot'); ?></th>
                <th style="width: 23%; text-align: center; background: #e8f5e8;"><?php _e('Business €299/jr', 'ai-product-chatbot'); ?></th>
                <th style="width: 24%; text-align: center; background: #f3e5f5;"><?php _e('Enterprise €599/jr', 'ai-product-chatbot'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong><?php _e('AI Chatbot', 'ai-product-chatbot'); ?></strong></td>
                <td style="text-align: center;">✅</td>
                <td style="text-align: center;">✅</td>
                <td style="text-align: center;">✅</td>
            </tr>
            <tr>
                <td><strong><?php _e('WooCommerce Integratie', 'ai-product-chatbot'); ?></strong></td>
                <td style="text-align: center;">✅ (basis)</td>
                <td style="text-align: center;">✅ (volledig)</td>
                <td style="text-align: center;">✅ (volledig)</td>
            </tr>
            <tr>
                <td><strong><?php _e('FAQ Systeem', 'ai-product-chatbot'); ?></strong></td>
                <td style="text-align: center;">20 items</td>
                <td style="text-align: center;">100 items</td>
                <td style="text-align: center;">♾️ Onbeperkt</td>
            </tr>
            <tr>
                <td><strong><?php _e('Kenmerken Beheer', 'ai-product-chatbot'); ?></strong></td>
                <td style="text-align: center;">❌</td>
                <td style="text-align: center;">✅</td>
                <td style="text-align: center;">✅</td>
            </tr>
            <tr>
                <td><strong><?php _e('Product Quiz', 'ai-product-chatbot'); ?></strong></td>
                <td style="text-align: center;">❌</td>
                <td style="text-align: center;">✅</td>
                <td style="text-align: center;">✅</td>
            </tr>
            <tr>
                <td><strong><?php _e('Documenten Upload', 'ai-product-chatbot'); ?></strong></td>
                <td style="text-align: center;">❌</td>
                <td style="text-align: center;">✅ (100MB)</td>
                <td style="text-align: center;">♾️ Onbeperkt</td>
            </tr>
            <tr>
                <td><strong><?php _e('Advanced Analytics', 'ai-product-chatbot'); ?></strong></td>
                <td style="text-align: center;">❌</td>
                <td style="text-align: center;">❌</td>
                <td style="text-align: center;">✅</td>
            </tr>
        </tbody>
    </table>
    
    <div style="text-align: center; margin-top: 20px;">
        <?php if ($current_tier === 'none'): ?>
            <a href="<?php echo esc_url($current_license ? $current_license->generate_upgrade_url('basic') : '#'); ?>" class="button button-primary">
                <?php _e('Start met Basic €99/jaar', 'ai-product-chatbot'); ?>
            </a>
            <a href="<?php echo esc_url($current_license ? $current_license->generate_upgrade_url('business') : '#'); ?>" class="button button-primary" style="background: #00a32a; border-color: #00a32a; margin-left: 10px;">
                <?php _e('Kies Business €299/jaar', 'ai-product-chatbot'); ?>
            </a>
        <?php elseif ($current_tier === 'basic'): ?>
            <a href="<?php echo esc_url($current_license ? $current_license->generate_upgrade_url('business') : '#'); ?>" class="button button-primary" style="background: #00a32a; border-color: #00a32a;">
                <?php _e('Upgrade naar Business €299/jaar', 'ai-product-chatbot'); ?>
            </a>
        <?php endif; ?>
        
        <a href="<?php echo admin_url('admin.php?page=aipc-license-compare'); ?>" class="button button-secondary" style="margin-left: 10px;">
            <?php _e('Bekijk Volledige Vergelijking', 'ai-product-chatbot'); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<style>
.aipc-tier-compact {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.aipc-tier-compact:hover:not(.aipc-current) {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.aipc-tier-compact.aipc-current {
    box-shadow: 0 4px 20px rgba(34, 113, 177, 0.2);
}

.aipc-tier-compact.aipc-popular:not(.aipc-current) {
    box-shadow: 0 4px 20px rgba(0, 163, 42, 0.2);
}

@media (max-width: 768px) {
    .aipc-pricing-compact-cards {
        grid-template-columns: 1fr;
    }
    
    .aipc-tier-compact.aipc-popular {
        transform: none;
    }
}
</style>
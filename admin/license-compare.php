<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get current license info
$current_license = null;
$current_tier = 'none';
$upgrade_url = '#';

if (class_exists('AIPC_License')) {
    $license = AIPC_License::getInstance();
    $current_license = $license;
    $current_tier = $license->is_active() ? $license->get_current_tier() : 'none';
    $upgrade_url = $license->generate_upgrade_url('business');
}
?>

<div class="wrap">
    <h1><?php _e('Licentie Vergelijking', 'ai-product-chatbot'); ?></h1>
    
    <!-- Current License Status -->
    <div class="aipc-current-license" style="background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 8px;">
        <h2 style="margin-top: 0;"><?php _e('Huidige Licentie Status', 'ai-product-chatbot'); ?></h2>
        <?php if ($current_tier === 'none'): ?>
            <p><strong style="color: #d63638;"><?php _e('⚠️ Geen Actieve Licentie', 'ai-product-chatbot'); ?></strong></p>
            <p><?php _e('Je hebt momenteel geen actieve licentie. De chatbot is uitgeschakeld. Voor alle functionaliteiten heb je minimaal een Basic licentie nodig.', 'ai-product-chatbot'); ?></p>
        <?php else: ?>
            <p><strong style="color: #2271b1;">
                <?php
                switch($current_tier) {
                    case 'basic': echo '💼 Basic Licentie Actief'; break;
                    case 'business': echo '🚀 Business Licentie Actief'; break;
                    case 'enterprise': echo '🏆 Enterprise Licentie Actief'; break;
                }
                ?>
            </strong></p>
            <p><?php _e('Je licentie is actief en alle functies van je tier zijn beschikbaar.', 'ai-product-chatbot'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Pricing Table -->
    <div class="aipc-pricing-table" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 30px 0;">
        
        <!-- No License -->
        <div class="aipc-tier-card <?php echo $current_tier === 'none' ? 'aipc-current-tier' : ''; ?>" style="background: white; border: 2px solid <?php echo $current_tier === 'none' ? '#d63638' : '#ddd'; ?>; border-radius: 12px; padding: 24px; text-align: center;">
            <div class="aipc-tier-header" style="border-bottom: 1px solid #eee; padding-bottom: 16px; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #d63638;">🚫 <?php _e('Geen Licentie', 'ai-product-chatbot'); ?></h3>
                <p style="font-size: 24px; font-weight: bold; margin: 8px 0; color: #d63638;">€0</p>
                <p style="color: #666; margin: 0;"><?php _e('Geen functionaliteit', 'ai-product-chatbot'); ?></p>
            </div>
            <div class="aipc-tier-features">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="padding: 4px 0; color: #d63638;">❌ <?php _e('Chatbot uitgeschakeld', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">⚙️ <?php _e('Alleen licentie activatie pagina', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #d63638;">❌ <?php _e('Geen admin functies', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #d63638;">❌ <?php _e('Geen FAQ toegang', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #d63638;">❌ <?php _e('Geen WooCommerce integratie', 'ai-product-chatbot'); ?></li>
                </ul>
            </div>
            <?php if ($current_tier === 'none'): ?>
                <div style="margin-top: 20px; padding: 12px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; color: #856404;">
                    <strong><?php _e('Huidige Status', 'ai-product-chatbot'); ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <!-- Basic Tier -->
        <div class="aipc-tier-card <?php echo $current_tier === 'basic' ? 'aipc-current-tier' : ''; ?>" style="background: white; border: 2px solid <?php echo $current_tier === 'basic' ? '#2271b1' : '#ddd'; ?>; border-radius: 12px; padding: 24px; text-align: center;">
            <div class="aipc-tier-header" style="border-bottom: 1px solid #eee; padding-bottom: 16px; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #2271b1;">💼 <?php _e('Basic', 'ai-product-chatbot'); ?></h3>
                <p style="font-size: 32px; font-weight: bold; margin: 8px 0; color: #2271b1;">€?</p>
                <p style="color: #666; margin: 0;"><?php _e('per jaar', 'ai-product-chatbot'); ?></p>
            </div>
            <div class="aipc-tier-features">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('Chatbot met FAQ/WooCommerce antwoorden', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('WooCommerce Integratie (50 producten)', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('FAQ Beheer (20 items)', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #666;">❌ <?php _e('Geen AI-gegenereerde antwoorden', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #666;">❌ <?php _e('Geen Visual Product Quiz Builder', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #666;">❌ <?php _e('Geen Kenmerken Beheer', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #666;">❌ <?php _e('Geen Documenten Upload', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #666;">❌ <?php _e('Geen Custom Post Types', 'ai-product-chatbot'); ?></li>
                </ul>
            </div>
            <?php if ($current_tier === 'basic'): ?>
                <div style="margin-top: 20px; padding: 12px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 6px; color: #0c5460;">
                    <strong><?php _e('Huidige Licentie', 'ai-product-chatbot'); ?></strong>
                </div>
            <?php elseif ($current_tier === 'none'): ?>
                <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-primary" style="margin-top: 20px; width: 100%; justify-content: center;">
                    <?php _e('Kies Basic', 'ai-product-chatbot'); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Business Tier -->
        <div class="aipc-tier-card aipc-popular <?php echo $current_tier === 'business' ? 'aipc-current-tier' : ''; ?>" style="background: white; border: 2px solid <?php echo $current_tier === 'business' ? '#2271b1' : '#00a32a'; ?>; border-radius: 12px; padding: 24px; text-align: center; position: relative; transform: scale(1.05);">
            <?php if ($current_tier !== 'business'): ?>
                <div style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #00a32a; color: white; padding: 6px 20px; border-radius: 20px; font-size: 12px; font-weight: bold;">
                    <?php _e('POPULAIR', 'ai-product-chatbot'); ?>
                </div>
            <?php endif; ?>
            <div class="aipc-tier-header" style="border-bottom: 1px solid #eee; padding-bottom: 16px; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #00a32a;">🚀 <?php _e('Business', 'ai-product-chatbot'); ?></h3>
                <p style="font-size: 32px; font-weight: bold; margin: 8px 0; color: #00a32a;">€?</p>
                <p style="color: #666; margin: 0;"><?php _e('per jaar', 'ai-product-chatbot'); ?></p>
            </div>
            <div class="aipc-tier-features">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('Alle Basic functies', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('AI-gegenereerde antwoorden (AI Provider)', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('Visual Product Quiz Builder (Drag & Drop)', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('Volledig Kenmerken Beheer', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('WooCommerce Onbeperkt + Bulk Sync', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('FAQ Uitgebreid (100 items)', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('Privacy & GDPR Tools', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('Multilingual Support', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('Priority Support', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #666;">❌ <?php _e('Geen Documenten Upload', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #666;">❌ <?php _e('Geen Custom Post Types', 'ai-product-chatbot'); ?></li>
                </ul>
            </div>
            <?php if ($current_tier === 'business'): ?>
                <div style="margin-top: 20px; padding: 12px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 6px; color: #0c5460;">
                    <strong><?php _e('Huidige Licentie', 'ai-product-chatbot'); ?></strong>
                </div>
            <?php elseif (in_array($current_tier, ['none', 'basic'])): ?>
                <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-primary" style="margin-top: 20px; width: 100%; justify-content: center; background: #00a32a; border-color: #00a32a;">
                    <?php _e($current_tier === 'none' ? 'Kies Business' : 'Upgrade naar Business', 'ai-product-chatbot'); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Enterprise Tier -->
        <div class="aipc-tier-card <?php echo $current_tier === 'enterprise' ? 'aipc-current-tier' : ''; ?>" style="background: white; border: 2px solid <?php echo $current_tier === 'enterprise' ? '#2271b1' : '#ddd'; ?>; border-radius: 12px; padding: 24px; text-align: center;">
            <div class="aipc-tier-header" style="border-bottom: 1px solid #eee; padding-bottom: 16px; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #8b5cf6;">🏆 <?php _e('Enterprise', 'ai-product-chatbot'); ?></h3>
                <p style="font-size: 32px; font-weight: bold; margin: 8px 0; color: #8b5cf6;">€?</p>
                <p style="color: #666; margin: 0;"><?php _e('per jaar', 'ai-product-chatbot'); ?></p>
            </div>
            <div class="aipc-tier-features">
                <ul style="list-style: none; padding: 0; text-align: left;">
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('Alle Business functies', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('Documenten Upload & Management', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('Custom Post Types Support', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('Advanced Analytics & Reporting', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('API Monitoring & Rate Limiting', 'ai-product-chatbot'); ?></li>
                    <li style="padding: 4px 0; color: #2d7c47;">✅ <?php _e('Onbeperkte Content Storage', 'ai-product-chatbot'); ?></li>
                </ul>
            </div>
            <?php if ($current_tier === 'enterprise'): ?>
                <div style="margin-top: 20px; padding: 12px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 6px; color: #0c5460;">
                    <strong><?php _e('Huidige Licentie', 'ai-product-chatbot'); ?></strong>
                </div>
            <?php else: ?>
                <a href="<?php echo esc_url($current_license ? $current_license->generate_upgrade_url('enterprise') : '#'); ?>" class="button button-secondary" style="margin-top: 20px; width: 100%; justify-content: center;">
                    <?php _e($current_tier === 'none' ? 'Kies Enterprise' : 'Upgrade naar Enterprise', 'ai-product-chatbot'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Feature Comparison Table -->
    <div class="aipc-feature-comparison" style="margin: 40px 0;">
        <h2><?php _e('Gedetailleerde Functie Vergelijking', 'ai-product-chatbot'); ?></h2>
        <div style="overflow-x: auto;">
            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th style="width: 40%;"><?php _e('Functie', 'ai-product-chatbot'); ?></th>
                        <th style="width: 15%; text-align: center; background: #f8f9fa;"><?php _e('Geen Licentie', 'ai-product-chatbot'); ?></th>
                        <th style="width: 15%; text-align: center; background: #e3f2fd;"><?php _e('Basic €?/jr', 'ai-product-chatbot'); ?></th>
                        <th style="width: 15%; text-align: center; background: #e8f5e8;"><?php _e('Business €?/jr', 'ai-product-chatbot'); ?></th>
                        <th style="width: 15%; text-align: center; background: #f3e5f5;"><?php _e('Enterprise €?/jr', 'ai-product-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $features = [
                        ['Chatbot Functionaliteit', '❌', '✅ (FAQ/WooCommerce)', '✅ (volledig)', '✅ (volledig)'],
                        ['AI-gegenereerde Antwoorden (AI Provider)', '❌', '❌', '✅', '✅'],
                        ['FAQ Systeem', '❌', '✅ (20 max)', '✅ (100 max)', '✅ (onbeperkt)'],
                        ['WooCommerce Integratie', '❌', '✅ (50 max)', '✅ (onbeperkt)', '✅ (onbeperkt)'],
                        ['Visual Product Quiz Builder', '❌', '❌', '✅', '✅'],
                        ['Kenmerken/Ingredients Beheer', '❌', '❌', '✅', '✅'],
                        ['Bulk WooCommerce Sync', '❌', '❌', '✅', '✅'],
                        ['Documenten Upload', '❌', '❌', '❌', '✅'],
                        ['Custom Post Types', '❌', '❌', '❌', '✅'],
                        ['Advanced Analytics', '❌', '❌', '❌', '✅'],
                        ['API Monitoring Dashboard', '❌', '❌', '❌', '✅'],
                        ['Rate Limiting & Error Tracking', '❌', '❌', '❌', '✅'],
                        ['Privacy & GDPR Tools', '❌', '❌', '✅', '✅'],
                        ['Multilingual Support', '❌', '❌', '✅', '✅'],
                        ['Priority Support', '❌', '❌', '✅', '✅'],
                    ];
                    
                    foreach ($features as $feature): ?>
                        <tr>
                            <td><strong><?php echo esc_html($feature[0]); ?></strong></td>
                            <td style="text-align: center;"><?php echo $feature[1]; ?></td>
                            <td style="text-align: center;"><?php echo $feature[2]; ?></td>
                            <td style="text-align: center;"><?php echo $feature[3]; ?></td>
                            <td style="text-align: center;"><?php echo $feature[4]; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Value Proposition -->
    <div class="aipc-value-proposition" style="background: #f0f6fc; border: 1px solid #c9d1d9; padding: 24px; margin: 30px 0; border-radius: 8px;">
        <h3 style="margin-top: 0; color: #24292f;"><?php _e('💰 Waarom deze prijzen?', 'ai-product-chatbot'); ?></h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div>
                <h4><?php _e('Basic (€?/jaar)', 'ai-product-chatbot'); ?></h4>
                <ul style="margin: 0;">
                    <li><?php _e('€? per maand', 'ai-product-chatbot'); ?></li>
                    <li><?php _e('Volledige AI chatbot functionaliteit', 'ai-product-chatbot'); ?></li>
                    <li><?php _e('Perfect om mee te starten', 'ai-product-chatbot'); ?></li>
                </ul>
            </div>
            <div>
                <h4><?php _e('Business (€?/jaar)', 'ai-product-chatbot'); ?></h4>
                <ul style="margin: 0;">
                    <li><?php _e('€? per maand', 'ai-product-chatbot'); ?></li>
                    <li><?php _e('Alle premium functionaliteiten', 'ai-product-chatbot'); ?></li>
                    <li><?php _e('Voor serieuze e-commerce', 'ai-product-chatbot'); ?></li>
                </ul>
            </div>
            <div>
                <h4><?php _e('Enterprise (€?/jaar)', 'ai-product-chatbot'); ?></h4>
                <ul style="margin: 0;">
                    <li><?php _e('€? per maand', 'ai-product-chatbot'); ?></li>
                    <li><?php _e('Geen limitaties, volledige vrijheid', 'ai-product-chatbot'); ?></li>
                    <li><?php _e('Voor professionele organisaties', 'ai-product-chatbot'); ?></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Contact Info -->
    <div class="aipc-contact-info" style="background: white; border: 1px solid #ddd; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php _e('Vragen over Licenties?', 'ai-product-chatbot'); ?></h3>
        <p><?php _e('Neem contact met ons op voor persoonlijk advies over welke licentie het beste bij jouw bedrijf past.', 'ai-product-chatbot'); ?></p>
        <p>
            <a href="mailto:info@i-works.nl" class="button button-secondary">
                <?php _e('📧 Contact Opnemen', 'ai-product-chatbot'); ?>
            </a>
        </p>
    </div>
</div>

<style>
.aipc-tier-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.aipc-tier-card:hover:not(.aipc-current-tier) {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.aipc-current-tier {
    box-shadow: 0 4px 20px rgba(34, 113, 177, 0.3);
}

.aipc-popular {
    box-shadow: 0 8px 30px rgba(0, 163, 42, 0.2);
}

@media (max-width: 768px) {
    .aipc-pricing-table {
        grid-template-columns: 1fr;
    }
    
    .aipc-tier-card.aipc-popular {
        transform: none;
    }
}
</style>
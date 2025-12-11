<?php
/**
 * VOORBEELD: Hoe de compact pricing table te gebruiken
 * 
 * Dit bestand toont hoe je de pricing table kunt gebruiken op admin pagina's
 * waar je upgrade prompts wilt tonen.
 */

// Gebruik in je admin pagina's:
?>

<!-- VOORBEELD 1: Card Style (default) -->
<div class="notice notice-info">
    <p><strong><?php _e('Upgrade voor meer mogelijkheden!', 'ai-product-chatbot'); ?></strong></p>
    <p><?php _e('Deze functie vereist een Business+ licentie. Bekijk hieronder de vergelijking:', 'ai-product-chatbot'); ?></p>
</div>

<?php 
// Include de compact pricing table met card style
$style = 'cards'; 
$show_current_badge = true;
include AIPC_PLUGIN_DIR . 'admin/components/pricing-table-compact.php'; 
?>

<hr>

<!-- VOORBEELD 2: Table Style -->
<h3><?php _e('Of bekijk de tabel vergelijking:', 'ai-product-chatbot'); ?></h3>

<?php 
// Include de compact pricing table met table style
$style = 'table'; 
$show_current_badge = false;
include AIPC_PLUGIN_DIR . 'admin/components/pricing-table-compact.php'; 
?>

<hr>

<!-- VOORBEELD 3: Inline upgrade prompt -->
<div style="background: #f0f6fc; border-left: 4px solid #0969da; padding: 16px; margin: 20px 0;">
    <h4 style="margin-top: 0; color: #0969da;">🚀 <?php _e('Wil je meer?', 'ai-product-chatbot'); ?></h4>
    <p><?php _e('Upgrade naar Business voor toegang tot alle geavanceerde functies!', 'ai-product-chatbot'); ?></p>
    
    <?php 
    // Gebruik de pricing table compact inline
    $style = 'cards';
    include AIPC_PLUGIN_DIR . 'admin/components/pricing-table-compact.php'; 
    ?>
</div>

<?php
/**
 * GEBRUIK INSTRUCTIES:
 * 
 * 1. Include het bestand in je admin pagina
 * 2. Stel optioneel variabelen in:
 *    - $style = 'cards' of 'table' (default: 'cards')
 *    - $show_current_badge = true/false (default: true)
 * 
 * VOORBEELDEN:
 * 
 * // Standaard card style
 * include AIPC_PLUGIN_DIR . 'admin/components/pricing-table-compact.php';
 * 
 * // Table style zonder "huidig" badge
 * $style = 'table';
 * $show_current_badge = false;
 * include AIPC_PLUGIN_DIR . 'admin/components/pricing-table-compact.php';
 * 
 * // Card style met custom styling
 * echo '<div style="background: #f9f9f9; padding: 20px; border-radius: 8px;">';
 * $style = 'cards';
 * include AIPC_PLUGIN_DIR . 'admin/components/pricing-table-compact.php';
 * echo '</div>';
 */
?>
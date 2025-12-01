<?php
/**
 * Stock Page Template - COMPLETE REBUILD v2.0
 * Handles item matching by both ID and name
 * ✅ NOW EXCLUDES HIDDEN/ARCHIVED ITEMS
 * 
 * @package Capitito_IMS
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$is_admin = current_user_can('manage_options');
$today = current_time('Y-m-d');

// ✅ FIX: Get only non-hidden items for stock management
global $wpdb;
$items_table = $wpdb->prefix . 'capitito_items';
$stock_table = $wpdb->prefix . 'capitito_stock';

// Get non-archived items only
$items = $wpdb->get_results(
    "SELECT * FROM $items_table 
     WHERE (is_archived IS NULL OR is_archived = 0) 
     ORDER BY name ASC"
);

// Get today's stock records
$stock_records_raw = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $stock_table WHERE stock_date = %s",
    $today
), ARRAY_A);

// Index stock records by item_id for quick lookup
$stock_records = array();
foreach ($stock_records_raw as $record) {
    $stock_records[$record['item_id']] = $record;
}
?>

<div class="capitito-page-wrapper">
    <!-- Sticky Green Header -->
    <div class="capitito-sticky-header">
        <div class="sticky-header-content">
            <h1 class="sticky-site-title">Capitito - Stock Management</h1>
            <div class="sticky-header-right">
                <div class="sticky-user-info">
                    <span class="user-icon">👤</span>
                    <span class="user-name"><?php echo esc_html($current_user->display_name); ?></span>
                </div>
                <div class="digital-clock"></div>
            </div>
        </div>
    </div>

    <!-- Page Content -->
    <div class="capitito-container" style="margin-top: 120px;">
        <!-- Main Card -->
        <div class="main-stock-card">
            <div class="card-header-green">
                <h2 class="card-title-white">
                    📦 Today's Stock - <?php echo date('l, F j, Y'); ?>
                </h2>
            </div>

            <div class="card-body-white" style="padding: 0;">
                <div class="table-responsive">
                    <table class="capitito-table-modern" id="stockTable">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding-left: 30px;">ITEM</th>
                                <th style="text-align: center;">OPENING</th>
                                <th style="text-align: center;">IMPORTS</th>
                                <th style="text-align: center;">SALES</th>
                                <th style="text-align: center;">HQ RETURNED</th>
                                <th style="text-align: center;">CLOSED</th>
                                <th style="text-align: center;">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (empty($items)): 
                            ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 60px;">
                                        <div style="font-size: 48px; margin-bottom: 20px;">📦</div>
                                        <p style="color: #999; font-size: 18px;">No items found</p>
                                        <p style="color: #666;">Please add items in the Admin Panel first</p>
                                    </td>
                                </tr>
                            <?php 
                            else:
                                $rows_displayed = 0;
                                
                                foreach ($items as $item): 
                                    // ✅ ROBUST MATCHING: Try ID first, then name fallback
                                    $stock = null;
                                    
                                    // Method 1: Match by item_id
                                    if (isset($stock_records[$item->id])) {
                                        $stock = $stock_records[$item->id];
                                    } 
                                    // Method 2: Fallback - match by item_name
                                    else {
                                        foreach ($stock_records as $stock_record) {
                                            if (isset($stock_record['item_name']) && 
                                                strtolower(trim($stock_record['item_name'])) === strtolower(trim($item->name))) {
                                                $stock = $stock_record;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    // Skip if no stock found
                                    if (!$stock) continue;
                                    
                                    // Extract stock values
                                    $stock_id = isset($stock['id']) ? intval($stock['id']) : 0;
                                    $opening = isset($stock['opening']) ? intval($stock['opening']) : 0;
                                    $imports = isset($stock['imports']) ? intval($stock['imports']) : 0;
                                    $sales = isset($stock['sales']) ? intval($stock['sales']) : 0;
                                    $hq_returned = isset($stock['hq_returned']) ? intval($stock['hq_returned']) : 0;
                                    $closed = isset($stock['closed']) ? intval($stock['closed']) : 0;
                                    $item_name = !empty($item->name) ? $item->name : 'Unknown Item';
                                    
                                    $rows_displayed++;
                            ?>
                            <tr data-stock-id="<?php echo $stock_id; ?>" data-item-id="<?php echo $item->id; ?>">
                                <td style="padding-left: 30px;">
                                    <strong style="font-size: 16px;"><?php echo esc_html($item_name); ?></strong>
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" 
                                           class="stock-input readonly" 
                                           value="<?php echo $opening; ?>" 
                                           data-field="opening"
                                           readonly
                                           disabled>
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" 
                                           class="stock-input editable" 
                                           value="<?php echo $imports; ?>" 
                                           data-stock-id="<?php echo $stock_id; ?>"
                                           data-field="imports"
                                           min="0"
                                           step="1"
                                           <?php echo ($stock_id === 0) ? 'disabled' : ''; ?>>
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" 
                                           class="stock-input readonly sales-field" 
                                           value="<?php echo $sales; ?>" 
                                           data-field="sales"
                                           readonly
                                           disabled>
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" 
                                           class="stock-input editable" 
                                           value="<?php echo $hq_returned; ?>" 
                                           data-stock-id="<?php echo $stock_id; ?>"
                                           data-field="hq_returned"
                                           min="0"
                                           step="1"
                                           <?php echo ($stock_id === 0) ? 'disabled' : ''; ?>>
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" 
                                           class="stock-input readonly closed-field" 
                                           value="<?php echo $closed; ?>" 
                                           data-field="closed"
                                           readonly
                                           disabled>
                                </td>
                                <td style="text-align: center;">
                                    <span class="status-indicator synced">✓ Synced</span>
                                </td>
                            </tr>
                            <?php 
                                endforeach;
                                
                                // Show message if no rows were displayed
                                if ($rows_displayed === 0):
                            ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 60px;">
                                        <div style="font-size: 48px; margin-bottom: 20px;">📊</div>
                                        <p style="color: #999; font-size: 18px;">No stock data available</p>
                                        <p style="color: #666;">Stock records will be created when you place orders</p>
                                    </td>
                                </tr>
                            <?php 
                                endif;
                            endif; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer Info -->
        <div class="info-note-card">
            <div class="info-icon-large">💡</div>
            <div class="info-text">
                <strong>How it works:</strong> Opening = Yesterday's closing | Sales auto-sync from orders every 30 seconds | Imports & HQ Returned are editable | Closed = Opening + Imports - Sales - HQ Returned
            </div>
        </div>
    </div>
</div>

<style>
/* Sticky Header */
.capitito-sticky-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: #185D30;
    color: white;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    z-index: 9999;
}

.sticky-header-content {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sticky-site-title {
    margin: 0;
    font-size: 24px;
    font-weight: 900;
    letter-spacing: 1px;
    color: white;
}

.sticky-header-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.sticky-user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.15);
    padding: 10px 20px;
    border-radius: 30px;
}

.user-icon {
    font-size: 18px;
}

.user-name {
    font-weight: 600;
    font-size: 15px;
}

.digital-clock {
    background: #CC232A;
    padding: 10px 20px;
    border-radius: 30px;
    font-size: 20px;
    font-weight: 900;
    font-family: 'Courier New', monospace;
    min-width: 130px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(204, 35, 42, 0.5);
}

/* Container */
.capitito-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px 40px 20px;
}

/* Main Card */
.main-stock-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    overflow: hidden;
    margin-bottom: 30px;
}

.card-header-green {
    background: #185D30;
    padding: 25px 30px;
    border-bottom: 4px solid #CC232A;
}

.card-title-white {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: white;
}

.card-body-white {
    background: white;
}

/* Table */
.table-responsive {
    overflow-x: auto;
}

.capitito-table-modern {
    width: 100%;
    border-collapse: collapse;
}

.capitito-table-modern thead {
    background: #185D30;
    color: white;
}

.capitito-table-modern thead th {
    padding: 18px 15px;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 13px;
    letter-spacing: 1px;
    border-right: 1px solid rgba(255,255,255,0.1);
}

.capitito-table-modern thead th:last-child {
    border-right: none;
}

.capitito-table-modern tbody tr {
    border-bottom: 1px solid #e8e8e8;
    transition: all 0.3s ease;
}

.capitito-table-modern tbody tr:hover {
    background: #f5f5f5;
}

.capitito-table-modern tbody tr.saving {
    background: #fff3e0;
}

.capitito-table-modern tbody td {
    padding: 20px 15px;
    vertical-align: middle;
}

/* Stock Inputs */
.stock-input {
    width: 100%;
    max-width: 130px;
    padding: 12px 18px;
    text-align: center;
    font-size: 17px;
    font-weight: 700;
    border: 3px solid #e0e0e0;
    border-radius: 30px;
    background: #fff;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.stock-input:focus {
    outline: none;
    border-color: #185D30;
    box-shadow: 0 0 0 4px rgba(24, 93, 48, 0.15);
}

.stock-input.editable {
    background: white;
    cursor: text;
}

.stock-input.editable:hover {
    border-color: #185D30;
    background: #f9f9f9;
}

.stock-input.readonly {
    background: #f8f9fa;
    cursor: not-allowed;
    color: #666;
}

.stock-input.sales-field {
    background: #fff3e0;
    border-color: #ff9800;
    color: #e65100;
}

.stock-input.closed-field {
    background: #e8f5e9;
    border-color: #4caf50;
    color: #1b5e20;
}

/* Status */
.status-indicator {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 14px;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.status-indicator.synced {
    background: #185D30;
    color: white;
}

.status-indicator.saving {
    background: #ff9800;
    color: white;
}

.status-indicator.error {
    background: #CC232A;
    color: white;
}

/* Info Card */
.info-note-card {
    background: white;
    border-left: 6px solid #185D30;
    border-radius: 15px;
    padding: 20px 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.info-icon-large {
    font-size: 40px;
}

.info-text {
    color: #333;
    font-size: 15px;
    line-height: 1.6;
}

.info-text strong {
    color: #185D30;
    font-size: 16px;
}

/* Responsive */
@media (max-width: 768px) {
    .capitito-sticky-header {
        padding: 15px;
    }

    .sticky-header-content {
        flex-direction: column;
        gap: 15px;
    }

    .sticky-site-title {
        font-size: 18px;
    }

    .sticky-header-right {
        width: 100%;
        justify-content: space-between;
    }

    .capitito-container {
        margin-top: 150px !important;
    }

    .stock-input {
        max-width: 90px;
        padding: 10px 12px;
        font-size: 15px;
    }
}
</style>
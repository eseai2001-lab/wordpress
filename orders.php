<?php
/**
 * Orders Page Template (with per-item Discount ₦)
 *
 * @package Capitito_IMS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// Primary items table (used by Admin Panel and Stock)
$items_table_primary = $wpdb->prefix . 'capitito_items';
// Legacy items table (older installs)
$items_table_legacy  = $wpdb->prefix . 'capitito_ims_items';

// Load items from the primary table first
$items = $wpdb->get_results( "SELECT * FROM $items_table_primary WHERE (is_archived IS NULL OR is_archived = 0) ORDER BY name ASC" );

// Fallback to legacy table if primary is empty or unavailable
if ( empty( $items ) ) {
    $legacy = $wpdb->get_results( "SELECT * FROM $items_table_legacy ORDER BY name ASC" );
    if ( ! empty( $legacy ) ) {
        $items = $legacy;
    }
}

$user = wp_get_current_user();
?>

<div class="capitito-ims-wrapper">
    <div class="capitito-ims-header">
        <div class="header-left">
            <h1>Capitito - New Order</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <span class="user-icon">👤</span>
                <span class="user-name"><?php echo esc_html( $user->display_name ); ?></span>
            </div>
            <div class="digital-clock" id="digitalClock"></div>
        </div>
    </div>

    <div class="capitito-ims-content">
        <div class="card">
            <div class="card-header">
                <h2>Create New Order</h2>
            </div>
            <div class="card-body">
                <form id="orderForm" class="capitito-form">
                    
                    <!-- Order Items Table -->
                    <div class="form-section">
                        <h3>Order Items</h3>
                        <div class="info-banner" style="margin-bottom: 20px;">
                            <strong>Instructions:</strong>
                            <span class="legend-item">Enter quantity and optional discount (₦) per item</span>
                            <span class="legend-item">Items with quantity 0 will not be included</span>
                        </div>
                        <div class="table-responsive">
                            <table class="capitito-table" id="orderItemsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 35%;">Item</th>
                                        <th style="width: 18%;">Price (₦)</th>
                                        <th style="width: 14%;">Quantity</th>
                                        <th style="width: 14%;">Discount (₦)</th>
                                        <th style="width: 19%;">Total (₦)</th>
                                    </tr>
                                </thead>
                                <tbody id="orderItemsBody">
                                    <?php if ( empty( $items ) ) : ?>
                                        <tr>
                                            <td colspan="5" class="text-center" style="padding: 40px;">
                                                <p style="color: #999; font-size: 16px;">
                                                    No items available. Please ask admin to add items first.
                                                </p>
                                            </td>
                                        </tr>
                                    <?php else : ?>
                                        <?php foreach ( $items as $item ) : ?>
                                        <tr data-item-id="<?php echo esc_attr( $item->id ); ?>">
                                            <td class="item-name-cell">
                                                <strong><?php echo esc_html( $item->name ); ?></strong>
                                            </td>
                                            <td>
                                                <strong>₦<?php echo number_format( (float) $item->price, 2 ); ?></strong>
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       class="form-control item-quantity" 
                                                       data-item-id="<?php echo esc_attr( $item->id ); ?>"
                                                       data-item-name="<?php echo esc_attr( $item->name ); ?>"
                                                       data-item-price="<?php echo esc_attr( (float) $item->price ); ?>"
                                                       value="0" 
                                                       min="0"
                                                       style="width: 100px; text-align: center; font-weight: 600;">
                                            </td>
                                            <td>
                                                <!-- Discount is NAIRA amount (formatted by JS) -->
                                                <input type="text" 
                                                       inputmode="decimal"
                                                       class="form-control item-discount" 
                                                       data-item-id="<?php echo esc_attr( $item->id ); ?>"
                                                       value="0.00"
                                                       placeholder="0.00"
                                                       style="width: 120px; text-align: right;">
                                            </td>
                                            <td>
                                                <strong class="item-total" data-item-id="<?php echo esc_attr( $item->id ); ?>">
                                                    ₦0.00
                                                </strong>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="form-section">
                        <h3>Payment Method</h3>
                        <div class="payment-methods-grid-container">
                            
                            <!-- Row 1: Single Methods -->
                            <div class="payment-row">
                                <label class="payment-method">
                                    <input type="radio" name="payment_method" value="cash" data-type="single">
                                    <span class="payment-label">
                                        <span class="payment-icon">💵</span>
                                        <span class="payment-text">Cash</span>
                                    </span>
                                </label>
                                <label class="payment-method">
                                    <input type="radio" name="payment_method" value="card" data-type="single">
                                    <span class="payment-label">
                                        <span class="payment-icon">💳</span>
                                        <span class="payment-text">Card</span>
                                    </span>
                                </label>
                                <label class="payment-method">
                                    <input type="radio" name="payment_method" value="transfer" data-type="single">
                                    <span class="payment-label">
                                        <span class="payment-icon">🏦</span>
                                        <span class="payment-text">Transfer</span>
                                    </span>
                                </label>
                            </div>

                            <!-- Row 2: Combo Methods (2 types) -->
                            <div class="payment-row">
                                <label class="payment-method payment-combo">
                                    <input type="radio" name="payment_method" value="cash_card" data-type="combo">
                                    <span class="payment-label">
                                        <span class="payment-icon">💵💳</span>
                                        <span class="payment-text">Cash + Card</span>
                                    </span>
                                </label>
                                <label class="payment-method payment-combo">
                                    <input type="radio" name="payment_method" value="cash_transfer" data-type="combo">
                                    <span class="payment-label">
                                        <span class="payment-icon">💵🏦</span>
                                        <span class="payment-text">Cash + Transfer</span>
                                    </span>
                                </label>
                                <label class="payment-method payment-combo">
                                    <input type="radio" name="payment_method" value="card_transfer" data-type="combo">
                                    <span class="payment-label">
                                        <span class="payment-icon">💳🏦</span>
                                        <span class="payment-text">Card + Transfer</span>
                                    </span>
                                </label>
                            </div>

                            <!-- Row 3: Triple Combo -->
                            <div class="payment-row payment-row-single">
                                <label class="payment-method payment-combo">
                                    <input type="radio" name="payment_method" value="cash_card_transfer" data-type="combo">
                                    <span class="payment-label">
                                        <span class="payment-icon">💵💳🏦</span>
                                        <span class="payment-text">Cash + Card + Transfer</span>
                                    </span>
                                </label>
                            </div>

                        </div>

                        <!-- Payment Breakdown Section (Hidden by default) -->
                        <div id="paymentBreakdownSection" style="display: none; margin-top: 25px;">
                            <div class="payment-breakdown-card">
                                <h4 style="color: #D02126; margin-bottom: 20px;">💰 Payment Breakdown</h4>
                                
                                <div class="payment-breakdown-grid">
                                    <!-- Cash Input -->
                                    <div class="payment-input-group" id="cashInputGroup" style="display: none;">
                                        <label for="cashAmount">💵 Cash Amount (₦):</label>
                                        <input type="text" 
                                               id="cashAmount" 
                                               class="form-control payment-split-input" 
                                               inputmode="decimal"
                                               placeholder="0.00"
                                               style="text-align: right;">
                                    </div>

                                    <!-- Card Input -->
                                    <div class="payment-input-group" id="cardInputGroup" style="display: none;">
                                        <label for="cardAmount">💳 Card Amount (₦):</label>
                                        <input type="text" 
                                               id="cardAmount" 
                                               class="form-control payment-split-input" 
                                               inputmode="decimal"
                                               placeholder="0.00"
                                               style="text-align: right;">
                                    </div>

                                    <!-- Transfer Input -->
                                    <div class="payment-input-group" id="transferInputGroup" style="display: none;">
                                        <label for="transferAmount">🏦 Transfer Amount (₦):</label>
                                        <input type="text" 
                                               id="transferAmount" 
                                               class="form-control payment-split-input" 
                                               inputmode="decimal"
                                               placeholder="0.00"
                                               style="text-align: right;">
                                    </div>
                                </div>

                                <!-- Payment Summary -->
                                <div class="payment-summary">
                                    <div class="summary-row">
                                        <span>Total Amount:</span>
                                        <strong id="summaryTotal">₦0.00</strong>
                                    </div>
                                    <div class="summary-row">
                                        <span>Amount Entered:</span>
                                        <strong id="summaryEntered">₦0.00</strong>
                                    </div>
                                    <div class="summary-row balance-row" id="balanceRow">
                                        <span>Balance:</span>
                                        <strong id="summaryBalance">₦0.00</strong>
                                    </div>
                                </div>

                                <div id="paymentValidationMessage" class="payment-validation" style="display: none;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Grand Total -->
                    <div class="form-section grand-total-section">
                        <h3>Grand Total</h3>
                        <div class="grand-total-display">
                            <span class="currency">₦</span>
                            <span id="grandTotalAmount">0.00</span>
                        </div>
                        <div class="discount-total-display" style="margin-top:10px; font-weight:700; color:#165E30; font-size: 16px;">
                            Total Discount: ₦<span id="orderDiscountTotal">0.00</span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg" id="submitOrderBtn">
                            <span class="btn-icon">✓</span> Submit Order
                        </button>
                        <button type="button" class="btn btn-secondary btn-lg" id="clearOrderBtn">
                            <span class="btn-icon">↻</span> Clear Quantities
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Order Confirmation Modal -->
<div id="orderConfirmationModal" class="capitito-modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Order Confirmation</h3>
            <button type="button" class="modal-close" data-modal="orderConfirmationModal">&times;</button>
        </div>
        <div class="modal-body" id="orderConfirmationContent">
            <!-- Order confirmation details will be inserted here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <span class="btn-icon">🖨️</span> Print
            </button>
            <button type="button" class="btn btn-secondary" onclick="location.reload()">
                New Order
            </button>
        </div>
    </div>
</div>
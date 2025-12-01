<?php
/**
 * Financial Summary Page Template (with Discount Today auto-field)
 *
 * @package Capitito_IMS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$user = wp_get_current_user();
$today = current_time( 'Y-m-d' );

// Get today's financial data with auto-calculated values
$today_financial = Capitito_IMS_Financial::get_todays_summary();

$is_submitted = $today_financial->is_submitted;

// Get discount total for today
$discount_today = isset($today_financial->discount_total) ? floatval($today_financial->discount_total) : Capitito_IMS_Financial::get_daily_discount_total($today);
?>

<div class="capitito-ims-wrapper">
    <div class="capitito-ims-header">
        <div class="header-left">
            <h1>Capitito - Financial Summary</h1>
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
                <h2>Today's Financial Summary - <?php echo date( 'F j, Y', strtotime( $today ) ); ?></h2>
                <?php if ( $is_submitted ) : ?>
                    <span class="badge badge-success">✓ Submitted</span>
                <?php else : ?>
                    <span class="badge badge-warning">⏳ Pending</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                
                <?php if ( $is_submitted ) : ?>
                    <div class="alert alert-info">
                        This financial summary has already been submitted. Only administrators can edit submitted records.
                    </div>
                <?php endif; ?>

                <div class="alert alert-info" style="margin-bottom: 20px;">
                    <strong>🔄 Auto-Calculation:</strong> Total Sales, Cash, Card, Transfer, and Discount amounts are automatically calculated from today's orders. You can optionally enter Expenses and Paid to Bank (leave as 0 if none).
                </div>

                <form id="financialSummaryForm" class="capitito-form">
                    
                    <div class="financial-grid">
                        
                        <!-- Total Sales (Auto-calculated, Read-only) -->
                        <div class="form-group">
                            <label>Total Sales (₦): <span class="badge badge-info">Auto</span></label>
                            <input type="number" id="totalSales" name="total_sales" class="form-control readonly-field" 
                                   value="<?php echo esc_attr( $today_financial->total_sales ); ?>" 
                                   readonly>
                            <small class="form-text">Automatically calculated from today's orders</small>
                        </div>

                        <!-- Card (Auto-calculated, Read-only) -->
                        <div class="form-group">
                            <label>Card (₦): <span class="badge badge-info">Auto</span></label>
                            <input type="number" id="card" name="card" class="form-control readonly-field" 
                                   value="<?php echo esc_attr( $today_financial->card ); ?>" 
                                   readonly>
                            <small class="form-text">From card payments in orders</small>
                        </div>

                        <!-- Cash (Auto-calculated, Read-only) -->
                        <div class="form-group">
                            <label>Cash (₦): <span class="badge badge-info">Auto</span></label>
                            <input type="number" id="cash" name="cash" class="form-control readonly-field" 
                                   value="<?php echo esc_attr( $today_financial->cash ); ?>" 
                                   readonly>
                            <small class="form-text">From cash payments in orders</small>
                        </div>

                        <!-- Transfer (Auto-calculated, Read-only) -->
                        <div class="form-group">
                            <label>Transfer (₦): <span class="badge badge-info">Auto</span></label>
                            <input type="number" id="transfer" name="transfer" class="form-control readonly-field" 
                                   value="<?php echo esc_attr( $today_financial->transfer ); ?>" 
                                   readonly>
                            <small class="form-text">From transfer payments in orders</small>
                        </div>

                        <!-- Discount Today (Auto-calculated, Read-only) -->
                        <div class="form-group">
                            <label>Discount Today (₦): <span class="badge badge-info">Auto</span></label>
                            <input type="text" id="discountToday" class="form-control readonly-field" 
                                   value="₦<?php echo number_format($discount_today, 2); ?>" 
                                   readonly>
                            <small class="form-text">Total discounts given today</small>
                            <button type="button" class="btn btn-secondary btn-sm" id="viewDiscountDetailsBtn" style="margin-top: 8px;">
                                View Discount Details
                            </button>
                        </div>

                        <!-- Expenses (User Input - OPTIONAL) -->
                        <div class="form-group">
                            <label>Expenses (₦): <span class="badge badge-secondary">Optional</span></label>
                            <input type="number" id="expenses" name="expenses" class="form-control financial-input" 
                                   value="<?php echo esc_attr( $today_financial->expenses ); ?>" 
                                   step="0.01" 
                                   min="0"
                                   oninput="if(this.value < 0) this.value = 0;"
                                   onkeydown="if(event.key === '-' || event.key === 'Minus') event.preventDefault();"
                                   placeholder="0.00"
                                   <?php echo $is_submitted ? 'readonly' : ''; ?>>
                            <small class="form-text">Enter total expenses for today (leave as 0 if none)</small>
                        </div>

                        <!-- Old Cash (Auto-calculated from yesterday) -->
                        <div class="form-group">
                            <label>Old Cash (₦): <span class="badge badge-info">Auto</span></label>
                            <input type="number" id="oldCash" name="old_cash" class="form-control readonly-field" 
                                   value="<?php echo esc_attr( $today_financial->old_cash ); ?>" 
                                   readonly>
                            <small class="form-text">Carried over from yesterday's cash left</small>
                        </div>

                        <!-- Paid to Bank (User Input - OPTIONAL) -->
                        <div class="form-group">
                            <label>Paid to Bank (₦): <span class="badge badge-secondary">Optional</span></label>
                            <input type="number" id="paidToBank" name="paid_to_bank" class="form-control financial-input" 
                                   value="<?php echo esc_attr( $today_financial->paid_to_bank ); ?>" 
                                   step="0.01" 
                                   min="0"
                                   oninput="if(this.value < 0) this.value = 0;"
                                   onkeydown="if(event.key === '-' || event.key === 'Minus') event.preventDefault();"
                                   placeholder="0.00"
                                   <?php echo $is_submitted ? 'readonly' : ''; ?>>
                            <small class="form-text">Amount deposited to bank today (leave as 0 if none)</small>
                        </div>

                        <!-- Cash Left (Auto-calculated) -->
                        <div class="form-group">
                            <label>Cash Left (₦): <span class="badge badge-success">Calculated</span></label>
                            <input type="number" id="cashLeft" name="cash_left" class="form-control readonly-field cash-left-display" 
                                   value="<?php echo esc_attr( $today_financial->cash_left ); ?>" 
                                   readonly>
                            <small class="form-text">Total Sales - Transfer - Card - Expenses + Old Cash - Paid to Bank</small>
                            <div id="cashLeftWarning" style="display: none; margin-top: 10px; padding: 12px; background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); border-left: 4px solid #D02126; border-radius: 8px;">
                                <strong style="color: #D02126;">⚠️ WARNING:</strong> 
                                <span style="color: #c62828;">Cash Left cannot be negative! Please adjust Expenses or Paid to Bank.</span>
                            </div>
                        </div>

                    </div>

                    <!-- Expense Remark (Also Optional) -->
                    <div class="form-group">
                        <label>Expense Remark: <span class="badge badge-secondary">Optional</span></label>
                        <textarea id="expenseRemark" name="expense_remark" class="form-control" 
                                  rows="4" 
                                  placeholder="Describe what the expenses were for (if any)..."
                                  <?php echo $is_submitted ? 'readonly' : ''; ?>><?php echo esc_textarea( $today_financial->expense_remark ); ?></textarea>
                        <small class="form-text">Add notes about expenses if needed</small>
                    </div>

                    <!-- Submit Button -->
                    <?php if ( ! $is_submitted ) : ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg" id="submitFinancialBtn">
                            <span class="btn-icon">💾</span> Submit Financial Summary
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="info-message">
                        <p><strong>Note:</strong> Once submitted, this summary will be locked. The "Cash Left" will automatically become tomorrow's "Old Cash". You can submit even if Expenses and Paid to Bank are 0.</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Discount Details Modal -->
<div id="discountDetailsModal" class="capitito-modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Discount Details - <?php echo date('F j, Y', strtotime($today)); ?></h3>
            <button type="button" class="modal-close" data-modal="discountDetailsModal">&times;</button>
        </div>
        <div class="modal-body">
            <div id="discountDetailsContent" style="max-height: 400px; overflow-y: auto;">
                <!-- Will be populated via AJAX -->
            </div>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #165E30;">
                <h4 style="color: #165E30;">Total Discount Today: ₦<span id="modalDiscountTotal">0.00</span></h4>
            </div>
        </div>
    </div>
</div>
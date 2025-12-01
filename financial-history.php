<?php
/**
 * Financial History Page Template - NEGATIVE VALUES BLOCKED
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$user = wp_get_current_user();
$is_admin = current_user_can( 'edit_capitito_ims_financial' );
?>

<div class="capitito-ims-wrapper">
    <div class="capitito-ims-header">
        <div class="header-left">
            <h1>Capitito - Financial History</h1>
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
                <h2>Financial History</h2>
            </div>
            <div class="card-body">
                
                <!-- Filters -->
                <div class="filters-section">
                    <div class="filter-group">
                        <label>Date From:</label>
                        <input type="date" id="filterDateFrom" class="filter-input">
                    </div>
                    <div class="filter-group">
                        <label>Date To:</label>
                        <input type="date" id="filterDateTo" class="filter-input">
                    </div>
                    <div class="filter-group">
                        <label>Submitted By:</label>
                        <input type="text" id="filterSubmittedBy" class="filter-input" placeholder="Staff name">
                    </div>
                    <div class="filter-actions">
                        <button type="button" class="btn btn-primary" id="applyFiltersBtn">
                            <span class="btn-icon">🔍</span> Apply Filters
                        </button>
                        <button type="button" class="btn btn-secondary" id="clearFiltersBtn">
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Financial History Table -->
                <div class="table-responsive">
                    <table class="capitito-table" id="financialHistoryTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total Sales (₦)</th>
                                <th>Cash Left (₦)</th>
                                <th>Submitted By</th>
                                <th>Submitted At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="financialHistoryBody">
                            <tr>
                                <td colspan="6" class="text-center">
                                    <div class="loading-spinner"></div>
                                    Loading financial history...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-section" id="paginationSection">
                    <!-- Pagination will be inserted here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Financial Details Modal -->
<div id="viewFinancialModal" class="capitito-modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Financial Details</h3>
            <button type="button" class="modal-close" data-modal="viewFinancialModal">&times;</button>
        </div>
        <div class="modal-body" id="viewFinancialContent">
            <!-- Financial details will be inserted here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal="viewFinancialModal">Close</button>
        </div>
    </div>
</div>

<!-- Edit Financial Modal (Admin Only) -->
<?php if ( $is_admin ) : ?>
<div id="editFinancialModal" class="capitito-modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Edit Financial Record</h3>
            <button type="button" class="modal-close" data-modal="editFinancialModal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editFinancialForm" class="capitito-form">
                <input type="hidden" id="editFinancialId" name="financial_id">
                
                <div class="financial-grid">
                    <div class="form-group">
                        <label>Total Sales (₦):</label>
                        <input type="number" 
                               id="editTotalSales" 
                               name="total_sales" 
                               class="form-control" 
                               step="0.01" 
                               min="0" 
                               oninput="if(this.value < 0) this.value = 0;"
                               onkeydown="if(event.key === '-' || event.key === 'Minus') event.preventDefault();"
                               required>
                    </div>
                    <div class="form-group">
                        <label>Card (₦):</label>
                        <input type="number" 
                               id="editCard" 
                               name="card" 
                               class="form-control" 
                               step="0.01" 
                               min="0" 
                               oninput="if(this.value < 0) this.value = 0;"
                               onkeydown="if(event.key === '-' || event.key === 'Minus') event.preventDefault();"
                               required>
                    </div>
                    <div class="form-group">
                        <label>Cash (₦):</label>
                        <input type="number" 
                               id="editCash" 
                               name="cash" 
                               class="form-control" 
                               step="0.01" 
                               min="0" 
                               oninput="if(this.value < 0) this.value = 0;"
                               onkeydown="if(event.key === '-' || event.key === 'Minus') event.preventDefault();"
                               required>
                    </div>
                    <div class="form-group">
                        <label>Transfer (₦):</label>
                        <input type="number" 
                               id="editTransfer" 
                               name="transfer" 
                               class="form-control" 
                               step="0.01" 
                               min="0" 
                               oninput="if(this.value < 0) this.value = 0;"
                               onkeydown="if(event.key === '-' || event.key === 'Minus') event.preventDefault();"
                               required>
                    </div>
                    <div class="form-group">
                        <label>Expenses (₦):</label>
                        <input type="number" 
                               id="editExpenses" 
                               name="expenses" 
                               class="form-control" 
                               step="0.01" 
                               min="0" 
                               oninput="if(this.value < 0) this.value = 0;"
                               onkeydown="if(event.key === '-' || event.key === 'Minus') event.preventDefault();"
                               required>
                    </div>
                    <div class="form-group">
                        <label>Old Cash (₦):</label>
                        <input type="number" 
                               id="editOldCash" 
                               name="old_cash" 
                               class="form-control" 
                               step="0.01" 
                               min="0" 
                               oninput="if(this.value < 0) this.value = 0;"
                               onkeydown="if(event.key === '-' || event.key === 'Minus') event.preventDefault();"
                               required>
                    </div>
                    <div class="form-group">
                        <label>Paid to Bank (₦):</label>
                        <input type="number" 
                               id="editPaidToBank" 
                               name="paid_to_bank" 
                               class="form-control" 
                               step="0.01" 
                               min="0" 
                               oninput="if(this.value < 0) this.value = 0;"
                               onkeydown="if(event.key === '-' || event.key === 'Minus') event.preventDefault();"
                               required>
                    </div>
                    <div class="form-group">
                        <label>Cash Left (₦):</label>
                        <input type="number" 
                               id="editCashLeft" 
                               class="form-control readonly-field" 
                               readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label>Expense Remark:</label>
                    <textarea id="editExpenseRemark" 
                              name="expense_remark" 
                              class="form-control" 
                              rows="4"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">💾</span> Save Changes
                    </button>
                    <button type="button" class="btn btn-secondary" data-modal="editFinancialModal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
/**
 * Order History Page Template
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$user = wp_get_current_user();
$is_admin = current_user_can( 'manage_capitito_ims' );
?>

<div class="capitito-ims-wrapper">
    <div class="capitito-ims-header">
        <div class="header-left">
            <h1>Capitito - Order History</h1>
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
                <h2>Order History</h2>
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
                        <label>Payment Method:</label>
                        <select id="filterPaymentMethod" class="filter-input">
                            <option value="">All</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="transfer">Transfer</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Staff:</label>
                        <input type="text" id="filterStaff" class="filter-input" placeholder="Staff name">
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

                <!-- Orders Table -->
                <div class="table-responsive">
                    <table class="capitito-table" id="orderHistoryTable">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Staff</th>
                                <th>Payment Method</th>
                                <th>Grand Total (₦)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="orderHistoryBody">
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="loading-spinner"></div>
                                    Loading orders...
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

<!-- View Order Modal -->
<div id="viewOrderModal" class="capitito-modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Order Details</h3>
            <button type="button" class="modal-close" data-modal="viewOrderModal">&times;</button>
        </div>
        <div class="modal-body" id="viewOrderContent">
            <!-- Order details will be inserted here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal="viewOrderModal">Close</button>
        </div>
    </div>
</div>

<!-- Edit Order Modal (Admin Only) -->
<?php if ( $is_admin ) : ?>
<div id="editOrderModal" class="capitito-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Order</h3>
            <button type="button" class="modal-close" data-modal="editOrderModal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editOrderForm">
                <input type="hidden" id="editOrderId" name="order_id">
                
                <div class="form-group">
                    <label>Payment Method:</label>
                    <select id="editPaymentMethod" name="payment_method" class="form-control" required>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="transfer">Transfer</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Grand Total (₦):</label>
                    <input type="number" id="editGrandTotal" name="grand_total" class="form-control" step="0.01" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">💾</span> Save Changes
                    </button>
                    <button type="button" class="btn btn-secondary" data-modal="editOrderModal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
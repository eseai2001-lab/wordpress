<?php
/**
 * Product Summary Page Template
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$user = wp_get_current_user();
?>

<div class="capitito-ims-wrapper">
    <div class="capitito-ims-header">
        <div class="header-left">
            <h1>Capitito - Product Summary</h1>
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
                <h2>Daily Product Summary</h2>
            </div>
            <div class="card-body">
                
                <!-- Filters -->
                <div class="filters-section">
                    <div class="filter-group">
                        <label>Date:</label>
                        <input type="date" id="filterDate" class="filter-input" value="<?php echo current_time( 'Y-m-d' ); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Item:</label>
                        <input type="text" id="filterItem" class="filter-input" placeholder="Item name">
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

                <!-- Summary Statistics -->
                <div class="summary-stats">
                    <div class="stat-card">
                        <div class="stat-label">Total Products Sold Today</div>
                        <div class="stat-value" id="totalProductsSold">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Unique Items</div>
                        <div class="stat-value" id="uniqueItems">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-value" id="totalOrders">0</div>
                    </div>
                </div>

                <!-- Product Summary Table -->
                <div class="table-responsive">
                    <table class="capitito-table" id="productSummaryTable">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Units Sold</th>
                                <th>Order Time</th>
                                <th>Staff</th>
                            </tr>
                        </thead>
                        <tbody id="productSummaryBody">
                            <tr>
                                <td colspan="4" class="text-center">
                                    <div class="loading-spinner"></div>
                                    Loading product summary...
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td><strong>TOTAL</strong></td>
                                <td><strong id="footerTotalUnits">0</strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
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
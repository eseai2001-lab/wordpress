<?php
/**
 * Stock History Page Template - BRAND COLORS ONLY
 * 
 * @package Capitito_IMS
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$is_admin = current_user_can('manage_options');
?>

<div class="capitito-page-wrapper">
    <!-- Sticky Green Header -->
    <div class="capitito-sticky-header">
        <div class="sticky-header-content">
            <h1 class="sticky-site-title">Capitito - Stock History</h1>
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
        <!-- Stats Cards - BRAND COLORS -->
        <div class="stats-grid-brand">
            <div class="stat-card-brand stat-green">
                <div class="stat-icon-circle">📊</div>
                <div class="stat-content">
                    <p class="stat-label">TOTAL RECORDS</p>
                    <p class="stat-value" id="totalRecords">0</p>
                </div>
            </div>
            <div class="stat-card-brand stat-green-light">
                <div class="stat-icon-circle">📅</div>
                <div class="stat-content">
                    <p class="stat-label">DATE RANGE</p>
                    <p class="stat-value-small" id="dateRange">All Dates</p>
                </div>
            </div>
        </div>

        <!-- Filters Card - GREEN BACKGROUND -->
        <div class="filter-card-brand">
            <div class="filter-card-header-green">
                <h3>🔍 Filter Stock Records</h3>
            </div>
            <div class="filter-card-body">
                <div class="filter-grid-brand">
                    <div class="form-group-brand">
                        <label>From Date</label>
                        <input type="date" id="filterDateFrom" class="form-control-brand">
                    </div>
                    <div class="form-group-brand">
                        <label>To Date</label>
                        <input type="date" id="filterDateTo" class="form-control-brand">
                    </div>
                    <div class="form-group-brand">
                        <label>Item</label>
                        <select id="filterItem" class="form-control-brand">
                            <option value="">All Items</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item->id; ?>"><?php echo esc_html($item->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-brand filter-buttons">
                        <button type="button" id="applyFiltersBtn" class="btn-brand btn-red">
                            🔍 Apply Filters
                        </button>
                        <button type="button" id="clearFiltersBtn" class="btn-brand btn-green">
                            ✖️ Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock History Table Card -->
        <div class="main-stock-card">
            <div class="card-header-green">
                <h2 class="card-title-white">📜 Stock Movement History</h2>
            </div>

            <div class="card-body-white" style="padding: 0;">
                <div class="table-responsive">
                    <table class="capitito-table-modern" id="stockHistoryTable">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding-left: 30px;">DATE</th>
                                <th style="text-align: left;">ITEM</th>
                                <th style="text-align: center;">OPENING</th>
                                <th style="text-align: center;">IMPORTS</th>
                                <th style="text-align: center;">SALES</th>
                                <th style="text-align: center;">HQ RETURNED</th>
                                <th style="text-align: center;">CLOSED</th>
                                <?php if ($is_admin): ?>
                                    <th style="text-align: center;">ACTIONS</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="stockHistoryBody">
                            <tr>
                                <td colspan="<?php echo $is_admin ? '8' : '7'; ?>" style="text-align: center; padding: 80px;">
                                    <div style="font-size: 60px; margin-bottom: 20px;">⏳</div>
                                    <p style="color: #999; font-size: 20px; font-weight: 600;">Loading stock history...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div id="paginationSection"></div>
    </div>
</div>

<!-- Edit Stock Modal -->
<?php if ($is_admin): ?>
<div id="editStockModal" class="capitito-modal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header-green">
            <h3>✏️ Edit Stock Record</h3>
            <button class="modal-close-brand" data-modal="editStockModal">×</button>
        </div>
        <div class="modal-body">
            <form id="editStockForm">
                <input type="hidden" id="editStockId">
                
                <div class="form-group-brand">
                    <label>Date</label>
                    <input type="date" id="editStockDate" class="form-control-brand" readonly disabled>
                </div>

                <div class="form-group-brand">
                    <label>Item</label>
                    <input type="text" id="editItemName" class="form-control-brand" readonly disabled>
                </div>

                <div class="form-row-brand">
                    <div class="form-group-brand">
                        <label>Opening</label>
                        <input type="number" id="editOpening" class="form-control-brand" min="0" step="1" required>
                    </div>
                    <div class="form-group-brand">
                        <label>Imports</label>
                        <input type="number" id="editImports" class="form-control-brand" min="0" step="1" required>
                    </div>
                </div>

                <div class="form-row-brand">
                    <div class="form-group-brand">
                        <label>Sales (Read-only)</label>
                        <input type="number" id="editSales" class="form-control-brand" readonly disabled>
                    </div>
                    <div class="form-group-brand">
                        <label>HQ Returned</label>
                        <input type="number" id="editHqReturned" class="form-control-brand" min="0" step="1" required>
                    </div>
                </div>

                <div class="form-group-brand">
                    <label>Closed (Auto-calculated)</label>
                    <input type="number" id="editClosed" class="form-control-brand" readonly disabled>
                </div>

                <div class="modal-actions-brand">
                    <button type="submit" class="btn-brand btn-red">
                        💾 Update Stock
                    </button>
                    <button type="button" class="btn-brand btn-green" data-modal="editStockModal">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Sticky Header - BRAND GREEN */
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

/* Stats Grid - BRAND COLORS */
.stats-grid-brand {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.stat-card-brand {
    border-radius: 20px;
    padding: 30px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    color: white;
}

.stat-green {
    background: #185D30;
}

.stat-green-light {
    background: #1e7a3e;
}

.stat-icon-circle {
    font-size: 48px;
    background: rgba(255,255,255,0.2);
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.stat-label {
    margin: 0;
    font-size: 13px;
    opacity: 0.9;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 1px;
}

.stat-value {
    margin: 8px 0 0 0;
    font-size: 36px;
    font-weight: 900;
}

.stat-value-small {
    margin: 8px 0 0 0;
    font-size: 18px;
    font-weight: 700;
}

/* Filter Card - GREEN BACKGROUND */
.filter-card-brand {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 30px;
}

.filter-card-header-green {
    background: #185D30;
    padding: 20px 25px;
    color: white;
    border-bottom: 4px solid #CC232A;
}

.filter-card-header-green h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
}

.filter-card-body {
    padding: 25px;
}

.filter-grid-brand {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    align-items: end;
}

.form-group-brand {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group-brand label {
    font-weight: 700;
    color: #333;
    font-size: 14px;
}

.form-control-brand {
    padding: 12px 16px;
    border: 2px solid #ddd;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.form-control-brand:focus {
    outline: none;
    border-color: #185D30;
    box-shadow: 0 0 0 4px rgba(24, 93, 48, 0.1);
}

.filter-buttons {
    display: flex;
    gap: 12px;
}

.btn-brand {
    padding: 12px 24px;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    color: white;
}

.btn-brand:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.btn-red {
    background: #CC232A;
}

.btn-green {
    background: #185D30;
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

/* Modern Table */
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

.capitito-table-modern tbody td {
    padding: 20px 15px;
    vertical-align: middle;
}

/* Modal */
.modal-header-green {
    background: #185D30;
    padding: 20px 25px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 4px solid #CC232A;
}

.modal-header-green h3 {
    margin: 0;
    font-size: 20px;
}

.modal-close-brand {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    font-size: 28px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
}

.modal-close-brand:hover {
    background: rgba(255,255,255,0.3);
    transform: rotate(90deg);
}

.form-row-brand {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.modal-actions-brand {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

.modal-actions-brand .btn-brand {
    flex: 1;
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

    .filter-grid-brand {
        grid-template-columns: 1fr;
    }

    .filter-buttons {
        flex-direction: column;
    }

    .form-row-brand {
        grid-template-columns: 1fr;
    }
}
</style>
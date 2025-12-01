<?php
/**
 * Reconciliation History Page
 * 
 * @package Capitito_IMS
 */

if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$is_admin = current_user_can('manage_options');

get_header();
?>

<div class="capitito-ims-wrapper">
    <!-- Header -->
    <div class="capitito-ims-header">
        <div class="header-left">
            <h1>📋 Reconciliation History</h1>
            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 14px;">View All Reconciliation Records</p>
        </div>
        <div class="header-right">
            <div class="user-info">
                <span class="user-icon">👤</span>
                <span class="user-name"><?php echo esc_html($current_user->display_name); ?></span>
            </div>
            <div class="digital-clock">00:00:00</div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="card">
            <div class="card-body" style="background: linear-gradient(135deg, var(--primary-green), var(--primary-green-dark)); color: white; border-radius: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; opacity: 0.9; font-size: 14px;">Total Records</p>
                        <h2 id="totalRecords" style="margin: 8px 0 0 0; font-size: 36px; font-weight: 700;">0</h2>
                    </div>
                    <div style="font-size: 48px; opacity: 0.3;">📊</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body" style="background: linear-gradient(135deg, #28a745, #20c997); color: white; border-radius: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; opacity: 0.9; font-size: 14px;">Evidence Submitted</p>
                        <h2 id="evidenceSent" style="margin: 8px 0 0 0; font-size: 36px; font-weight: 700;">0</h2>
                    </div>
                    <div style="font-size: 48px; opacity: 0.3;">✅</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body" style="background: linear-gradient(135deg, #ffc107, #ff9800); color: #000; border-radius: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; opacity: 0.9; font-size: 14px;">Pending Evidence</p>
                        <h2 id="pendingEvidence" style="margin: 8px 0 0 0; font-size: 36px; font-weight: 700;">0</h2>
                    </div>
                    <div style="font-size: 48px; opacity: 0.3;">⚠️</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h3>🔍 Filters</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="filterDateFrom">Date From</label>
                    <input type="date" id="filterDateFrom" class="form-control">
                </div>
                <div class="form-group">
                    <label for="filterDateTo">Date To</label>
                    <input type="date" id="filterDateTo" class="form-control">
                </div>
                <div class="form-group">
                    <label for="filterStaff">Staff Name</label>
                    <input type="text" id="filterStaff" class="form-control" placeholder="Search by staff name...">
                </div>
                <div class="form-group">
                    <label for="filterEvidence">Evidence Status</label>
                    <select id="filterEvidence" class="form-control">
                        <option value="">All</option>
                        <option value="1">Evidence Submitted</option>
                        <option value="0">Pending Evidence</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" id="applyFiltersBtn" class="btn btn-primary">
                    Apply Filters
                </button>
                <button type="button" id="clearFiltersBtn" class="btn btn-secondary">
                    Clear Filters
                </button>
            </div>
        </div>
    </div>

    <!-- History Table Card -->
    <div class="card">
        <div class="card-header">
            <h3>Reconciliation Records</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="reconciliationHistoryTable" class="capitito-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reconciled By</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Created At</th>
                            <th>Evidence Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="reconciliationHistoryBody">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 60px;">
                                <div style="font-size: 48px; margin-bottom: 20px;">⏳</div>
                                <p style="color: #999; font-size: 18px;">Loading history...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div id="paginationSection" style="margin-top: 30px;"></div>

</div>

<!-- View Details Modal -->
<div id="viewDetailsModal" class="capitito-modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>📋 Reconciliation Details</h3>
            <span class="modal-close" data-modal="viewDetailsModal">&times;</span>
        </div>
        <div class="modal-body">
            <div id="viewDetailsContent"></div>
            <button type="button" class="modal-close btn btn-primary btn-lg" data-modal="viewDetailsModal" style="width: 100%; margin-top: 20px;">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Submit Evidence Modal (for pending records) -->
<div id="submitEvidenceHistoryModal" class="capitito-modal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: #000;">
            <h3 style="margin: 0; color: #000;">📱 Submit Evidence via WhatsApp</h3>
            <span class="modal-close" data-modal="submitEvidenceHistoryModal" style="color: #000; cursor: pointer; font-size: 28px;">&times;</span>
        </div>
        <div class="modal-body">
            <div style="text-align: center; padding: 40px 30px;">
                <div style="font-size: 100px; margin-bottom: 25px;">📱</div>
                <p style="font-size: 16px; margin-bottom: 8px; color: #666; font-weight: 600;">Reconciliation Date:</p>
                <p id="evidenceDateHistory" style="font-size: 28px; font-weight: 700; color: var(--primary-green); margin: 0 0 35px 0;"></p>
                
                <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 20px; border-radius: 12px; margin-bottom: 30px; text-align: left; border-left: 5px solid #ffc107;">
                    <p style="margin: 0 0 12px 0; font-size: 16px; color: #856404; font-weight: 700;">📸 Instructions:</p>
                    <ol style="margin: 0; padding-left: 20px; font-size: 14px; color: #856404; line-height: 1.9;">
                        <li><strong>Prepare</strong> photos/documents as evidence</li>
                        <li><strong>Click</strong> button below to open WhatsApp</li>
                        <li><strong>Attach</strong> evidence files in WhatsApp</li>
                        <li><strong>Send</strong> the message to complete</li>
                    </ol>
                </div>

                <div style="background: linear-gradient(135deg, #25D366 0%, #20b358 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 30px;">
                    <p style="margin: 0; font-size: 14px; font-weight: 600; opacity: 0.95;">📞 Sending to:</p>
                    <p style="margin: 8px 0 0 0; font-size: 24px; font-weight: 700;">09019099708</p>
                </div>

                <button type="button" id="openWhatsAppHistoryBtn" class="btn btn-lg" style="background: linear-gradient(135deg, #25D366 0%, #20b358 100%); color: white; width: 100%; margin-bottom: 15px; font-size: 18px; padding: 18px; border: none; border-radius: 12px; cursor: pointer; font-weight: 700;">
                    <span style="font-size: 28px; margin-right: 12px;">📱</span> Open WhatsApp & Submit
                </button>
                <button type="button" class="modal-close btn btn-secondary btn-lg" data-modal="submitEvidenceHistoryModal" style="width: 100%; padding: 14px;">Cancel</button>
            </div>
        </div>
    </div>
</div>

<style>
.status-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.5px;
    display: inline-block;
}

.status-complete {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.status-pending {
    background: linear-gradient(135deg, #ffc107, #ff9800);
    color: #000;
}

.evidence-sent {
    color: #28a745;
    font-weight: 600;
}

.evidence-pending {
    color: #ffc107;
    font-weight: 600;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
    margin: 2px;
}

.btn-danger {
    background: var(--accent-red);
    color: white;
}

.btn-danger:hover {
    background: #b01f20;
}
</style>

<?php get_footer(); ?>
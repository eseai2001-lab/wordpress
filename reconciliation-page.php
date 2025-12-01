<?php
/**
 * Reconciliation Calendar Page - One Month View
 * 
 * @package Capitito_IMS
 */

if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$is_admin = current_user_can('manage_options');

// Get current month and year from URL or default to current
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Validate month and year
if ($month < 1 || $month > 12) $month = intval(date('n'));
if ($year < 2020 || $year > 2030) $year = intval(date('Y'));

// Get reconciliation data for the current month
global $wpdb;
$table = $wpdb->prefix . 'capitito_reconciliation';

$reconciled_dates = $wpdb->get_results($wpdb->prepare(
    "SELECT reconciliation_date, evidence_sent, evidence_sent_at, reconciled_by, notes, created_at
     FROM $table 
     WHERE MONTH(reconciliation_date) = %d AND YEAR(reconciliation_date) = %d
     ORDER BY reconciliation_date ASC",
    $month,
    $year
), ARRAY_A);

// Create a map for quick lookup
$reconciled_map = array();
if (is_array($reconciled_dates)) {
    foreach ($reconciled_dates as $record) {
        $reconciled_map[$record['reconciliation_date']] = $record;
    }
}

// Calendar generation
$first_day = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = intval(date('t', $first_day));
$day_of_week = intval(date('w', $first_day));
$month_name = date('F Y', $first_day);
$today = date('Y-m-d');

get_header();
?>

<div class="capitito-ims-wrapper">
    <!-- Header -->
    <div class="capitito-ims-header">
        <div class="header-left">
            <h1>📅 Reconciliation Calendar</h1>
            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 14px;">Daily Reconciliation Tracking System</p>
        </div>
        <div class="header-right">
            <div class="user-info">
                <span class="user-icon">👤</span>
                <span class="user-name"><?php echo esc_html($current_user->display_name); ?></span>
            </div>
            <div class="digital-clock">00:00:00</div>
        </div>
    </div>

    <!-- Navigation Card -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header" style="background: linear-gradient(135deg, #165E30 0%, #0d3d1f 100%); color: white; padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <h2 style="margin: 0; color: white; font-size: 24px; font-weight: 700;">
                    <?php echo esc_html($month_name); ?>
                </h2>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <button type="button" id="todayBtn" class="btn" style="background: white; color: #165E30; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        📍 Today
                    </button>
                    <select id="filterMonth" class="form-control" style="padding: 10px 15px; border: 2px solid white; border-radius: 8px; font-size: 14px; font-weight: 600; min-width: 140px;">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php selected($m, $month); ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <select id="filterYear" class="form-control" style="padding: 10px 15px; border: 2px solid white; border-radius: 8px; font-size: 14px; font-weight: 600; min-width: 100px;">
                        <?php for ($y = 2024; $y <= 2030; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php selected($y, $year); ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <button type="button" id="applyFilterBtn" class="btn btn-primary" style="padding: 10px 20px; border-radius: 8px; font-weight: 600;">
                        Apply
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Legend -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px; padding: 20px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 28px; height: 28px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 6px; flex-shrink: 0; box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);"></div>
                    <span style="font-size: 14px; font-weight: 700; color: #155724;">✅ Evidence Sent</span>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 28px; height: 28px; background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); border-radius: 6px; flex-shrink: 0; box-shadow: 0 2px 4px rgba(255, 193, 7, 0.3);"></div>
                    <span style="font-size: 14px; font-weight: 700; color: #856404;">⚠️ Pending Evidence</span>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 28px; height: 28px; background: white; border-radius: 6px; border: 3px solid #dee2e6; flex-shrink: 0;"></div>
                    <span style="font-size: 14px; font-weight: 700; color: #6c757d;">⬜ Not Reconciled</span>
                </div>
                <?php if (!$is_admin): ?>
                <div style="display: flex; align-items: center; gap: 12px; color: #D02223;">
                    <span style="font-size: 24px;">🔒</span>
                    <span style="font-size: 14px; font-weight: 700;">Reconciliation: Admin Only</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Calendar Grid -->
            <div class="reconciliation-calendar">
                <!-- Day Names Header -->
                <div class="calendar-header">
                    <?php foreach(['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'] as $day_name): ?>
                        <div class="calendar-day-name"><?php echo $day_name; ?></div>
                    <?php endforeach; ?>
                </div>

                <!-- Calendar Days -->
                <div class="calendar-body">
                    <?php
                    // Empty cells before first day of month
                    for ($i = 0; $i < $day_of_week; $i++) {
                        echo '<div class="calendar-day empty"></div>';
                    }

                    // Days of the month
                    for ($day = 1; $day <= $days_in_month; $day++) {
                        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $is_reconciled = isset($reconciled_map[$date]);
                        $evidence_sent = $is_reconciled && $reconciled_map[$date]['evidence_sent'] == 1;
                        $is_today = ($date === $today);
                        $is_future = ($date > $today);

                        // Determine class
                        $class = 'calendar-day';
                        if ($is_today) $class .= ' today';
                        if ($is_reconciled && $evidence_sent) $class .= ' status-complete';
                        if ($is_reconciled && !$evidence_sent) $class .= ' status-pending';

                        echo '<div class="' . esc_attr($class) . '" data-date="' . esc_attr($date) . '">';
                        echo '<div class="day-number">' . $day . '</div>';

                        if ($is_reconciled) {
                            // Date is reconciled
                            $reconciled_by = isset($reconciled_map[$date]['reconciled_by']) ? esc_html($reconciled_map[$date]['reconciled_by']) : 'Unknown';
                            
                            if ($evidence_sent) {
                                // Evidence sent - Fully complete and locked
                                echo '<div class="status-badge badge-complete">✅ LOCKED</div>';
                                echo '<div class="day-info">';
                                echo '<div class="info-line"><strong>✓ By:</strong> ' . $reconciled_by . '</div>';
                                
                                if (!empty($reconciled_map[$date]['evidence_sent_at'])) {
                                    try {
                                        $evidence_time = new DateTime($reconciled_map[$date]['evidence_sent_at']);
                                        echo '<div class="info-line"><small>Evidence: ' . $evidence_time->format('M j, g:i A') . '</small></div>';
                                    } catch (Exception $e) {
                                        echo '<div class="info-line"><small>Evidence sent</small></div>';
                                    }
                                }
                                echo '</div>';
                                
                                echo '<button type="button" class="calendar-btn btn-view" data-date="' . esc_attr($date) . '">👁️ View Details</button>';
                                
                            } else {
                                // Reconciled but pending evidence
                                echo '<div class="status-badge badge-pending">📋 RECONCILED</div>';
                                echo '<div class="day-info">';
                                echo '<div class="info-line"><strong>✓ By:</strong> ' . $reconciled_by . '</div>';
                                echo '<div class="info-line warning">⚠️ Pending Evidence</div>';
                                echo '</div>';
                                
                                echo '<button type="button" class="calendar-btn btn-submit-evidence" data-date="' . esc_attr($date) . '">📱 Submit Evidence</button>';
                            }
                            
                        } else {
                            // Date not reconciled yet
                            if ($is_future) {
                                echo '<div class="day-info future"><small style="color: #adb5bd; font-style: italic;">Future Date</small></div>';
                            } else {
                                if ($is_admin) {
                                    echo '<div class="day-info"><small>Not reconciled</small></div>';
                                    echo '<button type="button" class="calendar-btn btn-reconcile" data-date="' . esc_attr($date) . '">✓ Reconcile</button>';
                                } else {
                                    echo '<div class="day-info locked">';
                                    echo '<div class="info-line error"><strong>🔒 Admin Only</strong></div>';
                                    echo '<div class="info-line"><small>Not reconciled</small></div>';
                                    echo '</div>';
                                }
                            }
                        }

                        echo '</div>';
                    }

                    // Fill remaining cells to complete the grid
                    $total_cells = $day_of_week + $days_in_month;
                    $remaining = (7 - ($total_cells % 7)) % 7;
                    for ($i = 0; $i < $remaining; $i++) {
                        echo '<div class="calendar-day empty"></div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODALS -->

<!-- Reconcile Modal (Admin Only) -->
<?php if ($is_admin): ?>
<div id="reconcileModal" class="capitito-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>✓ Reconcile Date</h3>
            <span class="modal-close" data-modal="reconcileModal">&times;</span>
        </div>
        <div class="modal-body">
            <form id="reconcileForm">
                <input type="hidden" id="reconcileDate">
                <div class="form-group">
                    <label style="font-weight: 600; color: #666; margin-bottom: 5px; display: block;">Date</label>
                    <p id="reconcileDateDisplay" style="font-size: 22px; font-weight: 700; color: var(--primary-green); margin: 0 0 20px 0;"></p>
                </div>
                <div class="form-group">
                    <label for="reconcileNotes" style="font-weight: 600; color: #666; margin-bottom: 8px; display: block;">Notes (Optional)</label>
                    <textarea id="reconcileNotes" class="form-control" rows="5" placeholder="Add any notes about this reconciliation..." style="resize: vertical; font-size: 14px;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 20px; font-size: 16px; padding: 14px;">
                    <span class="btn-icon">✓</span> Confirm Reconciliation
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Submit Evidence Modal (All Users) -->
<div id="submitEvidenceModal" class="capitito-modal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: #000;">
            <h3 style="margin: 0; color: #000;">📱 Submit Evidence via WhatsApp</h3>
            <span class="modal-close" data-modal="submitEvidenceModal" style="color: #000; cursor: pointer; font-size: 28px;">&times;</span>
        </div>
        <div class="modal-body">
            <div style="text-align: center; padding: 40px 30px;">
                <div style="font-size: 100px; margin-bottom: 25px;">📱</div>
                <p style="font-size: 16px; margin-bottom: 8px; color: #666; font-weight: 600;">Reconciliation Date:</p>
                <p id="evidenceDate" style="font-size: 28px; font-weight: 700; color: var(--primary-green); margin: 0 0 35px 0;"></p>
                
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

                <button type="button" id="openWhatsAppBtn" class="btn btn-lg" style="background: linear-gradient(135deg, #25D366 0%, #20b358 100%); color: white; width: 100%; margin-bottom: 15px; font-size: 18px; padding: 18px; border: none; border-radius: 12px; cursor: pointer; font-weight: 700;">
                    <span style="font-size: 28px; margin-right: 12px;">📱</span> Open WhatsApp & Submit
                </button>
                <button type="button" class="modal-close btn btn-secondary btn-lg" data-modal="submitEvidenceModal" style="width: 100%; padding: 14px;">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- View Evidence Modal -->
<div id="viewEvidenceModal" class="capitito-modal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <h3 style="color: white; margin: 0;">✅ Evidence Submitted</h3>
            <span class="modal-close" data-modal="viewEvidenceModal" style="color: white; cursor: pointer; font-size: 28px;">&times;</span>
        </div>
        <div class="modal-body">
            <div style="text-align: center; padding: 40px 30px;">
                <div style="font-size: 100px; margin-bottom: 25px;">✅</div>
                <p style="font-size: 16px; margin-bottom: 8px; color: #666; font-weight: 600;">Reconciliation Date:</p>
                <p id="viewEvidenceDate" style="font-size: 28px; font-weight: 700; color: #28a745; margin: 0 0 35px 0;"></p>
                
                <div style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); padding: 25px; border-radius: 12px; margin-bottom: 25px; border-left: 5px solid #28a745;">
                    <p style="margin: 0 0 10px 0; font-size: 18px; color: #155724; font-weight: 700;">✓ Evidence Successfully Submitted</p>
                    <p id="evidenceSubmittedAt" style="margin: 0; font-size: 15px; color: #155724;"></p>
                </div>

                <div id="viewNotes" style="background: linear-gradient(135deg, #e7f3ff 0%, #cfe2ff 100%); padding: 20px; border-radius: 12px; margin-bottom: 25px; text-align: left; border-left: 5px solid #2196F3; display: none;">
                    <p style="margin: 0 0 8px 0; font-size: 14px; color: #0c5460; font-weight: 700;">📝 Notes:</p>
                    <p id="notesContent" style="margin: 0; font-size: 14px; color: #0c5460; line-height: 1.7; white-space: pre-wrap;"></p>
                </div>

                <button type="button" class="modal-close btn btn-primary btn-lg" data-modal="viewEvidenceModal" style="width: 100%; font-size: 16px; padding: 14px;">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Reconciliation Calendar Styles */
.reconciliation-calendar {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-green-dark) 100%);
    color: white;
}

.calendar-day-name {
    padding: 20px 12px;
    text-align: center;
    font-weight: 700;
    font-size: 13px;
    letter-spacing: 1px;
}

.calendar-body {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    background: #dee2e6;
    padding: 4px;
}

.calendar-day {
    background: white;
    min-height: 180px;
    padding: 16px;
    position: relative;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    border-radius: 8px;
}

.calendar-day.empty {
    background: #f8f9fa;
}

.calendar-day.today {
    border: 4px solid var(--accent-red);
    box-shadow: 0 0 20px rgba(208, 34, 35, 0.35);
    padding: 12px;
}

.calendar-day.status-complete {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border-left: 6px solid #28a745;
    padding-left: 10px;
}

.calendar-day.status-pending {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border-left: 6px solid #ffc107;
    padding-left: 10px;
}

.day-number {
    font-size: 26px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 14px;
    line-height: 1;
}

.status-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 7px 14px;
    border-radius: 8px;
    margin-bottom: 14px;
    text-align: center;
    letter-spacing: 0.6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.badge-complete {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.badge-pending {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: #000;
}

.day-info {
    flex-grow: 1;
    font-size: 12px;
    line-height: 1.8;
    color: #495057;
    margin-bottom: 12px;
}

.day-info.future {
    text-align: center;
    padding: 30px 0;
}

.day-info.locked {
    text-align: center;
    padding: 10px 0;
}

.info-line {
    margin-bottom: 8px;
    word-wrap: break-word;
}

.info-line.warning {
    color: #856404;
    font-weight: 700;
    font-size: 11px;
}

.info-line.error {
    color: var(--accent-red);
    font-weight: 700;
    font-size: 12px;
}

.calendar-btn {
    width: calc(100% - 8px);
    margin: 0 auto;
    padding: 10px 8px;
    border: none;
    border-radius: 6px;
    color: white;
    font-weight: 700;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: auto;
    text-align: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    display: block;
}

.btn-reconcile {
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-green-dark) 100%);
}

.btn-reconcile:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(22, 94, 48, 0.4);
}

.btn-submit-evidence {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: #000;
}

.btn-submit-evidence:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(255, 193, 7, 0.5);
}

.btn-view {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.btn-view:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(40, 167, 69, 0.5);
}

/* Responsive Design */
@media (max-width: 1400px) {
    .calendar-day {
        min-height: 170px;
        padding: 14px;
    }
    .day-number {
        font-size: 24px;
        margin-bottom: 12px;
    }
    .calendar-btn {
        font-size: 11px;
        padding: 9px 7px;
        width: calc(100% - 6px);
    }
}

@media (max-width: 1200px) {
    .calendar-day {
        min-height: 160px;
        padding: 12px;
    }
    .day-number {
        font-size: 22px;
        margin-bottom: 10px;
    }
    .calendar-btn {
        font-size: 10px;
        padding: 8px 6px;
        width: calc(100% - 6px);
    }
    .status-badge {
        font-size: 10px;
        padding: 6px 12px;
        margin-bottom: 12px;
    }
    .day-info {
        font-size: 11px;
    }
}

@media (max-width: 992px) {
    .calendar-day {
        min-height: 145px;
        padding: 10px;
    }
    .day-number {
        font-size: 20px;
        margin-bottom: 8px;
    }
    .calendar-btn {
        font-size: 9px;
        padding: 7px 5px;
        width: calc(100% - 4px);
    }
    .status-badge {
        font-size: 9px;
        padding: 5px 10px;
        margin-bottom: 10px;
    }
    .day-info {
        font-size: 10px;
        margin-bottom: 10px;
    }
}

@media (max-width: 768px) {
    .calendar-day {
        min-height: 130px;
        padding: 8px;
    }
    .calendar-day-name {
        padding: 16px 8px;
        font-size: 11px;
    }
    .day-number {
        font-size: 18px;
        margin-bottom: 6px;
    }
    .calendar-btn {
        font-size: 8px;
        padding: 6px 4px;
        width: calc(100% - 4px);
    }
    .status-badge {
        font-size: 8px;
        padding: 4px 8px;
        margin-bottom: 8px;
    }
    .day-info {
        font-size: 9px;
        margin-bottom: 8px;
    }
}

@media (max-width: 576px) {
    .calendar-day {
        min-height: 115px;
        padding: 6px;
    }
    .calendar-day-name {
        padding: 14px 6px;
        font-size: 10px;
    }
    .day-number {
        font-size: 16px;
        margin-bottom: 5px;
    }
    .calendar-btn {
        font-size: 7px;
        padding: 5px 3px;
        width: calc(100% - 4px);
    }
    .status-badge {
        font-size: 7px;
        padding: 3px 6px;
        margin-bottom: 6px;
    }
    .day-info {
        font-size: 8px;
        line-height: 1.5;
        margin-bottom: 6px;
    }
}
</style>

<?php get_footer(); ?>
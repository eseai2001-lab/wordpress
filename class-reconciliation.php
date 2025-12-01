<?php
/**
 * Reconciliation Management Class
 * 
 * @package Capitito_IMS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class Capitito_IMS_Reconciliation {
    
    /**
     * Submit reconciliation (Admin Only)
     */
    public static function ajax_submit_reconciliation() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');
        
        // Only admin can reconcile
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => '⛔ Only administrators can perform reconciliation'
            ));
            return;
        }
        
        $date = isset($_POST['reconciliation_date']) ? sanitize_text_field($_POST['reconciliation_date']) : '';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';
        
        // Validate date
        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            wp_send_json_error(array('message' => 'Invalid date format'));
            return;
        }
        
        // Cannot reconcile future dates
        if ($date > current_time('Y-m-d')) {
            wp_send_json_error(array('message' => 'Cannot reconcile future dates'));
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'capitito_reconciliation';
        
        // Check if already reconciled
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE reconciliation_date = %s",
            $date
        ));
        
        if ($exists) {
            wp_send_json_error(array(
                'message' => 'This date has already been reconciled and cannot be changed'
            ));
            return;
        }
        
        // Insert reconciliation record
        $result = $wpdb->insert(
            $table,
            array(
                'reconciliation_date' => $date,
                'reconciled_by' => wp_get_current_user()->display_name,
                'notes' => $notes,
                'evidence_sent' => 0,
                'evidence_sent_at' => null,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        if ($result) {
            wp_send_json_success(array(
                'message' => '✓ Reconciliation completed! Date is now locked. Staff can submit evidence.',
                'record_id' => $wpdb->insert_id,
                'date' => $date
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to save reconciliation. Please try again.'
            ));
        }
    }
    
    /**
     * Mark evidence as sent (All Staff)
     */
    public static function ajax_mark_evidence_sent() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');
        
        $date = isset($_POST['reconciliation_date']) ? sanitize_text_field($_POST['reconciliation_date']) : '';
        
        if (empty($date)) {
            wp_send_json_error(array('message' => 'Date is required'));
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'capitito_reconciliation';
        
        // Check if reconciliation exists
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE reconciliation_date = %s",
            $date
        ), ARRAY_A);
        
        if (!$record) {
            wp_send_json_error(array(
                'message' => 'This date has not been reconciled yet'
            ));
            return;
        }
        
        // Check if evidence already submitted
        if ($record['evidence_sent'] == 1) {
            wp_send_json_error(array(
                'message' => 'Evidence has already been submitted for this date'
            ));
            return;
        }
        
        // Mark evidence as sent
        $result = $wpdb->update(
            $table,
            array(
                'evidence_sent' => 1,
                'evidence_sent_at' => current_time('mysql')
            ),
            array('reconciliation_date' => $date),
            array('%d', '%s'),
            array('%s')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => '✓ Evidence submitted successfully! Date is now locked.'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to submit evidence'
            ));
        }
    }
    
    /**
     * Get reconciliation record by date
     */
    public static function ajax_get_reconciliation_record_by_date() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');
        
        $date = isset($_POST['reconciliation_date']) ? sanitize_text_field($_POST['reconciliation_date']) : '';
        
        if (empty($date)) {
            wp_send_json_error(array('message' => 'Date is required'));
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'capitito_reconciliation';
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE reconciliation_date = %s",
            $date
        ), ARRAY_A);
        
        if ($record) {
            wp_send_json_success($record);
        } else {
            wp_send_json_error(array('message' => 'Record not found'));
        }
    }
    
    /**
     * Get reconciliation history with pagination and filters
     */
    public static function ajax_get_reconciliation_history() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $offset = ($page - 1) * $per_page;
        
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        
        global $wpdb;
        $table = $wpdb->prefix . 'capitito_reconciliation';
        
        $where = array('1=1');
        $params = array();
        
        // Date filters
        if (!empty($filters['date_from'])) {
            $where[] = 'reconciliation_date >= %s';
            $params[] = sanitize_text_field($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'reconciliation_date <= %s';
            $params[] = sanitize_text_field($filters['date_to']);
        }
        
        // Staff filter
        if (!empty($filters['staff'])) {
            $where[] = 'reconciled_by LIKE %s';
            $params[] = '%' . $wpdb->esc_like(sanitize_text_field($filters['staff'])) . '%';
        }
        
        // Evidence filter
        if (isset($filters['evidence']) && $filters['evidence'] !== '') {
            $where[] = 'evidence_sent = %d';
            $params[] = intval($filters['evidence']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get total count
        if (!empty($params)) {
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE $where_clause",
                $params
            ));
        } else {
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where_clause");
        }
        
        // Get paginated records
        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY reconciliation_date DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $history = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        
        // Get stats
        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'evidence_sent' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE evidence_sent = 1"),
            'pending_evidence' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE evidence_sent = 0")
        );
        
        wp_send_json_success(array(
            'history' => $history,
            'total' => intval($total),
            'stats' => $stats
        ));
    }
    
    /**
     * Delete reconciliation record (Admin only)
     */
    public static function ajax_delete_reconciliation() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Admin access required'));
            return;
        }
        
        $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;
        
        if ($record_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid record ID'));
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'capitito_reconciliation';
        
        $result = $wpdb->delete(
            $table,
            array('id' => $record_id),
            array('%d')
        );
        
        if ($result) {
            wp_send_json_success(array('message' => 'Reconciliation record deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete record'));
        }
    }
    
    /**
     * Get reconciliation record by ID
     */
    public static function ajax_get_reconciliation_record() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');
        
        $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;
        
        if ($record_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid record ID'));
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'capitito_reconciliation';
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $record_id
        ), ARRAY_A);
        
        if ($record) {
            wp_send_json_success($record);
        } else {
            wp_send_json_error(array('message' => 'Record not found'));
        }
    }
    
    /**
     * Render reconciliation calendar page
     */
    public static function render_reconciliation() {
        if (!is_user_logged_in()) {
            return '<div style="text-align: center; padding: 40px;">
                        <p style="font-size: 18px; color: #D02223;">⚠️ Please log in to access reconciliation.</p>
                        <a href="' . wp_login_url(get_permalink()) . '" class="btn btn-primary">Login</a>
                    </div>';
        }
        
        ob_start();
        include CAPITITO_IMS_PLUGIN_DIR . 'templates/reconciliation-page.php';
        return ob_get_clean();
    }
    
    /**
     * Render reconciliation history page
     */
    public static function render_reconciliation_history() {
        if (!is_user_logged_in()) {
            return '<div style="text-align: center; padding: 40px;">
                        <p style="font-size: 18px; color: #D02223;">⚠️ Please log in to access reconciliation history.</p>
                        <a href="' . wp_login_url(get_permalink()) . '" class="btn btn-primary">Login</a>
                    </div>';
        }
        
        ob_start();
        include CAPITITO_IMS_PLUGIN_DIR . 'templates/reconciliation-history-page.php';
        return ob_get_clean();
    }
}
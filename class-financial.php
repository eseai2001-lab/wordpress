<?php
/**
 * Financial management class - Discount aggregation with fallbacks + active-table detection
 *
 * @package Capitito_IMS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Capitito_IMS_Financial {

    public static function render_financial_summary() {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __( 'Please log in to access this page.', 'capitito-ims' ) . '</p>';
        }
        ob_start();
        include CAPITITO_IMS_PLUGIN_DIR . 'templates/financial-summary.php';
        return ob_get_clean();
    }

    public static function render_financial_history() {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __( 'Please log in to access this page.', 'capitito-ims' ) . '</p>';
        }
        ob_start();
        include CAPITITO_IMS_PLUGIN_DIR . 'templates/financial-history.php';
        return ob_get_clean();
    }

    // ---------- Active table detection (keeps reads/writes aligned) ----------
    private static function table_exists($table) {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
            DB_NAME, $table
        ));
        return (int)$exists > 0;
    }
    private static function column_exists($table, $column) {
        global $wpdb;
        $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
        return !empty($col);
    }
    private static function resolve_orders_tables() {
        global $wpdb;
        $main_orders = $wpdb->prefix . 'capitito_orders';
        $ims_orders  = $wpdb->prefix . 'capitito_ims_orders';
        $main_items  = $wpdb->prefix . 'capitito_order_items';
        $ims_items   = $wpdb->prefix . 'capitito_ims_order_items';

        $main_exists = self::table_exists($main_orders);
        $ims_exists  = self::table_exists($ims_orders);

        if ($main_exists && !$ims_exists) {
            return [$main_orders, self::table_exists($main_items) ? $main_items : $ims_items];
        }
        if ($ims_exists && !$main_exists) {
            return [$ims_orders, self::table_exists($ims_items) ? $ims_items : $main_items];
        }
        if (!$main_exists && !$ims_exists) {
            return [$main_orders, $main_items];
        }

        $main_latest = $wpdb->get_var("SELECT MAX(created_at) FROM {$main_orders}");
        $ims_latest  = $wpdb->get_var("SELECT MAX(created_at) FROM {$ims_orders}");

        $pick_orders = $main_latest && $ims_latest
            ? ((strtotime($main_latest) >= strtotime($ims_latest)) ? $main_orders : $ims_orders)
            : ($main_latest ? $main_orders : $ims_orders);

        $pick_items = ($pick_orders === $main_orders)
            ? (self::table_exists($main_items) ? $main_items : $ims_items)
            : (self::table_exists($ims_items)  ? $ims_items  : $main_items);

        return [$pick_orders, $pick_items];
    }

    // ---------- Discounts ----------
    public static function get_daily_discount_total($dateYmd = null) {
        global $wpdb;
        $dateYmd = $dateYmd ?: current_time('Y-m-d');

        list($orders_table, $order_items_table) = self::resolve_orders_tables();

        // If discount_amount exists, sum it. Otherwise compute (unit_price*quantity - total)
        if ( self::column_exists($order_items_table, 'discount_amount') ) {
            $sql = $wpdb->prepare("
                SELECT COALESCE(SUM(oi.discount_amount), 0)
                FROM {$orders_table} o
                JOIN {$order_items_table} oi ON oi.order_id = o.id
                WHERE DATE(o.created_at) = %s
            ", $dateYmd);
        } else {
            $sql = $wpdb->prepare("
                SELECT COALESCE(SUM(GREATEST((oi.unit_price*oi.quantity - oi.total),0)), 0)
                FROM {$orders_table} o
                JOIN {$order_items_table} oi ON oi.order_id = o.id
                WHERE DATE(o.created_at) = %s
            ", $dateYmd);
        }

        $sum = $wpdb->get_var($sql);
        return floatval($sum ?: 0);
    }

    public static function ajax_get_daily_discount() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : current_time('Y-m-d');
        wp_send_json_success(['date'=>$date, 'discount_total'=> self::get_daily_discount_total($date)]);
    }

    public static function ajax_get_daily_discounts() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');
        global $wpdb;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : current_time('Y-m-d');

        list($orders_table, $order_items_table) = self::resolve_orders_tables();

        $has_columns = self::column_exists($order_items_table, 'discount_amount') && self::column_exists($order_items_table, 'discount_percent');

        if ($has_columns) {
            $rows = $wpdb->get_results($wpdb->prepare("
                SELECT oi.item_id,
                       COALESCE(oi.item_name,'') AS item_name,
                       oi.unit_price AS price,
                       oi.quantity,
                       oi.discount_percent,
                       oi.discount_amount,
                       oi.total AS line_total
                FROM {$orders_table} o
                JOIN {$order_items_table} oi ON oi.order_id = o.id
                WHERE DATE(o.created_at) = %s
                  AND oi.discount_amount > 0
                ORDER BY item_name ASC
            ", $date), ARRAY_A);
        } else {
            // Compute discount values on the fly
            $rows = $wpdb->get_results($wpdb->prepare("
                SELECT oi.item_id,
                       COALESCE(oi.item_name,'') AS item_name,
                       oi.unit_price AS price,
                       oi.quantity,
                       -- compute discount amount and percent
                       GREATEST((oi.unit_price*oi.quantity - oi.total), 0) AS discount_amount,
                       CASE
                         WHEN (oi.unit_price*oi.quantity) > 0
                         THEN ROUND(100 * GREATEST((oi.unit_price*oi.quantity - oi.total),0) / (oi.unit_price*oi.quantity), 2)
                         ELSE 0
                       END AS discount_percent,
                       oi.total AS line_total
                FROM {$orders_table} o
                JOIN {$order_items_table} oi ON oi.order_id = o.id
                WHERE DATE(o.created_at) = %s
                  AND GREATEST((oi.unit_price*oi.quantity - oi.total), 0) > 0
                ORDER BY item_name ASC
            ", $date), ARRAY_A);
        }

        $total = 0;
        foreach ($rows as $r) $total += floatval($r['discount_amount']);

        wp_send_json_success([
            'date' => $date,
            'items'=> $rows,
            'discount_total' => $total
        ]);
    }

    /**
     * Get today's financial summary (auto-calculated from orders)
     */
    public static function get_todays_summary() {
        global $wpdb;

        $today = current_time('Y-m-d');
        list($orders_table,) = self::resolve_orders_tables();

        // prefer non-ims finances when exists
        $financial_table = $wpdb->prefix . 'capitito_financial_summary';
        if (!self::table_exists($financial_table)) {
            $financial_table = $wpdb->prefix . 'capitito_ims_financial_summary';
        }

        // Current financial row
        $financial = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$financial_table} WHERE summary_date = %s",
            $today
        ));

        // Calculate totals from today's orders
        $total_sales = $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(grand_total), 0) FROM {$orders_table} WHERE DATE(created_at) = %s",
            $today
        ) );
        $total_sales = $total_sales ? floatval($total_sales) : 0;

        // Channel totals (decode split if provided)
        $orders = $wpdb->get_results( $wpdb->prepare(
            "SELECT payment_method, payment_breakdown, grand_total FROM {$orders_table} WHERE DATE(created_at) = %s",
            $today
        ) );

        $cash_total = 0; $card_total = 0; $transfer_total = 0;
        foreach ($orders as $order) {
            $grand_total = floatval($order->grand_total);
            $method = strtolower(trim($order->payment_method));
            $breakdown = null;
            if (!empty($order->payment_breakdown) && $order->payment_breakdown !== 'null' && $order->payment_breakdown !== '[]') {
                $breakdown = json_decode($order->payment_breakdown, true);
            }
            if (is_array($breakdown) && (!empty($breakdown['cash']) || !empty($breakdown['card']) || !empty($breakdown['transfer']))) {
                $cash_total     += isset($breakdown['cash']) ? floatval($breakdown['cash']) : 0;
                $card_total     += isset($breakdown['card']) ? floatval($breakdown['card']) : 0;
                $transfer_total += isset($breakdown['transfer']) ? floatval($breakdown['transfer']) : 0;
            } else {
                if ($method === 'cash')     $cash_total += $grand_total;
                if ($method === 'card')     $card_total += $grand_total;
                if ($method === 'transfer') $transfer_total += $grand_total;
            }
        }

        // Discount total for today (robust)
        $discount_total = self::get_daily_discount_total($today);

        // Yesterday's cash_left for old cash
        $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
        $yesterday_data = $wpdb->get_row( $wpdb->prepare(
            "SELECT cash_left FROM {$financial_table} WHERE summary_date = %s AND is_submitted = 1",
            $yesterday
        ));
        $old_cash = $yesterday_data ? floatval($yesterday_data->cash_left) : 0;

        // Expenses & Paid-to-bank from row (if exists)
        $expenses     = $financial ? floatval($financial->expenses)     : 0;
        $paid_to_bank = $financial ? floatval($financial->paid_to_bank) : 0;

        $cash_left = $total_sales - $transfer_total - $card_total - $expenses + $old_cash - $paid_to_bank;

        if (!$financial) {
            $wpdb->insert($financial_table, array(
                'summary_date'  => $today,
                'total_sales'   => $total_sales,
                'cash'          => $cash_total,
                'card'          => $card_total,
                'transfer'      => $transfer_total,
                'discount_total'=> self::column_exists($financial_table, 'discount_total') ? $discount_total : 0,
                'expenses'      => 0,
                'expense_remark'=> '',
                'old_cash'      => $old_cash,
                'paid_to_bank'  => 0,
                'cash_left'     => $cash_left,
                'submitted_by'  => 'System',
                'submitted_by_id'=> 0,
                'is_submitted'  => 0,
            ), array('%s','%f','%f','%f','%f','%f','%f','%s','%f','%f','%f','%s','%d','%d'));

            $financial = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$financial_table} WHERE summary_date = %s",
                $today
            ));
        } else {
            $update = array(
                'total_sales'   => $total_sales,
                'cash'          => $cash_total,
                'card'          => $card_total,
                'transfer'      => $transfer_total,
                'cash_left'     => $cash_left,
            );
            $update_fmt = array('%f','%f','%f','%f','%f');
            if (self::column_exists($financial_table, 'discount_total')) {
                $update['discount_total'] = $discount_total;
                $update_fmt = array('%f','%f','%f','%f','%f','%f');
            }

            $wpdb->update($financial_table, $update, array('summary_date'=>$today),
                $update_fmt, array('%s')
            );

            $financial = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$financial_table} WHERE summary_date = %s",
                $today
            ));
        }

        if ($financial && !isset($financial->discount_total)) {
            $financial->discount_total = $discount_total;
        }

        return $financial;
    }

    public static function ajax_submit_financial() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'capitito-ims' ) ) );
        }

        global $wpdb;

        $user  = wp_get_current_user();
        $today = current_time( 'Y-m-d' );

        $financial_table = $wpdb->prefix . 'capitito_financial_summary';
        if (!self::table_exists($financial_table)) {
            $financial_table = $wpdb->prefix . 'capitito_ims_financial_summary';
        }

        $summary = self::get_todays_summary();

        $expenses     = isset($_POST['expenses']) && $_POST['expenses'] !== '' ? floatval($_POST['expenses']) : 0;
        $paid_to_bank = isset($_POST['paid_to_bank']) && $_POST['paid_to_bank'] !== '' ? floatval($_POST['paid_to_bank']) : 0;

        if ($expenses < 0)     wp_send_json_error(['message'=>'❌ Expenses cannot be negative!']);
        if ($paid_to_bank < 0) wp_send_json_error(['message'=>'❌ Paid to Bank cannot be negative!']);

        $expense_remark = isset($_POST['expense_remark']) ? sanitize_textarea_field($_POST['expense_remark']) : '';

        // Compute discount total for the day at submission time
        $discount_total = self::get_daily_discount_total($today);

        $data = array(
            'total_sales'   => floatval($summary->total_sales),
            'cash'          => floatval($summary->cash),
            'card'          => floatval($summary->card),
            'transfer'      => floatval($summary->transfer),
            'expenses'      => $expenses,
            'expense_remark'=> $expense_remark,
            'old_cash'      => floatval($summary->old_cash),
            'paid_to_bank'  => $paid_to_bank,
            'submitted_by'  => $user->display_name,
            'submitted_by_id'=> $user->ID,
            'is_submitted'  => 1,
        );
        $fmt = array('%f','%f','%f','%f','%f','%s','%f','%f','%s','%d');

        if (self::column_exists($financial_table, 'discount_total')) {
            $data['discount_total'] = $discount_total;
            $fmt = array('%f','%f','%f','%f','%f','%s','%f','%f','%s','%d','%f');
        }

        $cash_left = $data['total_sales'] - $data['transfer'] - $data['card'] - $data['expenses'] + $data['old_cash'] - $data['paid_to_bank'];
        if ($cash_left < 0) {
            wp_send_json_error(['message' => '❌ Cash Left cannot be negative! Current: ₦' . number_format($cash_left, 2)]);
        }
        $data['cash_left'] = $cash_left;
        $fmt[] = '%f';

        $updated = $wpdb->update(
            $financial_table,
            $data,
            array('summary_date' => $today),
            $fmt,
            array('%s')
        );

        if ($updated === false) {
            wp_send_json_error(['message'=>__('Failed to submit', 'capitito-ims')]);
        }

        wp_send_json_success([
            'message' => '✓ Financial summary submitted successfully',
            'cash_left' => $cash_left,
            'discount_total' => $discount_total
        ]);
    }

    public static function ajax_get_financial() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }
        global $wpdb;

        $financial_id    = intval($_POST['financial_id']);
        $financial_table = $wpdb->prefix . 'capitito_financial_summary';
        if (!self::table_exists($financial_table)) {
            $financial_table = $wpdb->prefix . 'capitito_ims_financial_summary';
        }

        $financial = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$financial_table} WHERE id = %d",
            $financial_id
        ), ARRAY_A );

        if ( ! $financial ) {
            wp_send_json_error( array( 'message' => 'Record not found' ) );
        }

        wp_send_json_success( $financial );
    }

    public static function ajax_update_financial() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_capitito_ims_financial' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }
        global $wpdb;

        $financial_id    = intval($_POST['financial_id']);
        $financial_table = $wpdb->prefix . 'capitito_financial_summary';
        if (!self::table_exists($financial_table)) {
            $financial_table = $wpdb->prefix . 'capitito_ims_financial_summary';
        }

        $total_sales = floatval($_POST['total_sales']);
        $card        = floatval($_POST['card']);
        $cash        = floatval($_POST['cash']);
        $transfer    = floatval($_POST['transfer']);
        $expenses    = floatval($_POST['expenses']);
        $old_cash    = floatval($_POST['old_cash']);
        $paid_to_bank= floatval($_POST['paid_to_bank']);

        if ($total_sales < 0 || $card < 0 || $cash < 0 || $transfer < 0 || $expenses < 0 || $old_cash < 0 || $paid_to_bank < 0) {
            wp_send_json_error(['message'=>'❌ Negative values not allowed!']);
        }

        $data = array(
            'total_sales'   => $total_sales,
            'card'          => $card,
            'cash'          => $cash,
            'transfer'      => $transfer,
            'expenses'      => $expenses,
            'expense_remark'=> sanitize_textarea_field($_POST['expense_remark']),
            'old_cash'      => $old_cash,
            'paid_to_bank'  => $paid_to_bank,
        );

        $cash_left = $data['total_sales'] - $data['transfer'] - $data['card'] - $data['expenses'] + $data['old_cash'] - $data['paid_to_bank'];
        if ($cash_left < 0) {
            wp_send_json_error(['message'=>'❌ Cash Left would be negative!']);
        }
        $data['cash_left'] = $cash_left;

        $updated = $wpdb->update(
            $financial_table,
            $data,
            array('id' => $financial_id),
            array('%f','%f','%f','%f','%f','%s','%f','%f','%f'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(['message'=>'Failed to update']);
        }

        wp_send_json_success(['message'=>'✓ Updated successfully']);
    }

    public static function ajax_delete_financial() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_capitito_ims' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }
        global $wpdb;

        $financial_id    = intval($_POST['financial_id']);
        $financial_table = $wpdb->prefix . 'capitito_financial_summary';
        if (!self::table_exists($financial_table)) {
            $financial_table = $wpdb->prefix . 'capitito_ims_financial_summary';
        }

        $deleted = $wpdb->delete($financial_table, array('id'=>$financial_id), array('%d'));
        if (!$deleted) {
            wp_send_json_error(['message'=>'Failed to delete']);
        }
        wp_send_json_success(['message'=>'✓ Deleted successfully']);
    }

    public static function ajax_get_financial_history() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }
        global $wpdb;

        $page     = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $offset   = ($page - 1) * $per_page;

        $filters  = isset($_POST['filters']) ? $_POST['filters'] : array();

        $financial_table = $wpdb->prefix . 'capitito_financial_summary';
        if (!self::table_exists($financial_table)) {
            $financial_table = $wpdb->prefix . 'capitito_ims_financial_summary';
        }

        $where = array('1=1');
        $where_values = array();

        if ( ! empty( $filters['date_from'] ) ) {
            $where[] = 'summary_date >= %s';
            $where_values[] = sanitize_text_field( $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where[] = 'summary_date <= %s';
            $where_values[] = sanitize_text_field( $filters['date_to'] );
        }
        if ( ! empty( $filters['submitted_by'] ) ) {
            $where[] = 'submitted_by LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like( sanitize_text_field( $filters['submitted_by'] ) ) . '%';
        }

        $where_sql = implode(' AND ', $where);
        if (!empty($where_values)) {
            $where_sql = $wpdb->prepare($where_sql, $where_values);
        }

        $history = $wpdb->get_results(
            "SELECT * FROM {$financial_table}
             WHERE {$where_sql}
             ORDER BY summary_date DESC
             LIMIT {$offset}, {$per_page}",
            ARRAY_A
        );

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$financial_table} WHERE {$where_sql}");

        wp_send_json_success([
            'history' => $history,
            'total'   => intval($total),
        ]);
    }

    /**
     * Optional daily reset hook used by main plugin
     */
    public static function daily_reset() {
        // Intentionally minimal: recalculation is performed on-demand in get_todays_summary()
        // This prevents unintended overwrites while still avoiding fatal calls.
        return true;
    }
}
<?php
/**
 * Orders Management Class with Stock Integration (resilient + active-table detection)
 * Version: 1.1.2
 *
 * @package Capitito_IMS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Capitito_IMS_Orders {

    public static function render_orders_page() {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __( 'Please log in to access this page.', 'capitito-ims' ) . '</p>';
        }
        ob_start();
        include CAPITITO_IMS_PLUGIN_DIR . 'templates/orders.php';
        return ob_get_clean();
    }

    public static function render_order_history() {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __( 'Please log in to access this page.', 'capitito-ims' ) . '</p>';
        }
        ob_start();
        include CAPITITO_IMS_PLUGIN_DIR . 'templates/order-history.php';
        return ob_get_clean();
    }

    public static function render_product_summary() {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __( 'Please log in to access this page.', 'capitito-ims' ) . '</p>';
        }
        ob_start();
        include CAPITITO_IMS_PLUGIN_DIR . 'templates/product-summary.php';
        return ob_get_clean();
    }

    /**
     * Resolve the active orders and order_items tables.
     * We pick the table (main vs ims) that has the latest created_at row.
     */
    private static function resolve_orders_tables() {
        global $wpdb;
        $main_orders = $wpdb->prefix . 'capitito_orders';
        $ims_orders  = $wpdb->prefix . 'capitito_ims_orders';
        $main_items  = $wpdb->prefix . 'capitito_order_items';
        $ims_items   = $wpdb->prefix . 'capitito_ims_order_items';

        $main_exists = self::table_exists($main_orders);
        $ims_exists  = self::table_exists($ims_orders);

        // If only one exists, use it
        if ($main_exists && !$ims_exists) {
            return [$main_orders, self::table_exists($main_items) ? $main_items : $ims_items];
        }
        if ($ims_exists && !$main_exists) {
            return [$ims_orders, self::table_exists($ims_items) ? $ims_items : $main_items];
        }
        if (!$main_exists && !$ims_exists) {
            // Fall back to main naming
            return [$main_orders, $main_items];
        }

        // Both exist: use the one with the most recent created_at
        $main_latest = $wpdb->get_var("SELECT MAX(created_at) FROM {$main_orders}");
        $ims_latest  = $wpdb->get_var("SELECT MAX(created_at) FROM {$ims_orders}");

        $pick_orders = $main_latest && $ims_latest
            ? ((strtotime($main_latest) >= strtotime($ims_latest)) ? $main_orders : $ims_orders)
            : ($main_latest ? $main_orders : $ims_orders);

        // Match the items table namespace to the chosen orders table when possible
        $pick_items = ($pick_orders === $main_orders)
            ? (self::table_exists($main_items) ? $main_items : $ims_items)
            : (self::table_exists($ims_items)  ? $ims_items  : $main_items);

        return [$pick_orders, $pick_items];
    }

    /**
     * AJAX: Submit order (resilient inserts) + stock sync + financial update
     */
    public static function ajax_submit_order() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        global $wpdb;

        $user = wp_get_current_user();

        // Items payload
        if (isset($_POST['items']) && is_string($_POST['items'])) {
            $items = json_decode(stripslashes($_POST['items']), true);
        } else {
            $items = isset($_POST['items']) ? (array)$_POST['items'] : array();
        }

        $payment_method        = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
        $payment_breakdown_raw = isset($_POST['payment_breakdown']) ? stripslashes($_POST['payment_breakdown']) : '';
        $grand_total           = isset($_POST['grand_total']) ? floatval($_POST['grand_total']) : 0;
        $total_discount        = isset($_POST['total_discount']) ? floatval($_POST['total_discount']) : 0;

        if ( empty($items) || !is_array($items) ) {
            wp_send_json_error(array('message'=>'No items in order'));
        }
        if ( empty($payment_method) ) {
            wp_send_json_error(array('message'=>'Please select a payment method'));
        }
        if ( $grand_total <= 0 ) {
            wp_send_json_error(array('message'=>'Order total must be greater than zero'));
        }

        // Resolve active tables (keeps reads/writes consistent)
        list($order_table, $order_items_table) = self::resolve_orders_tables();

        // Also detect financial table
        $financial_table = $wpdb->prefix . 'capitito_financial_summary';
        if (!self::table_exists($financial_table)) {
            $financial_table = $wpdb->prefix . 'capitito_ims_financial_summary';
        }

        $today = current_time('Y-m-d');
        $now   = current_time('mysql');

        // Insert order (include total_discount / payment_breakdown only when columns exist)
        $order_data = array(
            'order_date'     => $today,
            'staff_id'       => $user->ID,
            'staff_name'     => $user->display_name,
            'payment_method' => $payment_method,
            'grand_total'    => $grand_total,
            'created_at'     => $now
        );
        $order_format = array('%s','%d','%s','%s','%f','%s');

        if (self::column_exists($order_table, 'total_discount')) {
            $order_data['total_discount'] = $total_discount;
            $order_format = array('%s','%d','%s','%s','%f','%f','%s');
        }
        if (self::column_exists($order_table, 'payment_breakdown')) {
            // keep JSON text, don't decode
            $order_data['payment_breakdown'] = ($payment_breakdown_raw && $payment_breakdown_raw !== 'null') ? $payment_breakdown_raw : null;
            // If we just added a column to data, append format in the right place
            // Ensure formats count matches data count – rebuild safely:
            $order_format = array(); // rebuild
            foreach (array_keys($order_data) as $k) {
                $order_format[] = ($k === 'staff_id') ? '%d' : (in_array($k, ['grand_total','total_discount']) ? '%f' : (in_array($k, ['payment_breakdown']) ? '%s' : (in_array($k, ['order_date','staff_name','payment_method','created_at']) ? '%s' : '%s')));
            }
        }

        $inserted = $wpdb->insert($order_table, $order_data, $order_format);
        if (!$inserted) {
            wp_send_json_error(array('message'=>'Failed to create order: ' . $wpdb->last_error));
        }
        $order_id = $wpdb->insert_id;
        if (!$order_id) {
            wp_send_json_error(array('message'=>'Failed to generate order ID'));
        }

        // Insert items (include discount columns only when present)
        $has_item_discount_cols = self::column_exists($order_items_table, 'discount_percent') &&
                                  self::column_exists($order_items_table, 'discount_amount');

        foreach ($items as $item) {
            if (!isset($item['item_id']) || !isset($item['quantity'])) continue;

            $data = array(
                'order_id'   => $order_id,
                'item_id'    => intval($item['item_id']),
                'item_name'  => sanitize_text_field($item['item_name'] ?? ''),
                'unit_price' => floatval($item['unit_price'] ?? 0),
                'quantity'   => intval($item['quantity'] ?? 0),
                'total'      => floatval($item['total'] ?? 0),
            );
            $format = array('%d','%d','%s','%f','%d','%f');

            if ($has_item_discount_cols) {
                $data['discount_percent'] = floatval($item['discount_percent'] ?? 0);
                $data['discount_amount']  = floatval($item['discount_amount'] ?? 0);
                $format = array('%d','%d','%s','%f','%d','%f','%f','%f');
            }

            $wpdb->insert($order_items_table, $data, $format);
        }

        // Sync stock (by date)
        foreach ($items as $item) {
            $item_id = intval($item['item_id']);

            $total_sales = $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(oi.quantity), 0)
                 FROM {$order_items_table} oi
                 INNER JOIN {$order_table} o ON oi.order_id = o.id
                 WHERE oi.item_id = %d AND DATE(o.created_at) = %s",
                $item_id, $today
            ) );

            $stock_table = $wpdb->prefix . 'capitito_stock';
            $stock = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$stock_table} WHERE item_id = %d AND stock_date = %s",
                $item_id, $today
            ), ARRAY_A );

            if ($stock) {
                $opening     = intval($stock['opening']);
                $imports     = intval($stock['imports']);
                $hq_returned = intval($stock['hq_returned']);
                $closed      = $opening + $imports - intval($total_sales) - $hq_returned;

                $wpdb->update($stock_table, array(
                    'sales'      => intval($total_sales),
                    'closed'     => $closed,
                    'updated_at' => current_time('mysql')
                ), array('id'=>$stock['id']), array('%d','%d','%s'), array('%d'));
            } else {
                $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
                $y_opening = $wpdb->get_var( $wpdb->prepare(
                    "SELECT closed FROM {$stock_table} WHERE item_id = %d AND stock_date = %s",
                    $item_id, $yesterday
                ) );
                $opening = $y_opening ? intval($y_opening) : 0;
                $closed  = $opening - intval($total_sales);

                $wpdb->insert($stock_table, array(
                    'item_id'     => $item_id,
                    'stock_date'  => $today,
                    'opening'     => $opening,
                    'imports'     => 0,
                    'sales'       => intval($total_sales),
                    'hq_returned' => 0,
                    'closed'      => $closed,
                    'created_at'  => current_time('mysql'),
                    'updated_at'  => current_time('mysql')
                ), array('%d','%s','%d','%d','%d','%d','%d','%s','%s'));
            }
        }

        // Update financial summary accumulators
        $financial_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$financial_table} WHERE summary_date = %s",
            $today
        ) );

        $breakdown = null;
        if (!empty($payment_breakdown_raw) && $payment_breakdown_raw !== '{}' && $payment_breakdown_raw !== 'null') {
            $breakdown = json_decode($payment_breakdown_raw, true);
        }
        $cash_amount = 0; $card_amount = 0; $transfer_amount = 0;
        if (is_array($breakdown) && (isset($breakdown['cash']) || isset($breakdown['card']) || isset($breakdown['transfer']))) {
            $cash_amount     = isset($breakdown['cash']) ? floatval($breakdown['cash']) : 0;
            $card_amount     = isset($breakdown['card']) ? floatval($breakdown['card']) : 0;
            $transfer_amount = isset($breakdown['transfer']) ? floatval($breakdown['transfer']) : 0;
        } else {
            if ($payment_method === 'cash')     $cash_amount = $grand_total;
            if ($payment_method === 'card')     $card_amount = $grand_total;
            if ($payment_method === 'transfer') $transfer_amount = $grand_total;
        }

        $has_discount_total_col = self::column_exists($financial_table, 'discount_total');

        if ($financial_exists) {
            $sql  = "UPDATE {$financial_table} SET total_sales = total_sales + %f, cash = cash + %f, card = card + %f, transfer = transfer + %f";
            $args = array($grand_total, $cash_amount, $card_amount, $transfer_amount);
            if ($has_discount_total_col) {
                $sql .= ", discount_total = discount_total + %f";
                $args[] = $total_discount;
            }
            $sql .= " WHERE summary_date = %s";
            $args[] = $today;
            $wpdb->query( $wpdb->prepare($sql, $args) );
        } else {
            $insert = array(
                'summary_date' => $today,
                'total_sales'  => $grand_total,
                'cash'         => $cash_amount,
                'card'         => $card_amount,
                'transfer'     => $transfer_amount,
                'submitted_by' => $user->display_name
            );
            $formats = array('%s','%f','%f','%f','%f','%s');
            if ($has_discount_total_col) {
                $insert['discount_total'] = $total_discount;
                $formats = array('%s','%f','%f','%f','%f','%f','%s');
            }
            $wpdb->insert($financial_table, $insert, $formats);
        }

        wp_send_json_success(array(
            'message'  => 'Order created successfully!',
            'order_id' => $order_id
        ));
    }

    public static function ajax_get_orders() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'capitito-ims' ) ) );
        }
        global $wpdb;

        $page     = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $offset   = ($page - 1) * $per_page;
        $filters  = isset($_POST['filters']) ? $_POST['filters'] : array();

        list($orders_table,) = self::resolve_orders_tables();

        $where = array('1=1');
        $vals  = array();

        if (!empty($filters['date_from'])) { $where[] = 'DATE(created_at) >= %s'; $vals[] = sanitize_text_field($filters['date_from']); }
        if (!empty($filters['date_to']))   { $where[] = 'DATE(created_at) <= %s'; $vals[] = sanitize_text_field($filters['date_to']); }
        if (!empty($filters['payment_method'])) { $where[] = 'payment_method = %s'; $vals[] = sanitize_text_field($filters['payment_method']); }
        if (!empty($filters['staff']))     { $where[] = 'staff_name LIKE %s'; $vals[] = '%' . $wpdb->esc_like(sanitize_text_field($filters['staff'])) . '%'; }

        $where_sql = implode(' AND ', $where);
        if (!empty($vals)) $where_sql = $wpdb->prepare($where_sql, $vals);

        $orders = $wpdb->get_results(
            "SELECT * FROM {$orders_table}
             WHERE {$where_sql}
             ORDER BY created_at DESC
             LIMIT {$offset}, {$per_page}",
            ARRAY_A
        );

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$orders_table} WHERE {$where_sql}");

        wp_send_json_success(array(
            'orders' => $orders,
            'total'  => intval($total),
        ));
    }

    public static function ajax_get_order() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'capitito-ims' ) ) );
        }
        global $wpdb;

        $order_id = intval($_POST['order_id']);

        list($order_table, $order_items_table) = self::resolve_orders_tables();

        $order = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$order_table} WHERE id = %d",
            $order_id
        ), ARRAY_A );
        if (!$order) wp_send_json_error(array('message'=>__('Order not found','capitito-ims')));

        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$order_items_table} WHERE order_id = %d",
            $order_id
        ), ARRAY_A );

        $order['items'] = $items;
        wp_send_json_success($order);
    }

    public static function ajax_update_order() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'capitito-ims' ) ) );
        }
        global $wpdb;

        $order_id       = intval($_POST['order_id']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        $grand_total    = floatval($_POST['grand_total']);

        list($order_table,) = self::resolve_orders_tables();

        $updated = $wpdb->update($order_table, array(
            'payment_method' => $payment_method,
            'grand_total'    => $grand_total,
        ), array('id'=>$order_id), array('%s','%f'), array('%d'));

        if ($updated === false) {
            wp_send_json_error(array('message'=>__('Failed to update order','capitito-ims')));
        }
        wp_send_json_success(array('message'=>__('Order updated successfully','capitito-ims')));
    }

    public static function ajax_delete_order() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'capitito-ims' ) ) );
        }
        global $wpdb;

        $order_id = intval($_POST['order_id']);

        list($order_table, $order_items_table) = self::resolve_orders_tables();

        $wpdb->delete($order_items_table, array('order_id'=>$order_id), array('%d'));
        $deleted = $wpdb->delete($order_table, array('id'=>$order_id), array('%d'));

        if (!$deleted) {
            wp_send_json_error(array('message'=>__('Failed to delete order','capitito-ims')));
        }
        wp_send_json_success(array('message'=>__('Order deleted successfully','capitito-ims')));
    }

    public static function ajax_get_product_summary() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }
        global $wpdb;

        $page     = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $offset   = ($page - 1) * $per_page;

        $filters     = isset($_POST['filters']) ? $_POST['filters'] : array();
        $date        = isset($filters['date']) ? sanitize_text_field($filters['date']) : current_time('Y-m-d');
        $item_filter = isset($filters['item']) ? intval($filters['item']) : 0;
        $staff_filter= isset($filters['staff']) ? sanitize_text_field($filters['staff']) : '';

        list($order_table, $order_items_table) = self::resolve_orders_tables();

        $where = array('DATE(o.created_at) = %s');
        $params = array($date);
        if ($item_filter) { $where[] = 'oi.item_id = %d'; $params[] = $item_filter; }
        if ($staff_filter) { $where[] = 'o.staff_name LIKE %s'; $params[] = '%' . $wpdb->esc_like($staff_filter) . '%'; }
        $where_clause = implode(' AND ', $where);

        $query = "SELECT oi.item_name, SUM(oi.quantity) as units_sold, TIME(o.created_at) as order_time, o.staff_name
                  FROM {$order_items_table} oi
                  INNER JOIN {$order_table} o ON oi.order_id = o.id
                  WHERE {$where_clause}
                  GROUP BY oi.item_id, o.id
                  ORDER BY o.created_at DESC
                  LIMIT %d OFFSET %d";
        $query_params = array_merge($params, array($per_page, $offset));
        $summary = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);

        $total_query = "SELECT COUNT(DISTINCT oi.id)
                        FROM {$order_items_table} oi
                        INNER JOIN {$order_table} o ON oi.order_id = o.id
                        WHERE {$where_clause}";
        $total = $wpdb->get_var($wpdb->prepare($total_query, $params));

        $stats_query = "SELECT SUM(oi.quantity) as total_units,
                               COUNT(DISTINCT oi.item_id) as unique_items,
                               COUNT(DISTINCT o.id) as total_orders
                        FROM {$order_items_table} oi
                        INNER JOIN {$order_table} o ON oi.order_id = o.id
                        WHERE {$where_clause}";
        $stats = $wpdb->get_row($wpdb->prepare($stats_query, $params), ARRAY_A);

        wp_send_json_success(array(
            'summary' => $summary,
            'total'   => intval($total),
            'stats'   => $stats
        ));
    }

    public static function daily_reset() {
        // No action needed - product summary is date-based
    }

    // Helpers
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
}
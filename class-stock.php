<?php
/**
 * Stock Management Class - COMPLETE REBUILD
 * Version: 2.0.0
 * 
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 */

if (!defined('ABSPATH')) {
    exit;
}

class Capitito_IMS_Stock {

    /**
     * Render stock page - AUTO-INITIALIZES ALL ITEMS
     */
    public static function render_stock_page() {
        if (!is_user_logged_in()) {
            return '<p>Please log in to access this page.</p>';
        }

        global $wpdb;
        $current_user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $today = current_time('Y-m-d');

        // Get all items
        $items = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}capitito_items ORDER BY name ASC");
        
        // Initialize stock records array
        $stock_records = array();
        
        foreach ($items as $item) {
            // Ensure item has valid name
            $item_name = !empty($item->name) ? $item->name : 'Unknown Item';
            
            // Check if stock exists for today
            $stock = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}capitito_stock WHERE item_id = %d AND stock_date = %s",
                $item->id,
                $today
            ), ARRAY_A);

            if (!$stock) {
                // Get yesterday's closing stock
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $yesterday_stock = $wpdb->get_row($wpdb->prepare(
                    "SELECT closed FROM {$wpdb->prefix}capitito_stock WHERE item_id = %d AND stock_date = %s",
                    $item->id,
                    $yesterday
                ), ARRAY_A);

                $opening = $yesterday_stock ? intval($yesterday_stock['closed']) : 0;
                
                // Get today's sales
                $sales = self::get_today_sales($item->id);
                
                // Calculate closed
                $closed = $opening - $sales;

                // Create stock record
                $wpdb->insert(
                    $wpdb->prefix . 'capitito_stock',
                    array(
                        'item_id' => $item->id,
                        'item_name' => $item_name,
                        'stock_date' => $today,
                        'opening' => $opening,
                        'imports' => 0,
                        'sales' => $sales,
                        'hq_returned' => 0,
                        'closed' => $closed,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s')
                );

                $stock_id = $wpdb->insert_id;
                
                $stock = array(
                    'id' => $stock_id,
                    'item_id' => $item->id,
                    'item_name' => $item_name,
                    'stock_date' => $today,
                    'opening' => $opening,
                    'imports' => 0,
                    'sales' => $sales,
                    'hq_returned' => 0,
                    'closed' => $closed
                );
            } else {
                // Update item_name if it's NULL
                if (empty($stock['item_name']) || $stock['item_name'] === 'null') {
                    $wpdb->update(
                        $wpdb->prefix . 'capitito_stock',
                        array('item_name' => $item_name),
                        array('id' => $stock['id']),
                        array('%s'),
                        array('%d')
                    );
                    $stock['item_name'] = $item_name;
                }
            }

            $stock_records[$item->id] = $stock;
        }

        ob_start();
        include CAPITITO_IMS_PLUGIN_DIR . 'templates/stock-page.php';
        return ob_get_clean();
    }

    /**
     * Get today's sales for an item
     */
    public static function get_today_sales($item_id) {
        global $wpdb;
        $today = current_time('Y-m-d');

        $sales = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(oi.quantity), 0)
             FROM {$wpdb->prefix}capitito_order_items oi
             INNER JOIN {$wpdb->prefix}capitito_orders o ON oi.order_id = o.id
             WHERE oi.item_id = %d AND DATE(o.created_at) = %s",
            $item_id,
            $today
        ));

        return intval($sales);
    }

    /**
     * Update stock field via AJAX
     */
    public static function ajax_update_stock() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $stock_id = isset($_POST['stock_id']) ? intval($_POST['stock_id']) : 0;
        $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
        $value = isset($_POST['value']) ? intval($_POST['value']) : 0;

        // Prevent negative values
        if ($value < 0) {
            wp_send_json_error(array('message' => '❌ Negative values are not allowed!'));
            return;
        }

        if (!$stock_id || $stock_id === 0) {
            wp_send_json_error(array('message' => 'Invalid stock ID'));
            return;
        }

        if (!in_array($field, array('imports', 'hq_returned'))) {
            wp_send_json_error(array('message' => 'Invalid field'));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'capitito_stock';

        // Get current stock
        $stock = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $stock_id
        ), ARRAY_A);

        if (!$stock) {
            wp_send_json_error(array('message' => 'Stock record not found'));
            return;
        }

        // Update the field
        $wpdb->update(
            $table,
            array($field => $value),
            array('id' => $stock_id),
            array('%d'),
            array('%d')
        );

        // Recalculate sales and closed
        $sales = self::get_today_sales($stock['item_id']);
        $opening = intval($stock['opening']);
        $imports = ($field === 'imports') ? $value : intval($stock['imports']);
        $hq_returned = ($field === 'hq_returned') ? $value : intval($stock['hq_returned']);
        $closed = $opening + $imports - $sales - $hq_returned;

        // Prevent negative closed
        if ($closed < 0) {
            wp_send_json_error(array(
                'message' => '⚠️ Warning: This would result in negative stock (' . $closed . '). Please adjust values.'
            ));
            return;
        }

        // Update calculated values
        $wpdb->update(
            $table,
            array(
                'sales' => $sales,
                'closed' => $closed,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $stock_id),
            array('%d', '%d', '%s'),
            array('%d')
        );

        wp_send_json_success(array(
            'message' => 'Stock updated successfully',
            'stock' => array(
                'opening' => $opening,
                'imports' => $imports,
                'sales' => $sales,
                'hq_returned' => $hq_returned,
                'closed' => $closed
            )
        ));
    }

    /**
     * Sync sales from orders
     */
    public static function sync_sales_from_order($order_items) {
        global $wpdb;
        $today = current_time('Y-m-d');
        $table = $wpdb->prefix . 'capitito_stock';

        foreach ($order_items as $item) {
            $item_id = isset($item['item_id']) ? intval($item['item_id']) : 0;
            
            if (!$item_id) continue;

            $stock = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE item_id = %d AND stock_date = %s",
                $item_id,
                $today
            ), ARRAY_A);

            if ($stock) {
                $sales = self::get_today_sales($item_id);
                $closed = $stock['opening'] + $stock['imports'] - $sales - $stock['hq_returned'];

                $wpdb->update(
                    $table,
                    array(
                        'sales' => $sales,
                        'closed' => $closed,
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $stock['id']),
                    array('%d', '%d', '%s'),
                    array('%d')
                );
            }
        }
    }

    /**
     * Sync sales via AJAX
     */
    public static function ajax_sync_sales() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $stock_id = isset($_POST['stock_id']) ? intval($_POST['stock_id']) : 0;

        if (!$stock_id) {
            wp_send_json_error(array('message' => 'Invalid stock ID'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'capitito_stock';

        $stock = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $stock_id
        ), ARRAY_A);

        if (!$stock) {
            wp_send_json_error(array('message' => 'Stock not found'));
        }

        $new_sales = self::get_today_sales($stock['item_id']);
        $old_sales = intval($stock['sales']);

        if ($new_sales !== $old_sales) {
            $closed = $stock['opening'] + $stock['imports'] - $new_sales - $stock['hq_returned'];

            $wpdb->update(
                $table,
                array(
                    'sales' => $new_sales,
                    'closed' => $closed,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $stock_id),
                array('%d', '%d', '%s'),
                array('%d')
            );

            wp_send_json_success(array(
                'updated' => true,
                'stock' => array(
                    'sales' => $new_sales,
                    'closed' => $closed
                )
            ));
        } else {
            wp_send_json_success(array('updated' => false));
        }
    }

    /**
     * Render stock history
     */
    public static function render_stock_history() {
        if (!is_user_logged_in()) {
            return '<p>Please log in to access this page.</p>';
        }

        $current_user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');

        global $wpdb;
        $items = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}capitito_items ORDER BY name ASC");

        ob_start();
        include CAPITITO_IMS_PLUGIN_DIR . 'templates/stock-history.php';
        return ob_get_clean();
    }

    /**
     * Get stock history via AJAX
     */
    public static function ajax_get_stock_history() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        global $wpdb;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $offset = ($page - 1) * $per_page;

        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        $date_from = isset($filters['date_from']) ? sanitize_text_field($filters['date_from']) : '';
        $date_to = isset($filters['date_to']) ? sanitize_text_field($filters['date_to']) : '';
        $item_filter = isset($filters['item']) ? intval($filters['item']) : 0;

        $where = array('1=1');
        $where_params = array();

        if ($date_from) {
            $where[] = "s.stock_date >= %s";
            $where_params[] = $date_from;
        }

        if ($date_to) {
            $where[] = "s.stock_date <= %s";
            $where_params[] = $date_to;
        }

        if ($item_filter) {
            $where[] = "s.item_id = %d";
            $where_params[] = $item_filter;
        }

        $where_clause = implode(' AND ', $where);

        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$wpdb->prefix}capitito_stock s WHERE $where_clause";
        if (!empty($where_params)) {
            $total_query = $wpdb->prepare($total_query, $where_params);
        }
        $total = $wpdb->get_var($total_query);

        // Get records with item names from items table (handles NULL item_name)
        $query = "SELECT s.*, COALESCE(NULLIF(s.item_name, ''), NULLIF(s.item_name, 'null'), i.name, 'Unknown Item') as item_name 
                  FROM {$wpdb->prefix}capitito_stock s
                  LEFT JOIN {$wpdb->prefix}capitito_items i ON s.item_id = i.id
                  WHERE $where_clause
                  ORDER BY s.stock_date DESC, s.item_name ASC
                  LIMIT %d OFFSET %d";

        $query_params = array_merge($where_params, array($per_page, $offset));
        $history = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);

        wp_send_json_success(array(
            'history' => $history,
            'total' => intval($total)
        ));
    }

    /**
     * Get single stock record
     */
    public static function ajax_get_stock() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $stock_id = isset($_POST['stock_id']) ? intval($_POST['stock_id']) : 0;

        if (!$stock_id) {
            wp_send_json_error(array('message' => 'Invalid stock ID'));
        }

        global $wpdb;
        $stock = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, COALESCE(NULLIF(s.item_name, ''), NULLIF(s.item_name, 'null'), i.name, 'Unknown Item') as item_name 
             FROM {$wpdb->prefix}capitito_stock s
             LEFT JOIN {$wpdb->prefix}capitito_items i ON s.item_id = i.id
             WHERE s.id = %d",
            $stock_id
        ), ARRAY_A);

        if (!$stock) {
            wp_send_json_error(array('message' => 'Stock record not found'));
        }

        wp_send_json_success($stock);
    }

    /**
     * Update stock history record (admin only)
     */
    public static function ajax_update_stock_history() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $stock_id = isset($_POST['stock_id']) ? intval($_POST['stock_id']) : 0;
        $opening = isset($_POST['opening']) ? intval($_POST['opening']) : 0;
        $imports = isset($_POST['imports']) ? intval($_POST['imports']) : 0;
        $hq_returned = isset($_POST['hq_returned']) ? intval($_POST['hq_returned']) : 0;

        if ($opening < 0 || $imports < 0 || $hq_returned < 0) {
            wp_send_json_error(array('message' => '❌ Negative values are not allowed!'));
            return;
        }

        if (!$stock_id) {
            wp_send_json_error(array('message' => 'Invalid stock ID'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'capitito_stock';

        $stock = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $stock_id
        ), ARRAY_A);

        if (!$stock) {
            wp_send_json_error(array('message' => 'Stock record not found'));
        }

        $sales = self::get_today_sales($stock['item_id']);
        $closed = $opening + $imports - $sales - $hq_returned;

        if ($closed < 0) {
            wp_send_json_error(array(
                'message' => '⚠️ Closed stock would be negative (' . $closed . ')!'
            ));
            return;
        }

        $wpdb->update(
            $table,
            array(
                'opening' => $opening,
                'imports' => $imports,
                'sales' => $sales,
                'hq_returned' => $hq_returned,
                'closed' => $closed,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $stock_id),
            array('%d', '%d', '%d', '%d', '%d', '%s'),
            array('%d')
        );

        wp_send_json_success(array('message' => 'Stock updated successfully'));
    }

    /**
     * Delete stock history record (admin only)
     */
    public static function ajax_delete_stock_history() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $stock_id = isset($_POST['stock_id']) ? intval($_POST['stock_id']) : 0;

        if (!$stock_id) {
            wp_send_json_error(array('message' => 'Invalid stock ID'));
        }

        global $wpdb;
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'capitito_stock',
            array('id' => $stock_id),
            array('%d')
        );

        if ($deleted) {
            wp_send_json_success(array('message' => 'Stock record deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete stock record'));
        }
    }

    /**
     * Daily reset
     */
    public static function daily_reset() {
        // Stock automatically carries forward
    }
}
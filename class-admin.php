<?php
/**
 * Admin management class - FIXED TABLE NAMES
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Capitito_IMS_Admin {

    /**
     * Render admin panel
     */
    public static function render_admin_panel() {
        if ( ! current_user_can( 'manage_capitito_ims' ) ) {
            return '<p>' . __( 'Unauthorized access', 'capitito-ims' ) . '</p>';
        }

        ob_start();
        include CAPITITO_IMS_PLUGIN_DIR . 'templates/admin-panel.php';
        return ob_get_clean();
    }

    /**
     * AJAX: Add item
     */
    public static function ajax_add_item() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_capitito_ims_items' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'capitito-ims' ) ) );
        }

        global $wpdb;

        $name = sanitize_text_field( $_POST['name'] );
        $price = floatval( $_POST['price'] );

        // ✅ FIX: Use correct table name
        $items_table = $wpdb->prefix . 'capitito_items';

        // Check if item already exists
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $items_table WHERE name = %s",
            $name
        ) );

        if ( $exists ) {
            wp_send_json_error( array( 'message' => __( 'Item already exists', 'capitito-ims' ) ) );
            return;
        }

        $inserted = $wpdb->insert(
            $items_table,
            array(
                'name' => $name,
                'price' => $price,
                'created_at' => current_time( 'mysql' )
            ),
            array( '%s', '%f', '%s' )
        );

        if ( ! $inserted ) {
            wp_send_json_error( array( 
                'message' => __( 'Failed to add item', 'capitito-ims' ),
                'error' => $wpdb->last_error
            ) );
            return;
        }

        $item_id = $wpdb->insert_id;

        // ✅ AUTO-CREATE stock record for today
        $today = current_time( 'Y-m-d' );
        $stock_table = $wpdb->prefix . 'capitito_stock';

        $wpdb->insert(
            $stock_table,
            array(
                'item_id' => $item_id,
                'item_name' => $name,
                'stock_date' => $today,
                'opening' => 0,
                'imports' => 0,
                'sales' => 0,
                'hq_returned' => 0,
                'closed' => 0,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' )
            ),
            array( '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
        );

        wp_send_json_success( array(
            'message' => __( 'Item added successfully and stock initialized', 'capitito-ims' ),
            'item_id' => $item_id,
        ) );
    }

    /**
     * AJAX: Update item
     */
    public static function ajax_update_item() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_capitito_ims_items' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'capitito-ims' ) ) );
        }

        global $wpdb;

        $item_id = intval( $_POST['item_id'] );
        $name = sanitize_text_field( $_POST['name'] );
        $price = floatval( $_POST['price'] );

        // ✅ FIX: Use correct table name
        $items_table = $wpdb->prefix . 'capitito_items';

        $updated = $wpdb->update(
            $items_table,
            array(
                'name' => $name,
                'price' => $price,
            ),
            array( 'id' => $item_id ),
            array( '%s', '%f' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            wp_send_json_error( array( 
                'message' => __( 'Failed to update item', 'capitito-ims' ),
                'error' => $wpdb->last_error
            ) );
            return;
        }

        // ✅ Also update item_name in stock records
        $stock_table = $wpdb->prefix . 'capitito_stock';
        $wpdb->update(
            $stock_table,
            array( 'item_name' => $name ),
            array( 'item_id' => $item_id ),
            array( '%s' ),
            array( '%d' )
        );

        wp_send_json_success( array( 'message' => __( 'Item updated successfully', 'capitito-ims' ) ) );
    }

        /**
     * AJAX: Delete item
     */
    public static function ajax_delete_item() {
        check_ajax_referer( 'capitito_ims_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_capitito_ims' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'capitito-ims' ) ) );
        }

        global $wpdb;

        $item_id = intval( $_POST['item_id'] );
        
        if ( $item_id <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid item ID', 'capitito-ims' ) ) );
            return;
        }
        
        // ✅ FIX: Use correct table name
        $items_table = $wpdb->prefix . 'capitito_items';

        // Verify item exists
        $item_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $items_table WHERE id = %d",
            $item_id
        ) );

        if ( ! $item_exists ) {
            wp_send_json_error( array( 'message' => __( 'Item not found', 'capitito-ims' ) ) );
            return;
        }

        // Check if item has orders (both tables for compatibility)
        $order_items_tables = array(
            $wpdb->prefix . 'capitito_order_items',
            $wpdb->prefix . 'capitito_ims_order_items'
        );

        $has_orders = false;
        foreach ( $order_items_tables as $table ) {
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table ) {
                $count = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE item_id = %d",
                    $item_id
                ) );
                if ( $count > 0 ) {
                    $has_orders = true;
                    break;
                }
            }
        }

        if ( $has_orders ) {
            wp_send_json_error( array( 
                'message' => __( 'Cannot delete item with existing orders. This item must be kept for historical data integrity.', 'capitito-ims' )
            ) );
            return;
        }

        // Check and delete stock records
        $stock_table = $wpdb->prefix . 'capitito_stock';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$stock_table'" ) === $stock_table ) {
            $has_stock = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $stock_table WHERE item_id = %d",
                $item_id
            ) );
            
            if ( $has_stock > 0 ) {
                // Delete stock records for this item
                $wpdb->delete( $stock_table, array( 'item_id' => $item_id ), array( '%d' ) );
            }
        }

        // Delete from items table
        $deleted = $wpdb->delete( $items_table, array( 'id' => $item_id ), array( '%d' ) );

        if ( ! $deleted ) {
            wp_send_json_error( array( 
                'message' => __( 'Failed to delete item', 'capitito-ims' ),
                'error' => $wpdb->last_error
            ) );
            return;
        }

        // Log deletion for audit trail
        error_log( sprintf(
            'Capitito IMS: Item #%d deleted by user #%d',
            $item_id,
            get_current_user_id()
        ) );

        wp_send_json_success( array( 'message' => __( 'Item deleted successfully', 'capitito-ims' ) ) );
    }
}
<?php
/**
 * Database management class
 * Handles database table creation and migrations
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Capitito_IMS_Database {

    /**
     * Database version
     */
    const DB_VERSION = '1.1.0';

    /**
     * Activate plugin - create tables
     */
    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $db_version = get_option( 'capitito_ims_db_version', '0.0.0' );

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // Items table
        $table_name = $wpdb->prefix . 'capitito_ims_items';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY name (name)
        ) $charset_collate;";
        dbDelta( $sql );

        // Orders table (UPDATED with total_discount)
        $table_name = $wpdb->prefix . 'capitito_ims_orders';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            staff_name varchar(255) NOT NULL,
            staff_id bigint(20) NOT NULL,
            payment_method varchar(50) NOT NULL,
            payment_breakdown text DEFAULT NULL,
            grand_total decimal(10,2) NOT NULL DEFAULT 0.00,
            total_discount decimal(10,2) NOT NULL DEFAULT 0.00,
            order_date date NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY staff_id (staff_id),
            KEY order_date (order_date)
        ) $charset_collate;";
        dbDelta( $sql );

        // Order items table (UPDATED with discount columns)
        $table_name = $wpdb->prefix . 'capitito_ims_order_items';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            item_id bigint(20) NOT NULL,
            item_name varchar(255) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 0,
            unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
            discount_percent decimal(5,2) NOT NULL DEFAULT 0.00,
            discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            total decimal(10,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY item_id (item_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Stock table
        $table_stock = $wpdb->prefix . 'capitito_stock';
        $sql = "CREATE TABLE IF NOT EXISTS $table_stock (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            item_id bigint(20) UNSIGNED NOT NULL,
            stock_date date NOT NULL,
            opening int(11) NOT NULL DEFAULT 0,
            imports int(11) NOT NULL DEFAULT 0,
            sales int(11) NOT NULL DEFAULT 0,
            hq_returned int(11) NOT NULL DEFAULT 0,
            closed int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_item_date (item_id, stock_date),
            KEY stock_date (stock_date),
            KEY item_id (item_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Financial summary table (UPDATED with discount_total)
        $table_name = $wpdb->prefix . 'capitito_ims_financial_summary';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            summary_date date NOT NULL,
            total_sales decimal(10,2) NOT NULL DEFAULT 0.00,
            card decimal(10,2) NOT NULL DEFAULT 0.00,
            cash decimal(10,2) NOT NULL DEFAULT 0.00,
            transfer decimal(10,2) NOT NULL DEFAULT 0.00,
            discount_total decimal(10,2) NOT NULL DEFAULT 0.00,
            expenses decimal(10,2) NOT NULL DEFAULT 0.00,
            expense_remark text,
            old_cash decimal(10,2) NOT NULL DEFAULT 0.00,
            paid_to_bank decimal(10,2) NOT NULL DEFAULT 0.00,
            cash_left decimal(10,2) NOT NULL DEFAULT 0.00,
            submitted_by varchar(255) NOT NULL,
            submitted_by_id bigint(20) NOT NULL,
            is_submitted tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY summary_date (summary_date),
            KEY submitted_by_id (submitted_by_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Product summary table
        $table_name = $wpdb->prefix . 'capitito_ims_product_summary';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            item_id bigint(20) NOT NULL,
            item_name varchar(255) NOT NULL,
            units_sold int(11) NOT NULL DEFAULT 0,
            order_time time NOT NULL,
            staff_name varchar(255) NOT NULL,
            staff_id bigint(20) NOT NULL,
            summary_date date NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY item_id (item_id),
            KEY summary_date (summary_date),
            KEY staff_id (staff_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Reconciliation table
        $table_name = $wpdb->prefix . 'capitito_ims_reconciliation';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            reconciliation_date date NOT NULL,
            staff_name varchar(255) NOT NULL,
            staff_id bigint(20) NOT NULL,
            notes text,
            reconciled_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY reconciliation_date (reconciliation_date),
            KEY staff_id (staff_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Ensure discount columns are added to existing tables
        self::ensure_discount_columns();

        // Update database version
        update_option( 'capitito_ims_db_version', self::DB_VERSION );

        // Create roles
        Capitito_IMS_Roles::create_roles();

        // Initialize today's financial summary if doesn't exist
        self::init_todays_financial_summary();
    }
    
    /**
     * Ensure discount columns exist on all tables (for migrations)
     */
    public static function ensure_discount_columns() {
        global $wpdb;

        // Orders tables - add total_discount
        $orders_tables = array(
            $wpdb->prefix . 'capitito_orders',
            $wpdb->prefix . 'capitito_ims_orders'
        );

        foreach ($orders_tables as $table) {
            if (self::table_exists($table) && !self::column_exists($table, 'total_discount')) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN total_discount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER grand_total");
            }
        }

        // Order items tables - add discount_percent and discount_amount
        $order_items_tables = array(
            $wpdb->prefix . 'capitito_order_items',
            $wpdb->prefix . 'capitito_ims_order_items'
        );

        foreach ($order_items_tables as $table) {
            if (self::table_exists($table)) {
                if (!self::column_exists($table, 'discount_percent')) {
                    $wpdb->query("ALTER TABLE {$table} ADD COLUMN discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER unit_price");
                }
                if (!self::column_exists($table, 'discount_amount')) {
                    $wpdb->query("ALTER TABLE {$table} ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER discount_percent");
                }
            }
        }

        // Financial summary tables - add discount_total
        $financial_tables = array(
            $wpdb->prefix . 'capitito_financial_summary',
            $wpdb->prefix . 'capitito_ims_financial_summary'
        );

        foreach ($financial_tables as $table) {
            if (self::table_exists($table) && !self::column_exists($table, 'discount_total')) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN discount_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER transfer");
            }
        }
    }

    /**
     * Check if table exists
     */
    private static function table_exists($table) {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
            DB_NAME, $table
        ));
        return (int)$exists > 0;
    }

    /**
     * Check if column exists in table
     */
    private static function column_exists($table, $column) {
        global $wpdb;
        $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
        return !empty($col);
    }

    /**
     * Initialize today's financial summary
     */
    private static function init_todays_financial_summary() {
        global $wpdb;
        
        $today = current_time( 'Y-m-d' );
        $table = $wpdb->prefix . 'capitito_ims_financial_summary';
        
        // Try non-ims table first
        $alt_table = $wpdb->prefix . 'capitito_financial_summary';
        if (self::table_exists($alt_table)) {
            $table = $alt_table;
        }
        
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE summary_date = %s",
            $today
        ) );
        
        if ( ! $exists ) {
            // Get yesterday's cash left to use as today's old cash
            $yesterday = date( 'Y-m-d', strtotime( '-1 day', strtotime( $today ) ) );
            $yesterday_data = $wpdb->get_row( $wpdb->prepare(
                                "SELECT cash_left FROM $table WHERE summary_date = %s AND is_submitted = 1",
                $yesterday
            ) );
            
            $old_cash = $yesterday_data ? $yesterday_data->cash_left : 0;
            
            $wpdb->insert(
                $table,
                array(
                    'summary_date' => $today,
                    'old_cash' => $old_cash,
                    'submitted_by' => 'System',
                    'submitted_by_id' => 0,
                    'is_submitted' => 0
                ),
                array( '%s', '%f', '%s', '%d', '%d' )
            );
        }
    }

    /**
     * Uninstall - remove tables and options
     */
    public static function uninstall() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'capitito_ims_items',
            $wpdb->prefix . 'capitito_ims_orders',
            $wpdb->prefix . 'capitito_ims_order_items',
            $wpdb->prefix . 'capitito_ims_stock',
            $wpdb->prefix . 'capitito_ims_financial_summary',
            $wpdb->prefix . 'capitito_ims_product_summary',
            $wpdb->prefix . 'capitito_ims_reconciliation',
            $wpdb->prefix . 'capitito_stock',
            $wpdb->prefix . 'capitito_orders',
            $wpdb->prefix . 'capitito_order_items',
            $wpdb->prefix . 'capitito_financial_summary',
        );

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS $table" );
        }

        delete_option( 'capitito_ims_db_version' );

        // Remove roles
        Capitito_IMS_Roles::remove_roles();
    }
}

// Register uninstall hook
register_uninstall_hook( CAPITITO_IMS_PLUGIN_BASENAME, array( 'Capitito_IMS_Database', 'uninstall' ) );
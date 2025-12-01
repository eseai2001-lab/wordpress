<?php
/**
 * Auto Repair Class
 * Automatically fixes common issues detected by Health Monitor
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Capitito_IMS_Auto_Repair {

    /**
     * Run auto-repair based on health check results
     */
    public static function run_auto_repair( $health_results ) {
        $fixes = array();

        // Fix database issues
        if ( isset( $health_results['checks']['database'] ) ) {
            $db_fixes = self::fix_database_issues( $health_results['checks']['database'] );
            $fixes = array_merge( $fixes, $db_fixes );
        }

        // Fix data integrity issues
        if ( isset( $health_results['checks']['data_integrity'] ) ) {
            $data_fixes = self::fix_data_integrity( $health_results['checks']['data_integrity'] );
            $fixes = array_merge( $fixes, $data_fixes );
        }

        // Optimize performance
        if ( isset( $health_results['checks']['performance'] ) ) {
            $perf_fixes = self::optimize_performance( $health_results['checks']['performance'] );
            $fixes = array_merge( $fixes, $perf_fixes );
        }

        return $fixes;
    }

    /**
     * Fix database issues
     */
    private static function fix_database_issues( $db_health ) {
        global $wpdb;
        $fixes = array();

        foreach ( $db_health['issues'] as $issue ) {
            // Fix missing columns
            if ( strpos( $issue, 'Missing column:' ) !== false ) {
                preg_match( '/Missing column: (.+)\.(.+)/', $issue, $matches );
                if ( count( $matches ) === 3 ) {
                    $table = $wpdb->prefix . $matches[1];
                    $column = $matches[2];

                    $fixed = self::add_missing_column( $table, $column );
                    if ( $fixed ) {
                        $fixes[] = "Added missing column: $column to $table";
                    }
                }
            }

            // Repair corrupted tables
            if ( strpos( $issue, 'Table corruption' ) !== false ) {
                preg_match( '/Table corruption detected: (.+)/', $issue, $matches );
                if ( isset( $matches[1] ) ) {
                    $table = $wpdb->prefix . $matches[1];
                    $wpdb->query( "REPAIR TABLE $table" );
                    $fixes[] = "Repaired table: $table";
                }
            }
        }

        return $fixes;
    }

    /**
     * Add missing database column
     */
    private static function add_missing_column( $table, $column ) {
        global $wpdb;

        $column_definitions = array(
            'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'archived_at' => 'DATETIME NULL DEFAULT NULL',
            'archived_by' => 'BIGINT NULL DEFAULT NULL',
            'total_discount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'discount_percent' => 'DECIMAL(5,2) NOT NULL DEFAULT 0',
            'discount_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'payment_breakdown' => 'TEXT NULL',
        );

        if ( isset( $column_definitions[$column] ) ) {
            $definition = $column_definitions[$column];
            $result = $wpdb->query( "ALTER TABLE $table ADD COLUMN `$column` $definition" );
            
            error_log( "Auto-Repair: Added column $column to $table" );
            return $result !== false;
        }

        return false;
    }

    /**
     * Fix data integrity issues
     */
    private static function fix_data_integrity( $data_health ) {
        global $wpdb;
        $fixes = array();

        // Remove orphaned order items
        if ( isset( $data_health['details']['orphaned_order_items'] ) && $data_health['details']['orphaned_order_items'] > 0 ) {
            $deleted = $wpdb->query( "
                DELETE oi FROM {$wpdb->prefix}capitito_order_items oi
                LEFT JOIN {$wpdb->prefix}capitito_orders o ON oi.order_id = o.id
                WHERE o.id IS NULL
            " );

            if ( $deleted ) {
                $fixes[] = "Removed $deleted orphaned order items";
            }
        }

        // Create missing stock records
        if ( isset( $data_health['details']['missing_stock_records'] ) && $data_health['details']['missing_stock_records'] > 0 ) {
            $created = self::create_missing_stock_records();
            if ( $created > 0 ) {
                $fixes[] = "Created $created missing stock records";
            }
        }

        return $fixes;
    }

    /**
     * Create missing stock records for today
     */
    private static function create_missing_stock_records() {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        $yesterday = date( 'Y-m-d', strtotime( '-1 day', strtotime( $today ) ) );

        $items_without_stock = $wpdb->get_results( "
            SELECT i.id, i.name 
            FROM {$wpdb->prefix}capitito_items i
            LEFT JOIN {$wpdb->prefix}capitito_stock s ON i.id = s.item_id AND s.stock_date = '$today'
            WHERE s.id IS NULL AND (i.is_archived IS NULL OR i.is_archived = 0)
        " );

        $created = 0;

        foreach ( $items_without_stock as $item ) {
            // Get yesterday's closing as today's opening
            $yesterday_stock = $wpdb->get_row( $wpdb->prepare(
                "SELECT closed FROM {$wpdb->prefix}capitito_stock 
                 WHERE item_id = %d AND stock_date = %s",
                $item->id,
                $yesterday
            ) );

            $opening = $yesterday_stock ? $yesterday_stock->closed : 0;

            // Insert today's stock record
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'capitito_stock',
                array(
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'stock_date' => $today,
                    'opening' => $opening,
                    'imports' => 0,
                    'sales' => 0,
                    'hq_returned' => 0,
                    'closed' => $opening,
                    'created_at' => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s' )
            );

            if ( $inserted ) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Optimize performance
     */
    private static function optimize_performance( $perf_health ) {
        global $wpdb;
        $fixes = array();

        // Optimize all monitored tables
        $tables = array(
            'capitito_items',
            'capitito_orders',
            'capitito_order_items',
            'capitito_stock',
            'capitito_financial_summary',
        );

        foreach ( $tables as $table ) {
            $table_name = $wpdb->prefix . $table;
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) ) {
                $wpdb->query( "OPTIMIZE TABLE $table_name" );
                $fixes[] = "Optimized table: $table";
            }
        }

        // Clear expired transients
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()" );
        $fixes[] = "Cleared expired transients";

        return $fixes;
    }

    /**
     * Manual repair tools
     */
    public static function force_database_sync() {
        global $wpdb;
        $results = array();

        // Ensure all discount columns exist
        $tables_columns = array(
            'capitito_orders' => array( 'total_discount' ),
            'capitito_order_items' => array( 'discount_percent', 'discount_amount' ),
            'capitito_financial_summary' => array( 'discount_total' ),
            'capitito_items' => array( 'is_archived', 'archived_at', 'archived_by' ),
        );

        foreach ( $tables_columns as $table => $columns ) {
            $table_name = $wpdb->prefix . $table;
            foreach ( $columns as $column ) {
                if ( ! self::column_exists( $table_name, $column ) ) {
                    self::add_missing_column( $table_name, $column );
                    $results[] = "Added column $column to $table";
                }
            }
        }

        return $results;
    }

    /**
     * Check if column exists
     */
    private static function column_exists( $table, $column ) {
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $table LIKE %s", $column ) );
        return ! empty( $exists );
    }

    /**
     * Optimize all tables
     */
    public static function optimize_all_tables() {
        global $wpdb;
        $results = array();

        $tables = $wpdb->get_results( "SHOW TABLES LIKE '{$wpdb->prefix}capitito_%'", ARRAY_N );

        foreach ( $tables as $table ) {
            $table_name = $table[0];
            $wpdb->query( "OPTIMIZE TABLE $table_name" );
            $results[] = "Optimized: $table_name";
        }

        return $results;
    }
}
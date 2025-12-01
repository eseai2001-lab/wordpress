<?php
/**
 * Health Monitor Class
 * Monitors plugin health, detects issues, and provides diagnostics
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Capitito_IMS_Health_Monitor {

    /**
     * Plugin files to monitor
     */
    private static $monitored_files = array(
        'capitito-inventory-management.php',
        'includes/class-database.php',
        'includes/class-roles.php',
        'includes/class-orders.php',
        'includes/class-stock.php',
        'includes/class-financial.php',
        'includes/class-admin.php',
        'includes/class-reconciliation.php',
        'assets/js/scripts.js',
        'assets/css/styles.css',
        'templates/admin-panel.php',
        'templates/orders.php',
        'templates/stock-page.php',
        'templates/financial-summary.php',
    );

    /**
     * Database tables to monitor
     */
    private static $monitored_tables = array(
        'capitito_items',
        'capitito_orders',
        'capitito_order_items',
        'capitito_stock',
        'capitito_financial_summary',
        'capitito_reconciliation',
    );

    /**
     * Initialize health monitoring
     */
    public static function init() {
        // Schedule health checks
        if ( ! wp_next_scheduled( 'capitito_ims_health_check' ) ) {
            wp_schedule_event( time(), 'hourly', 'capitito_ims_health_check' );
        }

        add_action( 'capitito_ims_health_check', array( __CLASS__, 'run_health_check' ) );
        add_action( 'admin_notices', array( __CLASS__, 'display_health_warnings' ) );
    }

    /**
     * Run comprehensive health check
     */
    public static function run_health_check() {
        $results = array(
            'timestamp' => current_time( 'mysql' ),
            'overall_score' => 100,
            'checks' => array(),
            'issues' => array(),
            'auto_fixes' => array(),
        );

        // 1. Database Health
        $db_health = self::check_database_health();
        $results['checks']['database'] = $db_health;
        $results['overall_score'] -= $db_health['penalty'];

        // 2. File Integrity
        $file_health = self::check_file_integrity();
        $results['checks']['files'] = $file_health;
        $results['overall_score'] -= $file_health['penalty'];

        // 3. Performance
        $perf_health = self::check_performance();
        $results['checks']['performance'] = $perf_health;
        $results['overall_score'] -= $perf_health['penalty'];

        // 4. Security
        $security_health = self::check_security();
        $results['checks']['security'] = $security_health;
        $results['overall_score'] -= $security_health['penalty'];

        // 5. Data Integrity
        $data_health = self::check_data_integrity();
        $results['checks']['data_integrity'] = $data_health;
        $results['overall_score'] -= $data_health['penalty'];

        // Ensure score doesn't go below 0
        $results['overall_score'] = max( 0, $results['overall_score'] );

        // Store results
        update_option( 'capitito_ims_health_status', $results );

        // Auto-fix critical issues
        if ( $results['overall_score'] < 70 ) {
            self::attempt_auto_fix( $results );
        }

        return $results;
    }

    /**
     * Check database health
     */
    private static function check_database_health() {
        global $wpdb;

        $health = array(
            'status' => 'healthy',
            'score' => 100,
            'penalty' => 0,
            'issues' => array(),
            'details' => array(),
        );

        // Check all required tables exist
        foreach ( self::$monitored_tables as $table ) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

            if ( ! $exists ) {
                $health['issues'][] = "Missing table: $table";
                $health['penalty'] += 15;
            } else {
                // Check table health
                $rows = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
                $health['details'][$table] = array(
                    'exists' => true,
                    'row_count' => $rows,
                );

                // Check for table corruption
                $check = $wpdb->get_row( "CHECK TABLE $table_name" );
                if ( $check && $check->Msg_text !== 'OK' ) {
                    $health['issues'][] = "Table corruption detected: $table";
                    $health['penalty'] += 20;
                }
            }
        }

        // Check required columns
        $required_columns = array(
            'capitito_items' => array( 'id', 'name', 'price', 'is_archived' ),
            'capitito_orders' => array( 'id', 'grand_total', 'total_discount', 'payment_method' ),
            'capitito_order_items' => array( 'id', 'order_id', 'item_id', 'discount_amount' ),
        );

        foreach ( $required_columns as $table => $columns ) {
            $table_name = $wpdb->prefix . $table;
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) ) {
                foreach ( $columns as $column ) {
                    $exists = $wpdb->get_var( "SHOW COLUMNS FROM $table_name LIKE '$column'" );
                    if ( ! $exists ) {
                        $health['issues'][] = "Missing column: $table.$column";
                        $health['penalty'] += 10;
                    }
                }
            }
        }

        $health['score'] = max( 0, 100 - $health['penalty'] );
        $health['status'] = $health['score'] >= 80 ? 'healthy' : ( $health['score'] >= 50 ? 'warning' : 'critical' );

        return $health;
    }

    /**
     * Check file integrity
     */
    private static function check_file_integrity() {
        $health = array(
            'status' => 'healthy',
            'score' => 100,
            'penalty' => 0,
            'issues' => array(),
            'details' => array(),
        );

        foreach ( self::$monitored_files as $file ) {
            $filepath = CAPITITO_IMS_PLUGIN_DIR . $file;
            
            if ( ! file_exists( $filepath ) ) {
                $health['issues'][] = "Missing file: $file";
                $health['penalty'] += 15;
                $health['details'][$file] = array( 'exists' => false );
            } else {
                $health['details'][$file] = array(
                    'exists' => true,
                    'size' => filesize( $filepath ),
                    'modified' => filemtime( $filepath ),
                    'readable' => is_readable( $filepath ),
                    'writable' => is_writable( $filepath ),
                );

                // Check file permissions
                if ( ! is_readable( $filepath ) ) {
                    $health['issues'][] = "File not readable: $file";
                    $health['penalty'] += 5;
                }
            }
        }

        $health['score'] = max( 0, 100 - $health['penalty'] );
        $health['status'] = $health['score'] >= 80 ? 'healthy' : ( $health['score'] >= 50 ? 'warning' : 'critical' );

        return $health;
    }

    /**
     * Check performance
     */
    private static function check_performance() {
        global $wpdb;

        $health = array(
            'status' => 'healthy',
            'score' => 100,
            'penalty' => 0,
            'issues' => array(),
            'details' => array(),
        );

        // Check slow queries
        $slow_queries = self::detect_slow_queries();
        if ( count( $slow_queries ) > 0 ) {
            $health['issues'][] = count( $slow_queries ) . " slow queries detected";
            $health['penalty'] += min( 20, count( $slow_queries ) * 5 );
        }
        $health['details']['slow_queries'] = $slow_queries;

        // Check table sizes
        foreach ( self::$monitored_tables as $table ) {
            $table_name = $wpdb->prefix . $table;
            $size = $wpdb->get_var( "
                SELECT ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) 
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
                AND TABLE_NAME = '$table_name'
            " );

            if ( $size ) {
                $health['details']['table_sizes'][$table] = $size . ' MB';
                
                // Warning for very large tables
                if ( $size > 100 ) {
                    $health['issues'][] = "Large table detected: $table ($size MB)";
                    $health['penalty'] += 5;
                }
            }
        }

        // Check memory usage
        $memory_limit = ini_get( 'memory_limit' );
        $memory_usage = memory_get_usage( true ) / 1024 / 1024;
        $health['details']['memory'] = array(
            'limit' => $memory_limit,
            'usage' => round( $memory_usage, 2 ) . ' MB',
        );

        $health['score'] = max( 0, 100 - $health['penalty'] );
        $health['status'] = $health['score'] >= 80 ? 'healthy' : ( $health['score'] >= 50 ? 'warning' : 'critical' );

        return $health;
    }

    /**
     * Detect slow queries
     */
    private static function detect_slow_queries() {
        global $wpdb;

        $slow_queries = array();

        // Test common queries
        $test_queries = array(
            'Orders count' => "SELECT COUNT(*) FROM {$wpdb->prefix}capitito_orders",
            'Today orders' => "SELECT COUNT(*) FROM {$wpdb->prefix}capitito_orders WHERE DATE(created_at) = CURDATE()",
            'Stock records' => "SELECT COUNT(*) FROM {$wpdb->prefix}capitito_stock",
        );

        foreach ( $test_queries as $name => $query ) {
            $start = microtime( true );
            $wpdb->get_var( $query );
            $duration = ( microtime( true ) - $start ) * 1000; // Convert to ms

            if ( $duration > 100 ) { // Queries over 100ms are considered slow
                $slow_queries[] = array(
                    'name' => $name,
                    'duration' => round( $duration, 2 ) . ' ms',
                );
            }
        }

        return $slow_queries;
    }

    /**
     * Check security
     */
    private static function check_security() {
        $health = array(
            'status' => 'healthy',
            'score' => 100,
            'penalty' => 0,
            'issues' => array(),
            'details' => array(),
        );

        // Check file permissions
        $sensitive_files = array(
            'capitito-inventory-management.php',
            'includes/class-database.php',
        );

        foreach ( $sensitive_files as $file ) {
            $filepath = CAPITITO_IMS_PLUGIN_DIR . $file;
            if ( file_exists( $filepath ) ) {
                $perms = substr( sprintf( '%o', fileperms( $filepath ) ), -4 );
                $health['details']['permissions'][$file] = $perms;

                // Check if file is world-writable
                if ( is_writable( $filepath ) && ( $perms === '0777' || $perms === '0666' ) ) {
                    $health['issues'][] = "Insecure permissions on $file ($perms)";
                    $health['penalty'] += 10;
                }
            }
        }

        // Check for debug mode in production
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $health['issues'][] = "WP_DEBUG is enabled";
            $health['penalty'] += 5;
        }

        $health['score'] = max( 0, 100 - $health['penalty'] );
        $health['status'] = $health['score'] >= 80 ? 'healthy' : ( $health['score'] >= 50 ? 'warning' : 'critical' );

        return $health;
    }

    /**
     * Check data integrity
     */
    private static function check_data_integrity() {
        global $wpdb;

        $health = array(
            'status' => 'healthy',
            'score' => 100,
            'penalty' => 0,
            'issues' => array(),
            'details' => array(),
        );

        // Check for orphaned records
        $orphaned_order_items = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$wpdb->prefix}capitito_order_items oi
            LEFT JOIN {$wpdb->prefix}capitito_orders o ON oi.order_id = o.id
            WHERE o.id IS NULL
        " );

        if ( $orphaned_order_items > 0 ) {
            $health['issues'][] = "$orphaned_order_items orphaned order items found";
            $health['penalty'] += 10;
        }
        $health['details']['orphaned_order_items'] = $orphaned_order_items;

        // Check for missing stock records
        $items_without_stock = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$wpdb->prefix}capitito_items i
            LEFT JOIN {$wpdb->prefix}capitito_stock s ON i.id = s.item_id AND s.stock_date = CURDATE()
            WHERE s.id IS NULL AND (i.is_archived IS NULL OR i.is_archived = 0)
        " );

        if ( $items_without_stock > 0 ) {
            $health['issues'][] = "$items_without_stock items missing today's stock record";
            $health['penalty'] += 5;
        }
        $health['details']['missing_stock_records'] = $items_without_stock;

        $health['score'] = max( 0, 100 - $health['penalty'] );
        $health['status'] = $health['score'] >= 80 ? 'healthy' : ( $health['score'] >= 50 ? 'warning' : 'critical' );

        return $health;
    }

    /**
     * Attempt automatic fixes
     */
    private static function attempt_auto_fix( $health_results ) {
        require_once CAPITITO_IMS_PLUGIN_DIR . 'includes/class-auto-repair.php';
        
        $fixes = Capitito_IMS_Auto_Repair::run_auto_repair( $health_results );
        
        // Log fixes
        $log = get_option( 'capitito_ims_auto_fix_log', array() );
        $log[] = array(
            'timestamp' => current_time( 'mysql' ),
            'fixes' => $fixes,
        );

        // Keep only last 50 logs
        if ( count( $log ) > 50 ) {
            $log = array_slice( $log, -50 );
        }

        update_option( 'capitito_ims_auto_fix_log', $log );

        return $fixes;
    }

    /**
     * Get health status badge
     */
    public static function get_health_badge() {
        $status = get_option( 'capitito_ims_health_status' );
        
        if ( ! $status ) {
            return '⚪ Unknown';
        }

        $score = $status['overall_score'];

        if ( $score >= 90 ) {
            return '🟢 Excellent (' . $score . '%)';
        } elseif ( $score >= 70 ) {
            return '🟡 Good (' . $score . '%)';
        } elseif ( $score >= 50 ) {
            return '🟠 Needs Attention (' . $score . '%)';
        } else {
            return '🔴 Critical (' . $score . '%)';
        }
    }

    /**
     * Display health warnings in admin
     */
    public static function display_health_warnings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $status = get_option( 'capitito_ims_health_status' );
        
        if ( ! $status || $status['overall_score'] >= 70 ) {
            return;
        }

        $message = '<strong>Capitito IMS Health Warning:</strong> System health is at ' . $status['overall_score'] . '%. ';
        $message .= '<a href="' . admin_url( 'admin.php?page=capitito-ims-health' ) . '">View Health Dashboard</a>';

        echo '<div class="notice notice-warning"><p>' . $message . '</p></div>';
    }

    /**
     * Get full health report
     */
    public static function get_health_report() {
        $status = get_option( 'capitito_ims_health_status' );
        
        if ( ! $status ) {
            $status = self::run_health_check();
        }

        return $status;
    }
}
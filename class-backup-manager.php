<?php
/**
 * Backup Manager Class
 * Handles automatic backups and data restoration
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Capitito_IMS_Backup_Manager {

    /**
     * Backup directory
     */
    private static $backup_dir = null;

    /**
     * Initialize backup manager
     */
    public static function init() {
        self::$backup_dir = WP_CONTENT_DIR . '/capitito-ims-backups/';

        // Create backup directory if it doesn't exist
        if ( ! file_exists( self::$backup_dir ) ) {
            wp_mkdir_p( self::$backup_dir );
            
            // Protect directory
            file_put_contents( self::$backup_dir . '.htaccess', 'Deny from all' );
            file_put_contents( self::$backup_dir . 'index.php', '<?php // Silence is golden' );
        }

        // Schedule automatic backups
        if ( ! wp_next_scheduled( 'capitito_ims_auto_backup' ) ) {
            wp_schedule_event( time(), 'daily', 'capitito_ims_auto_backup' );
        }

        add_action( 'capitito_ims_auto_backup', array( __CLASS__, 'create_automatic_backup' ) );
    }

    /**
     * Create automatic backup
     */
    public static function create_automatic_backup() {
        $backup = self::create_backup( 'automatic' );
        
        if ( $backup ) {
            // Keep only last 7 automatic backups
            self::cleanup_old_backups( 7 );
        }

        return $backup;
    }

    /**
     * Create database backup
     */
    public static function create_backup( $type = 'manual' ) {
        global $wpdb;

        $timestamp = current_time( 'Y-m-d_H-i-s' );
        $filename = "backup_{$type}_{$timestamp}.sql";
        $filepath = self::$backup_dir . $filename;

        $tables = array(
            $wpdb->prefix . 'capitito_items',
            $wpdb->prefix . 'capitito_orders',
            $wpdb->prefix . 'capitito_order_items',
            $wpdb->prefix . 'capitito_stock',
            $wpdb->prefix . 'capitito_financial_summary',
            $wpdb->prefix . 'capitito_reconciliation',
        );

        $sql_dump = "-- Capitito IMS Database Backup\n";
        $sql_dump .= "-- Created: " . current_time( 'mysql' ) . "\n";
        $sql_dump .= "-- Type: $type\n\n";

        foreach ( $tables as $table ) {
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
                continue;
            }

            // Get table structure
            $create_table = $wpdb->get_row( "SHOW CREATE TABLE $table", ARRAY_N );
            $sql_dump .= "\n\n-- Table structure for $table\n";
            $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql_dump .= $create_table[1] . ";\n\n";

            // Get table data
            $rows = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );
            
            if ( $rows ) {
                $sql_dump .= "-- Data for $table\n";
                
                foreach ( $rows as $row ) {
                    $values = array();
                    foreach ( $row as $value ) {
                        if ( $value === null ) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $wpdb->_real_escape( $value ) . "'";
                        }
                    }
                    $sql_dump .= "INSERT INTO `$table` VALUES (" . implode( ', ', $values ) . ");\n";
                }
            }
        }

        // Save backup file
        $saved = file_put_contents( $filepath, $sql_dump );

        if ( $saved ) {
            // Save backup metadata
            $backups = get_option( 'capitito_ims_backups', array() );
            $backups[] = array(
                'filename' => $filename,
                'filepath' => $filepath,
                'type' => $type,
                'size' => filesize( $filepath ),
                'created' => current_time( 'mysql' ),
            );
            update_option( 'capitito_ims_backups', $backups );

            return $filepath;
        }

        return false;
    }

    /**
     * Restore from backup
     */
    public static function restore_backup( $filename ) {
        global $wpdb;

        $filepath = self::$backup_dir . $filename;

        if ( ! file_exists( $filepath ) ) {
            return array( 'success' => false, 'message' => 'Backup file not found' );
        }

        $sql = file_get_contents( $filepath );

        if ( ! $sql ) {
            return array( 'success' => false, 'message' => 'Failed to read backup file' );
        }

        // Split SQL into individual queries
        $queries = array_filter( array_map( 'trim', explode( ';', $sql ) ) );

        $executed = 0;
        $failed = 0;

        foreach ( $queries as $query ) {
            if ( empty( $query ) || strpos( $query, '--' ) === 0 ) {
                continue;
            }

            $result = $wpdb->query( $query );
            
            if ( $result === false ) {
                $failed++;
            } else {
                $executed++;
            }
        }

        return array(
            'success' => true,
            'message' => "Restored successfully. Executed: $executed queries, Failed: $failed queries",
            'executed' => $executed,
            'failed' => $failed,
        );
    }

    /**
     * Get list of backups
     */
    public static function get_backups() {
        return get_option( 'capitito_ims_backups', array() );
    }

    /**
     * Delete backup
     */
    public static function delete_backup( $filename ) {
        $filepath = self::$backup_dir . $filename;

        if ( file_exists( $filepath ) ) {
            unlink( $filepath );
        }

        // Remove from metadata
        $backups = get_option( 'capitito_ims_backups', array() );
        $backups = array_filter( $backups, function( $backup ) use ( $filename ) {
            return $backup['filename'] !== $filename;
        } );
        update_option( 'capitito_ims_backups', array_values( $backups ) );

        return true;
    }

    /**
     * Download backup
     */
    public static function download_backup( $filename ) {
        $filepath = self::$backup_dir . $filename;

        if ( ! file_exists( $filepath ) ) {
            return false;
        }

        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $filepath ) );
        readfile( $filepath );
        exit;
    }

    /**
     * Cleanup old backups
     */
    private static function cleanup_old_backups( $keep = 7 ) {
        $backups = get_option( 'capitito_ims_backups', array() );

        // Filter automatic backups only
        $auto_backups = array_filter( $backups, function( $backup ) {
            return $backup['type'] === 'automatic';
        } );

        // Sort by creation date
        usort( $auto_backups, function( $a, $b ) {
            return strtotime( $b['created'] ) - strtotime( $a['created'] );
        } );

        // Delete old backups
        if ( count( $auto_backups ) > $keep ) {
            $to_delete = array_slice( $auto_backups, $keep );
            
            foreach ( $to_delete as $backup ) {
                self::delete_backup( $backup['filename'] );
            }
        }
    }
}
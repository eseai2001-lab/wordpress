<?php
/**
 * Database Update Handler
 * Adds payment_breakdown column to orders table
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Capitito_IMS_Database_Update {

    /**
     * Check and update database if needed
     */
    public static function check_and_update() {
        $current_db_version = get_option( 'capitito_ims_db_version', '0.0.0' );
        
        if ( version_compare( $current_db_version, '1.0.1', '<' ) ) {
            self::update_to_1_0_1();
        }
    }

    /**
     * Update to version 1.0.1 - Add payment_breakdown column
     */
    private static function update_to_1_0_1() {
        global $wpdb;
        
        $orders_table = $wpdb->prefix . 'capitito_ims_orders';
        
        // Check if column exists
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = %s 
                AND TABLE_NAME = %s 
                AND COLUMN_NAME = 'payment_breakdown'",
                DB_NAME,
                $orders_table
            )
        );
        
        if ( empty( $column_exists ) ) {
            // Add payment_breakdown column
            $wpdb->query(
                "ALTER TABLE $orders_table 
                ADD COLUMN payment_breakdown TEXT NULL AFTER payment_method"
            );
        }
        
        // Update database version
        update_option( 'capitito_ims_db_version', '1.0.1' );
    }
}
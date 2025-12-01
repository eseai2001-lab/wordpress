<?php
/**
 * Health Dashboard Admin Page
 * Registers admin menu and handles AJAX requests
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add health dashboard to admin menu
 */
function capitito_ims_health_dashboard_menu() {
    add_submenu_page(
        'capitito-ims',
        __( 'System Health', 'capitito-ims' ),
        __( 'System Health', 'capitito-ims' ),
        'manage_options',
        'capitito-ims-health',
        'capitito_ims_health_dashboard_page'
    );
}
add_action( 'admin_menu', 'capitito_ims_health_dashboard_menu', 20 );

/**
 * Render health dashboard page
 */
function capitito_ims_health_dashboard_page() {
    include CAPITITO_IMS_PLUGIN_DIR . 'templates/health-dashboard.php';
}

/**
 * AJAX: Run health check
 */
function capitito_ims_ajax_run_health_check() {
    check_ajax_referer( 'capitito_ims_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
    }

    $results = Capitito_IMS_Health_Monitor::run_health_check();

    wp_send_json_success( array(
        'message' => 'Health check completed',
        'results' => $results,
    ) );
}
add_action( 'wp_ajax_capitito_ims_run_health_check', 'capitito_ims_ajax_run_health_check' );

/**
 * AJAX: Force database sync
 */
function capitito_ims_ajax_force_database_sync() {
    check_ajax_referer( 'capitito_ims_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
    }

    $results = Capitito_IMS_Auto_Repair::force_database_sync();

    wp_send_json_success( array(
        'message' => 'Database sync completed',
        'results' => $results,
    ) );
}
add_action( 'wp_ajax_capitito_ims_force_database_sync', 'capitito_ims_ajax_force_database_sync' );

/**
 * AJAX: Optimize tables
 */
function capitito_ims_ajax_optimize_tables() {
    check_ajax_referer( 'capitito_ims_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
    }

    $results = Capitito_IMS_Auto_Repair::optimize_all_tables();

    wp_send_json_success( array(
        'message' => 'Tables optimized successfully',
        'results' => $results,
    ) );
}
add_action( 'wp_ajax_capitito_ims_optimize_tables', 'capitito_ims_ajax_optimize_tables' );

/**
 * AJAX: Create backup
 */
function capitito_ims_ajax_create_backup() {
    check_ajax_referer( 'capitito_ims_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
    }

    $backup = Capitito_IMS_Backup_Manager::create_backup( 'manual' );

    if ( $backup ) {
        wp_send_json_success( array(
            'message' => 'Backup created successfully',
            'filepath' => $backup,
        ) );
    } else {
        wp_send_json_error( array( 'message' => 'Failed to create backup' ) );
    }
}
add_action( 'wp_ajax_capitito_ims_create_backup', 'capitito_ims_ajax_create_backup' );

/**
 * AJAX: Download backup
 */
function capitito_ims_ajax_download_backup() {
    check_ajax_referer( 'capitito_ims_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }

    $filename = isset( $_GET['filename'] ) ? sanitize_file_name( $_GET['filename'] ) : '';

    if ( empty( $filename ) ) {
        wp_die( 'Invalid filename' );
    }

    Capitito_IMS_Backup_Manager::download_backup( $filename );
}
add_action( 'wp_ajax_capitito_ims_download_backup', 'capitito_ims_ajax_download_backup' );

/**
 * AJAX: Restore backup
 */
function capitito_ims_ajax_restore_backup() {
    check_ajax_referer( 'capitito_ims_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
    }

    $filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';

    if ( empty( $filename ) ) {
        wp_send_json_error( array( 'message' => 'Invalid filename' ) );
    }

    $result = Capitito_IMS_Backup_Manager::restore_backup( $filename );

    if ( $result['success'] ) {
        wp_send_json_success( $result );
    } else {
        wp_send_json_error( $result );
    }
}
add_action( 'wp_ajax_capitito_ims_restore_backup', 'capitito_ims_ajax_restore_backup' );

/**
 * AJAX: Delete backup
 */
function capitito_ims_ajax_delete_backup() {
    check_ajax_referer( 'capitito_ims_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
    }

    $filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';

    if ( empty( $filename ) ) {
        wp_send_json_error( array( 'message' => 'Invalid filename' ) );
    }

    $result = Capitito_IMS_Backup_Manager::delete_backup( $filename );

    wp_send_json_success( array( 'message' => 'Backup deleted successfully' ) );
}
add_action( 'wp_ajax_capitito_ims_delete_backup', 'capitito_ims_ajax_delete_backup' );

/**
 * AJAX: Clear caches
 */
function capitito_ims_ajax_clear_caches() {
    check_ajax_referer( 'capitito_ims_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
    }

    global $wpdb;

    // Clear expired transients
    $deleted = $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" );

    wp_send_json_success( array(
        'message' => 'Caches cleared successfully',
        'deleted' => $deleted,
    ) );
}
add_action( 'wp_ajax_capitito_ims_clear_caches', 'capitito_ims_ajax_clear_caches' );
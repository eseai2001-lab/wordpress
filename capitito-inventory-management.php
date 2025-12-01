<?php
/**
 * Plugin Name: Capitito Inventory Management System
 * Plugin URI: https://bendlesstech.com
 * Description: A comprehensive inventory management system for Capitito with orders, stock tracking, financial management, and reconciliation features.
 * Version: 2.0.0
 * Author: Okonudo EseAbasi
 * Author URI: https://bendlesstech.com
 * Company: Bendless Tech
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: capitito-ims
 * Domain Path: /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ✅ CRITICAL: Error handling for activation
if (!function_exists('capitito_ims_activation_check')) {
    function capitito_ims_activation_check() {
        $required_files = array(
            'includes/class-database.php',
            'includes/class-roles.php',
            'includes/class-orders.php',
            'includes/class-stock.php',
            'includes/class-financial.php',
            'includes/class-admin.php'
        );
        
        $missing = array();
        foreach ($required_files as $file) {
            if (!file_exists(plugin_dir_path(__FILE__) . $file)) {
                $missing[] = $file;
            }
        }
        
        if (!empty($missing)) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                '<h1>Plugin Activation Failed</h1>' .
                '<p><strong>Capitito IMS is missing required files:</strong></p>' .
                '<ul><li>' . implode('</li><li>', array_map('esc_html', $missing)) . '</li></ul>' .
                '<p><a href="' . admin_url('plugins.php') . '">← Back to Plugins</a></p>'
            );
        }
    }
}

// Define plugin constants
define( 'CAPITITO_IMS_VERSION', '2.0.0' );
define( 'CAPITITO_IMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CAPITITO_IMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CAPITITO_IMS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Capitito IMS Class
 */
class Capitito_IMS {

    /**
     * The single instance of the class
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // ✅ CRITICAL: Wrap everything in try-catch
        try {
            $this->includes();
            $this->init_hooks();
        } catch (Exception $e) {
            error_log('Capitito IMS Error: ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p><strong>Capitito IMS Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }

    /**
     * Get dashboard stats for home page
     */
    public function get_dashboard_stats() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');
        
        global $wpdb;
        $today = current_time('Y-m-d');
        
        // Today's orders count
        $orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}capitito_orders WHERE DATE(created_at) = %s",
            $today
        ));
        
        // Today's sales total
        $sales = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(grand_total) FROM {$wpdb->prefix}capitito_orders WHERE DATE(created_at) = %s",
            $today
        )) ?: 0;
        
        // Stock updated today
        $stock_updated = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}capitito_stock WHERE stock_date = %s",
            $today
        )) > 0;
        
        // Reconciled today
        $reconciled = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}capitito_reconciliation WHERE reconciliation_date = %s",
            $today
        )) > 0;
        
        wp_send_json_success(array(
            'orders' => $orders,
            'sales' => $sales,
            'stock_updated' => $stock_updated,
            'reconciled' => $reconciled
        ));
    }

    /**
     * Hide/Archive an item (soft-hide from Orders/Stock)
     */
    public function ajax_archive_item() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        if ($item_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid item ID'), 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'capitito_items';

        // Ensure archive columns exist
        $this->ensure_archive_columns();

        $updated = $wpdb->update(
            $table,
            array(
                'is_archived' => 1,
                'archived_at' => current_time('mysql'),
                'archived_by' => get_current_user_id(),
            ),
            array('id' => $item_id),
            array('%d','%s','%d'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Failed to hide item'));
        }

        error_log(sprintf(
            'Capitito IMS: Item #%d hidden by user #%d',
            $item_id,
            get_current_user_id()
        ));

        wp_send_json_success(array(
            'message' => 'Item hidden successfully',
            'item_id' => $item_id,
            'is_archived' => 1
        ));
    }

    /**
     * Unhide an item (show again in Orders/Stock)
     */
    public function ajax_unarchive_item() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        if ($item_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid item ID'), 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'capitito_items';

        $updated = $wpdb->update(
            $table,
            array(
                'is_archived' => 0,
                'archived_at' => null,
                'archived_by' => null,
            ),
            array('id' => $item_id),
            array('%d','%s','%d'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Failed to unhide item'));
        }

        error_log(sprintf(
            'Capitito IMS: Item #%d unhidden by user #%d',
            $item_id,
            get_current_user_id()
        ));

        wp_send_json_success(array(
            'message' => 'Item unhidden successfully',
            'item_id' => $item_id,
            'is_archived' => 0
        ));
    }

    /**
     * Ensure archive columns exist (self-healing)
     */
    private function ensure_archive_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'capitito_items';

        if (!$this->table_exists($table)) {
            return;
        }

        if (!$this->column_exists($table, 'is_archived')) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `price`");
            $wpdb->query("CREATE INDEX idx_items_is_archived ON {$table} (is_archived)");
        }
        if (!$this->column_exists($table, 'archived_at')) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL AFTER `is_archived`");
        }
        if (!$this->column_exists($table, 'archived_by')) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN `archived_by` BIGINT NULL DEFAULT NULL AFTER `archived_at`");
        }
    }

    /**
     * Include required files
     */
    private function includes() {
        // ✅ CORE REQUIRED FILES - MUST EXIST
        $required_files = array(
            'includes/class-database.php' => 'Database',
            'includes/class-roles.php'    => 'Roles',
            'includes/class-orders.php'   => 'Orders',
            'includes/class-stock.php'    => 'Stock',
            'includes/class-financial.php'=> 'Financial',
            'includes/class-admin.php'    => 'Admin'
        );

        foreach ($required_files as $file => $name) {
            $filepath = CAPITITO_IMS_PLUGIN_DIR . $file;
            if (!file_exists($filepath)) {
                throw new Exception("Missing required file: {$file} ({$name} Class)");
            }
            require_once $filepath;
            
            // ✅ Verify class loaded
            $class_name = 'Capitito_IMS_' . $name;
            if (!class_exists($class_name)) {
                throw new Exception("Class {$class_name} not found in {$file}");
            }
        }
        
        // Health Monitor System
        if (file_exists(CAPITITO_IMS_PLUGIN_DIR . 'includes/class-health-monitor.php')) {
            require_once CAPITITO_IMS_PLUGIN_DIR . 'includes/class-health-monitor.php';
            Capitito_IMS_Health_Monitor::init();
        }

        if (file_exists(CAPITITO_IMS_PLUGIN_DIR . 'includes/class-auto-repair.php')) {
            require_once CAPITITO_IMS_PLUGIN_DIR . 'includes/class-auto-repair.php';
        }

        if (file_exists(CAPITITO_IMS_PLUGIN_DIR . 'includes/class-backup-manager.php')) {
            require_once CAPITITO_IMS_PLUGIN_DIR . 'includes/class-backup-manager.php';
            Capitito_IMS_Backup_Manager::init();
        }

        // Health Dashboard Admin Page
        if (is_admin() && file_exists(CAPITITO_IMS_PLUGIN_DIR . 'admin/health-dashboard-page.php')) {
            require_once CAPITITO_IMS_PLUGIN_DIR . 'admin/health-dashboard-page.php';
        }
        
        // ✅ OPTIONAL: Reconciliation (gracefully handle if missing)
        $reconciliation_file = CAPITITO_IMS_PLUGIN_DIR . 'includes/class-reconciliation.php';
        if (file_exists($reconciliation_file)) {
            require_once $reconciliation_file;
        }
        
        // Admin files
        if (is_admin()) {
            $admin_files = array(
                'admin/admin-menu.php',
                'admin/admin-settings.php'
            );
            
            foreach ($admin_files as $file) {
                $filepath = CAPITITO_IMS_PLUGIN_DIR . $file;
                if (file_exists($filepath)) {
                    require_once $filepath;
                }
            }
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook( __FILE__, array( 'Capitito_IMS_Database', 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // Add custom viewport meta tag for desktop-style display on mobile
        add_action( 'wp_head', array( $this, 'add_viewport_meta' ), 1 );

        // Register shortcodes
        add_action( 'init', array( $this, 'register_shortcodes' ) );

        // AJAX actions
        $this->register_ajax_actions();

        // Daily cron for auto-reset
        add_action( 'capitito_ims_daily_reset', array( $this, 'daily_reset' ) );
        if ( ! wp_next_scheduled( 'capitito_ims_daily_reset' ) ) {
            wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', 'capitito_ims_daily_reset' );
        }

        // ✅ Automatic DB migrations on every request (front and admin), throttled
        add_action( 'init', array( $this, 'maybe_run_db_migrations' ) );
        add_action( 'admin_init', array( $this, 'maybe_run_db_migrations' ) );
        
        // Add health status to admin bar
        add_action( 'admin_bar_menu', array( $this, 'add_health_status_to_admin_bar' ), 999 );
    }
    
    /**
     * Add viewport meta tag for desktop-style display on mobile devices
     * Sets viewport width to 1024px so page shows all content at once without zooming
     */
    public function add_viewport_meta() {
        echo '<meta name="viewport" content="width=1024, initial-scale=0.35, maximum-scale=2.0, user-scalable=yes">' . "\n";
    }
    
    /**
     * Add health status to admin bar
     */
    public function add_health_status_to_admin_bar( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $health_badge = Capitito_IMS_Health_Monitor::get_health_badge();

        $wp_admin_bar->add_node( array(
            'id'    => 'capitito-ims-health',
            'title' => '🏥 ' . $health_badge,
            'href'  => admin_url( 'admin.php?page=capitito-ims-health' ),
            'meta'  => array(
                'title' => 'Capitito IMS Health Status',
            ),
        ) );
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style( 
            'capitito-ims-styles', 
            CAPITITO_IMS_PLUGIN_URL . 'assets/css/styles.css', 
            array(), 
            CAPITITO_IMS_VERSION
        );

        $js_file = CAPITITO_IMS_PLUGIN_DIR . 'assets/js/scripts.js';
        $js_url  = CAPITITO_IMS_PLUGIN_URL . 'assets/js/scripts.js';
        $version = file_exists( $js_file ) ? filemtime( $js_file ) : CAPITITO_IMS_VERSION;
        
        wp_enqueue_script( 
            'capitito-ims-scripts', 
            $js_url,
            array( 'jquery' ), 
            $version,
            true 
        );

        // ✅ Enqueue offline orders handler
        wp_enqueue_script(
            'capitito-ims-offline-orders',
            CAPITITO_IMS_PLUGIN_URL . 'assets/js/capitito-offline-orders.js',
            array('jquery', 'capitito-ims-scripts'),
            $version,
            true
        );

        wp_localize_script( 'capitito-ims-scripts', 'capitito_ims_ajax', array(
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'capitito_ims_nonce' ),
            'user_name'    => wp_get_current_user()->display_name,
            'user_login'   => wp_get_current_user()->user_login,
            'is_admin'     => current_user_can( 'manage_options' ),
            'plugin_url'   => CAPITITO_IMS_PLUGIN_URL,
            'current_date' => current_time( 'Y-m-d' ),
            'current_time' => current_time( 'H:i:s' ),
        ) );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'capitito-ims' ) === false ) {
            return;
        }

        wp_enqueue_style( 
            'capitito-ims-styles', 
            CAPITITO_IMS_PLUGIN_URL . 'assets/css/styles.css', 
            array(), 
            CAPITITO_IMS_VERSION
        );

        $js_file = CAPITITO_IMS_PLUGIN_DIR . 'assets/js/scripts.js';
        $js_url  = CAPITITO_IMS_PLUGIN_URL . 'assets/js/scripts.js';
        $version = file_exists( $js_file ) ? filemtime( $js_file ) : CAPITITO_IMS_VERSION;

        wp_enqueue_script( 
            'capitito-ims-admin-scripts', 
            $js_url,
            array( 'jquery' ), 
            $version,
            true 
        );

        wp_localize_script( 'capitito-ims-admin-scripts', 'capitito_ims_ajax', array(
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'capitito_ims_nonce' ),
            'user_name' => wp_get_current_user()->display_name,
            'user_login'=> wp_get_current_user()->user_login,
            'is_admin'  => current_user_can( 'manage_options' ),
        ) );
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        // Core shortcodes (always available)
        add_shortcode( 'ims_orders', array( 'Capitito_IMS_Orders', 'render_orders_page' ) );
        add_shortcode( 'ims_order_history', array( 'Capitito_IMS_Orders', 'render_order_history' ) );
        add_shortcode( 'ims_stock', array( 'Capitito_IMS_Stock', 'render_stock_page' ) );
        add_shortcode( 'ims_stock_history', array( 'Capitito_IMS_Stock', 'render_stock_history' ) );
        add_shortcode( 'ims_product_summary', array( 'Capitito_IMS_Orders', 'render_product_summary' ) );
        add_shortcode( 'ims_financial_summary', array( 'Capitito_IMS_Financial', 'render_financial_summary' ) );
        add_shortcode( 'ims_financial_history', array( 'Capitito_IMS_Financial', 'render_financial_history' ) );
        add_shortcode( 'ims_admin_panel', array( 'Capitito_IMS_Admin', 'render_admin_panel' ) );
        
        // ✅ ONLY register reconciliation shortcodes if class exists
        if (class_exists('Capitito_IMS_Reconciliation')) {
            add_shortcode( 'ims_reconciliation', array( 'Capitito_IMS_Reconciliation', 'render_reconciliation' ) );
            add_shortcode( 'ims_reconciliation_history', array( 'Capitito_IMS_Reconciliation', 'render_reconciliation_history' ) );
        }
        
        // ✅ NEW: Team Support shortcode
        add_shortcode( 'ims_team_support', array( $this, 'render_team_support' ) );
        
        // HOME PAGE SHORTCODE
        add_shortcode( 'capitito_home', array( $this, 'render_home_page' ) );
    }

    /**
     * Render home page shortcode
     */
    public function render_home_page($atts) {
        if (!is_user_logged_in()) {
            return '<div style="text-align: center; padding: 40px;">
                        <p style="font-size: 18px; color: #D02223;">⚠️ Please log in to access the dashboard.</p>
                        <a href="' . wp_login_url(get_permalink()) . '" style="display: inline-block; margin-top: 20px; padding: 12px 24px; background: #165E30; color: white; text-decoration: none; border-radius: 8px;">Login</a>
                    </div>';
        }

        $current_user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');

        ob_start();
        include CAPITITO_IMS_PLUGIN_DIR . 'templates/home-shortcode.php';
        return ob_get_clean();
    }

    /**
     * Render team support (placeholder)
     */
    public function render_team_support($atts) {
        return '<div style="padding:16px;background:#f7f7f7;border-radius:8px;">Team Support Area</div>';
    }

    /**
     * ✅ Automatic DB migrations (self-healing)
     * - Adds discount columns if missing
     * - Adds archive columns if missing
     * - Adds offline_synced column if missing
     * - Throttled via transient to avoid repeated ALTERs
     */
    public function maybe_run_db_migrations() {
        // throttle to at most once per minute across requests
        if ( get_transient('capitito_ims_db_migration_lock') ) {
            return;
        }
        set_transient('capitito_ims_db_migration_lock', 1, 60);

        // If version differs or columns missing, run migrations
        $stored = get_option('capitito_ims_db_version', '0.0.0');

        $ran = false;

        // Run discount migrations
        if ( version_compare($stored, '1.1.1', '<') || $this->discount_columns_missing() ) {
            $this->run_discount_migrations();
            $ran = true;
        }

        // Run archive migrations
        if ( version_compare($stored, '1.3.0', '<') || $this->archive_columns_missing() ) {
            $this->run_archive_migrations();
            $ran = true;
        }

        // ✅ Run offline sync migrations
        if ( version_compare($stored, '2.0.0', '<') || $this->offline_sync_column_missing() ) {
            $this->run_offline_sync_migrations();
            $ran = true;
        }

        // ✅ Run receipt number migrations
        if ( version_compare($stored, '2.1.0', '<') || $this->receipt_number_column_missing() ) {
            $this->run_receipt_number_migrations();
            $ran = true;
        }

        if ( $ran ) {
            update_option('capitito_ims_db_version', '2.1.0');
        }
    }

    private function discount_columns_missing() {
        global $wpdb;
        $checks = array(
            array($wpdb->prefix.'capitito_orders', 'total_discount'),
            array($wpdb->prefix.'capitito_ims_orders', 'total_discount'),
            array($wpdb->prefix.'capitito_order_items', 'discount_percent'),
            array($wpdb->prefix.'capitito_order_items', 'discount_amount'),
            array($wpdb->prefix.'capitito_ims_order_items', 'discount_percent'),
            array($wpdb->prefix.'capitito_ims_order_items', 'discount_amount'),
            array($wpdb->prefix.'capitito_financial_summary', 'discount_total'),
            array($wpdb->prefix.'capitito_ims_financial_summary', 'discount_total'),
        );

        foreach ($checks as $c) {
            list($table, $column) = $c;
            if ( $this->table_exists($table) && ! $this->column_exists($table, $column) ) {
                return true;
            }
        }
        return false;
    }

    private function run_discount_migrations() {
        global $wpdb;

        $alters = array();

        // Orders: total_discount
        $orders_main = $wpdb->prefix.'capitito_orders';
        $orders_ims  = $wpdb->prefix.'capitito_ims_orders';
        if ($this->table_exists($orders_main) && ! $this->column_exists($orders_main, 'total_discount')) {
            $alters[] = "ALTER TABLE {$orders_main} ADD COLUMN total_discount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER grand_total";
        }
        if ($this->table_exists($orders_ims) && ! $this->column_exists($orders_ims, 'total_discount')) {
            $alters[] = "ALTER TABLE {$orders_ims} ADD COLUMN total_discount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER grand_total";
        }

        // Order items: discount_percent, discount_amount
        $oi_main = $wpdb->prefix.'capitito_order_items';
        $oi_ims  = $wpdb->prefix.'capitito_ims_order_items';
        if ($this->table_exists($oi_main)) {
            if (! $this->column_exists($oi_main, 'discount_percent')) {
                $alters[] = "ALTER TABLE {$oi_main} ADD COLUMN discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER unit_price";
            }
            if (! $this->column_exists($oi_main, 'discount_amount')) {
                $alters[] = "ALTER TABLE {$oi_main} ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER discount_percent";
            }
        }
        if ($this->table_exists($oi_ims)) {
            if (! $this->column_exists($oi_ims, 'discount_percent')) {
                $alters[] = "ALTER TABLE {$oi_ims} ADD COLUMN discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER unit_price";
            }
            if (! $this->column_exists($oi_ims, 'discount_amount')) {
                $alters[] = "ALTER TABLE {$oi_ims} ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER discount_percent";
            }
        }

        // Financial summary: discount_total
        $fs_main = $wpdb->prefix.'capitito_financial_summary';
        $fs_ims  = $wpdb->prefix.'capitito_ims_financial_summary';
        if ($this->table_exists($fs_main) && ! $this->column_exists($fs_main, 'discount_total')) {
            $alters[] = "ALTER TABLE {$fs_main} ADD COLUMN discount_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER transfer";
        }
        if ($this->table_exists($fs_ims) && ! $this->column_exists($fs_ims, 'discount_total')) {
            $alters[] = "ALTER TABLE {$fs_ims} ADD COLUMN discount_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER transfer";
        }

        foreach ($alters as $sql) {
            $ok = $wpdb->query($sql);
            error_log('[Capitito IMS] Discount Migration: ' . $sql . ' => ' . var_export($ok, true));
        }
    }

    /**
     * Check if archive columns are missing
     */
    private function archive_columns_missing() {
        global $wpdb;
        $table = $wpdb->prefix . 'capitito_items';
        
        if (! $this->table_exists($table)) {
            return false;
        }

        if (! $this->column_exists($table, 'is_archived') ||
            ! $this->column_exists($table, 'archived_at') ||
            ! $this->column_exists($table, 'archived_by')) {
            return true;
        }
        
        return false;
    }

    /**
     * Run archive migrations
     */
    private function run_archive_migrations() {
        global $wpdb;
        $table = $wpdb->prefix . 'capitito_items';

        if (! $this->table_exists($table)) {
            return;
        }

        if (! $this->column_exists($table, 'is_archived')) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `price`");
            $wpdb->query("CREATE INDEX idx_items_is_archived ON {$table} (is_archived)");
            error_log('[Capitito IMS] Archive Migration: Added is_archived column');
        }
        if (! $this->column_exists($table, 'archived_at')) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL AFTER `is_archived`");
            error_log('[Capitito IMS] Archive Migration: Added archived_at column');
        }
        if (! $this->column_exists($table, 'archived_by')) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN `archived_by` BIGINT NULL DEFAULT NULL AFTER `archived_at`");
            error_log('[Capitito IMS] Archive Migration: Added archived_by column');
        }
    }

    /**
     * ✅ Check if offline_synced column is missing
     */
    private function offline_sync_column_missing() {
        global $wpdb;
        $checks = array(
            $wpdb->prefix.'capitito_orders',
            $wpdb->prefix.'capitito_ims_orders',
        );

        foreach ($checks as $table) {
            if ($this->table_exists($table) && !$this->column_exists($table, 'offline_synced')) {
                return true;
            }
        }
        return false;
    }

    /**
     * ✅ Run offline sync migrations
     */
    private function run_offline_sync_migrations() {
        global $wpdb;

        $orders_main = $wpdb->prefix.'capitito_orders';
        $orders_ims = $wpdb->prefix.'capitito_ims_orders';

        if ($this->table_exists($orders_main) && !$this->column_exists($orders_main, 'offline_synced')) {
            $wpdb->query("ALTER TABLE {$orders_main} ADD COLUMN offline_synced TINYINT(1) NOT NULL DEFAULT 0 AFTER created_at");
            error_log('[Capitito IMS] Offline Sync Migration: Added offline_synced to capitito_orders');
        }

        if ($this->table_exists($orders_ims) && !$this->column_exists($orders_ims, 'offline_synced')) {
            $wpdb->query("ALTER TABLE {$orders_ims} ADD COLUMN offline_synced TINYINT(1) NOT NULL DEFAULT 0 AFTER created_at");
            error_log('[Capitito IMS] Offline Sync Migration: Added offline_synced to capitito_ims_orders');
        }
    }

    /**
     * ✅ Check if receipt_number column is missing
     */
    private function receipt_number_column_missing() {
        global $wpdb;
        $checks = array(
            $wpdb->prefix.'capitito_orders',
            $wpdb->prefix.'capitito_ims_orders',
        );

        foreach ($checks as $table) {
            if ($this->table_exists($table) && !$this->column_exists($table, 'receipt_number')) {
                return true;
            }
        }
        return false;
    }

    /**
     * ✅ Run receipt number migrations
     */
    private function run_receipt_number_migrations() {
        global $wpdb;

        $orders_main = $wpdb->prefix.'capitito_orders';
        $orders_ims = $wpdb->prefix.'capitito_ims_orders';

        if ($this->table_exists($orders_main) && !$this->column_exists($orders_main, 'receipt_number')) {
            $wpdb->query("ALTER TABLE {$orders_main} ADD COLUMN receipt_number VARCHAR(20) NULL DEFAULT NULL AFTER id");
            $wpdb->query("CREATE INDEX idx_receipt_number ON {$orders_main} (receipt_number)");
            error_log('[Capitito IMS] Receipt Number Migration: Added receipt_number to capitito_orders');
        }

        if ($this->table_exists($orders_ims) && !$this->column_exists($orders_ims, 'receipt_number')) {
            $wpdb->query("ALTER TABLE {$orders_ims} ADD COLUMN receipt_number VARCHAR(20) NULL DEFAULT NULL AFTER id");
            $wpdb->query("CREATE INDEX idx_receipt_number ON {$orders_ims} (receipt_number)");
            error_log('[Capitito IMS] Receipt Number Migration: Added receipt_number to capitito_ims_orders');
        }
    }

    private function table_exists($table) {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
            DB_NAME, $table
        ));
        return (int)$exists > 0;
    }

    private function column_exists($table, $column) {
        global $wpdb;
        $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
        return !empty($col);
    }

    /**
     * ✅ Resolve orders tables helper
     */
    private function resolve_orders_tables() {
        global $wpdb;
        $main_orders = $wpdb->prefix . 'capitito_orders';
        $ims_orders = $wpdb->prefix . 'capitito_ims_orders';
        $main_items = $wpdb->prefix . 'capitito_order_items';
        $ims_items = $wpdb->prefix . 'capitito_ims_order_items';

        $main_exists = $this->table_exists($main_orders);
        $ims_exists = $this->table_exists($ims_orders);

        if ($main_exists && !$ims_exists) {
            return [$main_orders, $this->table_exists($main_items) ? $main_items : $ims_items];
        }
        if ($ims_exists && !$main_exists) {
            return [$ims_orders, $this->table_exists($ims_items) ? $ims_items : $main_items];
        }

        return [$main_orders, $main_items];
    }

    /**
     * ✅ AJAX: Sync offline order to server
     */
    public function ajax_sync_offline_order() {
        check_ajax_referer('capitito_ims_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        global $wpdb;
        $user = wp_get_current_user();

        // Get order data
        $items = isset($_POST['items']) && is_string($_POST['items'])
            ? json_decode(stripslashes($_POST['items']), true)
            : (isset($_POST['items']) ? (array)$_POST['items'] : array());
        
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
        $payment_breakdown_raw = isset($_POST['payment_breakdown']) ? stripslashes($_POST['payment_breakdown']) : '';
        $grand_total = isset($_POST['grand_total']) ? floatval($_POST['grand_total']) : 0;
        $total_discount = isset($_POST['total_discount']) ? floatval($_POST['total_discount']) : 0;
        $offline_order_id = isset($_POST['offline_order_id']) ? sanitize_text_field($_POST['offline_order_id']) : '';
        $created_at_offline = isset($_POST['created_at_offline']) ? sanitize_text_field($_POST['created_at_offline']) : current_time('mysql');

        if (empty($items) || !is_array($items)) {
            wp_send_json_error(array('message' => 'No items in order'));
        }

        list($order_table, $order_items_table) = $this->resolve_orders_tables();

        $today = current_time('Y-m-d');
        $now = current_time('mysql');

        // Insert order with offline_synced flag
        $order_data = array(
            'order_date' => $today,
            'staff_id' => $user->ID,
            'staff_name' => $user->display_name,
            'payment_method' => $payment_method,
            'grand_total' => $grand_total,
            'created_at' => $now,
            'offline_synced' => 1
        );
        $order_format = array('%s', '%d', '%s', '%s', '%f', '%s', '%d');

        // Add total_discount if column exists
        if ($this->column_exists($order_table, 'total_discount')) {
            $order_data['total_discount'] = $total_discount;
            $order_format[] = '%f';
        }

        // Add payment_breakdown if column exists
        if ($this->column_exists($order_table, 'payment_breakdown')) {
            $order_data['payment_breakdown'] = (!empty($payment_breakdown_raw) && $payment_breakdown_raw !== 'null') 
                ? $payment_breakdown_raw 
                : null;
            $order_format[] = '%s';
        }

        $inserted = $wpdb->insert($order_table, $order_data, $order_format);
        
        if (!$inserted) {
            wp_send_json_error(array('message' => 'Failed to sync order: ' . $wpdb->last_error));
        }

        $order_id = $wpdb->insert_id;

        // Insert order items
        $has_discount_cols = $this->column_exists($order_items_table, 'discount_percent') &&
                            $this->column_exists($order_items_table, 'discount_amount');

        foreach ($items as $item) {
            if (!isset($item['item_id']) || !isset($item['quantity'])) continue;

            $data = array(
                'order_id' => $order_id,
                'item_id' => intval($item['item_id']),
                'item_name' => sanitize_text_field($item['item_name'] ?? ''),
                'unit_price' => floatval($item['unit_price'] ?? 0),
                'quantity' => intval($item['quantity'] ?? 0),
                'total' => floatval($item['total'] ?? 0),
            );
            $format = array('%d', '%d', '%s', '%f', '%d', '%f');

            if ($has_discount_cols) {
                $data['discount_percent'] = floatval($item['discount_percent'] ?? 0);
                $data['discount_amount'] = floatval($item['discount_amount'] ?? 0);
                $format[] = '%f';
                $format[] = '%f';
            }

            $wpdb->insert($order_items_table, $data, $format);
        }

        wp_send_json_success(array(
            'message' => 'Offline order synced successfully',
            'order_id' => $order_id,
            'offline_order_id' => $offline_order_id
        ));
    }

    /**
     * Register AJAX actions
     */
    private function register_ajax_actions() {
        // Orders
        add_action( 'wp_ajax_capitito_ims_submit_order', array( 'Capitito_IMS_Orders', 'ajax_submit_order' ) );
        add_action( 'wp_ajax_capitito_ims_get_orders', array( 'Capitito_IMS_Orders', 'ajax_get_orders' ) );
        add_action( 'wp_ajax_capitito_ims_delete_order', array( 'Capitito_IMS_Orders', 'ajax_delete_order' ) );
        add_action( 'wp_ajax_capitito_ims_get_order', array( 'Capitito_IMS_Orders', 'ajax_get_order' ) );
        add_action( 'wp_ajax_capitito_ims_update_order', array( 'Capitito_IMS_Orders', 'ajax_update_order' ) );
        add_action( 'wp_ajax_capitito_ims_get_product_summary', array( 'Capitito_IMS_Orders', 'ajax_get_product_summary' ) );

        // ✅ Offline order sync
        add_action('wp_ajax_capitito_ims_sync_offline_order', array($this, 'ajax_sync_offline_order'));

        // Stock
        add_action( 'wp_ajax_capitito_ims_update_stock', array( 'Capitito_IMS_Stock', 'ajax_update_stock' ) );
        add_action( 'wp_ajax_capitito_ims_force_sync_stock', array( 'Capitito_IMS_Stock', 'ajax_force_sync_stock' ) );
        add_action( 'wp_ajax_capitito_ims_get_stock_history', array( 'Capitito_IMS_Stock', 'ajax_get_stock_history' ) );
        add_action( 'wp_ajax_capitito_ims_get_stock', array( 'Capitito_IMS_Stock', 'ajax_get_stock' ) );
        add_action( 'wp_ajax_capitito_ims_update_stock_history', array( 'Capitito_IMS_Stock', 'ajax_update_stock_history' ) );
        add_action( 'wp_ajax_capitito_ims_delete_stock_history', array( 'Capitito_IMS_Stock', 'ajax_delete_stock_history' ) );
        add_action( 'wp_ajax_capitito_ims_sync_stock_sales', array( 'Capitito_IMS_Stock', 'ajax_sync_sales' ) );

        // Financial
        add_action( 'wp_ajax_capitito_ims_submit_financial', array( 'Capitito_IMS_Financial', 'ajax_submit_financial' ) );
        add_action( 'wp_ajax_capitito_ims_delete_financial', array( 'Capitito_IMS_Financial', 'ajax_delete_financial' ) );
        add_action( 'wp_ajax_capitito_ims_get_financial', array( 'Capitito_IMS_Financial', 'ajax_get_financial' ) );
        add_action( 'wp_ajax_capitito_ims_update_financial', array( 'Capitito_IMS_Financial', 'ajax_update_financial' ) );
        add_action( 'wp_ajax_capitito_ims_get_financial_history', array( 'Capitito_IMS_Financial', 'ajax_get_financial_history' ) );

        // ✅ Discount endpoints (fix "Discount Today" and "View Discount Details" not working)
        add_action( 'wp_ajax_capitito_ims_get_daily_discount', array( 'Capitito_IMS_Financial', 'ajax_get_daily_discount' ) );
        add_action( 'wp_ajax_capitito_ims_get_daily_discounts', array( 'Capitito_IMS_Financial', 'ajax_get_daily_discounts' ) );
        add_action( 'wp_ajax_nopriv_capitito_ims_get_daily_discount', array( 'Capitito_IMS_Financial', 'ajax_get_daily_discount' ) );
        add_action( 'wp_ajax_nopriv_capitito_ims_get_daily_discounts', array( 'Capitito_IMS_Financial', 'ajax_get_daily_discounts' ) );

        // ✅ ONLY register reconciliation AJAX if class exists
        if (class_exists('Capitito_IMS_Reconciliation')) {
            add_action( 'wp_ajax_capitito_ims_submit_reconciliation', array( 'Capitito_IMS_Reconciliation', 'ajax_submit_reconciliation' ) );
            add_action( 'wp_ajax_capitito_ims_mark_evidence_sent', array( 'Capitito_IMS_Reconciliation', 'ajax_mark_evidence_sent' ) );
            add_action( 'wp_ajax_capitito_ims_get_reconciliation_record_by_date', array( 'Capitito_IMS_Reconciliation', 'ajax_get_reconciliation_record_by_date' ) );
            add_action( 'wp_ajax_capitito_ims_get_reconciliation_history', array( 'Capitito_IMS_Reconciliation', 'ajax_get_reconciliation_history' ) );
            add_action( 'wp_ajax_capitito_ims_delete_reconciliation', array( 'Capitito_IMS_Reconciliation', 'ajax_delete_reconciliation' ) );
            add_action( 'wp_ajax_capitito_ims_get_reconciliation_record', array( 'Capitito_IMS_Reconciliation', 'ajax_get_reconciliation_record' ) );
        }

        // Home Dashboard
        add_action('wp_ajax_capitito_ims_get_dashboard_stats', array($this, 'get_dashboard_stats'));

        // Admin items (Add, Update, Delete, Hide, Unhide)
        add_action( 'wp_ajax_capitito_ims_add_item', array( 'Capitito_IMS_Admin', 'ajax_add_item' ) );
        add_action( 'wp_ajax_capitito_ims_update_item', array( 'Capitito_IMS_Admin', 'ajax_update_item' ) );
        add_action( 'wp_ajax_capitito_ims_delete_item', array( 'Capitito_IMS_Admin', 'ajax_delete_item' ) );
        
        // ✅ Hide/Unhide items
        add_action( 'wp_ajax_capitito_ims_archive_item', array( $this, 'ajax_archive_item' ) );
        add_action( 'wp_ajax_capitito_ims_unarchive_item', array( $this, 'ajax_unarchive_item' ) );
    }

    /**
     * Daily reset function
     */
    public function daily_reset() {
        // Reset product summary for new day
        if (class_exists('Capitito_IMS_Orders')) {
            Capitito_IMS_Orders::daily_reset();
        }
        
        // Reset financial summary (carry over cash left to old cash)
        if (class_exists('Capitito_IMS_Financial') && method_exists('Capitito_IMS_Financial','daily_reset')) {
            Capitito_IMS_Financial::daily_reset();
        }
        
        // Stock daily reset
        if (class_exists('Capitito_IMS_Stock')) {
            Capitito_IMS_Stock::daily_reset();
        }
    }

    /**
     * Deactivation hook
     */
    public function deactivate() {
        wp_clear_scheduled_hook( 'capitito_ims_daily_reset' );
    }
}

/**
 * Initialize the plugin
 */
function capitito_ims_init() {
    return Capitito_IMS::get_instance();
}

// ✅ Start the plugin with error handling
add_action('plugins_loaded', 'capitito_ims_init');
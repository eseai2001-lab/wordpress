<?php
/**
 * Admin Menu Configuration
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add to admin menu temporarily
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Migrate Stock Data',
        'Migrate Stock Data',
        'manage_options',
        'migrate-stock',
        function() {
            if (isset($_POST['run_migration'])) {
                $plugin = Capitito_IMS_Plugin::get_instance();
                $result = $plugin->migrate_stock_carry_forward();
                echo '<div class="notice notice-success"><p>' . $result . '</p></div>';
            }
            ?>
            <div class="wrap">
                <h1>Migrate Stock Data</h1>
                <p>This will carry forward closing stock to next day's opening for all existing records.</p>
                <form method="post">
                    <button type="submit" name="run_migration" class="button button-primary">Run Migration</button>
                </form>
            </div>
            <?php
        }
    );
});

/**
 * Add admin menu
 */
function capitito_ims_admin_menu() {
    add_menu_page(
        __( 'Capitito IMS', 'capitito-ims' ),
        __( 'Capitito IMS', 'capitito-ims' ),
        'manage_capitito_ims',
        'capitito-ims',
        'capitito_ims_admin_page',
        'dashicons-cart',
        30
    );

    add_submenu_page(
        'capitito-ims',
        __( 'Dashboard', 'capitito-ims' ),
        __( 'Dashboard', 'capitito-ims' ),
        'manage_capitito_ims',
        'capitito-ims',
        'capitito_ims_admin_page'
    );

    add_submenu_page(
        'capitito-ims',
        __( 'Settings', 'capitito-ims' ),
        __( 'Settings', 'capitito-ims' ),
        'manage_capitito_ims',
        'capitito-ims-settings',
        'capitito_ims_settings_page'
    );

    add_submenu_page(
        'capitito-ims',
        __( 'Items Management', 'capitito-ims' ),
        __( 'Items Management', 'capitito-ims' ),
        'manage_capitito_ims',
        'capitito-ims-items',
        'capitito_ims_items_page'
    );
}
add_action( 'admin_menu', 'capitito_ims_admin_menu' );

/**
 * Admin dashboard page
 */
function capitito_ims_admin_page() {
    global $wpdb;

    // Get statistics
    $items_table = $wpdb->prefix . 'capitito_items';  // ✅ CORRECT
$orders_table = $wpdb->prefix . 'capitito_orders';  // ✅ CORRECT
$financial_table = $wpdb->prefix . 'capitito_financial_summary';  // ✅ CORRECT

    $today = current_time( 'Y-m-d' );
    $this_month = current_time( 'Y-m' );

    $total_orders_today = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $orders_table WHERE order_date = %s",
        $today
    ) );

    $total_sales_today = $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(grand_total) FROM $orders_table WHERE order_date = %s",
        $today
    ) );

    $total_orders_month = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $orders_table WHERE DATE_FORMAT(order_date, '%%Y-%%m') = %s",
        $this_month
    ) );

    $total_sales_month = $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(grand_total) FROM $orders_table WHERE DATE_FORMAT(order_date, '%%Y-%%m') = %s",
        $this_month
    ) );

    $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $items_table" );

    $todays_financial = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $financial_table WHERE summary_date = %s",
        $today
    ) );

    ?>
    <div class="wrap">
        <h1 style="color: #185D30;">
            <span class="dashicons dashicons-cart" style="font-size: 32px; margin-right: 10px;"></span>
            Capitito Inventory Management System
        </h1>
        
        <p style="font-size: 14px; color: #666;">
            Built by <strong>Okonudo EseAbasi</strong> from <strong>Bendless Tech</strong>
        </p>

        <hr style="margin: 20px 0;">

        <!-- Statistics Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            
            <!-- Today's Orders -->
            <div style="background: linear-gradient(135deg, #185D30 0%, #1a7a3d 100%); color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="font-size: 14px; opacity: 0.9; margin-bottom: 10px;">TODAY'S ORDERS</div>
                <div style="font-size: 42px; font-weight: 700; font-family: 'Courier New', monospace;"><?php echo $total_orders_today; ?></div>
            </div>

            <!-- Today's Sales -->
            <div style="background: linear-gradient(135deg, #185D30 0%, #1a7a3d 100%); color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="font-size: 14px; opacity: 0.9; margin-bottom: 10px;">TODAY'S SALES</div>
                <div style="font-size: 42px; font-weight: 700; font-family: 'Courier New', monospace;">₦<?php echo number_format( $total_sales_today, 2 ); ?></div>
            </div>

            <!-- This Month Orders -->
            <div style="background: linear-gradient(135deg, #D02126 0%, #a01a1f 100%); color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="font-size: 14px; opacity: 0.9; margin-bottom: 10px;">THIS MONTH'S ORDERS</div>
                <div style="font-size: 42px; font-weight: 700; font-family: 'Courier New', monospace;"><?php echo $total_orders_month; ?></div>
            </div>

            <!-- This Month Sales -->
            <div style="background: linear-gradient(135deg, #D02126 0%, #a01a1f 100%); color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="font-size: 14px; opacity: 0.9; margin-bottom: 10px;">THIS MONTH'S SALES</div>
                <div style="font-size: 42px; font-weight: 700; font-family: 'Courier New', monospace;">₦<?php echo number_format( $total_sales_month, 2 ); ?></div>
            </div>

        </div>

        <!-- Quick Actions -->
        <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2 style="color: #185D30; margin-top: 0;">Quick Actions</h2>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="<?php echo admin_url( 'admin.php?page=capitito-ims-items' ); ?>" class="button button-primary button-large">
                    Manage Items
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=capitito-ims-settings' ); ?>" class="button button-secondary button-large">
                    Settings
                </a>
            </div>
        </div>

        <!-- Today's Financial Summary -->
        <?php if ( $todays_financial ) : ?>
        <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
            <h2 style="color: #185D30; margin-top: 0;">Today's Financial Summary</h2>
            <table class="widefat" style="border: 1px solid #ddd;">
                <tr>
                    <td style="padding: 12px; font-weight: 600;">Total Sales:</td>
                    <td style="padding: 12px;">₦<?php echo number_format( $todays_financial->total_sales, 2 ); ?></td>
                    <td style="padding: 12px; font-weight: 600;">Card:</td>
                    <td style="padding: 12px;">₦<?php echo number_format( $todays_financial->card, 2 ); ?></td>
                </tr>
                <tr style="background: #f9f9f9;">
                    <td style="padding: 12px; font-weight: 600;">Cash:</td>
                    <td style="padding: 12px;">₦<?php echo number_format( $todays_financial->cash, 2 ); ?></td>
                    <td style="padding: 12px; font-weight: 600;">Transfer:</td>
                    <td style="padding: 12px;">₦<?php echo number_format( $todays_financial->transfer, 2 ); ?></td>
                </tr>
                <tr>
                    <td style="padding: 12px; font-weight: 600;">Expenses:</td>
                    <td style="padding: 12px;">₦<?php echo number_format( $todays_financial->expenses, 2 ); ?></td>
                    <td style="padding: 12px; font-weight: 600;">Cash Left:</td>
                    <td style="padding: 12px; background: #185D30; color: white; font-weight: 700;">₦<?php echo number_format( $todays_financial->cash_left, 2 ); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <!-- Shortcodes Reference -->
        <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2 style="color: #185D30; margin-top: 0;">Shortcodes Reference</h2>
            <p>Use these shortcodes to display inventory pages on your WordPress pages or posts:</p>
            
            <table class="widefat" style="border: 1px solid #ddd;">
                <thead>
                    <tr style="background: #185D30; color: white;">
                        <th style="padding: 12px;">Shortcode</th>
                        <th style="padding: 12px;">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 12px;"><code>[ims_orders]</code></td>
                        <td style="padding: 12px;">Orders page - Create new orders</td>
                    </tr>
                    <tr style="background: #f9f9f9;">
                        <td style="padding: 12px;"><code>[ims_order_history]</code></td>
                        <td style="padding: 12px;">Order history - View all past orders</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px;"><code>[ims_stock]</code></td>
                        <td style="padding: 12px;">Stock inventory management</td>
                    </tr>
                    <tr style="background: #f9f9f9;">
                        <td style="padding: 12px;"><code>[ims_product_summary]</code></td>
                        <td style="padding: 12px;">Daily product summary</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px;"><code>[ims_financial_summary]</code></td>
                        <td style="padding: 12px;">Daily financial summary</td>
                    </tr>
                    <tr style="background: #f9f9f9;">
                        <td style="padding: 12px;"><code>[ims_financial_history]</code></td>
                        <td style="padding: 12px;">Financial history records</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px;"><code>[ims_reconciliation]</code></td>
                        <td style="padding: 12px;">Reconciliation calendar</td>
                    </tr>
                    <tr style="background: #f9f9f9;">
                        <td style="padding: 12px;"><code>[ims_admin_panel]</code></td>
                        <td style="padding: 12px;">Admin panel (Admin only)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/**
 * Items management page
 */
function capitito_ims_items_page() {
    global $wpdb;
    $items_table = $wpdb->prefix . 'capitito_ims_items';
    $items = $wpdb->get_results( "SELECT * FROM $items_table ORDER BY name ASC" );
    ?>
    <div class="wrap">
        <h1 style="color: #185D30;">Manage Inventory Items</h1>
        
        <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
            <p>Total Items: <strong><?php echo count( $items ); ?></strong></p>
            
            <table class="widefat" style="border: 1px solid #ddd; margin-top: 20px;">
                <thead>
                    <tr style="background: #185D30; color: white;">
                        <th style="padding: 12px;">ID</th>
                        <th style="padding: 12px;">Item Name</th>
                        <th style="padding: 12px;">Price (₦)</th>
                        <th style="padding: 12px;">Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $items as $item ) : ?>
                    <tr>
                        <td style="padding: 12px;"><?php echo $item->id; ?></td>
                        <td style="padding: 12px;"><strong><?php echo esc_html( $item->name ); ?></strong></td>
                        <td style="padding: 12px;">₦<?php echo number_format( $item->price, 2 ); ?></td>
                        <td style="padding: 12px;"><?php echo date( 'M j, Y g:i A', strtotime( $item->created_at ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top: 20px;">
                <em>Use the <code>[ims_admin_panel]</code> shortcode on a page to manage items (Add, Edit, Delete).</em>
            </p>
        </div>
    </div>
    <?php
}
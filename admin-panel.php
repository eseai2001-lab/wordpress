<?php
/**
 * Admin Panel Template - WITH HIDE/UNHIDE FUNCTIONALITY
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_capitito_ims' ) ) {
    echo '<p>Unauthorized access</p>';
    return;
}

global $wpdb;
$items_table = $wpdb->prefix . 'capitito_items';

// Check if we should show hidden items
$show_hidden = isset($_GET['show_hidden']) && $_GET['show_hidden'] === '1';

// Query items based on show_hidden parameter
if ($show_hidden) {
    $items = $wpdb->get_results( "SELECT * FROM $items_table ORDER BY is_archived ASC, name ASC" );
} else {
    $items = $wpdb->get_results( "SELECT * FROM $items_table WHERE (is_archived IS NULL OR is_archived = 0) ORDER BY name ASC" );
}

$user = wp_get_current_user();
?>

<div class="capitito-ims-wrapper">
    <div class="capitito-ims-header">
        <div class="header-left">
            <h1>Capitito - Admin Panel</h1>
        </div>
        <div class="header-right">
            <div class="user-info">
                <span class="user-icon">👤</span>
                <span class="user-name"><?php echo esc_html( $user->display_name ); ?></span>
            </div>
            <div class="digital-clock" id="digitalClock"></div>
        </div>
    </div>

    <div class="capitito-ims-content">
        <div class="card">
            <div class="card-header">
                <h2>Manage Inventory Items</h2>
                <button type="button" class="btn btn-primary" id="addNewItemBtn">
                    <span class="btn-icon">➕</span> Add New Item
                </button>
            </div>
            <div class="card-body">
                
                <!-- Info Banners -->
                <div class="alert alert-info" style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin-bottom: 15px; border-radius: 8px;">
                    <strong>ℹ️ How it works:</strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <li><strong>Delete:</strong> Permanently removes items with NO orders (cannot be undone)</li>
                        <li><strong>Hide:</strong> Hides items from Orders/Stock but keeps historical data intact</li>
                        <li><strong>Items with orders cannot be deleted</strong> - use Hide instead</li>
                    </ul>
                </div>

                <!-- Show/Hide Toggle -->
                <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; background: #f8f9fa; padding: 10px 15px; border-radius: 8px; border: 2px solid #ddd;">
                        <input type="checkbox" id="toggleShowHidden" <?php echo $show_hidden ? 'checked' : ''; ?> style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="font-weight: 600;">Show hidden items</span>
                    </label>
                    <span style="color: #666; font-size: 13px;">Hidden items won't appear in Orders or Stock</span>
                </div>
                
                <!-- Items Table -->
                <div class="table-responsive">
                    <table class="capitito-table" id="adminItemsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Item Name</th>
                                <th>Price (₦)</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="adminItemsBody">
                            <?php foreach ( $items as $item ) : 
                                $is_hidden = isset($item->is_archived) && $item->is_archived == 1;
                            ?>
                            <tr data-item-id="<?php echo esc_attr( $item->id ); ?>" 
                                class="<?php echo $is_hidden ? 'hidden-item-row' : ''; ?>"
                                data-is-hidden="<?php echo $is_hidden ? '1' : '0'; ?>">
                                <td><?php echo esc_html( $item->id ); ?></td>
                                <td>
                                    <?php echo esc_html( $item->name ); ?>
                                    <?php if ($is_hidden): ?>
                                        <span class="badge-hidden">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td>₦<?php echo number_format( $item->price, 2 ); ?></td>
                                <td><?php echo date( 'M j, Y g:i A', strtotime( $item->created_at ) ); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-secondary edit-item-btn" 
                                            data-item-id="<?php echo esc_attr( $item->id ); ?>"
                                            data-item-name="<?php echo esc_attr( $item->name ); ?>"
                                            data-item-price="<?php echo esc_attr( $item->price ); ?>">
                                        ✏️ Edit
                                    </button>
                                    
                                    <?php if ($is_hidden): ?>
                                        <!-- Unhide button -->
                                        <button type="button" class="btn btn-sm btn-success unhide-item-btn" 
                                                data-item-id="<?php echo esc_attr( $item->id ); ?>"
                                                data-item-name="<?php echo esc_attr( $item->name ); ?>">
                                            👁️ Unhide
                                        </button>
                                    <?php else: ?>
                                        <!-- Hide button -->
                                        <button type="button" class="btn btn-sm btn-warning hide-item-btn" 
                                                data-item-id="<?php echo esc_attr( $item->id ); ?>"
                                                data-item-name="<?php echo esc_attr( $item->name ); ?>">
                                            🙈 Hide
                                        </button>
                                        <!-- Delete button (only for items without orders) -->
                                        <button type="button" class="btn btn-sm btn-danger delete-item-btn" 
                                                data-item-id="<?php echo esc_attr( $item->id ); ?>"
                                                data-item-name="<?php echo esc_attr( $item->name ); ?>">
                                            🗑️ Delete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div id="addItemModal" class="capitito-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Item</h3>
            <button type="button" class="modal-close" data-modal="addItemModal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addItemForm">
                <div class="form-group">
                    <label>Item Name:</label>
                    <input type="text" id="addItemName" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Price (₦):</label>
                    <input type="number" id="addItemPrice" name="price" class="form-control" step="0.01" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">💾</span> Add Item
                    </button>
                    <button type="button" class="btn btn-secondary" data-modal="addItemModal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div id="editItemModal" class="capitito-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Item</h3>
            <button type="button" class="modal-close" data-modal="editItemModal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editItemForm">
                <input type="hidden" id="editItemId" name="item_id">
                <div class="form-group">
                    <label>Item Name:</label>
                    <input type="text" id="editItemName" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Price (₦):</label>
                    <input type="number" id="editItemPrice" name="price" class="form-control" step="0.01" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">💾</span> Save Changes
                    </button>
                    <button type="button" class="btn btn-secondary" data-modal="editItemModal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Hidden item styling */
.hidden-item-row {
    opacity: 0.6;
    background: #f8f9fa !important;
}

.badge-hidden {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    background: #ff9800;
    color: white;
    font-size: 11px;
    font-weight: 700;
    margin-left: 8px;
}

.btn-warning {
    background: #ff9800;
    color: white;
}

.btn-warning:hover {
    background: #f57c00;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle show hidden items
    $('#toggleShowHidden').on('change', function() {
        const url = new URL(window.location.href);
        if (this.checked) {
            url.searchParams.set('show_hidden', '1');
        } else {
            url.searchParams.delete('show_hidden');
        }
        window.location.href = url.toString();
    });
});
</script>
<?php
/**
 * AJAX endpoints to archive/unarchive items.
 * Wire these with add_action in your plugin bootstrap.
 *
 * Table name: wp_capitito_ims_items (adjust if different).
 */

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_capitito_ims_archive_item', 'capitito_ims_archive_item');
add_action('wp_ajax_capitito_ims_unarchive_item', 'capitito_ims_unarchive_item');

function capitito_ims_archive_item() {
    check_ajax_referer('capitito_ims_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    if ($item_id <= 0) {
        wp_send_json_error(['message' => 'Invalid item ID'], 400);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'capitito_ims_items'; // Adjust if your table name differs

    $updated = $wpdb->update(
        $table,
        [
            'is_archived' => 1,
            'archived_at' => current_time('mysql'),
            'archived_by' => get_current_user_id(),
        ],
        ['id' => $item_id],
        ['%d','%s','%d'],
        ['%d']
    );

    if ($updated === false) {
        wp_send_json_error(['message' => 'Failed to archive item']);
    }

    wp_send_json_success([
        'message' => 'Item archived successfully',
        'item_id' => $item_id,
        'is_archived' => 1
    ]);
}

function capitito_ims_unarchive_item() {
    check_ajax_referer('capitito_ims_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    if ($item_id <= 0) {
        wp_send_json_error(['message' => 'Invalid item ID'], 400);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'capitito_ims_items'; // Adjust if your table name differs

    $updated = $wpdb->update(
        $table,
        [
            'is_archived' => 0,
            'archived_at' => null,
            'archived_by' => null,
        ],
        ['id' => $item_id],
        ['%d','%s','%d'],
        ['%d']
    );

    if ($updated === false) {
        wp_send_json_error(['message' => 'Failed to unarchive item']);
    }

    wp_send_json_success([
        'message' => 'Item unarchived successfully',
        'item_id' => $item_id,
        'is_archived' => 0
    ]);
}
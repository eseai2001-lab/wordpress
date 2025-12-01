<?php
/**
 * Ensure archived items never show up on Orders and Stock pages.
 * Update your item-fetching queries to include WHERE is_archived = 0.
 * Below are simple examples; integrate into your existing repository/helpers.
 */

if (!defined('ABSPATH')) exit;

function capitito_ims_get_active_items($args = []) {
    global $wpdb;
    $table = $wpdb->prefix . 'capitito_ims_items';
    $order_by = !empty($args['order_by']) ? esc_sql($args['order_by']) : 'created_at';
    $order    = !empty($args['order']) ? (strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC') : 'DESC';

    $sql = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE is_archived = %d ORDER BY {$order_by} {$order}",
        0
    );

    return $wpdb->get_results($sql);
}

/**
 * Example usage in endpoints that serve the Orders page and Stock page:
 * Replace your existing item lists with capitito_ims_get_active_items().
 *
 * For Stock History (past records), DO NOT filter by is_archived — keep history intact.
 */
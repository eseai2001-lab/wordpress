<?php
/**
 * User roles and capabilities management
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Capitito_IMS_Roles {

    /**
     * Create custom roles and capabilities
     */
    public static function create_roles() {
        // Add capabilities to administrator
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->add_cap( 'manage_capitito_ims' );
            $admin->add_cap( 'edit_capitito_ims_orders' );
            $admin->add_cap( 'delete_capitito_ims_orders' );
            $admin->add_cap( 'edit_capitito_ims_items' );
            $admin->add_cap( 'edit_capitito_ims_stock_opening' );
            $admin->add_cap( 'edit_capitito_ims_financial' );
        }

        // Create staff role
        add_role( 'capitito_ims_staff', __( 'Capitito Staff', 'capitito-ims' ), array(
            'read' => true,
            'view_capitito_ims' => true,
        ) );
    }

    /**
     * Remove custom roles
     */
    public static function remove_roles() {
        // Remove capabilities from administrator
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->remove_cap( 'manage_capitito_ims' );
            $admin->remove_cap( 'edit_capitito_ims_orders' );
            $admin->remove_cap( 'delete_capitito_ims_orders' );
            $admin->remove_cap( 'edit_capitito_ims_items' );
            $admin->remove_cap( 'edit_capitito_ims_stock_opening' );
            $admin->remove_cap( 'edit_capitito_ims_financial' );
        }

        // Remove staff role
        remove_role( 'capitito_ims_staff' );
    }

    /**
     * Check if current user is admin
     */
    public static function is_admin() {
        return current_user_can( 'manage_capitito_ims' );
    }

    /**
     * Check if current user is staff
     */
    public static function is_staff() {
        return current_user_can( 'view_capitito_ims' ) || self::is_admin();
    }
}
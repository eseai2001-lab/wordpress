<?php
/**
 * Admin Settings Page - WITH SUPPORT WHATSAPP
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings page
 */
function capitito_ims_settings_page() {
    // Save settings
    if ( isset( $_POST['capitito_ims_save_settings'] ) && check_admin_referer( 'capitito_ims_settings' ) ) {
        update_option( 'capitito_ims_whatsapp_number', sanitize_text_field( $_POST['whatsapp_number'] ) );
        update_option( 'capitito_ims_company_name', sanitize_text_field( $_POST['company_name'] ) );
        update_option( 'capitito_ims_support_whatsapp', sanitize_text_field( $_POST['support_whatsapp_number'] ) ); // ✅ NEW
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }

    $whatsapp_number = get_option( 'capitito_ims_whatsapp_number', '' );
    $company_name = get_option( 'capitito_ims_company_name', 'Capitito' );
    $support_whatsapp = get_option( 'capitito_ims_support_whatsapp', '2349019099708' ); // ✅ NEW
    ?>
    <div class="wrap">
        <h1 style="color: #185D30;">Capitito IMS Settings</h1>

        <form method="post" action="">
            <?php wp_nonce_field( 'capitito_ims_settings' ); ?>

            <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px; max-width: 800px;">
                
                <h2 style="color: #185D30; margin-top: 0;">General Settings</h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="company_name">Company Name</label>
                        </th>
                        <td>
                            <input type="text" id="company_name" name="company_name" value="<?php echo esc_attr( $company_name ); ?>" class="regular-text">
                            <p class="description">This name will appear on order confirmations.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="whatsapp_number">Reconciliation WhatsApp Number</label>
                        </th>
                        <td>
                            <input type="text" id="whatsapp_number" name="whatsapp_number" value="<?php echo esc_attr( $whatsapp_number ); ?>" class="regular-text" placeholder="234XXXXXXXXXX">
                            <p class="description">Enter WhatsApp number (with country code, no +). Example: 2348012345678</p>
                            <p class="description">This will be used for the "Send Evidence of Stock" button on the reconciliation page.</p>
                        </td>
                    </tr>

                    <!-- ✅ NEW: Support WhatsApp Number -->
                    <tr style="background: #e8f5e9;">
                        <th scope="row">
                            <label for="support_whatsapp_number">
                                <strong style="color: #185D30;">🛠️ Support WhatsApp Number</strong>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="support_whatsapp_number" 
                                   name="support_whatsapp_number" 
                                   value="<?php echo esc_attr( $support_whatsapp ); ?>" 
                                   class="regular-text" 
                                   placeholder="234XXXXXXXXXX"
                                   style="border: 2px solid #185D30; font-weight: 600;">
                            <p class="description">
                                <strong>📱 Team Support Contact Number</strong><br>
                                Enter WhatsApp number for the Team Support page (with country code, no +).<br>
                                Example: <code>2348012345678</code> for Nigerian numbers<br>
                                This number will be used for the "Contact Support" button on the <strong>Team Support</strong> page.
                            </p>
                            <p class="description" style="background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin-top: 10px;">
                                💡 <strong>Current Setting:</strong> <?php echo !empty($support_whatsapp) ? '<code style="background: #28a745; color: white; padding: 3px 8px; border-radius: 4px;">' . esc_html($support_whatsapp) . '</code>' : '<em>Not set (using default: 2349019099708)</em>'; ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2 style="color: #185D30; margin-top: 40px;">User Roles & Permissions</h2>
                
                <table class="widefat" style="border: 1px solid #ddd; margin-top: 20px;">
                    <thead>
                        <tr style="background: #185D30; color: white;">
                            <th style="padding: 12px;">Role</th>
                            <th style="padding: 12px;">Permissions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 12px;"><strong>Administrator</strong></td>
                            <td style="padding: 12px;">
                                ✓ Full access to all features<br>
                                ✓ Add, edit, delete items<br>
                                ✓ Edit/delete orders<br>
                                ✓ Edit stock opening values<br>
                                ✓ Edit/delete financial records<br>
                                ✓ <strong style="color: #185D30;">Access Team Support page</strong>
                            </td>
                        </tr>
                        <tr style="background: #f9f9f9;">
                            <td style="padding: 12px;"><strong>Capitito Staff</strong></td>
                            <td style="padding: 12px;">
                                ✓ Create orders<br>
                                ✓ View order history<br>
                                ✓ Update stock (imports, HQ returned)<br>
                                ✓ Submit financial summaries<br>
                                ✓ Reconcile daily records
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p style="margin-top: 20px;">
                    <?php submit_button( 'Save Settings', 'primary', 'capitito_ims_save_settings', false ); ?>
                </p>
            </div>
        </form>

        <!-- System Information -->
        <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px; max-width: 800px;">
            <h2 style="color: #185D30; margin-top: 0;">System Information</h2>
            
            <table class="widefat" style="border: 1px solid #ddd;">
                <tr>
                    <td style="padding: 12px; font-weight: 600;">Plugin Version:</td>
                    <td style="padding: 12px;"><?php echo CAPITITO_IMS_VERSION; ?></td>
                </tr>
                <tr style="background: #f9f9f9;">
                    <td style="padding: 12px; font-weight: 600;">Database Version:</td>
                    <td style="padding: 12px;"><?php echo get_option( 'capitito_ims_db_version', 'N/A' ); ?></td>
                </tr>
                <tr>
                    <td style="padding: 12px; font-weight: 600;">Developed By:</td>
                    <td style="padding: 12px;"><strong>Okonudo EseAbasi</strong> from <strong>Bendless Tech</strong></td>
                </tr>
                <tr style="background: #f9f9f9;">
                    <td style="padding: 12px; font-weight: 600;">WordPress Version:</td>
                    <td style="padding: 12px;"><?php echo get_bloginfo( 'version' ); ?></td>
                </tr>
                <tr>
                    <td style="padding: 12px; font-weight: 600;">PHP Version:</td>
                    <td style="padding: 12px;"><?php echo phpversion(); ?></td>
                </tr>
            </table>
        </div>
    </div>
    <?php
}
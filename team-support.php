<?php
/**
 * Team Support Page Template - PROFESSIONAL DESIGN
 * 24/7 Support for Capitito Inventory Management System
 * 
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 */

if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$support_whatsapp = get_option('capitito_ims_support_whatsapp', '2349019099708'); // ✅ Dynamic from settings
?>

<div class="capitito-ims-wrapper">
    <!-- Header -->
    <div class="capitito-ims-header">
        <div class="header-left">
            <h1>🛠️ Team Support Center</h1>
            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 14px;">24/7 Professional Technical Assistance</p>
        </div>
        <div class="header-right">
            <div class="user-info">
                <span class="user-icon">👤</span>
                <span class="user-name"><?php echo esc_html($current_user->display_name); ?></span>
            </div>
            <div class="digital-clock"></div>
        </div>
    </div>

    <!-- Support Status Banner -->
    <div class="support-status-banner">
        <div class="status-indicator-live"></div>
        <div class="status-text">
            <strong>🟢 SUPPORT TEAM ACTIVE 24/7</strong>
            <p>Our development team is available around the clock to assist with fixes, updates, and technical guidance</p>
        </div>
    </div>

    <!-- Main Support Card -->
    <div class="card">
        <div class="card-header">
            <h2>📱 Contact Technical Support</h2>
        </div>
        <div class="card-body">
            
            <!-- Support Overview -->
            <div class="support-overview-grid">
                <div class="support-feature-card">
                    <div class="feature-icon">🔧</div>
                    <h3>Bug Fixes & Patches</h3>
                    <p>Report any issues and receive immediate fixes from our development team</p>
                </div>

                <div class="support-feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>System Updates</h3>
                    <p>Request new features, enhancements, and system improvements</p>
                </div>

                <div class="support-feature-card">
                    <div class="feature-icon">📚</div>
                    <h3>Technical Guidance</h3>
                    <p>Get expert advice on system usage, best practices, and workflows</p>
                </div>

                <div class="support-feature-card">
                    <div class="feature-icon">🚀</div>
                    <h3>Performance Optimization</h3>
                    <p>Assistance with speed improvements and system optimization</p>
                </div>
            </div>

            <!-- WhatsApp Contact Section -->
            <div class="whatsapp-contact-section">
                <div class="whatsapp-hero">
                    <div class="whatsapp-icon-large">
                        <svg viewBox="0 0 32 32" width="120" height="120">
                            <path fill="#25D366" d="M16,0C7.164,0,0,7.164,0,16c0,2.784,0.736,5.488,2.128,7.84L0,32l8.4-2.064C10.656,31.28,13.296,32,16,32c8.836,0,16-7.164,16-16S24.836,0,16,0z"/>
                            <path fill="#FFFFFF" d="M25.408,22.528c-0.352,0.992-2.08,1.92-2.848,1.984c-0.736,0.064-1.408,0.336-4.736-0.992c-4-1.6-6.592-5.664-6.784-5.92c-0.192-0.256-1.568-2.08-1.568-3.968s0.992-2.816,1.344-3.2c0.352-0.384,0.768-0.48,1.024-0.48s0.512,0,0.736,0.016c0.224,0.016,0.528-0.08,0.832,0.624c0.32,0.736,1.088,2.624,1.184,2.816c0.096,0.192,0.16,0.416,0.032,0.672c-0.128,0.256-0.192,0.416-0.384,0.64c-0.192,0.224-0.4,0.496-0.576,0.672c-0.192,0.192-0.384,0.4-0.16,0.784c0.224,0.384,0.992,1.632,2.128,2.64c1.472,1.28,2.688,1.696,3.072,1.888c0.384,0.192,0.608,0.16,0.832-0.096c0.224-0.256,0.96-1.12,1.216-1.504c0.256-0.384,0.512-0.32,0.864-0.192c0.352,0.128,2.24,1.056,2.624,1.248c0.384,0.192,0.64,0.288,0.736,0.448C25.76,20.736,25.76,21.536,25.408,22.528z"/>
                        </svg>
                    </div>
                    <h2>Direct WhatsApp Support</h2>
                    <p style="font-size: 16px; color: #666; max-width: 600px; margin: 15px auto;">
                        Connect directly with <strong>Okonudo EseAbasi</strong>, the lead developer from <strong>Bendless Tech</strong>. 
                        Get instant responses and professional technical assistance for the Capitito Inventory Management System.
                    </p>
                </div>

                <div class="whatsapp-info-card">
                    <div class="info-row">
                        <span class="info-label">📞 Support Number:</span>
                        <span class="info-value"><?php echo $support_whatsapp; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">👨‍💻 Developer:</span>
                        <span class="info-value">Okonudo EseAbasi</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">🏢 Company:</span>
                        <span class="info-value">Bendless Tech</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">⏰ Availability:</span>
                        <span class="info-value availability-badge">24/7 Active Support</span>
                    </div>
                </div>

                <button type="button" id="openSupportWhatsApp" class="btn btn-whatsapp-large">
                    <svg viewBox="0 0 32 32" width="32" height="32" style="margin-right: 12px;">
                        <path fill="#FFFFFF" d="M16,0C7.164,0,0,7.164,0,16c0,2.784,0.736,5.488,2.128,7.84L0,32l8.4-2.064C10.656,31.28,13.296,32,16,32c8.836,0,16-7.164,16-16S24.836,0,16,0z M25.408,22.528c-0.352,0.992-2.08,1.92-2.848,1.984c-0.736,0.064-1.408,0.336-4.736-0.992c-4-1.6-6.592-5.664-6.784-5.92c-0.192-0.256-1.568-2.08-1.568-3.968s0.992-2.816,1.344-3.2c0.352-0.384,0.768-0.48,1.024-0.48s0.512,0,0.736,0.016c0.224,0.016,0.528-0.08,0.832,0.624c0.32,0.736,1.088,2.624,1.184,2.816c0.096,0.192,0.16,0.416,0.032,0.672c-0.128,0.256-0.192,0.416-0.384,0.64c-0.192,0.224-0.4,0.496-0.576,0.672c-0.192,0.192-0.384,0.4-0.16,0.784c0.224,0.384,0.992,1.632,2.128,2.64c1.472,1.28,2.688,1.696,3.072,1.888c0.384,0.192,0.608,0.16,0.832-0.096c0.224-0.256,0.96-1.12,1.216-1.504c0.256-0.384,0.512-0.32,0.864-0.192c0.352,0.128,2.24,1.056,2.624,1.248c0.384,0.192,0.64,0.288,0.736,0.448C25.76,20.736,25.76,21.536,25.408,22.528z"/>
                    </svg>
                    Open WhatsApp & Contact Support
                </button>

                <p style="text-align: center; color: #666; font-size: 13px; margin-top: 20px;">
                    💡 <strong>Tip:</strong> Have your system details ready (plugin version, WordPress version, issue description) for faster assistance
                </p>
            </div>

        </div>
    </div>

    <!-- Common Support Topics -->
    <div class="card">
        <div class="card-header">
            <h2>📖 Common Support Topics</h2>
        </div>
        <div class="card-body">
            <div class="support-topics-grid">
                
                <div class="topic-card">
                    <div class="topic-icon">🐛</div>
                    <h4>Reporting Bugs</h4>
                    <ul>
                        <li>Describe the issue in detail</li>
                        <li>Provide steps to reproduce</li>
                        <li>Include screenshots if possible</li>
                        <li>Mention what you expected vs. what happened</li>
                    </ul>
                </div>

                <div class="topic-card">
                    <div class="topic-icon">✨</div>
                    <h4>Feature Requests</h4>
                    <ul>
                        <li>Explain the feature you need</li>
                        <li>Describe how it would help your workflow</li>
                        <li>Provide examples if applicable</li>
                        <li>Discuss priority and timeline needs</li>
                    </ul>
                </div>

                <div class="topic-card">
                    <div class="topic-icon">⚙️</div>
                    <h4>System Configuration</h4>
                    <ul>
                        <li>User role and permission setup</li>
                        <li>Custom workflow adjustments</li>
                        <li>Integration with other systems</li>
                        <li>Performance optimization</li>
                    </ul>
                </div>

                <div class="topic-card">
                    <div class="topic-icon">📊</div>
                    <h4>Data & Reporting</h4>
                    <ul>
                        <li>Custom report generation</li>
                        <li>Data export/import assistance</li>
                        <li>Database cleanup and maintenance</li>
                        <li>Analytics and insights setup</li>
                    </ul>
                </div>

            </div>
        </div>
    </div>

    <!-- System Information (Useful for Support) -->
    <div class="card">
        <div class="card-header">
            <h2>🔍 System Information</h2>
            <p style="margin: 0; font-size: 14px; opacity: 0.9;">Share this info when contacting support</p>
        </div>
        <div class="card-body">
            <table class="capitito-table">
                <tr>
                    <td style="font-weight: 600; width: 40%;">Plugin Version:</td>
                    <td><code><?php echo defined('CAPITITO_IMS_VERSION') ? CAPITITO_IMS_VERSION : 'N/A'; ?></code></td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">WordPress Version:</td>
                    <td><code><?php echo get_bloginfo('version'); ?></code></td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">PHP Version:</td>
                    <td><code><?php echo phpversion(); ?></code></td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">Site URL:</td>
                    <td><code><?php echo get_site_url(); ?></code></td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">Your Username:</td>
                    <td><code><?php echo esc_html($current_user->user_login); ?></code></td>
                </tr>
            </table>

            <button type="button" id="copySysInfo" class="btn btn-secondary" style="margin-top: 20px;">
                📋 Copy System Info to Clipboard
            </button>
        </div>
    </div>

    <!-- Developer Credit -->
    <div class="developer-credit-card">
        <div class="credit-content">
            <div class="credit-logo">
                <div class="logo-circle">OE</div>
            </div>
            <div class="credit-text">
                <h3>Developed & Maintained By</h3>
                <p class="dev-name">Okonudo EseAbasi</p>
                <p class="company-name">Bendless Tech</p>
                <p class="tagline">Delivering Premium Inventory Management Solutions</p>
            </div>
        </div>
    </div>

</div>

<style>
/* Support Status Banner */
.support-status-banner {
    display: flex;
    align-items: center;
    gap: 20px;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 20px 30px;
    border-radius: var(--radius);
    margin-bottom: 30px;
    box-shadow: var(--shadow-3), 0 0 30px rgba(40, 167, 69, 0.3);
    animation: pulse-border 2s ease-in-out infinite;
}

@keyframes pulse-border {
    0%, 100% { box-shadow: var(--shadow-3), 0 0 30px rgba(40, 167, 69, 0.3); }
    50% { box-shadow: var(--shadow-4), 0 0 40px rgba(40, 167, 69, 0.5); }
}

.status-indicator-live {
    width: 20px;
    height: 20px;
    background: #fff;
    border-radius: 50%;
    animation: live-pulse 1.5s ease-in-out infinite;
    box-shadow: 0 0 0 0 rgba(255, 255, 255, 1);
}

@keyframes live-pulse {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 255, 255, 1); }
    50% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(255, 255, 255, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 255, 255, 0); }
}

.status-text strong {
    display: block;
    font-size: 18px;
    margin-bottom: 5px;
}

.status-text p {
    margin: 0;
    opacity: 0.95;
    font-size: 14px;
}

/* Support Features Grid */
.support-overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.support-feature-card {
    background: linear-gradient(135deg, var(--off-white) 0%, rgba(22, 94, 48, 0.03) 100%);
    padding: 30px;
    border-radius: var(--radius);
    border: 2px solid rgba(22, 94, 48, 0.1);
    text-align: center;
    transition: var(--transition-base);
    box-shadow: var(--shadow-2);
}

.support-feature-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-4);
    border-color: var(--primary-green);
}

.feature-icon {
    font-size: 48px;
    margin-bottom: 15px;
    animation: float 3s ease-in-out infinite;
}

.support-feature-card h3 {
    color: var(--primary-green);
    font-size: 18px;
    margin-bottom: 10px;
}

.support-feature-card p {
    color: var(--text-secondary);
    font-size: 14px;
    line-height: 1.6;
}

/* WhatsApp Contact Section */
.whatsapp-contact-section {
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
    padding: 50px 40px;
    border-radius: var(--radius);
    text-align: center;
    margin: 40px 0;
    border: 3px solid #25D366;
    box-shadow: var(--shadow-4);
}

.whatsapp-hero {
    margin-bottom: 40px;
}

.whatsapp-icon-large {
    margin: 0 auto 25px;
    animation: float 3s ease-in-out infinite;
    filter: drop-shadow(0 10px 20px rgba(37, 211, 102, 0.3));
}

.whatsapp-hero h2 {
    font-size: 32px;
    color: var(--primary-green);
    margin-bottom: 15px;
}

/* WhatsApp Info Card */
.whatsapp-info-card {
    background: white;
    border-radius: var(--radius);
    padding: 30px;
    max-width: 600px;
    margin: 0 auto 30px;
    box-shadow: var(--shadow-3);
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid rgba(22, 94, 48, 0.1);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: var(--text-secondary);
}

.info-value {
    font-weight: 700;
    color: var(--primary-green);
    font-size: 16px;
}

.availability-badge {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 6px 16px;
    border-radius: var(--radius-full);
    font-size: 13px;
}

/* WhatsApp Button */
.btn-whatsapp-large {
    background: linear-gradient(135deg, #25D366 0%, #20b358 100%) !important;
    color: white !important;
    font-size: 20px !important;
    padding: 20px 50px !important;
    border-radius: var(--radius) !important;
    border: none !important;
    box-shadow: var(--shadow-4), 0 0 30px rgba(37, 211, 102, 0.4) !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition-base);
    animation: whatsapp-pulse 2s ease-in-out infinite;
}

@keyframes whatsapp-pulse {
    0%, 100% { 
        transform: scale(1);
        box-shadow: var(--shadow-4), 0 0 30px rgba(37, 211, 102, 0.4);
    }
    50% { 
        transform: scale(1.05);
        box-shadow: var(--shadow-5), 0 0 40px rgba(37, 211, 102, 0.6);
    }
}

.btn-whatsapp-large:hover {
    background: linear-gradient(135deg, #20b358 0%, #1a9e4d 100%) !important;
    transform: translateY(-4px) scale(1.02) !important;
    box-shadow: var(--shadow-6), 0 0 50px rgba(37, 211, 102, 0.6) !important;
}

/* Support Topics Grid */
.support-topics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
}

.topic-card {
    background: var(--off-white);
    padding: 25px;
    border-radius: var(--radius);
    border: 2px solid rgba(22, 94, 48, 0.1);
    transition: var(--transition-base);
    box-shadow: var(--shadow-2);
}

.topic-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-4);
    border-color: var(--primary-green);
}

.topic-icon {
    font-size: 40px;
    margin-bottom: 15px;
}

.topic-card h4 {
    color: var(--primary-green);
    font-size: 18px;
    margin-bottom: 15px;
}

.topic-card ul {
    margin: 0;
    padding-left: 20px;
}

.topic-card li {
    color: var(--text-secondary);
    font-size: 14px;
    line-height: 1.8;
    margin-bottom: 8px;
}

/* Developer Credit Card */
.developer-credit-card {
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-green-dark) 100%);
    color: white;
    padding: 40px;
    border-radius: var(--radius);
    margin-top: 40px;
    box-shadow: var(--shadow-5);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.credit-content {
    display: flex;
    align-items: center;
    gap: 30px;
    max-width: 800px;
    margin: 0 auto;
}

.logo-circle {
    width: 100px;
    height: 100px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    font-weight: 700;
    border: 3px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(10px);
}

.credit-text h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.dev-name {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 5px 0;
}

.company-name {
    font-size: 20px;
    margin: 0 0 10px 0;
    opacity: 0.95;
}

.tagline {
    font-size: 14px;
    opacity: 0.85;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .support-status-banner {
        flex-direction: column;
        text-align: center;
    }

    .whatsapp-contact-section {
        padding: 30px 20px;
    }

    .credit-content {
        flex-direction: column;
        text-align: center;
    }

    .support-topics-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Open WhatsApp with pre-filled message
    $('#openSupportWhatsApp').on('click', function() {
        const supportNumber = '<?php echo $support_whatsapp; ?>';
        const pluginVersion = '<?php echo defined("CAPITITO_IMS_VERSION") ? CAPITITO_IMS_VERSION : "N/A"; ?>';
        const wpVersion = '<?php echo get_bloginfo("version"); ?>';
        const phpVersion = '<?php echo phpversion(); ?>';
        const siteUrl = '<?php echo get_site_url(); ?>';
        const username = '<?php echo esc_html($current_user->user_login); ?>';

        const message = `*CAPITITO IMS - SUPPORT REQUEST*\n\n` +
                       `🏢 Company: Capitito\n` +
                       `👤 User: ${username}\n\n` +
                       `*SYSTEM INFORMATION:*\n` +
                       `📦 Plugin Version: ${pluginVersion}\n` +
                       `🌐 WordPress: ${wpVersion}\n` +
                       `⚙️ PHP: ${phpVersion}\n` +
                       `🔗 Site: ${siteUrl}\n\n` +
                       `*ISSUE DESCRIPTION:*\n` +
                       `[Please describe your issue or request here]\n\n` +
                       `_Sent from Capitito IMS Support Center_`;

        const whatsappUrl = `https://wa.me/${supportNumber}?text=${encodeURIComponent(message)}`;
        window.open(whatsappUrl, '_blank');
    });

    // Copy system info to clipboard
    $('#copySysInfo').on('click', function() {
        const sysInfo = `Plugin Version: <?php echo defined("CAPITITO_IMS_VERSION") ? CAPITITO_IMS_VERSION : "N/A"; ?>\n` +
                       `WordPress Version: <?php echo get_bloginfo("version"); ?>\n` +
                       `PHP Version: <?php echo phpversion(); ?>\n` +
                       `Site URL: <?php echo get_site_url(); ?>\n` +
                       `Username: <?php echo esc_html($current_user->user_login); ?>`;

        navigator.clipboard.writeText(sysInfo).then(function() {
            const $btn = $('#copySysInfo');
            const originalText = $btn.html();
            $btn.html('✅ Copied to Clipboard!');
            setTimeout(function() {
                $btn.html(originalText);
            }, 2000);
        });
    });
});
</script>
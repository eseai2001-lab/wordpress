<?php
/**
 * Capitito IMS Home Page Shortcode Template
 * 
 * @package Capitito_IMS
 */

if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$is_admin = current_user_can('manage_options');
?>

<div class="capitito-ims-wrapper">
    <!-- Header with Welcome & Clock -->
    <div class="capitito-ims-header">
        <div class="header-left">
            <h1>🏪 CAPITITO IMS</h1>
            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 14px;">Inventory Management System</p>
        </div>
        <div class="header-right">
            <div class="user-info">
                <span class="user-icon">👤</span>
                <span class="user-name"><?php echo esc_html($current_user->display_name); ?></span>
            </div>
            <div class="digital-clock">00:00:00</div>
        </div>
    </div>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="welcome-content">
            <h2>Welcome Back, <?php echo esc_html($current_user->display_name); ?>! 👋</h2>
            <p>Ready to manage your inventory? Select a module below to get started.</p>
            <div class="welcome-stats">
                <div class="stat-mini">
                    <span class="stat-icon">📅</span>
                    <span class="stat-text"><?php echo date('l, F j, Y'); ?></span>
                </div>
                <div class="stat-mini">
                    <span class="stat-icon">⏰</span>
                    <span class="stat-text" id="currentTime">Loading...</span>
                </div>
                <div class="stat-mini">
                    <span class="stat-icon">👨‍💼</span>
                    <span class="stat-text"><?php echo $is_admin ? 'Administrator' : 'Staff Member'; ?></span>
                </div>
            </div>
        </div>
        <div class="welcome-illustration">
            <div class="floating-icon">📦</div>
            <div class="floating-icon">💰</div>
            <div class="floating-icon">📊</div>
        </div>
    </div>

    <!-- Quick Actions Grid - REORGANIZED -->
    <div class="quick-actions-grid">
        
        <!-- 1. New Order -->
        <a href="<?php echo home_url('/orders-page/'); ?>" class="action-card orders-card">
            <div class="card-icon">🛒</div>
            <h3>New Order</h3>
            <p>Create and manage customer orders</p>
            <div class="card-footer">
                <span class="card-badge">Primary</span>
                <span class="card-arrow">→</span>
            </div>
        </a>

        <!-- 2. Order History -->
        <a href="<?php echo home_url('/order-history/'); ?>" class="action-card history-card">
            <div class="card-icon">📋</div>
            <h3>Order History</h3>
            <p>View all past orders</p>
            <div class="card-footer">
                <span class="card-badge">Reports</span>
                <span class="card-arrow">→</span>
            </div>
        </a>

        <!-- 3. Stock Management -->
        <a href="<?php echo home_url('/stock-page/'); ?>" class="action-card stock-card">
            <div class="card-icon">📦</div>
            <h3>Stock Management</h3>
            <p>Update daily stock levels</p>
            <div class="card-footer">
                <span class="card-badge">Daily</span>
                <span class="card-arrow">→</span>
            </div>
        </a>

        <!-- 4. Stock History -->
        <a href="<?php echo home_url('/stock-history/'); ?>" class="action-card history-card">
            <div class="card-icon">📊</div>
            <h3>Stock History</h3>
            <p>View stock records</p>
            <div class="card-footer">
                <span class="card-badge">Reports</span>
                <span class="card-arrow">→</span>
            </div>
        </a>

        <!-- 5. Financial Summary -->
        <a href="<?php echo home_url('/financial-summary/'); ?>" class="action-card financial-card">
            <div class="card-icon">💰</div>
            <h3>Financial Summary</h3>
            <p>Submit daily financial report</p>
            <div class="card-footer">
                <span class="card-badge">Daily</span>
                <span class="card-arrow">→</span>
            </div>
        </a>

        <!-- 6. Financial History -->
        <a href="<?php echo home_url('/financial-history/'); ?>" class="action-card history-card">
            <div class="card-icon">💵</div>
            <h3>Financial History</h3>
            <p>View financial records</p>
            <div class="card-footer">
                <span class="card-badge">Reports</span>
                <span class="card-arrow">→</span>
            </div>
        </a>

        <!-- 7. Reconciliation -->
        <a href="<?php echo home_url('/reconciliation/'); ?>" class="action-card reconciliation-card">
            <div class="card-icon">✅</div>
            <h3>Reconciliation</h3>
            <p>Daily reconciliation calendar</p>
            <div class="card-footer">
                <span class="card-badge">Daily</span>
                <span class="card-arrow">→</span>
            </div>
        </a>

        <!-- 8. Reconciliation History -->
        <a href="<?php echo home_url('/reconciliation-history/'); ?>" class="action-card history-card">
            <div class="card-icon">📅</div>
            <h3>Reconciliation History</h3>
            <p>View reconciliation records</p>
            <div class="card-footer">
                <span class="card-badge">Reports</span>
                <span class="card-arrow">→</span>
            </div>
        </a>

        <!-- 9. Product Summary -->
        <a href="<?php echo home_url('/product-summary/'); ?>" class="action-card summary-card">
            <div class="card-icon">📈</div>
            <h3>Product Summary</h3>
            <p>View product sales summary</p>
            <div class="card-footer">
                <span class="card-badge">Analytics</span>
                <span class="card-arrow">→</span>
            </div>
        </a>

        <?php if ($is_admin): ?>
        <!-- 10. Admin Panel (Admin Only) -->
        <a href="<?php echo home_url('/admin-panel/'); ?>" class="action-card admin-card">
            <div class="card-icon">⚙️</div>
            <h3>Admin Panel</h3>
            <p>Manage items & settings</p>
            <div class="card-footer">
                <span class="card-badge">Admin Only</span>
                <span class="card-arrow">→</span>
            </div>
        </a>

        <!-- ✅ NEW: 11. Team Support (Admin Only) -->
        <a href="<?php echo home_url('/team-support/'); ?>" class="action-card support-card">
            <div class="card-icon">🛠️</div>
            <h3>Team Support</h3>
            <p>24/7 technical assistance from developers</p>
            <div class="card-footer">
                <span class="card-badge">Admin Only</span>
                <span class="card-arrow">→</span>
            </div>
        </a>
        <?php endif; ?>
        
    </div>

    <!-- Logout Button -->
    <div class="logout-section">
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="logout-btn">
            <span class="btn-icon">🚪</span>
            <span>Logout</span>
        </a>
    </div>

    <!-- Footer -->
    <div class="home-footer">
        <p>Capitito Inventory Management System v1.0.8</p>
        <p style="font-size: 13px; opacity: 0.8;">Built by Okonudo EseAbasi - Bendless Tech</p>
    </div>
</div>

<style>
/* Welcome Banner */
.welcome-banner {
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-green-dark) 100%);
    color: var(--off-white);
    padding: var(--space-5);
    border-radius: var(--radius);
    margin-bottom: var(--space-4);
    box-shadow: var(--shadow-4);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.welcome-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 4s ease-in-out infinite;
}

.welcome-content h2 {
    font-size: 32px;
    margin: 0 0 10px 0;
    color: var(--off-white);
}

.welcome-content p {
    font-size: 16px;
    margin: 0 0 20px 0;
    opacity: 0.9;
}

.welcome-stats {
    display: flex;
    gap: var(--space-3);
    flex-wrap: wrap;
}

.stat-mini {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.15);
    padding: 10px 20px;
    border-radius: var(--radius-full);
    backdrop-filter: blur(10px);
}

.stat-icon {
    font-size: 20px;
}

.stat-text {
    font-weight: 600;
    font-size: 14px;
}

.welcome-illustration {
    position: relative;
    width: 200px;
    height: 200px;
}

.floating-icon {
    position: absolute;
    font-size: 60px;
    animation: float 3s ease-in-out infinite;
}

.floating-icon:nth-child(1) {
    top: 0;
    left: 0;
    animation-delay: 0s;
}

.floating-icon:nth-child(2) {
    top: 50px;
    right: 20px;
    animation-delay: 0.5s;
}

.floating-icon:nth-child(3) {
    bottom: 0;
    left: 50px;
    animation-delay: 1s;
}

/* Quick Actions Grid - BETTER LAYOUT */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-3);
    margin-bottom: var(--space-4);
}

.action-card {
    background: var(--off-white);
    padding: var(--space-4);
    border-radius: var(--radius);
    box-shadow: var(--shadow-2);
    text-decoration: none;
    color: var(--text-primary);
    transition: var(--transition-base);
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
    display: block;
}

.action-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-green) 0%, var(--accent-red) 100%);
    transform: scaleX(0);
    transition: var(--transition-base);
}

.action-card:hover::before {
    transform: scaleX(1);
}

.action-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-5);
    border-color: var(--primary-green);
}

.card-icon {
    font-size: 48px;
    margin-bottom: var(--space-2);
    animation: bounce 2s ease-in-out infinite;
}

.action-card h3 {
    font-size: 20px;
    margin: 0 0 10px 0;
    color: var(--primary-green);
}

.action-card p {
    font-size: 14px;
    color: var(--text-secondary);
    margin: 0 0 20px 0;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-badge {
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-green-dark) 100%);
    color: var(--off-white);
    padding: 4px 12px;
    border-radius: var(--radius-full);
    font-size: 12px;
    font-weight: 600;
}

.orders-card .card-badge {
    background: linear-gradient(135deg, var(--accent-red) 0%, var(--accent-red-dark) 100%);
}

.admin-card .card-badge,
.support-card .card-badge {
    background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
}

.card-arrow {
    font-size: 24px;
    color: var(--primary-green);
    transition: var(--transition-base);
}

.action-card:hover .card-arrow {
    transform: translateX(5px);
}

/* Logout Section */
.logout-section {
    text-align: center;
    margin: var(--space-5) 0;
}

.logout-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, var(--accent-red) 0%, var(--accent-red-dark) 100%);
    color: var(--off-white);
    padding: 14px 32px;
    border-radius: var(--radius-full);
    text-decoration: none;
    font-weight: 600;
    font-size: 16px;
    box-shadow: var(--shadow-3);
    transition: var(--transition-base);
}

.logout-btn:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-5), 0 0 20px rgba(208, 34, 35, 0.4);
    color: var(--off-white);
}

/* Footer */
.home-footer {
    text-align: center;
    padding: var(--space-4);
    background: var(--neutral-bg);
    border-radius: var(--radius);
    margin-top: var(--space-4);
}

.home-footer p {
    margin: 5px 0;
    color: var(--text-secondary);
}

/* Animations */
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.1); }
}

/* Responsive */
@media (max-width: 768px) {
    .welcome-banner {
        flex-direction: column;
        text-align: center;
    }

    .welcome-illustration {
        display: none;
    }

    .quick-actions-grid {
        grid-template-columns: 1fr;
    }

    .welcome-stats {
        justify-content: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Update time display
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        $('#currentTime').text(timeString);
    }
    
    updateTime();
    setInterval(updateTime, 1000);
});
</script>
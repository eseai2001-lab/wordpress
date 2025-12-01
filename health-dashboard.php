<?php
/**
 * Health Dashboard Template
 * Displays system health status and provides manual repair tools
 *
 * @package Capitito_IMS
 * @author Okonudo EseAbasi - Bendless Tech
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    echo '<p>Unauthorized access</p>';
    return;
}

// Get health status
$health = Capitito_IMS_Health_Monitor::get_health_report();
$backups = Capitito_IMS_Backup_Manager::get_backups();
$auto_fix_log = get_option( 'capitito_ims_auto_fix_log', array() );

// Calculate health badge
$score = $health['overall_score'];
if ( $score >= 90 ) {
    $badge_color = '#28a745';
    $badge_text = '🟢 EXCELLENT';
} elseif ( $score >= 70 ) {
    $badge_color = '#ffc107';
    $badge_text = '🟡 GOOD';
} elseif ( $score >= 50 ) {
    $badge_color = '#ff9800';
    $badge_text = '🟠 NEEDS ATTENTION';
} else {
    $badge_color = '#D02126';
    $badge_text = '🔴 CRITICAL';
}

$user = wp_get_current_user();
?>

<div class="capitito-ims-wrapper">
    <!-- Header -->
    <div class="capitito-ims-header" style="background: linear-gradient(135deg, #185D30 0%, #0d3d1f 100%);">
        <div class="header-left">
            <h1>🏥 Capitito IMS - System Health Monitor</h1>
            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 14px;">Real-time monitoring, diagnostics & auto-repair system</p>
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

        <!-- Overall Health Card -->
        <div class="card" style="margin-bottom: 30px; background: linear-gradient(135deg, <?php echo $badge_color; ?> 0%, <?php echo $badge_color; ?>dd 100%); color: white; border: none;">
            <div class="card-body" style="padding: 40px; text-align: center;">
                <div style="font-size: 72px; margin-bottom: 20px;">
                    <?php 
                    if ( $score >= 90 ) echo '😊';
                    elseif ( $score >= 70 ) echo '🙂';
                    elseif ( $score >= 50 ) echo '😐';
                    else echo '😨';
                    ?>
                </div>
                <h2 style="margin: 0 0 10px 0; font-size: 48px; color: white;"><?php echo $score; ?>%</h2>
                <p style="margin: 0; font-size: 24px; font-weight: 700; opacity: 0.95;"><?php echo $badge_text; ?></p>
                <p style="margin: 10px 0 0 0; opacity: 0.85; font-size: 14px;">Last checked: <?php echo date( 'F j, Y g:i A', strtotime( $health['timestamp'] ) ); ?></p>
                <button type="button" id="runHealthCheckBtn" class="btn btn-lg" style="background: white; color: <?php echo $badge_color; ?>; margin-top: 20px; font-weight: 700; padding: 15px 40px; border: none; border-radius: 50px; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                    🔄 Run Health Check Now
                </button>
            </div>
        </div>

        <!-- Quick Stats Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px;">
            
            <?php
            $categories = array(
                'database' => array( 'icon' => '💾', 'name' => 'Database Health' ),
                'files' => array( 'icon' => '📁', 'name' => 'File Integrity' ),
                'performance' => array( 'icon' => '⚡', 'name' => 'Performance' ),
                'security' => array( 'icon' => '🔒', 'name' => 'Security' ),
                'data_integrity' => array( 'icon' => '🔗', 'name' => 'Data Integrity' ),
            );

            foreach ( $categories as $key => $cat ) {
                if ( ! isset( $health['checks'][$key] ) ) continue;
                
                $check = $health['checks'][$key];
                $status_color = $check['status'] === 'healthy' ? '#28a745' : ( $check['status'] === 'warning' ? '#ffc107' : '#D02126' );
                $status_icon = $check['status'] === 'healthy' ? '✅' : ( $check['status'] === 'warning' ? '⚠️' : '❌' );
                ?>
                <div class="card">
                    <div class="card-body" style="border-left: 4px solid <?php echo $status_color; ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <span style="font-size: 32px;"><?php echo $cat['icon']; ?></span>
                            <span style="font-size: 24px;"><?php echo $status_icon; ?></span>
                        </div>
                        <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #666;"><?php echo $cat['name']; ?></h3>
                        <div style="font-size: 36px; font-weight: 700; color: <?php echo $status_color; ?>; margin-bottom: 10px;">
                            <?php echo $check['score']; ?>%
                        </div>
                        <?php if ( count( $check['issues'] ) > 0 ) : ?>
                            <div style="background: #fff3cd; padding: 10px; border-radius: 5px; font-size: 13px;">
                                <strong><?php echo count( $check['issues'] ); ?> issue(s) detected</strong>
                            </div>
                        <?php else : ?>
                            <div style="color: #28a745; font-size: 13px; font-weight: 600;">
                                ✓ All checks passed
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>

        <!-- Recent Auto-Fixes -->
        <?php if ( ! empty( $auto_fix_log ) ) : ?>
        <div class="card" style="margin-bottom: 30px;">
            <div class="card-header" style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: white;">
                <h2 style="margin: 0; color: white;">🔧 Recent Auto-Fixes (Last 24 Hours)</h2>
            </div>
            <div class="card-body">
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php 
                    $recent_logs = array_slice( array_reverse( $auto_fix_log ), 0, 10 );
                    foreach ( $recent_logs as $log ) : 
                        $log_time = strtotime( $log['timestamp'] );
                        if ( time() - $log_time > 86400 ) continue; // Skip logs older than 24 hours
                        ?>
                        <div style="padding: 15px; border-left: 4px solid #2196F3; background: #f8f9fa; margin-bottom: 10px; border-radius: 5px;">
                            <div style="font-size: 12px; color: #666; margin-bottom: 8px;">
                                <?php echo date( 'M j, Y g:i A', $log_time ); ?>
                            </div>
                            <?php if ( ! empty( $log['fixes'] ) ) : ?>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <?php foreach ( $log['fixes'] as $fix ) : ?>
                                        <li style="color: #28a745; font-weight: 600;"><?php echo esc_html( $fix ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <p style="margin: 0; color: #666;">No fixes needed</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Detailed Issues -->
        <?php
        $all_issues = array();
        foreach ( $health['checks'] as $category => $check ) {
            foreach ( $check['issues'] as $issue ) {
                $all_issues[] = array(
                    'category' => $category,
                    'issue' => $issue,
                    'severity' => $check['status'],
                );
            }
        }
        ?>

        <?php if ( ! empty( $all_issues ) ) : ?>
        <div class="card" style="margin-bottom: 30px;">
            <div class="card-header" style="background: #ff9800; color: white;">
                <h2 style="margin: 0; color: white;">⚠️ Issues Detected (<?php echo count( $all_issues ); ?>)</h2>
            </div>
            <div class="card-body">
                <table class="capitito-table">
                    <thead>
                        <tr>
                            <th>Severity</th>
                            <th>Category</th>
                            <th>Issue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $all_issues as $item ) : ?>
                        <tr>
                            <td>
                                <?php 
                                if ( $item['severity'] === 'critical' ) {
                                    echo '<span style="background: #D02126; color: white; padding: 5px 10px; border-radius: 5px; font-weight: 700;">🔴 CRITICAL</span>';
                                } elseif ( $item['severity'] === 'warning' ) {
                                    echo '<span style="background: #ffc107; color: #000; padding: 5px 10px; border-radius: 5px; font-weight: 700;">⚠️ WARNING</span>';
                                } else {
                                    echo '<span style="background: #28a745; color: white; padding: 5px 10px; border-radius: 5px; font-weight: 700;">ℹ️ INFO</span>';
                                }
                                ?>
                            </td>
                            <td style="text-transform: uppercase; font-weight: 600; color: #666;">
                                <?php echo str_replace( '_', ' ', $item['category'] ); ?>
                            </td>
                            <td><?php echo esc_html( $item['issue'] ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- File Monitor -->
        <div class="card" style="margin-bottom: 30px;">
            <div class="card-header" style="background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); color: white;">
                <h2 style="margin: 0; color: white;">📁 File Monitor (<?php echo isset( $health['checks']['files']['details'] ) ? count( $health['checks']['files']['details'] ) : 0; ?> files tracked)</h2>
            </div>
            <div class="card-body">
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="capitito-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>File</th>
                                <th>Size</th>
                                <th>Last Modified</th>
                                <th>Permissions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ( isset( $health['checks']['files']['details'] ) ) :
                                foreach ( $health['checks']['files']['details'] as $file => $details ) : 
                                    $status_icon = $details['exists'] ? '✅' : '❌';
                                    $status_color = $details['exists'] ? '#28a745' : '#D02126';
                                    ?>
                                    <tr>
                                        <td style="text-align: center; font-size: 20px;"><?php echo $status_icon; ?></td>
                                        <td><code><?php echo esc_html( $file ); ?></code></td>
                                        <td><?php echo $details['exists'] ? size_format( $details['size'], 2 ) : 'N/A'; ?></td>
                                        <td><?php echo $details['exists'] ? date( 'M j, Y g:i A', $details['modified'] ) : 'N/A'; ?></td>
                                        <td>
                                            <?php 
                                            if ( $details['exists'] ) {
                                                $readable = $details['readable'] ? '✓ R' : '✗ R';
                                                $writable = $details['writable'] ? '✓ W' : '✗ W';
                                                echo "<span style='color: " . ( $details['readable'] ? '#28a745' : '#D02126' ) . ";'>$readable</span> ";
                                                echo "<span style='color: " . ( $details['writable'] ? '#28a745' : '#D02126' ) . ";'>$writable</span>";
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; 
                            endif;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Manual Repair Tools -->
        <div class="card" style="margin-bottom: 30px;">
            <div class="card-header" style="background: linear-gradient(135deg, #FF5722 0%, #E64A19 100%); color: white;">
                <h2 style="margin: 0; color: white;">🛠️ Manual Repair Tools</h2>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    
                    <button type="button" id="forceDatabaseSyncBtn" class="btn btn-primary btn-lg" style="padding: 20px; font-size: 16px;">
                        <span style="font-size: 24px; display: block; margin-bottom: 10px;">🔄</span>
                        Force Database Sync
                        <div style="font-size: 12px; opacity: 0.9; margin-top: 5px;">Add missing columns</div>
                    </button>

                    <button type="button" id="optimizeTablesBtn" class="btn btn-primary btn-lg" style="padding: 20px; font-size: 16px;">
                        <span style="font-size: 24px; display: block; margin-bottom: 10px;">⚡</span>
                        Optimize All Tables
                        <div style="font-size: 12px; opacity: 0.9; margin-top: 5px;">Improve performance</div>
                    </button>

                    <button type="button" id="createBackupBtn" class="btn btn-primary btn-lg" style="padding: 20px; font-size: 16px;">
                        <span style="font-size: 24px; display: block; margin-bottom: 10px;">💾</span>
                        Create Backup Now
                        <div style="font-size: 12px; opacity: 0.9; margin-top: 5px;">Manual backup</div>
                    </button>

                    <button type="button" id="clearCachesBtn" class="btn btn-primary btn-lg" style="padding: 20px; font-size: 16px;">
                        <span style="font-size: 24px; display: block; margin-bottom: 10px;">🧹</span>
                        Clear All Caches
                        <div style="font-size: 12px; opacity: 0.9; margin-top: 5px;">Clear transients</div>
                    </button>

                </div>
            </div>
        </div>

        <!-- Backup Manager -->
        <div class="card" style="margin-bottom: 30px;">
            <div class="card-header" style="background: linear-gradient(135deg, #00BCD4 0%, #0097A7 100%); color: white;">
                <h2 style="margin: 0; color: white;">💾 Backup Manager (<?php echo count( $backups ); ?> backups)</h2>
            </div>
            <div class="card-body">
                <?php if ( empty( $backups ) ) : ?>
                    <p style="text-align: center; padding: 40px; color: #999;">No backups found. Create your first backup using the button above.</p>
                <?php else : ?>
                    <table class="capitito-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Created</th>
                                <th>Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $backups_sorted = array_reverse( $backups );
                            foreach ( $backups_sorted as $backup ) : 
                                ?>
                                <tr>
                                    <td>
                                        <span style="background: <?php echo $backup['type'] === 'automatic' ? '#28a745' : '#2196F3'; ?>; color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px; font-weight: 700;">
                                            <?php echo strtoupper( $backup['type'] ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date( 'M j, Y g:i A', strtotime( $backup['created'] ) ); ?></td>
                                    <td><?php echo size_format( $backup['size'], 2 ); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-secondary download-backup-btn" data-filename="<?php echo esc_attr( $backup['filename'] ); ?>">
                                            📥 Download
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning restore-backup-btn" data-filename="<?php echo esc_attr( $backup['filename'] ); ?>">
                                            ↩️ Restore
                                        </button>
                                        <?php if ( $backup['type'] === 'manual' ) : ?>
                                        <button type="button" class="btn btn-sm btn-danger delete-backup-btn" data-filename="<?php echo esc_attr( $backup['filename'] ); ?>">
                                            🗑️ Delete
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Performance Metrics -->
        <?php if ( isset( $health['checks']['performance']['details'] ) ) : ?>
        <div class="card" style="margin-bottom: 30px;">
            <div class="card-header" style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); color: white;">
                <h2 style="margin: 0; color: white;">⚡ Performance Metrics</h2>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    
                    <?php if ( isset( $health['checks']['performance']['details']['memory'] ) ) : ?>
                    <div style="padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #FF9800;">
                        <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Memory Usage</div>
                        <div style="font-size: 24px; font-weight: 700; color: #FF9800;">
                            <?php echo $health['checks']['performance']['details']['memory']['usage']; ?>
                        </div>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                            Limit: <?php echo $health['checks']['performance']['details']['memory']['limit']; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ( isset( $health['checks']['performance']['details']['slow_queries'] ) ) : ?>
                    <div style="padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #FF9800;">
                        <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Slow Queries</div>
                        <div style="font-size: 24px; font-weight: 700; color: #FF9800;">
                            <?php echo count( $health['checks']['performance']['details']['slow_queries'] ); ?>
                        </div>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                            Detected
                        </div>
                    </div>
                    <?php endif; ?>

                </div>

                <?php if ( ! empty( $health['checks']['performance']['details']['slow_queries'] ) ) : ?>
                <div style="margin-top: 20px;">
                    <h4 style="color: #666; margin-bottom: 15px;">Slow Query Details:</h4>
                    <table class="capitito-table">
                        <thead>
                            <tr>
                                <th>Query</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $health['checks']['performance']['details']['slow_queries'] as $query ) : ?>
                            <tr>
                                <td><?php echo esc_html( $query['name'] ); ?></td>
                                <td style="color: #ff9800; font-weight: 700;"><?php echo $query['duration']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<style>
.capitito-table {
    width: 100%;
    border-collapse: collapse;
}

.capitito-table thead {
    background: #f8f9fa;
}

.capitito-table th {
    padding: 12px;
    text-align: left;
    font-weight: 700;
    border-bottom: 2px solid #dee2e6;
}

.capitito-table td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
}

.capitito-table tbody tr:hover {
    background: #f8f9fa;
}
</style>
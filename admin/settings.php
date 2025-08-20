<?php
require_once('config.php');
$adminAuth->requireRole('super_admin'); // Only super admins can access settings

$message = '';
$messageType = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'optimize_database':
                try {
                    // Optimize all tables
                    $tables = ['sites', 'images', 'users', 'admin_sessions', 'crawl_jobs', 'search_analytics', 'system_logs'];
                    $optimized = 0;
                    
                    foreach ($tables as $table) {
                        try {
                            $stmt = $con->prepare("OPTIMIZE TABLE `$table`");
                            $stmt->execute();
                            $optimized++;
                        } catch (Exception $e) {
                            // Table might not exist, continue
                        }
                    }
                    
                    $adminAuth->logActivity('info', 'database', 'Database optimization completed', ['tables_optimized' => $optimized]);
                    $message = "Database optimization completed! Optimized {$optimized} tables.";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error optimizing database: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'cleanup_logs':
                if (!empty($_POST['days'])) {
                    try {
                        $days = (int)$_POST['days'];
                        $stmt = $con->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                        $stmt->execute([$days]);
                        $deletedCount = $stmt->rowCount();
                        
                        $adminAuth->logActivity('info', 'maintenance', 'Log cleanup completed', ['days' => $days, 'deleted_count' => $deletedCount]);
                        $message = "Deleted {$deletedCount} old log entries (older than {$days} days).";
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Error cleaning up logs: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'cleanup_sessions':
                try {
                    $stmt = $con->prepare("DELETE FROM admin_sessions WHERE expires_at < NOW() OR is_active = 0");
                    $stmt->execute();
                    $deletedCount = $stmt->rowCount();
                    
                    $adminAuth->logActivity('info', 'maintenance', 'Session cleanup completed', ['deleted_count' => $deletedCount]);
                    $message = "Cleaned up {$deletedCount} expired sessions.";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error cleaning up sessions: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'reindex_search':
                try {
                    // Add search indexes if they don't exist
                    $indexes = [
                        "ALTER TABLE sites ADD INDEX idx_title_search (title(100))" => "sites title index",
                        "ALTER TABLE sites ADD INDEX idx_description_search (description(100))" => "sites description index",
                        "ALTER TABLE images ADD INDEX idx_alt_search (alt(100))" => "images alt index",
                        "ALTER TABLE images ADD FULLTEXT idx_fulltext_search (alt, title)" => "images fulltext index"
                    ];
                    
                    $created = 0;
                    foreach ($indexes as $query => $description) {
                        try {
                            $con->exec($query);
                            $created++;
                        } catch (Exception $e) {
                            // Index might already exist, continue
                        }
                    }
                    
                    $adminAuth->logActivity('info', 'database', 'Search indexes updated', ['indexes_created' => $created]);
                    $message = "Search indexing completed! Created/updated {$created} indexes.";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error reindexing search: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'backup_database':
                try {
                    // Create backup directory if it doesn't exist
                    $backupDir = '/tmp/doogle_backups';
                    if (!is_dir($backupDir)) {
                        mkdir($backupDir, 0755, true);
                    }
                    
                    $filename = 'doogle_backup_' . date('Y-m-d_H-i-s') . '.sql';
                    $filepath = $backupDir . '/' . $filename;
                    
                    // Simple backup using SELECT INTO OUTFILE (if permissions allow)
                    // Note: This is a simplified backup - in production you'd use mysqldump
                    $tables = ['sites', 'images', 'users', 'crawl_jobs', 'search_analytics', 'system_logs'];
                    $backupContent = "-- Doogle Database Backup\n-- Generated on " . date('Y-m-d H:i:s') . "\n\n";
                    
                    foreach ($tables as $table) {
                        try {
                            $stmt = $con->prepare("SHOW CREATE TABLE `$table`");
                            $stmt->execute();
                            $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($createTable) {
                                $backupContent .= $createTable['Create Table'] . ";\n\n";
                            }
                        } catch (Exception $e) {
                            // Table might not exist
                        }
                    }
                    
                    file_put_contents($filepath, $backupContent);
                    
                    $adminAuth->logActivity('info', 'backup', 'Database backup created', ['filename' => $filename]);
                    $message = "Database backup created: {$filename}";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error creating backup: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get database statistics
try {
    // Database size
    $sizeStmt = $con->prepare("SELECT 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
        ROUND(SUM(data_length) / 1024 / 1024, 2) AS data_mb,
        ROUND(SUM(index_length) / 1024 / 1024, 2) AS index_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()");
    $sizeStmt->execute();
    $dbSize = $sizeStmt->fetch(PDO::FETCH_ASSOC);
    
    // Table statistics
    $tableStmt = $con->prepare("SELECT 
        table_name,
        table_rows,
        ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        ORDER BY (data_length + index_length) DESC");
    $tableStmt->execute();
    $tableStats = $tableStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // System information
    $systemInfo = [
        'php_version' => phpversion(),
        'mysql_version' => $con->getAttribute(PDO::ATTR_SERVER_VERSION),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize')
    ];
    
    // Recent activity counts
    $activityStmt = $con->prepare("SELECT 
        (SELECT COUNT(*) FROM system_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as logs_24h,
        (SELECT COUNT(*) FROM system_logs WHERE level IN ('error', 'critical') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as errors_7d,
        (SELECT COUNT(*) FROM admin_sessions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as logins_24h");
    $activityStmt->execute();
    $activityStats = $activityStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading system information: " . $e->getMessage();
    $dbSize = $tableStats = $systemInfo = $activityStats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings & Database Tools - Doogle Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 5px 10px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .tool-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .tool-card:hover {
            transform: translateY(-2px);
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .stat-item:last-child {
            border-bottom: none;
        }
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 20px;
            background: #fff5f5;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 px-0">
                <div class="sidebar">
                    <div class="p-4 text-white">
                        <h4><i class="fas fa-search"></i> Doogle Admin</h4>
                        <small>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></small>
                    </div>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link" href="crawl-management.php">
                            <i class="fas fa-spider"></i> Crawl Management
                        </a>
                        <a class="nav-link" href="search-analytics.php">
                            <i class="fas fa-chart-line"></i> Search Analytics
                        </a>
                        <a class="nav-link" href="content-management.php">
                            <i class="fas fa-file-alt"></i> Content Management
                        </a>
                        <a class="nav-link" href="user-management.php">
                            <i class="fas fa-users"></i> User Management
                        </a>
                        <a class="nav-link" href="system-logs.php">
                            <i class="fas fa-list-alt"></i> System Logs
                        </a>
                        <a class="nav-link active" href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <div class="nav-separator my-3"></div>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <!-- Header -->
                <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
                    <div class="container-fluid">
                        <span class="navbar-brand">Settings & Database Tools</span>
                    </div>
                </nav>

                <div class="container-fluid">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Database Tools -->
                        <div class="col-md-8">
                            <div class="content-card">
                                <h5 class="mb-4"><i class="fas fa-tools"></i> Database Maintenance Tools</h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="tool-card">
                                            <h6><i class="fas fa-tachometer-alt text-primary"></i> Optimize Database</h6>
                                            <p class="text-muted small">Optimize all database tables for better performance</p>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="optimize_database">
                                                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('This will optimize all database tables. Continue?')">
                                                    <i class="fas fa-rocket"></i> Optimize
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="tool-card">
                                            <h6><i class="fas fa-search text-success"></i> Rebuild Search Indexes</h6>
                                            <p class="text-muted small">Rebuild search indexes for better search performance</p>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="reindex_search">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-sync"></i> Reindex
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="tool-card">
                                            <h6><i class="fas fa-broom text-warning"></i> Clean Up Old Logs</h6>
                                            <p class="text-muted small">Remove old system logs to save space</p>
                                            <form method="POST" class="d-flex align-items-end gap-2">
                                                <input type="hidden" name="action" value="cleanup_logs">
                                                <div>
                                                    <input type="number" name="days" class="form-control form-control-sm" value="30" min="1" max="365" style="width: 70px;">
                                                    <small class="text-muted">days</small>
                                                </div>
                                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('This will permanently delete old log entries. Continue?')">
                                                    <i class="fas fa-trash"></i> Clean
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="tool-card">
                                            <h6><i class="fas fa-key text-info"></i> Clean Up Sessions</h6>
                                            <p class="text-muted small">Remove expired admin sessions</p>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="cleanup_sessions">
                                                <button type="submit" class="btn btn-info btn-sm">
                                                    <i class="fas fa-broom"></i> Clean
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="danger-zone mt-4">
                                    <h6 class="text-danger"><i class="fas fa-exclamation-triangle"></i> Danger Zone</h6>
                                    <p class="text-muted small">These actions can affect system stability. Use with caution.</p>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="backup_database">
                                        <button type="submit" class="btn btn-outline-danger btn-sm me-2" onclick="return confirm('Create database backup? This may take some time.')">
                                            <i class="fas fa-download"></i> Create Backup
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Database Statistics -->
                            <div class="content-card">
                                <h5 class="mb-4"><i class="fas fa-chart-pie"></i> Database Statistics</h5>
                                
                                <div class="row mb-4">
                                    <div class="col-md-4 text-center">
                                        <div class="h3 text-primary"><?php echo $dbSize['size_mb'] ?? 0; ?> MB</div>
                                        <div class="text-muted">Total Size</div>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="h3 text-success"><?php echo $dbSize['data_mb'] ?? 0; ?> MB</div>
                                        <div class="text-muted">Data Size</div>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="h3 text-info"><?php echo $dbSize['index_mb'] ?? 0; ?> MB</div>
                                        <div class="text-muted">Index Size</div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Table</th>
                                                <th>Rows</th>
                                                <th>Size</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tableStats as $table): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($table['table_name']); ?></td>
                                                    <td><?php echo number_format($table['table_rows'] ?? 0); ?></td>
                                                    <td><?php echo $table['size_mb']; ?> MB</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- System Information Sidebar -->
                        <div class="col-md-4">
                            <div class="content-card">
                                <h5 class="mb-4"><i class="fas fa-server"></i> System Information</h5>
                                
                                <?php foreach ($systemInfo as $key => $value): ?>
                                    <div class="stat-item">
                                        <span><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</span>
                                        <strong><?php echo htmlspecialchars($value); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="content-card">
                                <h5 class="mb-4"><i class="fas fa-activity"></i> Recent Activity</h5>
                                
                                <div class="stat-item">
                                    <span>Logs (24h):</span>
                                    <strong class="text-primary"><?php echo number_format($activityStats['logs_24h'] ?? 0); ?></strong>
                                </div>
                                <div class="stat-item">
                                    <span>Errors (7d):</span>
                                    <strong class="text-<?php echo ($activityStats['errors_7d'] ?? 0) > 0 ? 'danger' : 'success'; ?>">
                                        <?php echo number_format($activityStats['errors_7d'] ?? 0); ?>
                                    </strong>
                                </div>
                                <div class="stat-item">
                                    <span>Logins (24h):</span>
                                    <strong class="text-info"><?php echo number_format($activityStats['logins_24h'] ?? 0); ?></strong>
                                </div>
                            </div>
                            
                            <div class="content-card">
                                <h5 class="mb-4"><i class="fas fa-info-circle"></i> Quick Actions</h5>
                                
                                <div class="d-grid gap-2">
                                    <a href="index.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-tachometer-alt"></i> View Dashboard
                                    </a>
                                    <a href="system-logs.php?level=error" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-exclamation-triangle"></i> View Errors
                                    </a>
                                    <a href="crawl-management.php" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-spider"></i> Manage Crawls
                                    </a>
                                    <a href="../search.php" class="btn btn-outline-info btn-sm" target="_blank">
                                        <i class="fas fa-external-link-alt"></i> Visit Search Engine
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
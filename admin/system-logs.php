<?php
require_once('config.php');
$adminAuth->requireAuth();

// Get filters from URL
$level = $_GET['level'] ?? 'all';
$category = $_GET['category'] ?? 'all';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Build where conditions
$whereConditions = ["DATE(created_at) BETWEEN ? AND ?"];
$params = [$startDate, $endDate];

if ($level !== 'all') {
    $whereConditions[] = "level = ?";
    $params[] = $level;
}

if ($category !== 'all') {
    $whereConditions[] = "category = ?";
    $params[] = $category;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

try {
    // Get total count
    $countStmt = $con->prepare("SELECT COUNT(*) as total FROM system_logs $whereClause");
    $countStmt->execute($params);
    $totalLogs = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalLogs / $limit);
    
    // Get logs with user info
    $logsStmt = $con->prepare("SELECT sl.*, u.username FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id $whereClause ORDER BY sl.created_at DESC LIMIT $limit OFFSET $offset");
    $logsStmt->execute($params);
    $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get log statistics
    $statsStmt = $con->prepare("SELECT level, COUNT(*) as count FROM system_logs $whereClause GROUP BY level");
    $statsStmt->execute($params);
    $levelStats = [];
    while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
        $levelStats[$row['level']] = $row['count'];
    }
    
    // Get categories
    $categoryStmt = $con->prepare("SELECT DISTINCT category FROM system_logs WHERE category IS NOT NULL ORDER BY category");
    $categoryStmt->execute();
    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get system health metrics
    $healthMetrics = [];
    
    // Database size
    $dbSizeStmt = $con->prepare("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE()");
    $dbSizeStmt->execute();
    $healthMetrics['db_size_mb'] = $dbSizeStmt->fetch(PDO::FETCH_ASSOC)['size_mb'] ?? 0;
    
    // Table counts
    $tablesStmt = $con->prepare("SELECT 
        (SELECT COUNT(*) FROM sites) as sites_count,
        (SELECT COUNT(*) FROM images) as images_count,
        (SELECT COUNT(*) FROM users) as users_count,
        (SELECT COUNT(*) FROM system_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)) as logs_today");
    $tablesStmt->execute();
    $tableCounts = $tablesStmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent errors
    $errorStmt = $con->prepare("SELECT COUNT(*) as error_count FROM system_logs WHERE level IN ('error', 'critical') AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $errorStmt->execute();
    $recentErrors = $errorStmt->fetch(PDO::FETCH_ASSOC)['error_count'];
    
} catch (Exception $e) {
    $error = "Error loading system logs: " . $e->getMessage();
    $logs = [];
    $levelStats = [];
    $categories = [];
    $healthMetrics = [];
    $tableCounts = [];
    $recentErrors = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Doogle Admin</title>
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
        .log-entry {
            border-left: 4px solid #dee2e6;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            border-radius: 0 10px 10px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .log-entry.info { border-left-color: #17a2b8; }
        .log-entry.warning { border-left-color: #ffc107; }
        .log-entry.error { border-left-color: #dc3545; }
        .log-entry.critical { border-left-color: #6f42c1; }
        
        .log-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }
        .log-message {
            font-weight: 500;
            margin-bottom: 5px;
        }
        .log-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .log-context {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
        }
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .health-metric {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            text-align: center;
        }
        .level-badge {
            font-size: 0.7rem;
            padding: 4px 8px;
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
                        <a class="nav-link active" href="system-logs.php">
                            <i class="fas fa-list-alt"></i> System Logs
                        </a>
                        <a class="nav-link" href="settings.php">
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
                        <span class="navbar-brand">System Logs & Monitoring</span>
                        <div class="navbar-nav ms-auto">
                            <span class="nav-text me-3">
                                <i class="fas fa-calendar"></i> <?php echo date('M j', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?>
                            </span>
                        </div>
                    </div>
                </nav>

                <div class="container-fluid">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- System Health Sidebar -->
                        <div class="col-md-3 mb-4">
                            <div class="content-card">
                                <h5 class="mb-4"><i class="fas fa-heartbeat"></i> System Health</h5>
                                
                                <div class="health-metric">
                                    <div class="h5 text-<?php echo $recentErrors > 10 ? 'danger' : ($recentErrors > 0 ? 'warning' : 'success'); ?>">
                                        <?php echo $recentErrors; ?>
                                    </div>
                                    <div class="text-muted small">Errors (24h)</div>
                                </div>
                                
                                <div class="health-metric">
                                    <div class="h5 text-info"><?php echo $healthMetrics['db_size_mb']; ?> MB</div>
                                    <div class="text-muted small">Database Size</div>
                                </div>
                                
                                <div class="health-metric">
                                    <div class="h5 text-primary"><?php echo formatNumber($tableCounts['sites_count'] ?? 0); ?></div>
                                    <div class="text-muted small">Sites Indexed</div>
                                </div>
                                
                                <div class="health-metric">
                                    <div class="h5 text-success"><?php echo formatNumber($tableCounts['images_count'] ?? 0); ?></div>
                                    <div class="text-muted small">Images Indexed</div>
                                </div>
                                
                                <div class="health-metric">
                                    <div class="h5 text-warning"><?php echo $tableCounts['logs_today'] ?? 0; ?></div>
                                    <div class="text-muted small">Logs Today</div>
                                </div>
                            </div>

                            <!-- Log Level Statistics -->
                            <div class="content-card">
                                <h6 class="mb-3"><i class="fas fa-chart-bar"></i> Log Levels</h6>
                                <?php
                                $levelConfig = [
                                    'info' => ['color' => 'info', 'icon' => 'info-circle'],
                                    'warning' => ['color' => 'warning', 'icon' => 'exclamation-triangle'],
                                    'error' => ['color' => 'danger', 'icon' => 'times-circle'],
                                    'critical' => ['color' => 'dark', 'icon' => 'skull']
                                ];
                                ?>
                                <?php foreach ($levelConfig as $logLevel => $config): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>
                                            <i class="fas fa-<?php echo $config['icon']; ?> text-<?php echo $config['color']; ?>"></i>
                                            <?php echo ucfirst($logLevel); ?>
                                        </span>
                                        <span class="badge bg-<?php echo $config['color']; ?>">
                                            <?php echo $levelStats[$logLevel] ?? 0; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Logs Content -->
                        <div class="col-md-9">
                            <!-- Filters -->
                            <div class="filter-card">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label for="level" class="form-label">Log Level</label>
                                        <select class="form-select" name="level" id="level">
                                            <option value="all" <?php echo $level === 'all' ? 'selected' : ''; ?>>All Levels</option>
                                            <option value="info" <?php echo $level === 'info' ? 'selected' : ''; ?>>Info</option>
                                            <option value="warning" <?php echo $level === 'warning' ? 'selected' : ''; ?>>Warning</option>
                                            <option value="error" <?php echo $level === 'error' ? 'selected' : ''; ?>>Error</option>
                                            <option value="critical" <?php echo $level === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="category" class="form-label">Category</label>
                                        <select class="form-select" name="category" id="category">
                                            <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $cat))); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date" id="start_date" value="<?php echo $startDate; ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date" id="end_date" value="<?php echo $endDate; ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-filter"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Log Entries -->
                            <div class="content-card">
                                <h5 class="mb-4">
                                    <i class="fas fa-list-alt"></i> System Logs 
                                    <span class="badge bg-secondary"><?php echo number_format($totalLogs); ?> entries</span>
                                </h5>

                                <?php if (!empty($logs)): ?>
                                    <?php foreach ($logs as $log): ?>
                                        <div class="log-entry <?php echo $log['level']; ?>">
                                            <div class="log-header">
                                                <div>
                                                    <span class="badge bg-<?php echo $levelConfig[$log['level']]['color'] ?? 'secondary'; ?> level-badge">
                                                        <i class="fas fa-<?php echo $levelConfig[$log['level']]['icon'] ?? 'circle'; ?>"></i>
                                                        <?php echo strtoupper($log['level']); ?>
                                                    </span>
                                                    <?php if ($log['category']): ?>
                                                        <span class="badge bg-outline-secondary ms-2"><?php echo htmlspecialchars($log['category']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y g:i:s A', strtotime($log['created_at'])); ?>
                                                </small>
                                            </div>
                                            
                                            <div class="log-message">
                                                <?php echo htmlspecialchars($log['message']); ?>
                                            </div>
                                            
                                            <div class="log-meta">
                                                <?php if ($log['username']): ?>
                                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($log['username']); ?>
                                                <?php endif; ?>
                                                <?php if ($log['ip_address'] && $log['ip_address'] !== 'unknown'): ?>
                                                    <i class="fas fa-globe ms-3"></i> <?php echo htmlspecialchars($log['ip_address']); ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($log['context']): ?>
                                                <div class="log-context">
                                                    <strong>Context:</strong><br>
                                                    <pre><?php echo htmlspecialchars(json_encode(json_decode($log['context']), JSON_PRETTY_PRINT)); ?></pre>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-list-alt fa-3x mb-3"></i>
                                        <p>No log entries found for the selected filters</p>
                                        <a href="system-logs.php" class="btn btn-outline-primary">
                                            <i class="fas fa-refresh"></i> Reset Filters
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <!-- Pagination -->
                                <?php if ($totalPages > 1): ?>
                                    <nav aria-label="Logs pagination" class="mt-4">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?level=<?php echo $level; ?>&category=<?php echo $category; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&page=<?php echo $page - 1; ?>">Previous</a>
                                            </li>
                                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?level=<?php echo $level; ?>&category=<?php echo $category; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?level=<?php echo $level; ?>&category=<?php echo $category; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&page=<?php echo $page + 1; ?>">Next</a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 60 seconds for real-time monitoring
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>
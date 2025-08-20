<?php
require_once('config.php');
$adminAuth->requireAuth();

// Get dashboard statistics
try {
    // Sites statistics
    $stmt = $con->prepare("SELECT COUNT(*) as total_sites FROM sites");
    $stmt->execute();
    $totalSites = $stmt->fetch(PDO::FETCH_ASSOC)['total_sites'];
    
    $stmt = $con->prepare("SELECT COUNT(*) as sites_today FROM sites WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $sitesToday = $stmt->fetch(PDO::FETCH_ASSOC)['sites_today'] ?? 0;
    
    // Images statistics
    $stmt = $con->prepare("SELECT COUNT(*) as total_images FROM images");
    $stmt->execute();
    $totalImages = $stmt->fetch(PDO::FETCH_ASSOC)['total_images'];
    
    $stmt = $con->prepare("SELECT COUNT(*) as images_today FROM images WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $imagesToday = $stmt->fetch(PDO::FETCH_ASSOC)['images_today'] ?? 0;
    
    $stmt = $con->prepare("SELECT COUNT(*) as broken_images FROM images WHERE broken = 1");
    $stmt->execute();
    $brokenImages = $stmt->fetch(PDO::FETCH_ASSOC)['broken_images'];
    
    // Search analytics (if table exists)
    $searchesToday = 0;
    $topSearchTerms = [];
    try {
        $stmt = $con->prepare("SELECT COUNT(*) as searches_today FROM search_analytics WHERE DATE(search_date) = CURDATE()");
        $stmt->execute();
        $searchesToday = $stmt->fetch(PDO::FETCH_ASSOC)['searches_today'] ?? 0;
        
        $stmt = $con->prepare("SELECT search_term, COUNT(*) as count FROM search_analytics WHERE search_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY search_term ORDER BY count DESC LIMIT 10");
        $stmt->execute();
        $topSearchTerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table might not exist yet
    }
    
    // Crawl jobs (if table exists)
    $activeCrawls = 0;
    $recentCrawls = [];
    try {
        $stmt = $con->prepare("SELECT COUNT(*) as active_crawls FROM crawl_jobs WHERE status IN ('pending', 'running')");
        $stmt->execute();
        $activeCrawls = $stmt->fetch(PDO::FETCH_ASSOC)['active_crawls'] ?? 0;
        
        $stmt = $con->prepare("SELECT * FROM crawl_jobs ORDER BY created_at DESC LIMIT 5");
        $stmt->execute();
        $recentCrawls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table might not exist yet
    }
    
    // System logs (if table exists)
    $recentLogs = [];
    try {
        $stmt = $con->prepare("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 10");
        $stmt->execute();
        $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table might not exist yet
    }
    
} catch (Exception $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doogle Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css" rel="stylesheet">
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
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stats-change {
            font-size: 0.8rem;
            margin-top: 10px;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .navbar-brand {
            font-weight: bold;
            color: #667eea !important;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        .activity-item {
            padding: 15px;
            border-left: 3px solid #667eea;
            margin-bottom: 15px;
            background: white;
            border-radius: 0 10px 10px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .activity-time {
            color: #6c757d;
            font-size: 0.8rem;
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
                        <a class="nav-link active" href="index.php">
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
                        <span class="navbar-brand">Dashboard</span>
                        <div class="navbar-nav ms-auto">
                            <span class="nav-text me-3">
                                <i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A'); ?>
                            </span>
                        </div>
                    </div>
                </nav>

                <div class="container-fluid">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-number text-primary"><?php echo formatNumber($totalSites); ?></div>
                                <div class="stats-label">Total Sites</div>
                                <div class="stats-change text-success">
                                    <i class="fas fa-arrow-up"></i> +<?php echo $sitesToday; ?> today
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-number text-success"><?php echo formatNumber($totalImages); ?></div>
                                <div class="stats-label">Total Images</div>
                                <div class="stats-change text-success">
                                    <i class="fas fa-arrow-up"></i> +<?php echo $imagesToday; ?> today
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-number text-warning"><?php echo formatNumber($searchesToday); ?></div>
                                <div class="stats-label">Searches Today</div>
                                <div class="stats-change text-info">
                                    <i class="fas fa-search"></i> Live tracking
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-number text-danger"><?php echo formatNumber($brokenImages); ?></div>
                                <div class="stats-label">Broken Images</div>
                                <div class="stats-change text-muted">
                                    <i class="fas fa-tools"></i> Need fixing
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Top Search Terms -->
                        <div class="col-md-6 mb-4">
                            <div class="chart-container">
                                <h5 class="mb-4"><i class="fas fa-fire"></i> Top Search Terms (7 days)</h5>
                                <?php if (!empty($topSearchTerms)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Search Term</th>
                                                    <th>Count</th>
                                                    <th>Trend</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($topSearchTerms as $index => $term): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge badge-secondary me-2"><?php echo $index + 1; ?></span>
                                                            <?php echo htmlspecialchars($term['search_term']); ?>
                                                        </td>
                                                        <td><?php echo number_format($term['count']); ?></td>
                                                        <td>
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar bg-primary" style="width: <?php echo min(100, ($term['count'] / $topSearchTerms[0]['count']) * 100); ?>%"></div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                                        <p>No search data available yet</p>
                                        <small>Search analytics will appear once users start searching</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Crawl Jobs -->
                        <div class="col-md-6 mb-4">
                            <div class="chart-container">
                                <h5 class="mb-4"><i class="fas fa-spider"></i> Recent Crawl Jobs</h5>
                                <?php if (!empty($recentCrawls)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>URL</th>
                                                    <th>Status</th>
                                                    <th>Progress</th>
                                                    <th>Created</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentCrawls as $crawl): ?>
                                                    <tr>
                                                        <td title="<?php echo htmlspecialchars($crawl['url']); ?>">
                                                            <?php echo htmlspecialchars(substr($crawl['url'], 0, 30)) . (strlen($crawl['url']) > 30 ? '...' : ''); ?>
                                                        </td>
                                                        <td><?php echo getStatusBadge($crawl['status']); ?></td>
                                                        <td>
                                                            <small><?php echo number_format($crawl['pages_crawled']); ?> pages</small>
                                                        </td>
                                                        <td><?php echo timeAgo($crawl['created_at']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-spider fa-3x mb-3"></i>
                                        <p>No crawl jobs yet</p>
                                        <a href="crawl-management.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus"></i> Start Crawling
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- System Activity -->
                    <div class="row">
                        <div class="col-12">
                            <div class="chart-container">
                                <h5 class="mb-4"><i class="fas fa-activity"></i> Recent System Activity</h5>
                                <?php if (!empty($recentLogs)): ?>
                                    <?php foreach ($recentLogs as $log): ?>
                                        <div class="activity-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong>
                                                        <?php
                                                        $icons = [
                                                            'info' => 'fas fa-info-circle text-info',
                                                            'warning' => 'fas fa-exclamation-triangle text-warning',
                                                            'error' => 'fas fa-times-circle text-danger',
                                                            'critical' => 'fas fa-skull text-danger'
                                                        ];
                                                        $icon = $icons[$log['level']] ?? 'fas fa-circle text-secondary';
                                                        ?>
                                                        <i class="<?php echo $icon; ?>"></i>
                                                        <?php echo htmlspecialchars($log['message']); ?>
                                                    </strong>
                                                    <div class="activity-time mt-1">
                                                        <i class="fas fa-clock"></i> <?php echo timeAgo($log['created_at']); ?>
                                                        <?php if ($log['category']): ?>
                                                            • <span class="badge badge-outline-secondary"><?php echo htmlspecialchars($log['category']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-list-alt fa-3x mb-3"></i>
                                        <p>No system activity logged yet</p>
                                        <small>System logs will appear here as activity occurs</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        // Auto-refresh dashboard every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
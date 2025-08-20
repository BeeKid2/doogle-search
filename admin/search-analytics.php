<?php
require_once('config.php');
$adminAuth->requireAuth();

// Get date range from URL parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

try {
    // Total searches in date range
    $stmt = $con->prepare("SELECT COUNT(*) as total_searches FROM search_analytics WHERE DATE(search_date) BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    $totalSearches = $stmt->fetch(PDO::FETCH_ASSOC)['total_searches'] ?? 0;
    
    // Unique search terms
    $stmt = $con->prepare("SELECT COUNT(DISTINCT search_term) as unique_terms FROM search_analytics WHERE DATE(search_date) BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    $uniqueTerms = $stmt->fetch(PDO::FETCH_ASSOC)['unique_terms'] ?? 0;
    
    // Average response time
    $stmt = $con->prepare("SELECT AVG(response_time_ms) as avg_response_time FROM search_analytics WHERE DATE(search_date) BETWEEN ? AND ? AND response_time_ms IS NOT NULL");
    $stmt->execute([$startDate, $endDate]);
    $avgResponseTime = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_response_time'] ?? 0, 2);
    
    // Click-through rate (searches that resulted in clicks)
    $stmt = $con->prepare("SELECT COUNT(*) as searches_with_clicks FROM search_analytics WHERE DATE(search_date) BETWEEN ? AND ? AND clicked_result_id IS NOT NULL");
    $stmt->execute([$startDate, $endDate]);
    $searchesWithClicks = $stmt->fetch(PDO::FETCH_ASSOC)['searches_with_clicks'] ?? 0;
    $clickThroughRate = $totalSearches > 0 ? round(($searchesWithClicks / $totalSearches) * 100, 2) : 0;
    
    // Top search terms
    $stmt = $con->prepare("SELECT search_term, COUNT(*) as count, search_type FROM search_analytics WHERE DATE(search_date) BETWEEN ? AND ? GROUP BY search_term, search_type ORDER BY count DESC LIMIT 20");
    $stmt->execute([$startDate, $endDate]);
    $topSearchTerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search trends by day
    $stmt = $con->prepare("SELECT DATE(search_date) as search_day, COUNT(*) as count FROM search_analytics WHERE DATE(search_date) BETWEEN ? AND ? GROUP BY DATE(search_date) ORDER BY search_day");
    $stmt->execute([$startDate, $endDate]);
    $dailyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search type distribution
    $stmt = $con->prepare("SELECT search_type, COUNT(*) as count FROM search_analytics WHERE DATE(search_date) BETWEEN ? AND ? GROUP BY search_type");
    $stmt->execute([$startDate, $endDate]);
    $searchTypeDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Most clicked results
    $stmt = $con->prepare("SELECT sa.clicked_result_type, sa.clicked_result_id, COUNT(*) as click_count, 
                          CASE 
                            WHEN sa.clicked_result_type = 'site' THEN s.title 
                            WHEN sa.clicked_result_type = 'image' THEN i.alt 
                          END as result_title,
                          CASE 
                            WHEN sa.clicked_result_type = 'site' THEN s.url 
                            WHEN sa.clicked_result_type = 'image' THEN i.imageUrl 
                          END as result_url
                          FROM search_analytics sa
                          LEFT JOIN sites s ON sa.clicked_result_type = 'site' AND sa.clicked_result_id = s.id
                          LEFT JOIN images i ON sa.clicked_result_type = 'image' AND sa.clicked_result_id = i.id
                          WHERE DATE(sa.search_date) BETWEEN ? AND ? AND sa.clicked_result_id IS NOT NULL
                          GROUP BY sa.clicked_result_type, sa.clicked_result_id
                          ORDER BY click_count DESC LIMIT 15");
    $stmt->execute([$startDate, $endDate]);
    $mostClickedResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Failed searches (searches with 0 results)
    $stmt = $con->prepare("SELECT search_term, COUNT(*) as count FROM search_analytics WHERE DATE(search_date) BETWEEN ? AND ? AND results_count = 0 GROUP BY search_term ORDER BY count DESC LIMIT 10");
    $stmt->execute([$startDate, $endDate]);
    $failedSearches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading analytics data: " . $e->getMessage();
    // Set default values
    $totalSearches = $uniqueTerms = $avgResponseTime = $clickThroughRate = 0;
    $topSearchTerms = $dailyTrends = $searchTypeDistribution = $mostClickedResults = $failedSearches = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Analytics - Doogle Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.3s ease;
            margin-bottom: 20px;
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
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        .date-filter {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
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
                        <a class="nav-link active" href="search-analytics.php">
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
                        <span class="navbar-brand">Search Analytics</span>
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
                            <br><small>Note: Analytics data will be available once the search_analytics table is created and users start searching.</small>
                        </div>
                    <?php endif; ?>

                    <!-- Date Filter -->
                    <div class="date-filter">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filter
                                </button>
                                <a href="search-analytics.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-refresh"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-primary"><?php echo formatNumber($totalSearches); ?></div>
                                <div class="stats-label">Total Searches</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-success"><?php echo formatNumber($uniqueTerms); ?></div>
                                <div class="stats-label">Unique Terms</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-warning"><?php echo $avgResponseTime; ?>ms</div>
                                <div class="stats-label">Avg Response Time</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number text-info"><?php echo $clickThroughRate; ?>%</div>
                                <div class="stats-label">Click-Through Rate</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Search Trends Chart -->
                        <div class="col-md-8 mb-4">
                            <div class="chart-container">
                                <h5 class="mb-4"><i class="fas fa-chart-line"></i> Search Trends</h5>
                                <canvas id="searchTrendsChart" height="100"></canvas>
                            </div>
                        </div>

                        <!-- Search Type Distribution -->
                        <div class="col-md-4 mb-4">
                            <div class="chart-container">
                                <h5 class="mb-4"><i class="fas fa-chart-pie"></i> Search Types</h5>
                                <canvas id="searchTypeChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Top Search Terms -->
                        <div class="col-md-6 mb-4">
                            <div class="chart-container">
                                <h5 class="mb-4"><i class="fas fa-fire"></i> Top Search Terms</h5>
                                <?php if (!empty($topSearchTerms)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Term</th>
                                                    <th>Type</th>
                                                    <th>Count</th>
                                                    <th>Trend</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($topSearchTerms as $index => $term): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($term['search_term']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $term['search_type'] === 'sites' ? 'primary' : 'success'; ?>">
                                                                <?php echo ucfirst($term['search_type']); ?>
                                                            </span>
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
                                        <i class="fas fa-search fa-3x mb-3"></i>
                                        <p>No search data available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Most Clicked Results -->
                        <div class="col-md-6 mb-4">
                            <div class="chart-container">
                                <h5 class="mb-4"><i class="fas fa-mouse-pointer"></i> Most Clicked Results</h5>
                                <?php if (!empty($mostClickedResults)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Result</th>
                                                    <th>Type</th>
                                                    <th>Clicks</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($mostClickedResults as $result): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($result['result_title'] ?: $result['result_url']); ?>">
                                                                <?php echo htmlspecialchars($result['result_title'] ?: $result['result_url']); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $result['clicked_result_type'] === 'site' ? 'primary' : 'success'; ?>">
                                                                <?php echo ucfirst($result['clicked_result_type']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo number_format($result['click_count']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-mouse-pointer fa-3x mb-3"></i>
                                        <p>No click data available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Failed Searches -->
                    <?php if (!empty($failedSearches)): ?>
                        <div class="row">
                            <div class="col-12">
                                <div class="chart-container">
                                    <h5 class="mb-4"><i class="fas fa-exclamation-triangle text-warning"></i> Failed Searches (0 Results)</h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Search Term</th>
                                                    <th>Failed Attempts</th>
                                                    <th>Suggested Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($failedSearches as $failed): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($failed['search_term']); ?></td>
                                                        <td><?php echo number_format($failed['count']); ?></td>
                                                        <td>
                                                            <small class="text-muted">
                                                                <i class="fas fa-lightbulb"></i> Consider crawling content related to this term
                                                            </small>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search Trends Chart
        const trendsData = <?php echo json_encode($dailyTrends); ?>;
        const trendsLabels = trendsData.map(item => item.search_day);
        const trendsValues = trendsData.map(item => parseInt(item.count));

        if (trendsLabels.length > 0) {
            new Chart(document.getElementById('searchTrendsChart'), {
                type: 'line',
                data: {
                    labels: trendsLabels,
                    datasets: [{
                        label: 'Daily Searches',
                        data: trendsValues,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Search Type Distribution Chart
        const typeData = <?php echo json_encode($searchTypeDistribution); ?>;
        const typeLabels = typeData.map(item => item.search_type.charAt(0).toUpperCase() + item.search_type.slice(1));
        const typeValues = typeData.map(item => parseInt(item.count));

        if (typeLabels.length > 0) {
            new Chart(document.getElementById('searchTypeChart'), {
                type: 'doughnut',
                data: {
                    labels: typeLabels,
                    datasets: [{
                        data: typeValues,
                        backgroundColor: ['#667eea', '#34a853', '#ea4335', '#fbbc05']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    </script>
</body>
</html>
<?php
require_once('config.php');
$adminAuth->requireRole('super_admin'); // Only super admins can modify ranking

$message = '';
$messageType = '';

// Include ranking algorithm
require_once('../classes/RankingAlgorithm.php');
$rankingAlgorithm = new RankingAlgorithm($con);

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_weights':
                try {
                    $newWeights = [
                        'content_relevance' => (float)$_POST['content_relevance'],
                        'authority_score' => (float)$_POST['authority_score'],
                        'user_signals' => (float)$_POST['user_signals'],
                        'freshness' => (float)$_POST['freshness'],
                        'quality_score' => (float)$_POST['quality_score']
                    ];
                    
                    $rankingAlgorithm->updateWeights($newWeights);
                    
                    $adminAuth->logActivity('info', 'ranking', 'Ranking weights updated', $newWeights);
                    $message = 'Ranking weights updated successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error updating weights: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'test_ranking':
                if (!empty($_POST['test_query'])) {
                    try {
                        $testQuery = $_POST['test_query'];
                        $testType = $_POST['test_type'] ?? 'sites';
                        
                        // Get sample results for testing
                        if ($testType === 'sites') {
                            $stmt = $con->prepare("SELECT * FROM sites WHERE title LIKE ? OR description LIKE ? LIMIT 10");
                            $searchTerm = "%{$testQuery}%";
                            $stmt->execute([$searchTerm, $searchTerm]);
                        } else {
                            $stmt = $con->prepare("SELECT * FROM images WHERE alt LIKE ? OR title LIKE ? AND broken = 0 LIMIT 10");
                            $searchTerm = "%{$testQuery}%";
                            $stmt->execute([$searchTerm, $searchTerm]);
                        }
                        
                        $testResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($testResults)) {
                            $rankingAlgorithm->setDebugMode(true);
                            $rankedResults = $rankingAlgorithm->rankResults($testQuery, $testResults, $testType);
                            
                            $_SESSION['test_results'] = $rankedResults;
                            $_SESSION['test_query'] = $testQuery;
                            $_SESSION['test_type'] = $testType;
                        } else {
                            $message = 'No results found for test query: ' . htmlspecialchars($testQuery);
                            $messageType = 'warning';
                        }
                    } catch (Exception $e) {
                        $message = 'Error testing ranking: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'reset_weights':
                try {
                    $defaultWeights = [
                        'content_relevance' => 0.35,
                        'authority_score' => 0.25,
                        'user_signals' => 0.20,
                        'freshness' => 0.10,
                        'quality_score' => 0.10
                    ];
                    
                    $rankingAlgorithm->updateWeights($defaultWeights);
                    
                    $adminAuth->logActivity('info', 'ranking', 'Ranking weights reset to defaults');
                    $message = 'Ranking weights reset to default values!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error resetting weights: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get current weights
$currentWeights = $rankingAlgorithm->getWeights();

// Get ranking statistics
try {
    $statsStmt = $con->prepare("
        SELECT 
            COUNT(*) as total_searches,
            COUNT(DISTINCT search_term) as unique_queries,
            AVG(response_time_ms) as avg_response_time,
            AVG(CASE WHEN clicked_result_id IS NOT NULL THEN 1 ELSE 0 END) as avg_ctr
        FROM search_analytics 
        WHERE search_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $statsStmt->execute();
    $rankingStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get top performing queries
    $topQueriesStmt = $con->prepare("
        SELECT 
            search_term,
            COUNT(*) as search_count,
            AVG(CASE WHEN clicked_result_id IS NOT NULL THEN 1 ELSE 0 END) as ctr,
            AVG(response_time_ms) as avg_response_time
        FROM search_analytics 
        WHERE search_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY search_term
        HAVING search_count >= 2
        ORDER BY ctr DESC, search_count DESC
        LIMIT 10
    ");
    $topQueriesStmt->execute();
    $topQueries = $topQueriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $rankingStats = ['total_searches' => 0, 'unique_queries' => 0, 'avg_response_time' => 0, 'avg_ctr' => 0];
    $topQueries = [];
}

// Get test results if available
$testResults = $_SESSION['test_results'] ?? null;
$testQuery = $_SESSION['test_query'] ?? '';
$testType = $_SESSION['test_type'] ?? 'sites';

// Clear test results after displaying
if (isset($_SESSION['test_results'])) {
    unset($_SESSION['test_results'], $_SESSION['test_query'], $_SESSION['test_type']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ranking Algorithm Settings - Doogle Admin</title>
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
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .weight-slider {
            margin: 15px 0;
        }
        .weight-value {
            font-weight: bold;
            color: #667eea;
        }
        .test-result {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            background: #f8f9fa;
        }
        .ranking-score {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .stats-mini {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            text-align: center;
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
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <a class="nav-link active" href="ranking-settings.php">
                            <i class="fas fa-sort-amount-up"></i> Ranking Algorithm
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
                        <span class="navbar-brand">Ranking Algorithm Settings</span>
                        <div class="navbar-nav ms-auto">
                            <a href="../search.php?term=test&debug=1" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-external-link-alt"></i> Test Search (Debug Mode)
                            </a>
                        </div>
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

                    <div class="row">
                        <!-- Ranking Weights Configuration -->
                        <div class="col-md-8">
                            <div class="content-card">
                                <h5 class="mb-4"><i class="fas fa-sliders-h"></i> Ranking Weight Configuration</h5>
                                
                                <form method="POST" id="weightsForm">
                                    <input type="hidden" name="action" value="update_weights">
                                    
                                    <div class="weight-slider">
                                        <label class="form-label">
                                            <i class="fas fa-file-alt text-primary"></i> Content Relevance 
                                            <span class="weight-value" id="content_relevance_value"><?php echo round($currentWeights['content_relevance'] * 100, 1); ?>%</span>
                                        </label>
                                        <input type="range" class="form-range" name="content_relevance" id="content_relevance" 
                                               min="0" max="1" step="0.01" value="<?php echo $currentWeights['content_relevance']; ?>"
                                               oninput="updateWeightDisplay('content_relevance', this.value)">
                                        <small class="text-muted">How well content matches the search query (title, description, keywords)</small>
                                    </div>
                                    
                                    <div class="weight-slider">
                                        <label class="form-label">
                                            <i class="fas fa-crown text-warning"></i> Authority Score 
                                            <span class="weight-value" id="authority_score_value"><?php echo round($currentWeights['authority_score'] * 100, 1); ?>%</span>
                                        </label>
                                        <input type="range" class="form-range" name="authority_score" id="authority_score" 
                                               min="0" max="1" step="0.01" value="<?php echo $currentWeights['authority_score']; ?>"
                                               oninput="updateWeightDisplay('authority_score', this.value)">
                                        <small class="text-muted">Page authority, domain reputation, and link analysis</small>
                                    </div>
                                    
                                    <div class="weight-slider">
                                        <label class="form-label">
                                            <i class="fas fa-users text-success"></i> User Signals 
                                            <span class="weight-value" id="user_signals_value"><?php echo round($currentWeights['user_signals'] * 100, 1); ?>%</span>
                                        </label>
                                        <input type="range" class="form-range" name="user_signals" id="user_signals" 
                                               min="0" max="1" step="0.01" value="<?php echo $currentWeights['user_signals']; ?>"
                                               oninput="updateWeightDisplay('user_signals', this.value)">
                                        <small class="text-muted">Click-through rates, user engagement, and popularity metrics</small>
                                    </div>
                                    
                                    <div class="weight-slider">
                                        <label class="form-label">
                                            <i class="fas fa-clock text-info"></i> Freshness 
                                            <span class="weight-value" id="freshness_value"><?php echo round($currentWeights['freshness'] * 100, 1); ?>%</span>
                                        </label>
                                        <input type="range" class="form-range" name="freshness" id="freshness" 
                                               min="0" max="1" step="0.01" value="<?php echo $currentWeights['freshness']; ?>"
                                               oninput="updateWeightDisplay('freshness', this.value)">
                                        <small class="text-muted">Content recency and update frequency</small>
                                    </div>
                                    
                                    <div class="weight-slider">
                                        <label class="form-label">
                                            <i class="fas fa-star text-danger"></i> Quality Score 
                                            <span class="weight-value" id="quality_score_value"><?php echo round($currentWeights['quality_score'] * 100, 1); ?>%</span>
                                        </label>
                                        <input type="range" class="form-range" name="quality_score" id="quality_score" 
                                               min="0" max="1" step="0.01" value="<?php echo $currentWeights['quality_score']; ?>"
                                               oninput="updateWeightDisplay('quality_score', this.value)">
                                        <small class="text-muted">Content quality indicators and completeness</small>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Weights
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="resetToDefaults()">
                                            <i class="fas fa-undo"></i> Reset to Defaults
                                        </button>
                                        <div class="mt-2">
                                            <small class="text-muted">Total Weight: <span id="total_weight">100.0%</span></small>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Algorithm Testing -->
                            <div class="content-card">
                                <h5 class="mb-4"><i class="fas fa-flask"></i> Algorithm Testing</h5>
                                
                                <form method="POST">
                                    <input type="hidden" name="action" value="test_ranking">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Test Query</label>
                                            <input type="text" class="form-control" name="test_query" placeholder="Enter search term to test..." required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Search Type</label>
                                            <select class="form-select" name="test_type">
                                                <option value="sites">Sites</option>
                                                <option value="images">Images</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="submit" class="btn btn-success d-block">
                                                <i class="fas fa-play"></i> Test Ranking
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                
                                <?php if ($testResults): ?>
                                    <div class="mt-4">
                                        <h6>Test Results for: "<em><?php echo htmlspecialchars($testQuery); ?></em>" (<?php echo $testType; ?>)</h6>
                                        
                                        <?php foreach (array_slice($testResults, 0, 5) as $index => $result): ?>
                                            <div class="test-result">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong>#<?php echo $index + 1; ?>: 
                                                            <?php echo htmlspecialchars($result['title'] ?? $result['alt'] ?? 'No title'); ?>
                                                        </strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars(substr($result['description'] ?? $result['imageUrl'] ?? '', 0, 100)); ?>...
                                                        </small>
                                                        <?php if (isset($result['ranking_details'])): ?>
                                                            <div class="mt-2">
                                                                <small>
                                                                    Content: <?php echo round($result['ranking_details']['content_relevance'] * 100, 1); ?>% | 
                                                                    Authority: <?php echo round($result['ranking_details']['authority_score'] * 100, 1); ?>% | 
                                                                    User Signals: <?php echo round($result['ranking_details']['user_signals'] * 100, 1); ?>% | 
                                                                    Freshness: <?php echo round($result['ranking_details']['freshness'] * 100, 1); ?>% | 
                                                                    Quality: <?php echo round($result['ranking_details']['quality_score'] * 100, 1); ?>%
                                                                </small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="ranking-score"><?php echo number_format($result['ranking_score'], 4); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Statistics Sidebar -->
                        <div class="col-md-4">
                            <div class="content-card">
                                <h5 class="mb-4"><i class="fas fa-chart-bar"></i> Ranking Performance (7 days)</h5>
                                
                                <div class="stats-mini">
                                    <div class="h4 text-primary"><?php echo number_format($rankingStats['total_searches'] ?? 0); ?></div>
                                    <div class="text-muted">Total Searches</div>
                                </div>
                                
                                <div class="stats-mini">
                                    <div class="h4 text-success"><?php echo number_format($rankingStats['unique_queries'] ?? 0); ?></div>
                                    <div class="text-muted">Unique Queries</div>
                                </div>
                                
                                <div class="stats-mini">
                                    <div class="h4 text-info"><?php echo round($rankingStats['avg_response_time'] ?? 0, 1); ?>ms</div>
                                    <div class="text-muted">Avg Response Time</div>
                                </div>
                                
                                <div class="stats-mini">
                                    <div class="h4 text-warning"><?php echo round(($rankingStats['avg_ctr'] ?? 0) * 100, 2); ?>%</div>
                                    <div class="text-muted">Avg Click-Through Rate</div>
                                </div>
                            </div>
                            
                            <div class="content-card">
                                <h5 class="mb-4"><i class="fas fa-trophy"></i> Top Performing Queries</h5>
                                
                                <?php if (!empty($topQueries)): ?>
                                    <?php foreach ($topQueries as $query): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                                            <div>
                                                <strong><?php echo htmlspecialchars($query['search_term']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo $query['search_count']; ?> searches | 
                                                    <?php echo round($query['avg_response_time'], 0); ?>ms
                                                </small>
                                            </div>
                                            <span class="badge bg-success"><?php echo round($query['ctr'] * 100, 1); ?>% CTR</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-chart-line fa-2x mb-3"></i>
                                        <p>No query data available yet</p>
                                        <small>Performance data will appear as users search</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Weights Form -->
    <form id="resetForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="reset_weights">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateWeightDisplay(weightName, value) {
            document.getElementById(weightName + '_value').textContent = (parseFloat(value) * 100).toFixed(1) + '%';
            updateTotalWeight();
        }

        function updateTotalWeight() {
            const weights = [
                'content_relevance', 'authority_score', 'user_signals', 'freshness', 'quality_score'
            ];
            
            let total = 0;
            weights.forEach(weight => {
                total += parseFloat(document.getElementById(weight).value);
            });
            
            document.getElementById('total_weight').textContent = (total * 100).toFixed(1) + '%';
            
            // Warn if total is not close to 1.0
            if (Math.abs(total - 1.0) > 0.05) {
                document.getElementById('total_weight').style.color = '#dc3545';
            } else {
                document.getElementById('total_weight').style.color = '#28a745';
            }
        }

        function resetToDefaults() {
            if (confirm('Reset all weights to default values? This will override your current settings.')) {
                document.getElementById('resetForm').submit();
            }
        }

        // Initialize total weight display
        updateTotalWeight();
    </script>
</body>
</html>
<?php
require_once('config.php');
$adminAuth->requireAuth();

$message = '';
$messageType = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_crawl':
                if (!empty($_POST['url'])) {
                    try {
                        $stmt = $con->prepare("INSERT INTO crawl_jobs (url, priority, created_by) VALUES (?, ?, ?)");
                        $priority = $_POST['priority'] ?? 'normal';
                        $stmt->execute([$_POST['url'], $priority, $_SESSION['admin_id']]);
                        
                        $adminAuth->logActivity('info', 'crawl', 'New crawl job added', ['url' => $_POST['url']]);
                        $message = 'Crawl job added successfully!';
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Error adding crawl job: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_status':
                if (!empty($_POST['job_id']) && !empty($_POST['status'])) {
                    try {
                        $stmt = $con->prepare("UPDATE crawl_jobs SET status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$_POST['status'], $_POST['job_id']]);
                        
                        $adminAuth->logActivity('info', 'crawl', 'Crawl job status updated', ['job_id' => $_POST['job_id'], 'status' => $_POST['status']]);
                        $message = 'Crawl job status updated!';
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Error updating crawl job: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_crawl':
                if (!empty($_POST['job_id'])) {
                    try {
                        $stmt = $con->prepare("DELETE FROM crawl_jobs WHERE id = ?");
                        $stmt->execute([$_POST['job_id']]);
                        
                        $adminAuth->logActivity('warning', 'crawl', 'Crawl job deleted', ['job_id' => $_POST['job_id']]);
                        $message = 'Crawl job deleted!';
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Error deleting crawl job: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Get crawl jobs with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$filter = $_GET['filter'] ?? 'all';
$whereClause = '';
$params = [];

if ($filter !== 'all') {
    $whereClause = 'WHERE status = ?';
    $params[] = $filter;
}

try {
    // Get total count
    $countStmt = $con->prepare("SELECT COUNT(*) as total FROM crawl_jobs $whereClause");
    $countStmt->execute($params);
    $totalJobs = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalJobs / $limit);
    
    // Get crawl jobs
    $stmt = $con->prepare("SELECT cj.*, u.username as created_by_username FROM crawl_jobs cj LEFT JOIN users u ON cj.created_by = u.id $whereClause ORDER BY cj.created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $crawlJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get status counts
    $statusStmt = $con->prepare("SELECT status, COUNT(*) as count FROM crawl_jobs GROUP BY status");
    $statusStmt->execute();
    $statusCounts = [];
    while ($row = $statusStmt->fetch(PDO::FETCH_ASSOC)) {
        $statusCounts[$row['status']] = $row['count'];
    }
    
} catch (Exception $e) {
    $message = 'Error loading crawl jobs: ' . $e->getMessage();
    $messageType = 'danger';
    $crawlJobs = [];
    $statusCounts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crawl Management - Doogle Admin</title>
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
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        .filter-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
        }
        .filter-tabs .nav-link.active {
            background: #667eea;
            color: white;
        }
        .url-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
                        <a class="nav-link active" href="crawl-management.php">
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
                        <span class="navbar-brand">Crawl Management</span>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCrawlModal">
                            <i class="fas fa-plus"></i> Add New Crawl
                        </button>
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

                    <!-- Status Overview Cards -->
                    <div class="row mb-4">
                        <div class="col-md-2 mb-3">
                            <div class="content-card text-center">
                                <div class="h3 text-warning"><?php echo $statusCounts['pending'] ?? 0; ?></div>
                                <div class="text-muted">Pending</div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="content-card text-center">
                                <div class="h3 text-primary"><?php echo $statusCounts['running'] ?? 0; ?></div>
                                <div class="text-muted">Running</div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="content-card text-center">
                                <div class="h3 text-success"><?php echo $statusCounts['completed'] ?? 0; ?></div>
                                <div class="text-muted">Completed</div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="content-card text-center">
                                <div class="h3 text-danger"><?php echo $statusCounts['failed'] ?? 0; ?></div>
                                <div class="text-muted">Failed</div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="content-card text-center">
                                <div class="h3 text-secondary"><?php echo $statusCounts['paused'] ?? 0; ?></div>
                                <div class="text-muted">Paused</div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="content-card text-center">
                                <div class="h3 text-info"><?php echo array_sum($statusCounts); ?></div>
                                <div class="text-muted">Total</div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Tabs -->
                    <div class="content-card">
                        <ul class="nav nav-pills filter-tabs mb-4">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" href="?filter=all">All Jobs</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $filter === 'pending' ? 'active' : ''; ?>" href="?filter=pending">Pending</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $filter === 'running' ? 'active' : ''; ?>" href="?filter=running">Running</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $filter === 'completed' ? 'active' : ''; ?>" href="?filter=completed">Completed</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $filter === 'failed' ? 'active' : ''; ?>" href="?filter=failed">Failed</a>
                            </li>
                        </ul>

                        <!-- Crawl Jobs Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>URL</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Progress</th>
                                        <th>Created By</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($crawlJobs)): ?>
                                        <?php foreach ($crawlJobs as $job): ?>
                                            <tr>
                                                <td><?php echo $job['id']; ?></td>
                                                <td class="url-cell" title="<?php echo htmlspecialchars($job['url']); ?>">
                                                    <a href="<?php echo htmlspecialchars($job['url']); ?>" target="_blank" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($job['url']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo getStatusBadge($job['status']); ?></td>
                                                <td>
                                                    <?php
                                                    $priorityClass = ['low' => 'secondary', 'normal' => 'info', 'high' => 'warning'];
                                                    $class = $priorityClass[$job['priority']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $class; ?>"><?php echo ucfirst($job['priority']); ?></span>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?php echo number_format($job['pages_crawled']); ?> pages<br>
                                                        <?php echo number_format($job['images_found']); ?> images
                                                        <?php if ($job['errors_count'] > 0): ?>
                                                            <br><span class="text-danger"><?php echo number_format($job['errors_count']); ?> errors</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td><?php echo htmlspecialchars($job['created_by_username'] ?? 'Unknown'); ?></td>
                                                <td><?php echo timeAgo($job['created_at']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="updateJobStatus(<?php echo $job['id']; ?>, '<?php echo $job['status']; ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($job['status'] === 'pending'): ?>
                                                            <button class="btn btn-outline-success" onclick="updateJobStatus(<?php echo $job['id']; ?>, 'running')">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                        <?php elseif ($job['status'] === 'running'): ?>
                                                            <button class="btn btn-outline-warning" onclick="updateJobStatus(<?php echo $job['id']; ?>, 'paused')">
                                                                <i class="fas fa-pause"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button class="btn btn-outline-danger" onclick="deleteCrawlJob(<?php echo $job['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="fas fa-spider fa-3x mb-3"></i>
                                                <p>No crawl jobs found</p>
                                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCrawlModal">
                                                    <i class="fas fa-plus"></i> Add Your First Crawl Job
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Crawl jobs pagination">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Crawl Modal -->
    <div class="modal fade" id="addCrawlModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Crawl Job</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_crawl">
                        <div class="mb-3">
                            <label for="url" class="form-label">URL to Crawl</label>
                            <input type="url" class="form-control" id="url" name="url" required placeholder="https://example.com">
                            <div class="form-text">Enter the starting URL for the crawl job</div>
                        </div>
                        <div class="mb-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Crawl Job</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden forms for actions -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="job_id" id="statusJobId">
        <input type="hidden" name="status" id="statusValue">
    </form>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_crawl">
        <input type="hidden" name="job_id" id="deleteJobId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateJobStatus(jobId, currentStatus) {
            const statuses = ['pending', 'running', 'paused', 'completed', 'failed'];
            const statusNames = {
                'pending': 'Pending',
                'running': 'Running', 
                'paused': 'Paused',
                'completed': 'Completed',
                'failed': 'Failed'
            };

            let options = '';
            statuses.forEach(status => {
                const selected = status === currentStatus ? 'selected' : '';
                options += `<option value="${status}" ${selected}>${statusNames[status]}</option>`;
            });

            const newStatus = prompt(`Update status for job ${jobId}:\n\nCurrent: ${statusNames[currentStatus]}\n\nEnter new status (pending, running, paused, completed, failed):`);
            
            if (newStatus && statuses.includes(newStatus.toLowerCase())) {
                document.getElementById('statusJobId').value = jobId;
                document.getElementById('statusValue').value = newStatus.toLowerCase();
                document.getElementById('statusForm').submit();
            }
        }

        function deleteCrawlJob(jobId) {
            if (confirm(`Are you sure you want to delete crawl job ${jobId}? This action cannot be undone.`)) {
                document.getElementById('deleteJobId').value = jobId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Auto-refresh every 30 seconds for running jobs
        setTimeout(function() {
            if (window.location.search.includes('filter=running') || window.location.search.includes('filter=pending')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
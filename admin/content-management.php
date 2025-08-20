<?php
require_once('config.php');
$adminAuth->requireAuth();

$message = '';
$messageType = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_site':
                if (!empty($_POST['site_id'])) {
                    try {
                        $stmt = $con->prepare("DELETE FROM sites WHERE id = ?");
                        $stmt->execute([$_POST['site_id']]);
                        
                        $adminAuth->logActivity('warning', 'content', 'Site deleted', ['site_id' => $_POST['site_id']]);
                        $message = 'Site deleted successfully!';
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Error deleting site: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_image':
                if (!empty($_POST['image_id'])) {
                    try {
                        $stmt = $con->prepare("DELETE FROM images WHERE id = ?");
                        $stmt->execute([$_POST['image_id']]);
                        
                        $adminAuth->logActivity('warning', 'content', 'Image deleted', ['image_id' => $_POST['image_id']]);
                        $message = 'Image deleted successfully!';
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Error deleting image: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'mark_broken':
                if (!empty($_POST['image_id'])) {
                    try {
                        $stmt = $con->prepare("UPDATE images SET broken = 1 WHERE id = ?");
                        $stmt->execute([$_POST['image_id']]);
                        
                        $adminAuth->logActivity('info', 'content', 'Image marked as broken', ['image_id' => $_POST['image_id']]);
                        $message = 'Image marked as broken!';
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Error updating image: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'fix_broken':
                if (!empty($_POST['image_id'])) {
                    try {
                        $stmt = $con->prepare("UPDATE images SET broken = 0 WHERE id = ?");
                        $stmt->execute([$_POST['image_id']]);
                        
                        $adminAuth->logActivity('info', 'content', 'Image marked as fixed', ['image_id' => $_POST['image_id']]);
                        $message = 'Image marked as fixed!';
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Error updating image: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'bulk_delete_broken':
                try {
                    $stmt = $con->prepare("DELETE FROM images WHERE broken = 1");
                    $stmt->execute();
                    $deletedCount = $stmt->rowCount();
                    
                    $adminAuth->logActivity('warning', 'content', 'Bulk deleted broken images', ['count' => $deletedCount]);
                    $message = "Deleted {$deletedCount} broken images!";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error deleting broken images: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get content type and filters
$contentType = $_GET['type'] ?? 'sites';
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query based on content type and filters
$whereConditions = [];
$params = [];

if ($contentType === 'sites') {
    $baseQuery = "FROM sites";
    
    if ($search) {
        $whereConditions[] = "(title LIKE ? OR url LIKE ? OR description LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
} else { // images
    $baseQuery = "FROM images";
    
    if ($filter === 'broken') {
        $whereConditions[] = "broken = 1";
    } elseif ($filter === 'working') {
        $whereConditions[] = "broken = 0";
    }
    
    if ($search) {
        $whereConditions[] = "(alt LIKE ? OR title LIKE ? OR imageUrl LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

try {
    // Get total count
    $countQuery = "SELECT COUNT(*) as total {$baseQuery} {$whereClause}";
    $countStmt = $con->prepare($countQuery);
    $countStmt->execute($params);
    $totalItems = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalItems / $limit);
    
    // Get content items
    if ($contentType === 'sites') {
        $query = "SELECT * {$baseQuery} {$whereClause} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
    } else {
        $query = "SELECT * {$baseQuery} {$whereClause} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
    }
    
    $stmt = $con->prepare($query);
    $stmt->execute($params);
    $contentItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get content statistics
    if ($contentType === 'sites') {
        $statsQuery = "SELECT COUNT(*) as total FROM sites";
        $statsStmt = $con->prepare($statsQuery);
        $statsStmt->execute();
        $totalSites = $statsStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $contentStats = ['total' => $totalSites];
    } else {
        $statsQuery = "SELECT COUNT(*) as total, SUM(CASE WHEN broken = 1 THEN 1 ELSE 0 END) as broken FROM images";
        $statsStmt = $con->prepare($statsQuery);
        $statsStmt->execute();
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        $contentStats = [
            'total' => $stats['total'],
            'broken' => $stats['broken'],
            'working' => $stats['total'] - $stats['broken']
        ];
    }
    
} catch (Exception $e) {
    $message = 'Error loading content: ' . $e->getMessage();
    $messageType = 'danger';
    $contentItems = [];
    $contentStats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - Doogle Admin</title>
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
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        .content-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
        }
        .content-tabs .nav-link.active {
            background: #667eea;
            color: white;
        }
        .filter-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .filter-tabs .nav-link.active {
            background: #34a853;
            color: white;
        }
        .url-cell, .image-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .image-preview {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .stats-mini {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
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
                        <a class="nav-link active" href="content-management.php">
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
                        <span class="navbar-brand">Content Management</span>
                        <div class="navbar-nav ms-auto">
                            <form class="d-flex" method="GET">
                                <input type="hidden" name="type" value="<?php echo $contentType; ?>">
                                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                                <input class="form-control me-2" type="search" name="search" placeholder="Search content..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-success" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </form>
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

                    <!-- Content Type Tabs -->
                    <ul class="nav nav-pills content-tabs mb-4">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $contentType === 'sites' ? 'active' : ''; ?>" href="?type=sites">
                                <i class="fas fa-globe"></i> Sites (<?php echo $contentType === 'sites' ? $contentStats['total'] : ''; ?>)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $contentType === 'images' ? 'active' : ''; ?>" href="?type=images">
                                <i class="fas fa-image"></i> Images (<?php echo $contentType === 'images' ? $contentStats['total'] : ''; ?>)
                            </a>
                        </li>
                    </ul>

                    <!-- Statistics Cards -->
                    <?php if ($contentType === 'sites'): ?>
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="stats-mini">
                                    <div class="h4 text-primary"><?php echo formatNumber($contentStats['total']); ?></div>
                                    <div class="text-muted">Total Sites Indexed</div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="stats-mini">
                                    <div class="h4 text-primary"><?php echo formatNumber($contentStats['total']); ?></div>
                                    <div class="text-muted">Total Images</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-mini">
                                    <div class="h4 text-success"><?php echo formatNumber($contentStats['working']); ?></div>
                                    <div class="text-muted">Working Images</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-mini">
                                    <div class="h4 text-danger"><?php echo formatNumber($contentStats['broken']); ?></div>
                                    <div class="text-muted">Broken Images</div>
                                </div>
                            </div>
                        </div>

                        <!-- Image Filter Tabs -->
                        <ul class="nav nav-pills filter-tabs mb-4">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" href="?type=images&filter=all">All Images</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $filter === 'working' ? 'active' : ''; ?>" href="?type=images&filter=working">Working</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $filter === 'broken' ? 'active' : ''; ?>" href="?type=images&filter=broken">Broken</a>
                            </li>
                        </ul>

                        <?php if ($filter === 'broken' && $contentStats['broken'] > 0): ?>
                            <div class="mb-3">
                                <button class="btn btn-danger btn-sm" onclick="bulkDeleteBroken()">
                                    <i class="fas fa-trash-alt"></i> Delete All Broken Images
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Content Table -->
                    <div class="content-card">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <?php if ($contentType === 'sites'): ?>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>URL</th>
                                            <th>Description</th>
                                            <th>Clicks</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        <?php else: ?>
                                            <th>ID</th>
                                            <th>Preview</th>
                                            <th>Alt Text</th>
                                            <th>Image URL</th>
                                            <th>Source Site</th>
                                            <th>Status</th>
                                            <th>Clicks</th>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($contentItems)): ?>
                                        <?php foreach ($contentItems as $item): ?>
                                            <tr>
                                                <?php if ($contentType === 'sites'): ?>
                                                    <td><?php echo $item['id']; ?></td>
                                                    <td class="url-cell" title="<?php echo htmlspecialchars($item['title']); ?>">
                                                        <?php echo htmlspecialchars($item['title']); ?>
                                                    </td>
                                                    <td class="url-cell">
                                                        <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($item['url']); ?>
                                                        </a>
                                                    </td>
                                                    <td class="url-cell" title="<?php echo htmlspecialchars($item['description']); ?>">
                                                        <?php echo htmlspecialchars(substr($item['description'], 0, 100)) . (strlen($item['description']) > 100 ? '...' : ''); ?>
                                                    </td>
                                                    <td><?php echo number_format($item['clicks']); ?></td>
                                                    <td><?php echo timeAgo($item['created_at'] ?? $item['id']); ?></td>
                                                    <td>
                                                        <button class="btn btn-outline-danger btn-sm" onclick="deleteSite(<?php echo $item['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                <?php else: ?>
                                                    <td><?php echo $item['id']; ?></td>
                                                    <td>
                                                        <img src="<?php echo htmlspecialchars($item['imageUrl']); ?>" 
                                                             class="image-preview" 
                                                             alt="<?php echo htmlspecialchars($item['alt']); ?>"
                                                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIHZpZXdCb3g9IjAgMCA1MCA1MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjUwIiBoZWlnaHQ9IjUwIiBmaWxsPSIjZjhmOWZhIi8+CjxwYXRoIGQ9Ik0yNSAxNWMtNS41MjMgMC0xMCA0LjQ3Ny0xMCAxMHM0LjQ3NyAxMCAxMCAxMCAxMC00LjQ3NyAxMC0xMC00LjQ3Ny0xMC0xMC0xMHptMCAxNmMtMy4zMTQgMC02LTIuNjg2LTYtNnMyLjY4Ni02IDYtNiA2IDIuNjg2IDYgNi0yLjY4NiA2LTYgNnoiIGZpbGw9IiM2Yzc1N2QiLz4KPC9zdmc+Cg=='; this.onerror=null;">
                                                    </td>
                                                    <td class="image-cell" title="<?php echo htmlspecialchars($item['alt']); ?>">
                                                        <?php echo htmlspecialchars($item['alt']); ?>
                                                    </td>
                                                    <td class="url-cell">
                                                        <a href="<?php echo htmlspecialchars($item['imageUrl']); ?>" target="_blank" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($item['imageUrl']); ?>
                                                        </a>
                                                    </td>
                                                    <td class="url-cell">
                                                        <a href="<?php echo htmlspecialchars($item['siteUrl']); ?>" target="_blank" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($item['siteUrl']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <?php if ($item['broken']): ?>
                                                            <span class="badge bg-danger">Broken</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Working</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo number_format($item['clicks']); ?></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <?php if ($item['broken']): ?>
                                                                <button class="btn btn-outline-success" onclick="fixImage(<?php echo $item['id']; ?>)" title="Mark as Fixed">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-outline-warning" onclick="markBroken(<?php echo $item['id']; ?>)" title="Mark as Broken">
                                                                    <i class="fas fa-exclamation-triangle"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <button class="btn btn-outline-danger" onclick="deleteImage(<?php echo $item['id']; ?>)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?php echo $contentType === 'sites' ? '7' : '8'; ?>" class="text-center text-muted py-4">
                                                <i class="fas fa-<?php echo $contentType === 'sites' ? 'globe' : 'image'; ?> fa-3x mb-3"></i>
                                                <p>No <?php echo $contentType; ?> found</p>
                                                <?php if ($search): ?>
                                                    <a href="?type=<?php echo $contentType; ?>" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-times"></i> Clear Search
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Content pagination">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?type=<?php echo $contentType; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">Previous</a>
                                    </li>
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?type=<?php echo $contentType; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?type=<?php echo $contentType; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden forms for actions -->
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="actionType">
        <input type="hidden" name="site_id" id="siteId">
        <input type="hidden" name="image_id" id="imageId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteSite(siteId) {
            if (confirm(`Are you sure you want to delete site ${siteId}? This action cannot be undone.`)) {
                document.getElementById('actionType').value = 'delete_site';
                document.getElementById('siteId').value = siteId;
                document.getElementById('actionForm').submit();
            }
        }

        function deleteImage(imageId) {
            if (confirm(`Are you sure you want to delete image ${imageId}? This action cannot be undone.`)) {
                document.getElementById('actionType').value = 'delete_image';
                document.getElementById('imageId').value = imageId;
                document.getElementById('actionForm').submit();
            }
        }

        function markBroken(imageId) {
            if (confirm(`Mark image ${imageId} as broken?`)) {
                document.getElementById('actionType').value = 'mark_broken';
                document.getElementById('imageId').value = imageId;
                document.getElementById('actionForm').submit();
            }
        }

        function fixImage(imageId) {
            if (confirm(`Mark image ${imageId} as fixed?`)) {
                document.getElementById('actionType').value = 'fix_broken';
                document.getElementById('imageId').value = imageId;
                document.getElementById('actionForm').submit();
            }
        }

        function bulkDeleteBroken() {
            if (confirm('Are you sure you want to delete ALL broken images? This action cannot be undone and may take a while to complete.')) {
                document.getElementById('actionType').value = 'bulk_delete_broken';
                document.getElementById('actionForm').submit();
            }
        }
    </script>
</body>
</html>
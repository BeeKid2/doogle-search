<?php
require_once('config.php');
$adminAuth->requireRole('super_admin'); // Only super admins can manage users

$message = '';
$messageType = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_user':
                if (!empty($_POST['username']) && !empty($_POST['email']) && !empty($_POST['password'])) {
                    try {
                        // Check if username or email already exists
                        $stmt = $con->prepare("SELECT COUNT(*) as count FROM users WHERE username = ? OR email = ?");
                        $stmt->execute([$_POST['username'], $_POST['email']]);
                        
                        if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                            $message = 'Username or email already exists!';
                            $messageType = 'danger';
                        } else {
                            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                            $role = $_POST['role'] ?? 'admin';
                            
                            $stmt = $con->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
                            $stmt->execute([$_POST['username'], $_POST['email'], $hashedPassword, $role]);
                            
                            $adminAuth->logActivity('info', 'user_management', 'New user created', [
                                'username' => $_POST['username'],
                                'role' => $role
                            ]);
                            
                            $message = 'User created successfully!';
                            $messageType = 'success';
                        }
                    } catch (Exception $e) {
                        $message = 'Error creating user: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_user':
                if (!empty($_POST['user_id'])) {
                    try {
                        $updates = [];
                        $params = [];
                        
                        if (!empty($_POST['username'])) {
                            $updates[] = "username = ?";
                            $params[] = $_POST['username'];
                        }
                        
                        if (!empty($_POST['email'])) {
                            $updates[] = "email = ?";
                            $params[] = $_POST['email'];
                        }
                        
                        if (!empty($_POST['password'])) {
                            $updates[] = "password = ?";
                            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        }
                        
                        if (!empty($_POST['role'])) {
                            $updates[] = "role = ?";
                            $params[] = $_POST['role'];
                        }
                        
                        if (!empty($_POST['status'])) {
                            $updates[] = "status = ?";
                            $params[] = $_POST['status'];
                        }
                        
                        if (!empty($updates)) {
                            $params[] = $_POST['user_id'];
                            $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
                            $stmt = $con->prepare($query);
                            $stmt->execute($params);
                            
                            $adminAuth->logActivity('info', 'user_management', 'User updated', [
                                'user_id' => $_POST['user_id'],
                                'updated_fields' => array_keys($_POST)
                            ]);
                            
                            $message = 'User updated successfully!';
                            $messageType = 'success';
                        }
                    } catch (Exception $e) {
                        $message = 'Error updating user: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_user':
                if (!empty($_POST['user_id']) && $_POST['user_id'] != $_SESSION['admin_id']) {
                    try {
                        // First deactivate all sessions for this user
                        $stmt = $con->prepare("UPDATE admin_sessions SET is_active = 0 WHERE user_id = ?");
                        $stmt->execute([$_POST['user_id']]);
                        
                        // Then delete the user
                        $stmt = $con->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$_POST['user_id']]);
                        
                        $adminAuth->logActivity('warning', 'user_management', 'User deleted', [
                            'user_id' => $_POST['user_id']
                        ]);
                        
                        $message = 'User deleted successfully!';
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Error deleting user: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Cannot delete yourself or invalid user ID!';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$filter = $_GET['filter'] ?? 'all';

$whereClause = '';
$params = [];

if ($filter !== 'all') {
    $whereClause = 'WHERE role = ?';
    $params[] = $filter;
}

try {
    // Get total count
    $countStmt = $con->prepare("SELECT COUNT(*) as total FROM users $whereClause");
    $countStmt->execute($params);
    $totalUsers = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalUsers / $limit);
    
    // Get users
    $stmt = $con->prepare("SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get role counts
    $roleStmt = $con->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $roleStmt->execute();
    $roleCounts = [];
    while ($row = $roleStmt->fetch(PDO::FETCH_ASSOC)) {
        $roleCounts[$row['role']] = $row['count'];
    }
    
    // Get recent user activity
    $activityStmt = $con->prepare("SELECT u.username, u.last_login, u.status FROM users u WHERE u.last_login IS NOT NULL ORDER BY u.last_login DESC LIMIT 10");
    $activityStmt->execute();
    $recentActivity = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = 'Error loading users: ' . $e->getMessage();
    $messageType = 'danger';
    $users = [];
    $roleCounts = [];
    $recentActivity = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Doogle Admin</title>
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
        .filter-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
        }
        .filter-tabs .nav-link.active {
            background: #667eea;
            color: white;
        }
        .stats-mini {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            text-align: center;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
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
                        <a class="nav-link active" href="user-management.php">
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
                        <span class="navbar-brand">User Management</span>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                            <i class="fas fa-user-plus"></i> Create User
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

                    <!-- Role Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-mini">
                                <div class="h4 text-danger"><?php echo $roleCounts['super_admin'] ?? 0; ?></div>
                                <div class="text-muted">Super Admins</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-mini">
                                <div class="h4 text-warning"><?php echo $roleCounts['admin'] ?? 0; ?></div>
                                <div class="text-muted">Admins</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-mini">
                                <div class="h4 text-info"><?php echo $roleCounts['user'] ?? 0; ?></div>
                                <div class="text-muted">Users</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-mini">
                                <div class="h4 text-primary"><?php echo array_sum($roleCounts); ?></div>
                                <div class="text-muted">Total Users</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Users Table -->
                        <div class="col-md-8">
                            <!-- Filter Tabs -->
                            <ul class="nav nav-pills filter-tabs mb-4">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" href="?filter=all">All Users</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $filter === 'super_admin' ? 'active' : ''; ?>" href="?filter=super_admin">Super Admins</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $filter === 'admin' ? 'active' : ''; ?>" href="?filter=admin">Admins</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $filter === 'user' ? 'active' : ''; ?>" href="?filter=user">Users</a>
                                </li>
                            </ul>

                            <div class="content-card">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Last Login</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($users)): ?>
                                                <?php foreach ($users as $user): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="user-avatar me-3">
                                                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                                </div>
                                                                <div>
                                                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                                    <br><small class="text-muted">ID: <?php echo $user['id']; ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                        <td>
                                                            <?php
                                                            $roleClasses = [
                                                                'super_admin' => 'danger',
                                                                'admin' => 'warning', 
                                                                'user' => 'info'
                                                            ];
                                                            $class = $roleClasses[$user['role']] ?? 'secondary';
                                                            ?>
                                                            <span class="badge bg-<?php echo $class; ?>"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                                                        </td>
                                                        <td><?php echo getStatusBadge($user['status']); ?></td>
                                                        <td>
                                                            <?php echo $user['last_login'] ? timeAgo($user['last_login']) : 'Never'; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <button class="btn btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                                                    <button class="btn btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        <i class="fas fa-users fa-3x mb-3"></i>
                                                        <p>No users found</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($totalPages > 1): ?>
                                    <nav aria-label="Users pagination">
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

                        <!-- Recent Activity Sidebar -->
                        <div class="col-md-4">
                            <div class="content-card">
                                <h5 class="mb-4"><i class="fas fa-clock"></i> Recent Activity</h5>
                                <?php if (!empty($recentActivity)): ?>
                                    <?php foreach ($recentActivity as $activity): ?>
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="user-avatar me-3" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                                <?php echo strtoupper(substr($activity['username'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($activity['username']); ?></strong>
                                                <br><small class="text-muted">
                                                    Last login: <?php echo timeAgo($activity['last_login']); ?>
                                                </small>
                                            </div>
                                            <div class="ms-auto">
                                                <?php echo getStatusBadge($activity['status']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-clock fa-2x mb-3"></i>
                                        <p>No recent activity</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_user">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="user">User</option>
                                <option value="admin" selected>Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username">
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="banned">Banned</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden forms for actions -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" id="delete_user_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function deleteUser(userId, username) {
            if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
                document.getElementById('delete_user_id').value = userId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
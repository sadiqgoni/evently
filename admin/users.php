<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require admin privileges
requireAdmin();

// Get admin information
$admin_id = $_SESSION['user_id'];
$admin = getUserDetails($admin_id);

// Handle user status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];
    
    switch ($action) {
        case 'delete':
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "User deleted successfully.";
                $_SESSION['message_type'] = "success";
            }
            break;
            
        case 'block':
            $stmt = $conn->prepare("UPDATE users SET status = 'blocked' WHERE id = ? AND role != 'admin'");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "User blocked successfully.";
                $_SESSION['message_type'] = "success";
            }
            break;
            
        case 'unblock':
            $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "User unblocked successfully.";
                $_SESSION['message_type'] = "success";
            }
            break;
    }
    
    header("Location: users.php");
    exit();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build the query
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $sql .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

if ($role_filter) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if ($status_filter) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
?>

<?php require_once '../includes/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card sidebar-card fade-in">
                <div class="card-body text-center p-4">
                    <div class="mb-4">
                        <div class="avatar-circle">
                            <i class="fas fa-user-shield fa-4x text-warning"></i>
                        </div>
                    </div>
                    <h5 class="card-title text-warning mb-1">
                        <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                    </h5>
                    <p class="text-light mb-3"><?php echo htmlspecialchars($admin['email']); ?></p>
                    <div class="badge bg-warning mb-3">Administrator</div>
                    <hr class="border-light">
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                    <a href="events.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar me-2"></i>Manage Events
                    </a>
                    <a href="transactions.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-money-bill-wave me-2"></i>Transactions
                    </a>
                    <a href="settings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Page Header -->
            <div class="card welcome-card mb-4 fade-in">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="welcome-icon me-4">
                                <i class="fas fa-users fa-2x text-warning"></i>
                            </div>
                            <div>
                                <h4 class="mb-1 text-warning">Manage Users</h4>
                                <p class="text-light mb-0">View and manage all user accounts in the system.</p>
                            </div>
                        </div>
                        <a href="auth/register.php" class="btn btn-warning">
                            <i class="fas fa-user-plus me-2"></i>Add Admin
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4 fade-in">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-warning" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="role" onchange="this.form.submit()">
                                <option value="">All Roles</option>
                                <option value="customer" <?php echo $role_filter === 'customer' ? 'selected' : ''; ?>>Customers</option>
                                <option value="vendor" <?php echo $role_filter === 'vendor' ? 'selected' : ''; ?>>Vendors</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="status" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="blocked" <?php echo $status_filter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-warning w-100" onclick="resetFilters()">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card fade-in">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle-sm me-3">
                                                    <i class="fas fa-user text-warning"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 text-warning">
                                                        <?php echo htmlspecialchars($user['username']); ?>
                                                    </h6>
                                                    <small class="text-light">
                                                        <?php echo htmlspecialchars($user['email']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'vendor' ? 'warning' : 'info'); ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-light">
                                                <?php echo formatDate($user['created_at']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($user['role'] !== 'admin' || ($user['role'] === 'admin' && $user['id'] !== $_SESSION['user_id'])): ?>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                            onclick="viewUser(<?php echo $user['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($user['role'] !== 'admin'): ?>
                                                        <?php if ($user['status'] !== 'blocked'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-warning" 
                                                                    onclick="blockUser(<?php echo $user['id']; ?>)">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                                    onclick="unblockUser(<?php echo $user['id']; ?>)">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteUser(<?php echo $user['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- User Action Forms -->
<form id="blockForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="block">
    <input type="hidden" name="user_id" value="">
</form>

<form id="unblockForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="unblock">
    <input type="hidden" name="user_id" value="">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" value="">
</form>

<style>
.sidebar-card {
    border: none;
    border-radius: 15px;
    background: var(--dark-card);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.avatar-circle {
    width: 80px;
    height: 80px;
    margin: 0 auto;
    background: rgba(255, 215, 0, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.list-group-item {
    background: var(--dark-card);
    border-color: rgba(255, 255, 255, 0.1);
    color: var(--text-color);
    padding: 1rem 1.5rem;
    transition: all 0.3s ease;
}

.list-group-item:hover {
    transform: translateX(5px);
    background-color: rgba(255, 215, 0, 0.1) !important;
}

.list-group-item.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--dark-bg);
}

.stat-card {
    background: var(--dark-card);
    border: none;
    border-radius: 15px;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto;
    background: rgba(255, 215, 0, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
}

.stat-label {
    font-size: 1rem;
    opacity: 0.9;
}
.avatar-circle-sm {
    width: 40px;
    height: 40px;
    background: rgba(255, 215, 0, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.table {
    color: var(--text-color);
}

.table > :not(caption) > * > * {
    background-color: var(--dark-card);
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

.table thead th {
    color: var(--primary-color);
    font-weight: 600;
    border-bottom: 2px solid var(--primary-color);
}

.table tbody tr:hover {
    background-color: var(--dark-hover) !important;
}

.form-control, .form-select {
    background-color: var(--dark-card);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--text-color);
}

.form-control:focus, .form-select:focus {
    background-color: var(--dark-card);
    border-color: var(--primary-color);
    color: var(--text-color);
    box-shadow: none;
}

.form-select option {
    background-color: var(--dark-card);
    color: var(--text-color);
}
</style>

<script>
function resetFilters() {
    window.location.href = 'users.php';
}

function viewUser(userId) {
    // Implement user details view
    alert('View user details - Coming soon!');
}

function blockUser(userId) {
    if (confirm('Are you sure you want to block this user?')) {
        const form = document.getElementById('blockForm');
        form.querySelector('input[name="user_id"]').value = userId;
        form.submit();
    }
}

function unblockUser(userId) {
    if (confirm('Are you sure you want to unblock this user?')) {
        const form = document.getElementById('unblockForm');
        form.querySelector('input[name="user_id"]').value = userId;
        form.submit();
    }
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        const form = document.getElementById('deleteForm');
        form.querySelector('input[name="user_id"]').value = userId;
        form.submit();
    }
}
</script>

 
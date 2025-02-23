<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require admin privileges
requireAdmin();

// Get admin information
$admin_id = $_SESSION['user_id'];
$admin = getUserDetails($admin_id);

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';

// Build the query
$sql = "SELECT t.*, 
        u.username, u.first_name, u.last_name, u.email, u.role
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        WHERE 1=1";

$params = [];
$types = "";

if ($search) {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR t.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= "sss";
}

if ($type_filter) {
    $sql .= " AND t.type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

switch ($date_filter) {
    case 'today':
        $sql .= " AND DATE(t.created_at) = CURDATE()";
        break;
    case 'yesterday':
        $sql .= " AND DATE(t.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'this_week':
        $sql .= " AND YEARWEEK(t.created_at) = YEARWEEK(CURDATE())";
        break;
    case 'this_month':
        $sql .= " AND MONTH(t.created_at) = MONTH(CURDATE()) AND YEAR(t.created_at) = YEAR(CURDATE())";
        break;
}

$sql .= " ORDER BY t.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transactions = $stmt->get_result();

// Calculate totals
$total_credit = 0;
$total_debit = 0;
$transactions_data = [];

while ($transaction = $transactions->fetch_assoc()) {
    $transactions_data[] = $transaction;
    if ($transaction['type'] === 'credit') {
        $total_credit += $transaction['amount'];
    } else {
        $total_debit += $transaction['amount'];
    }
}
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
                    <a href="dashboard.php" class="list-group-item list-group-item-action ">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                    <a href="events.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar me-2"></i>Manage Events
                    </a>
                    <a href="transactions.php" class="list-group-item list-group-item-action active">
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
                    <div class="d-flex align-items-center">
                        <div class="welcome-icon me-4">
                            <i class="fas fa-money-bill-wave fa-2x text-warning"></i>
                        </div>
                        <div>
                            <h4 class="mb-1 text-warning">Transactions</h4>
                            <p class="text-light mb-0">View and manage all financial transactions in the system.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Summary -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card fade-in">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-success bg-opacity-10 me-3">
                                    <i class="fas fa-arrow-up text-success"></i>
                                </div>
                                <div>
                                    <h6 class="text-success mb-1">Total Credits</h6>
                                    <h4 class="text-light mb-0"><?php echo formatCurrency($total_credit); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card fade-in">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-danger bg-opacity-10 me-3">
                                    <i class="fas fa-arrow-down text-danger"></i>
                                </div>
                                <div>
                                    <h6 class="text-danger mb-1">Total Debits</h6>
                                    <h4 class="text-light mb-0"><?php echo formatCurrency($total_debit); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card fade-in">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-warning bg-opacity-10 me-3">
                                    <i class="fas fa-wallet text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="text-warning mb-1">Net Balance</h6>
                                    <h4 class="text-light mb-0"><?php echo formatCurrency($total_credit - $total_debit); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4 fade-in">
                <div class="card-body">
                    <form method="GET" class="row g-3 search-form">
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" class="form-control text-light" name="search" 
                                       placeholder="Search transactions..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-warning" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select text-light" name="type" onchange="this.form.submit()">
                                <option value="">All Types</option>
                                <option value="credit" <?php echo $type_filter === 'credit' ? 'selected' : ''; ?>>Credits</option>
                                <option value="debit" <?php echo $type_filter === 'debit' ? 'selected' : ''; ?>>Debits</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select text-light" name="date" onchange="this.form.submit()">
                                <option value="">All Time</option>
                                <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="yesterday" <?php echo $date_filter === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                                <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="this_month" <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
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

            <!-- Transactions Table -->
            <div class="card fade-in">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions_data as $transaction): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <h6 class="mb-0 text-warning">
                                                    <?php echo htmlspecialchars($transaction['username']); ?>
                                                </h6>
                                                <small class="text-light">
                                                    <?php echo htmlspecialchars($transaction['email']); ?>
                                                </small>
                                                <span class="badge bg-info ms-2">
                                                    <?php echo ucfirst($transaction['role']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $transaction['type'] === 'credit' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($transaction['type']); ?>
                                            </span>
                                        </td>
                                        <td class="text-<?php echo $transaction['type'] === 'credit' ? 'success' : 'danger'; ?>">
                                            <?php echo formatCurrency($transaction['amount']); ?>
                                        </td>
                                        <td>
                                            <div class="text-light">
                                                <?php echo htmlspecialchars($transaction['description']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-light">
                                                <?php echo formatDate($transaction['created_at']); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
.icon-circle {
    width: 48px;
    height: 48px;
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
    window.location.href = 'transactions.php';
}
</script>

 
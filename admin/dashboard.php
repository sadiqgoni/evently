<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

requireAdmin();

$admin_id = $_SESSION['user_id'];
$admin = getUserDetails($admin_id);

$stats = [];

$sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $stats['users'][$row['role']] = $row['count'];
}

$sql = "SELECT COUNT(*) as count FROM events";
$result = $conn->query($sql);
$stats['total_events'] = $result->fetch_assoc()['count'];

$sql = "SELECT COUNT(*) as count FROM tickets";
$result = $conn->query($sql);
$stats['tickets_sold'] = $result->fetch_assoc()['count'];

$sql = "SELECT SUM(amount) as total FROM transactions WHERE type = 'credit'";
$result = $conn->query($sql);
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

$sql = "SELECT t.*, u.username, u.first_name, u.last_name 
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY t.created_at DESC LIMIT 5";
$recent_transactions = $conn->query($sql);

$sql = "SELECT * FROM users ORDER BY created_at DESC LIMIT 5";
$recent_users = $conn->query($sql);

$sql = "SELECT e.*, u.username as organizer 
        FROM events e 
        JOIN users u ON e.vendor_id = u.id 
        ORDER BY e.created_at DESC LIMIT 5";
$recent_events = $conn->query($sql);
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
                    <a href="dashboard.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action">
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
            <!-- Welcome Message -->
            <div class="card welcome-card mb-4 fade-in">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="welcome-icon me-4">
                            <i class="fas fa-sun fa-2x text-warning"></i>
                        </div>
                        <div>
                            <h4 class="mb-1 text-warning">
                                Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'); ?>!
                            </h4>
                            <p class="text-light mb-0">Welcome to your admin dashboard. Here's an overview of your system.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card fade-in">
                        <div class="card-body text-center p-4">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-users fa-2x text-warning"></i>
                            </div>
                            <h2 class="stat-value text-warning mb-2">
                                <?php echo array_sum($stats['users']); ?>
                            </h2>
                            <p class="stat-label text-light mb-0">Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card fade-in">
                        <div class="card-body text-center p-4">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-calendar-alt fa-2x text-warning"></i>
                            </div>
                            <h2 class="stat-value text-warning mb-2">
                                <?php echo $stats['total_events']; ?>
                            </h2>
                            <p class="stat-label text-light mb-0">Total Events</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card fade-in">
                        <div class="card-body text-center p-4">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-ticket-alt fa-2x text-warning"></i>
                            </div>
                            <h2 class="stat-value text-warning mb-2">
                                <?php echo $stats['tickets_sold']; ?>
                            </h2>
                            <p class="stat-label text-light mb-0">Tickets Sold</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card fade-in">
                        <div class="card-body text-center p-4">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-money-bill-wave fa-2x text-warning"></i>
                            </div>
                            <h2 class="stat-value text-warning mb-2">
                                <?php echo formatCurrency($stats['total_revenue']); ?>
                            </h2>
                            <p class="stat-label text-light mb-0">Total Revenue</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- User Distribution -->
                <div class="col-md-6 mb-4">
                    <div class="card fade-in h-100">
                        <div class="card-header bg-transparent border-warning">
                            <h5 class="text-warning mb-0">
                                <i class="fas fa-chart-pie me-2"></i>User Distribution
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="userChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="col-md-6 mb-4">
                    <div class="card fade-in h-100">
                        <div class="card-header bg-transparent border-warning d-flex justify-content-between align-items-center">
                            <h5 class="text-warning mb-0">
                                <i class="fas fa-users me-2"></i>Recent Users
                            </h5>
                            <a href="users.php" class="btn btn-sm btn-warning">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php while ($user = $recent_users->fetch_assoc()): ?>
                                    <div class="list-group-item border-bottom border-light">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1 text-warning">
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                </h6>
                                                <small class="text-light">
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'vendor' ? 'warning' : 'info'); ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                                <div><small class="text-light"><?php echo formatDate($user['created_at']); ?></small></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Events -->
                <div class="col-md-6 mb-4">
                    <div class="card fade-in h-100">
                        <div class="card-header bg-transparent border-warning d-flex justify-content-between align-items-center">
                            <h5 class="text-warning mb-0">
                                <i class="fas fa-calendar me-2"></i>Recent Events
                            </h5>
                            <a href="events.php" class="btn btn-sm btn-warning">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php while ($event = $recent_events->fetch_assoc()): ?>
                                    <div class="list-group-item border-bottom border-light">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1 text-warning">
                                                    <?php echo htmlspecialchars($event['title']); ?>
                                                </h6>
                                                <small class="text-light">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($event['organizer']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <div class="text-warning"><?php echo formatCurrency($event['ticket_price']); ?></div>
                                                <small class="text-light"><?php echo formatDate($event['date']); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="col-md-6 mb-4">
                    <div class="card fade-in h-100">
                        <div class="card-header bg-transparent border-warning d-flex justify-content-between align-items-center">
                            <h5 class="text-warning mb-0">
                                <i class="fas fa-money-bill-wave me-2"></i>Recent Transactions
                            </h5>
                            <a href="transactions.php" class="btn btn-sm btn-warning">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php while ($transaction = $recent_transactions->fetch_assoc()): ?>
                                    <div class="list-group-item border-bottom border-light">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1 text-warning">
                                                    <?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?>
                                                </h6>
                                                <small class="text-light">
                                                    <?php echo htmlspecialchars($transaction['description']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <div class="text-<?php echo $transaction['type'] === 'credit' ? 'success' : 'danger'; ?>">
                                                    <?php echo $transaction['type'] === 'credit' ? '+' : '-'; ?>
                                                    <?php echo formatCurrency($transaction['amount']); ?>
                                                </div>
                                                <small class="text-light"><?php echo formatDate($transaction['created_at']); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
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
    background-color: rgba(255, 215, 0, 0.1) !important;
}

.list-group-item.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--dark-bg);
}

/* Fix for dark theme text colors */
.form-control, .form-select {
    background-color: var(--dark-card);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--text-color) !important;
}

.form-control:focus, .form-select:focus {
    background-color: var(--dark-card);
    border-color: var(--primary-color);
    color: var(--text-color) !important;
    box-shadow: none;
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

/* Fix for statistics text colors */
.stat-card .stat-value,
.stat-card .stat-label,
.stat-card .text-muted {
    color: var(--text-color) !important;
}

/* Fix for search inputs */
.search-form input,
.search-form select {
    background-color: var(--dark-card) !important;
    color: var(--text-color) !important;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.search-form input::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

/* Fix for transaction amounts */
.transaction-amount {
    color: var(--text-color) !important;
}

.amount-credit {
    color: #28a745 !important;
}

.amount-debit {
    color: #dc3545 !important;
}

/* Fix for balance displays */
.balance-display {
    color: var(--text-color) !important;
}

.net-balance {
    color: var(--warning) !important;
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

.welcome-card {
    background: var(--dark-card);
    border: none;
    border-radius: 15px;
}

.welcome-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 215, 0, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('userChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Customers', 'Vendors', 'Admins'],
        datasets: [{
            data: [
                <?php echo $stats['users']['customer'] ?? 0; ?>,
                <?php echo $stats['users']['vendor'] ?? 0; ?>,
                <?php echo $stats['users']['admin'] ?? 0; ?>
            ],
            backgroundColor: [
                '#0dcaf0',  
                '#ffc107', 
                '#dc3545'   
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: '#ffffff'
                }
            }
        }
    }
});
</script>

 
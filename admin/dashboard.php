<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require admin privileges
requireAdmin();

// Get system statistics
$stats = [];

// Total users by role
$sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $stats['users'][$row['role']] = $row['count'];
}

// Total events
$sql = "SELECT COUNT(*) as count FROM events";
$result = $conn->query($sql);
$stats['total_events'] = $result->fetch_assoc()['count'];

// Total tickets sold
$sql = "SELECT COUNT(*) as count FROM tickets";
$result = $conn->query($sql);
$stats['tickets_sold'] = $result->fetch_assoc()['count'];

// Total revenue
$sql = "SELECT SUM(amount) as total FROM transactions WHERE type = 'credit'";
$result = $conn->query($sql);
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Recent transactions
$sql = "SELECT t.*, u.username FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY t.created_at DESC LIMIT 5";
$recent_transactions = $conn->query($sql);

// Recent users
$sql = "SELECT * FROM users ORDER BY created_at DESC LIMIT 5";
$recent_users = $conn->query($sql);
?>

<?php require_once '../includes/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3">
            <!-- Sidebar -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Admin Menu</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action active">Dashboard</a>
                    <a href="users.php" class="list-group-item list-group-item-action">Manage Users</a>
                    <a href="events.php" class="list-group-item list-group-item-action">Manage Events</a>
                    <a href="categories.php" class="list-group-item list-group-item-action">Manage Categories</a>
                    <a href="transactions.php" class="list-group-item list-group-item-action">View Transactions</a>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Total Events</h5>
                            <h2 class="card-text"><?php echo $stats['total_events']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Tickets Sold</h5>
                            <h2 class="card-text"><?php echo $stats['tickets_sold']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Total Users</h5>
                            <h2 class="card-text"><?php echo array_sum($stats['users']); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Total Revenue</h5>
                            <h2 class="card-text"><?php echo formatCurrency($stats['total_revenue']); ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Distribution -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">User Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="userChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Users</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <?php while ($user = $recent_users->fetch_assoc()): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($user['username']); ?></h6>
                                            <small><?php echo formatDate($user['created_at']); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo ucfirst($user['role']); ?></p>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Transactions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($transaction = $recent_transactions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                                        <td><?php echo formatCurrency($transaction['amount']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $transaction['type'] === 'credit' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($transaction['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                        <td><?php echo formatDate($transaction['created_at']); ?></td>
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

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// User distribution chart
const ctx = document.getElementById('userChart').getContext('2d');
new Chart(ctx, {
    type: 'pie',
    data: {
        labels: ['Customers', 'Vendors', 'Admins'],
        datasets: [{
            data: [
                <?php echo $stats['users']['customer'] ?? 0; ?>,
                <?php echo $stats['users']['vendor'] ?? 0; ?>,
                <?php echo $stats['users']['admin'] ?? 0; ?>
            ],
            backgroundColor: [
                '#36a2eb',
                '#ff6384',
                '#4bc0c0'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?> 
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require customer privileges
requireCustomer();

// Get customer information
$user_id = $_SESSION['user_id'];

// Get user details including wallet balance
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get upcoming events from purchased tickets
$sql = "SELECT e.*, t.ticket_code, t.purchase_date 
        FROM tickets t 
        JOIN events e ON t.event_id = e.id 
        WHERE t.user_id = ? AND e.date > NOW() 
        ORDER BY e.date ASC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_events = $stmt->get_result();

// Get recent transactions
$sql = "SELECT * FROM transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_transactions = $stmt->get_result();

// Get total tickets purchased
$sql = "SELECT COUNT(*) as count FROM tickets WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_tickets = $stmt->get_result()->fetch_assoc()['count'];

// Get total amount spent
$sql = "SELECT SUM(amount) as total FROM transactions 
        WHERE user_id = ? AND type = 'debit'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_spent = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
?>

<?php require_once '../includes/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3">
            <!-- Sidebar -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Customer Menu</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action active">Dashboard</a>
                    <a href="events.php" class="list-group-item list-group-item-action">Browse Events</a>
                    <a href="tickets.php" class="list-group-item list-group-item-action">My Tickets</a>
                    <a href="wallet.php" class="list-group-item list-group-item-action">My Wallet</a>
                </div>
            </div>

            <!-- Wallet Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">My Wallet</h5>
                </div>
                <div class="card-body">
                    <h2 class="text-primary mb-3"><?php echo formatCurrency($user['balance']); ?></h2>
                    <a href="wallet.php" class="btn btn-primary btn-sm">Add Funds</a>
                    <a href="transactions.php" class="btn btn-outline-primary btn-sm">View History</a>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Wallet Balance</h5>
                            <h2 class="card-text"><?php echo formatCurrency($user['balance']); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Tickets Purchased</h5>
                            <h2 class="card-text"><?php echo $total_tickets; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Total Spent</h5>
                            <h2 class="card-text"><?php echo formatCurrency($total_spent); ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">My Upcoming Events</h5>
                    <a href="tickets.php" class="btn btn-primary btn-sm">View All Tickets</a>
                </div>
                <div class="card-body">
                    <?php if ($upcoming_events->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date</th>
                                        <th>Location</th>
                                        <th>Ticket Code</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($event = $upcoming_events->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($event['title']); ?></td>
                                            <td><?php echo formatDate($event['date']); ?></td>
                                            <td><?php echo htmlspecialchars($event['location']); ?></td>
                                            <td><code><?php echo $event['ticket_code']; ?></code></td>
                                            <td>
                                                <a href="tickets/view.php?code=<?php echo $event['ticket_code']; ?>" class="btn btn-sm btn-primary">View</a>
                                                <a href="tickets/download.php?code=<?php echo $event['ticket_code']; ?>" class="btn btn-sm btn-success">Download</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No upcoming events. <a href="events.php">Browse events</a> to purchase tickets.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Transactions</h5>
                    <a href="transactions.php" class="btn btn-primary btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <?php if ($recent_transactions->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($transaction = $recent_transactions->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo formatDate($transaction['created_at']); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $transaction['type'] === 'credit' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($transaction['type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatCurrency($transaction['amount']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No recent transactions.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 
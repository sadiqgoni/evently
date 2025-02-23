<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require vendor privileges
requireVendor();

// Get vendor statistics
$vendor_id = $_SESSION['user_id'];

// Total events by vendor
$sql = "SELECT COUNT(*) as count FROM events WHERE vendor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$total_events = $stmt->get_result()->fetch_assoc()['count'];

// Total tickets sold
$sql = "SELECT COUNT(t.id) as count 
        FROM tickets t 
        JOIN events e ON t.event_id = e.id 
        WHERE e.vendor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$tickets_sold = $stmt->get_result()->fetch_assoc()['count'];

// Total earnings
$sql = "SELECT SUM(t.amount) as total 
        FROM transactions t 
        JOIN events e ON t.description LIKE CONCAT('%', e.title, '%')
        WHERE e.vendor_id = ? AND t.type = 'credit'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$total_earnings = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Upcoming events
$sql = "SELECT * FROM events 
        WHERE vendor_id = ? AND date > NOW() 
        ORDER BY date ASC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$upcoming_events = $stmt->get_result();

// Recent sales
$sql = "SELECT t.*, e.title as event_title, u.username 
        FROM tickets t 
        JOIN events e ON t.event_id = e.id 
        JOIN users u ON t.user_id = u.id
        WHERE e.vendor_id = ?
        ORDER BY t.purchase_date DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$recent_sales = $stmt->get_result();
?>

<?php require_once '../includes/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3">
            <!-- Sidebar -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Vendor Menu</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action active">Dashboard</a>
                    <a href="events/create.php" class="list-group-item list-group-item-action">Create Event</a>
                    <a href="events/index.php" class="list-group-item list-group-item-action">My Events</a>
                    <a href="sales.php" class="list-group-item list-group-item-action">Sales Report</a>
                    <a href="earnings.php" class="list-group-item list-group-item-action">Earnings</a>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Total Events</h5>
                            <h2 class="card-text"><?php echo $total_events; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Tickets Sold</h5>
                            <h2 class="card-text"><?php echo $tickets_sold; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Total Earnings</h5>
                            <h2 class="card-text"><?php echo formatCurrency($total_earnings); ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events and Recent Sales -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Upcoming Events</h5>
                            <a href="events/create.php" class="btn btn-primary btn-sm">Create Event</a>
                        </div>
                        <div class="card-body">
                            <?php if ($upcoming_events->num_rows > 0): ?>
                                <div class="list-group">
                                    <?php while ($event = $upcoming_events->fetch_assoc()): ?>
                                        <a href="events/edit.php?id=<?php echo $event['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                                <small><?php echo formatDate($event['date']); ?></small>
                                            </div>
                                            <p class="mb-1">
                                                Available Tickets: <?php echo $event['available_tickets']; ?> |
                                                Price: <?php echo formatCurrency($event['ticket_price']); ?>
                                            </p>
                                        </a>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No upcoming events.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Sales</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($recent_sales->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Event</th>
                                                <th>Customer</th>
                                                <th>Ticket Code</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($sale['event_title']); ?></td>
                                                    <td><?php echo htmlspecialchars($sale['username']); ?></td>
                                                    <td><code><?php echo $sale['ticket_code']; ?></code></td>
                                                    <td><?php echo formatDate($sale['purchase_date']); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No recent sales.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <a href="events/create.php" class="btn btn-primary btn-lg w-100 mb-3">
                                <i class="fas fa-plus"></i> Create Event
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="sales.php" class="btn btn-success btn-lg w-100 mb-3">
                                <i class="fas fa-chart-line"></i> View Sales Report
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="earnings.php" class="btn btn-info btn-lg w-100 mb-3">
                                <i class="fas fa-wallet"></i> Manage Earnings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 
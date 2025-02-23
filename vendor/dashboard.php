<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require vendor privileges
requireVendor();

// Get vendor statistics
$vendor_id = $_SESSION['user_id'];

// Get vendor details
$vendor = getUserDetails($vendor_id);

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
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card sidebar-card fade-in">
                <div class="card-body text-center p-4">
                    <div class="mb-4">
                        <div class="avatar-circle">
                            <i class="fas fa-user-circle fa-4x text-warning"></i>
                        </div>
                    </div>
                    <h5 class="card-title text-warning mb-1"><?php echo htmlspecialchars($vendor['first_name'] . ' ' . $vendor['last_name']); ?></h5>
                    <p class="text-light mb-3"><?php echo htmlspecialchars($vendor['email']); ?></p>
                    <hr class="border-light">
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="events/create.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-plus-circle me-2"></i>Create Event
                    </a>
                    <a href="events/index.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar me-2"></i>My Events
                    </a>
                    <a href="sales.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-line me-2"></i>Sales Report
                    </a>
                    <a href="earnings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-wallet me-2"></i>Earnings
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
                            <p class="text-light mb-0">Welcome to your vendor dashboard. Here's an overview of your events and sales.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stat-card fade-in">
                        <div class="card-body text-center p-4">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-calendar-alt fa-2x text-warning"></i>
                            </div>
                            <h2 class="stat-value text-warning mb-2"><?php echo $total_events; ?></h2>
                            <p class="stat-label text-light mb-0">Total Events</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card fade-in">
                        <div class="card-body text-center p-4">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-ticket-alt fa-2x text-warning"></i>
                            </div>
                            <h2 class="stat-value text-warning mb-2"><?php echo $tickets_sold; ?></h2>
                            <p class="stat-label text-light mb-0">Tickets Sold</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card fade-in">
                        <div class="card-body text-center p-4">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-dollar-sign fa-2x text-warning"></i>
                            </div>
                            <h2 class="stat-value text-warning mb-2"><?php echo formatCurrency($total_earnings); ?></h2>
                            <p class="stat-label text-light mb-0">Total Earnings</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card action-card mb-4 fade-in">
                <div class="card-header bg-transparent border-bottom border-warning">
                    <h5 class="text-warning mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="events/create.php" class="btn btn-warning w-100 py-3">
                                <i class="fas fa-plus-circle me-2"></i>Create New Event
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="sales.php" class="btn btn-outline-warning w-100 py-3">
                                <i class="fas fa-chart-bar me-2"></i>View Sales Report
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="earnings.php" class="btn btn-outline-warning w-100 py-3">
                                <i class="fas fa-wallet me-2"></i>Manage Earnings
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Upcoming Events -->
                <div class="col-md-6">
                    <div class="card event-card mb-4 fade-in">
                        <div class="card-header bg-transparent border-bottom border-warning d-flex justify-content-between align-items-center">
                            <h5 class="text-warning mb-0"><i class="fas fa-calendar me-2"></i>Upcoming Events</h5>
                            <a href="events/index.php" class="btn btn-sm btn-warning">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($upcoming_events->num_rows > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php while ($event = $upcoming_events->fetch_assoc()): ?>
                                        <a href="events/edit.php?id=<?php echo $event['id']; ?>" 
                                           class="list-group-item list-group-item-action border-bottom border-light">
                                            <div class="d-flex w-100 justify-content-between align-items-center p-3">
                                                <div>
                                                    <h6 class="text-warning mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                                    <small class="text-light">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($event['location']); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <div class="text-warning"><?php echo formatCurrency($event['ticket_price']); ?></div>
                                                    <small class="text-light"><?php echo formatDate($event['date']); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-3x text-warning mb-3"></i>
                                    <p class="text-light mb-3">No upcoming events</p>
                                    <a href="events/create.php" class="btn btn-sm btn-warning">Create Event</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Sales -->
                <div class="col-md-6">
                    <div class="card sales-card mb-4 fade-in">
                        <div class="card-header bg-transparent border-bottom border-warning d-flex justify-content-between align-items-center">
                            <h5 class="text-warning mb-0"><i class="fas fa-receipt me-2"></i>Recent Sales</h5>
                            <a href="sales.php" class="btn btn-sm btn-warning">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($recent_sales->num_rows > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                        <div class="list-group-item border-bottom border-light">
                                            <div class="d-flex w-100 justify-content-between align-items-center p-3">
                                                <div>
                                                    <h6 class="text-warning mb-1"><?php echo htmlspecialchars($sale['event_title']); ?></h6>
                                                    <small class="text-light">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($sale['username']); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <code class="text-warning"><?php echo $sale['ticket_code']; ?></code>
                                                    <div><small class="text-light"><?php echo formatDate($sale['purchase_date']); ?></small></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-shopping-cart fa-3x text-warning mb-3"></i>
                                    <p class="text-light mb-0">No recent sales</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styles for vendor dashboard */
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
    background: var(--dark-hover);
    color: var(--primary-color);
}

.list-group-item.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--dark-bg);
}

.welcome-card, .stat-card, .action-card, .event-card, .sales-card {
    background: var(--dark-card);
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.stat-card {
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

.btn-warning {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--dark-bg);
}

.btn-outline-warning {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.btn-outline-warning:hover {
    background-color: var(--primary-color);
    color: var(--dark-bg);
}
</style>

<?php require_once '../includes/footer.php'; ?> 
<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// Require vendor privileges
requireVendor();

// Get vendor details
$vendor_id = $_SESSION['user_id'];
$vendor = getUserDetails($vendor_id);

// Get all events by this vendor
$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM tickets WHERE event_id = e.id) as tickets_sold
        FROM events e 
        WHERE e.vendor_id = ? 
        ORDER BY e.date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$events = $stmt->get_result();
?>

<?php require_once '../../includes/header.php'; ?>

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
                    <a href="../dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="create.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-plus-circle me-2"></i>Create Event
                    </a>
                    <a href="index.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-calendar me-2"></i>My Events
                    </a>
                    <a href="../sales.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-line me-2"></i>Sales Report
                    </a>
                    <a href="../earnings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-wallet me-2"></i>Earnings
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <div class="card fade-in">
                <div class="card-header bg-transparent border-bottom border-warning d-flex justify-content-between align-items-center">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-calendar me-2"></i>My Events
                    </h5>
                    <a href="create.php" class="btn btn-warning">
                        <i class="fas fa-plus-circle me-2"></i>Create New Event
                    </a>
                </div>
                <div class="card-body p-4">
                    <?php if ($events->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date</th>
                                        <th>Location</th>
                                        <th>Price</th>
                                        <th>Tickets Sold</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($event = $events->fetch_assoc()): ?>
                                        <?php 
                                        $is_past = strtotime($event['date']) < time();
                                        $tickets_remaining = $event['available_tickets'] - $event['tickets_sold'];
                                        $status = $is_past ? 'Ended' : ($tickets_remaining > 0 ? 'Active' : 'Sold Out');
                                        $status_class = $is_past ? 'secondary' : ($tickets_remaining > 0 ? 'success' : 'danger');
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($event['image_url']): ?>
                                                        <img src="<?php echo $event['image_url']; ?>" 
                                                             class="rounded me-3" 
                                                             style="width: 48px; height: 48px; object-fit: cover;" 
                                                             alt="Event Image">
                                                    <?php endif; ?>
                                                    <div>
                                                        <h6 class="mb-0 text-warning"><?php echo htmlspecialchars($event['title']); ?></h6>
                                                        <small class="text-light"><?php echo $event['category']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-light">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo formatDate($event['date']); ?>
                                            </td>
                                            <td class="text-light">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($event['location']); ?>
                                            </td>
                                            <td class="text-warning">
                                                <?php echo formatCurrency($event['ticket_price']); ?>
                                            </td>
                                            <td class="text-light">
                                                <?php echo $event['tickets_sold']; ?> / <?php echo $event['available_tickets']; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo $status; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="edit.php?id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="view.php?id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-sm btn-outline-warning">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="tickets.php?event_id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-sm btn-outline-warning">
                                                        <i class="fas fa-ticket-alt"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-warning mb-3"></i>
                            <p class="text-light mb-3">You haven't created any events yet.</p>
                            <a href="create.php" class="btn btn-warning">
                                <i class="fas fa-plus-circle me-2"></i>Create Your First Event
                            </a>
                        </div>
                    <?php endif; ?>
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
    transform: translateX(5px);
    background-color: rgba(255, 215, 0, 0.1) !important;
}

.list-group-item.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--dark-bg);
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

.btn-group .btn {
    margin: 0 2px;
}
</style>

<?php require_once '../../includes/footer.php'; ?> 
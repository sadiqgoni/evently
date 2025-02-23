<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug point 1
echo "<!-- Debug 1: Starting execution -->\n";

// Require vendor privileges
requireVendor();

// Debug point 2
echo "<!-- Debug 2: After vendor check -->\n";

// Get vendor details
$vendor_id = $_SESSION['user_id'];
$vendor = getUserDetails($vendor_id);

// Debug point 3
echo "<!-- Debug 3: Vendor ID: " . $vendor_id . " -->\n";

// Get event ID
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Debug point 4
echo "<!-- Debug 4: Event ID: " . $event_id . " -->\n";

// Get event details with ticket count
$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id) as tickets_sold,
        (SELECT COUNT(*) * e.ticket_price FROM tickets t WHERE t.event_id = e.id) as total_sales
        FROM events e 
        WHERE e.id = ? AND e.vendor_id = ?";

// Debug point 5
echo "<!-- Debug 5: SQL Query with values: " . str_replace(['?', '?'], [$event_id, $vendor_id], $sql) . " -->\n";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $event_id, $vendor_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

// Debug point 6
echo "<!-- Debug 6: Event data: " . ($event ? 'found' : 'not found') . " -->\n";

if (!$event) {
    redirectWith('index.php', 'Event not found or access denied.', 'error');
}

// Get recent tickets
$sql = "SELECT t.*, u.first_name, u.last_name, u.email, t.purchase_date
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.event_id = ?
        ORDER BY t.purchase_date DESC
        LIMIT 5";

// Debug point 7
echo "<!-- Debug 7: Tickets SQL Query prepared -->\n";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$recent_tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Debug point 8
echo "<!-- Debug 8: Recent tickets count: " . count($recent_tickets) . " -->\n";
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
                <div class="card-header bg-transparent border-bottom border-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="text-warning mb-0">
                            <i class="fas fa-eye me-2"></i>View Event
                        </h5>
                        <div>
                            <a href="edit.php?id=<?php echo $event_id; ?>" class="btn btn-warning btn-sm me-2">
                                <i class="fas fa-edit me-2"></i>Edit Event
                            </a>
                            <a href="index.php" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-arrow-left me-2"></i>Back to Events
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <!-- Event Details -->
                        <div class="col-md-8">
                            <div class="event-image mb-4">
                                <?php if ($event['image_url']): ?>
                                    <img src="<?php echo $event['image_url']; ?>" class="img-fluid rounded" alt="Event Image">
                                <?php else: ?>
                                    <div class="placeholder-image">
                                        <i class="fas fa-image fa-4x"></i>
                                        <p>No image available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <h3 class="text-warning mb-3"><?php echo htmlspecialchars($event['title']); ?></h3>
                            
                            <div class="event-meta mb-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <i class="fas fa-calendar-alt text-warning me-2"></i>
                                            <?php echo date('F j, Y - g:i A', strtotime($event['date'])); ?>
                                        </p>
                                        <p class="mb-2">
                                            <i class="fas fa-map-marker-alt text-warning me-2"></i>
                                            <?php echo htmlspecialchars($event['location']); ?>
                                        </p>
                                        <p class="mb-2">
                                            <i class="fas fa-tag text-warning me-2"></i>
                                            <?php echo htmlspecialchars($event['category']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <i class="fas fa-ticket-alt text-warning me-2"></i>
                                            <?php echo number_format($event['tickets_sold']); ?> tickets sold
                                        </p>
                                        <p class="mb-2">
                                            <i class="fas fa-box text-warning me-2"></i>
                                            <?php echo number_format($event['available_tickets']); ?> tickets available
                                        </p>
                                        <p class="mb-2">
                                            <i class="fas fa-dollar-sign text-warning me-2"></i>
                                            ₦<?php echo number_format($event['total_sales'], 2); ?> total sales
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="event-description mb-4">
                                <h5 class="text-warning mb-3">Description</h5>
                                <p class="text-light"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                            </div>
                        </div>

                        <!-- Recent Tickets -->
                        <div class="col-md-4">
                            <div class="card bg-dark">
                                <div class="card-header bg-transparent border-warning">
                                    <h5 class="text-warning mb-0">
                                        <i class="fas fa-ticket-alt me-2"></i>Recent Tickets
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (empty($recent_tickets)): ?>
                                        <div class="text-center p-4">
                                            <i class="fas fa-ticket-alt fa-3x text-warning mb-3"></i>
                                            <p class="mb-0">No tickets sold yet</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($recent_tickets as $ticket): ?>
                                                <div class="list-group-item bg-dark border-light">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <h6 class="mb-1 text-warning">
                                                                <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
                                                            </h6>
                                                            <small class="text-light">
                                                                <?php echo htmlspecialchars($ticket['email']); ?>
                                                            </small>
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="text-warning">
                                                                $<?php echo number_format($ticket['price'], 2); ?>
                                                            </div>
                                                            <small class="text-light">
                                                                <?php echo formatDate($ticket['purchase_date']); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="card bg-dark mt-4">
                                <div class="card-header bg-transparent border-warning">
                                    <h5 class="text-warning mb-0">
                                        <i class="fas fa-bolt me-2"></i>Quick Actions
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <a href="../tickets/scan.php?event_id=<?php echo $event_id; ?>" class="btn btn-warning btn-block mb-3 w-100">
                                        <i class="fas fa-qrcode me-2"></i>Scan Tickets
                                    </a>
                                    <a href="#" class="btn btn-outline-warning btn-block w-100">
                                        <i class="fas fa-download me-2"></i>Download Report
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scanned Tickets -->
            <div class="card mt-4">
                <div class="card-header bg-transparent border-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="text-warning mb-0">
                            <i class="fas fa-check-circle me-2"></i>Scanned Tickets
                        </h5>
                        <a href="../tickets/scan.php?event_id=<?php echo $event_id; ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-qrcode me-2"></i>Scan More
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php
                    // Get scanned tickets
                    $sql = "SELECT t.*, u.first_name, u.last_name, u.email
                            FROM tickets t
                            JOIN users u ON t.user_id = u.id
                            WHERE t.event_id = ? AND t.is_used = 1
                            ORDER BY t.used_at DESC";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $event_id);
                    $stmt->execute();
                    $scanned_tickets = $stmt->get_result();
                    ?>

                    <?php if ($scanned_tickets->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Ticket Code</th>
                                        <th>Customer</th>
                                        <th>Email</th>
                                        <th>Scanned At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($ticket = $scanned_tickets->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <code class="text-warning"><?php echo $ticket['ticket_code']; ?></code>
                                            </td>
                                            <td class="text-light">
                                                <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
                                            </td>
                                            <td class="text-light">
                                                <?php echo htmlspecialchars($ticket['email']); ?>
                                            </td>
                                            <td class="text-light">
                                                <?php echo formatDate($ticket['used_at']); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-light text-center mb-0">No tickets have been scanned yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom styles */
    .table {
        color: var(--text-color);
    }

    .table > :not(caption) > * > * {
        background-color: transparent !important;
        border-bottom-color: rgba(255, 255, 255, 0.1);
    }

    .table thead th {
        color: var(--primary-color);
        font-weight: 600;
        border-bottom: 2px solid var(--primary-color);
    }

    .table tbody tr:hover {
        background-color: rgba(255, 215, 0, 0.1) !important;
    }

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
    .event-image {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
        max-height: 400px;
    }

    .event-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .placeholder-image {
        background-color: var(--dark-card);
        height: 300px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: rgba(255, 255, 255, 0.5);
        border-radius: 8px;
    }

    .event-meta {
        background-color: var(--dark-card);
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 2rem;
    }

    .event-meta p {
        color: var(--text-color);
        margin-bottom: 0.5rem;
    }

    .event-description {
        background-color: var(--dark-card);
        padding: 1.5rem;
        border-radius: 8px;
    }

    .btn-warning {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        color: var(--dark-bg);
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(255, 215, 0, 0.2);
    }

    .btn-outline-warning {
        border-color: var(--primary-color);
        color: var(--primary-color);
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-outline-warning:hover {
        background-color: var(--primary-color);
        color: var(--dark-bg);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(255, 215, 0, 0.2);
    }

    .list-group-item {
        transition: all 0.3s ease;
    }

    .list-group-item:hover {
        transform: translateX(5px);
        background-color: rgba(255, 215, 0, 0.1) !important;
    }
</style>


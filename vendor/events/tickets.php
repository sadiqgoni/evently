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
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Debug point 4
echo "<!-- Debug 4: Event ID: " . $event_id . " -->\n";

// Get event details and verify ownership
$sql = "SELECT * FROM events WHERE id = ? AND vendor_id = ?";

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

// Get tickets with user details
$sql = "SELECT t.*, u.first_name, u.last_name, u.email, e.ticket_price as price
        FROM tickets t 
        JOIN users u ON t.user_id = u.id 
        JOIN events e ON t.event_id = e.id
        WHERE t.event_id = ? 
        ORDER BY t.purchase_date DESC";

// Debug point 7
echo "<!-- Debug 7: Tickets SQL Query prepared -->\n";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$tickets = $stmt->get_result();

// Debug point 8
echo "<!-- Debug 8: Tickets count: " . $tickets->num_rows . " -->\n";

// Get ticket statistics
$sql = "SELECT 
        COUNT(*) as total_tickets,
        COALESCE(SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END), 0) as used_tickets,
        COALESCE(COUNT(*) * e.ticket_price, 0) as total_revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.id 
        WHERE t.event_id = ?";

// Debug point 9
echo "<!-- Debug 9: Stats SQL Query prepared -->\n";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Debug point 10
echo "<!-- Debug 10: Stats data retrieved -->\n";
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
                            <i class="fas fa-ticket-alt me-2"></i>Tickets for: <?php echo htmlspecialchars($event['title']); ?>
                        </h5>
                        <div>
                            <a href="../tickets/scan.php?event_id=<?php echo $event_id; ?>" class="btn btn-warning btn-sm me-2">
                                <i class="fas fa-qrcode me-2"></i>Scan Tickets
                            </a>
                            <a href="view.php?id=<?php echo $event_id; ?>" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-arrow-left me-2"></i>Back to Event
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <div class="stat-icon mb-3">
                                        <i class="fas fa-ticket-alt text-warning"></i>
                                    </div>
                                    <h3 class="text-warning mb-1"><?php echo number_format($stats['total_tickets'] ?? 0); ?></h3>
                                    <p class="text-light mb-0">Total Tickets</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <div class="stat-icon mb-3">
                                        <i class="fas fa-check-circle text-warning"></i>
                                    </div>
                                    <h3 class="text-warning mb-1"><?php echo number_format($stats['used_tickets'] ?? 0); ?></h3>
                                    <p class="text-light mb-0">Used Tickets</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <div class="stat-icon mb-3">
                                        <i class="fas fa-dollar-sign text-warning"></i>
                                    </div>
                                    <h3 class="text-warning mb-1"><?php echo formatCurrency($stats['total_revenue'] ?? 0); ?></h3>
                                    <p class="text-light mb-0">Total Revenue</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tickets Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Ticket Code</th>
                                    <th>Customer</th>
                                    <th>Purchase Date</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($ticket = $tickets->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <code class="text-warning"><?php echo $ticket['ticket_code']; ?></code>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="text-warning"><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></div>
                                                <small class="text-light"><?php echo htmlspecialchars($ticket['email']); ?></small>
                                            </div>
                                        </td>
                                        <td class="text-light">
                                            <?php echo formatDate($ticket['purchase_date']); ?>
                                        </td>
                                        <td class="text-warning">
                                            <?php echo formatCurrency($ticket['price']); ?>
                                        </td>
                                        <td>
                                            <?php if ($ticket['is_used']): ?>
                                                <span class="badge bg-secondary">
                                                    Used on <?php echo formatDate($ticket['used_at']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Valid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-warning" onclick="showQRCode('<?php echo $ticket['ticket_code']; ?>')">
                                                    <i class="fas fa-qrcode"></i>
                                                </button>
                                                <a href="../customer/download_ticket.php?code=<?php echo $ticket['ticket_code']; ?>" 
                                                   class="btn btn-sm btn-outline-warning">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </div>
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

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-warning">
                <h5 class="modal-title text-warning">Ticket QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrcode"></div>
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

#qrcode {
    padding: 20px;
    background: white;
    border-radius: 8px;
    display: inline-block;
}
</style>

<script>
function showQRCode(data) {
    const modal = new bootstrap.Modal(document.getElementById('qrModal'));
    const qrcodeDiv = document.getElementById('qrcode');
    qrcodeDiv.innerHTML = '';
    
    new QRCode(qrcodeDiv, {
        text: data,
        width: 256,
        height: 256
    });
    
    modal.show();
}
</script>


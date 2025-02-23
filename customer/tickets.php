<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require customer privileges
requireCustomer();

// Get customer information
$user_id = $_SESSION['user_id'];
$user = getUserDetails($user_id);

// Get user's tickets
$sql = "SELECT t.*, e.title as event_title, e.date as event_date, e.location, e.ticket_price,
        u.first_name as organizer_first_name, u.last_name as organizer_last_name
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        JOIN users u ON e.vendor_id = u.id
        WHERE t.user_id = ?
        ORDER BY e.date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tickets = $stmt->get_result();

// Group tickets by event
$grouped_tickets = [];
while ($ticket = $tickets->fetch_assoc()) {
    $event_id = $ticket['event_id'];
    if (!isset($grouped_tickets[$event_id])) {
        $grouped_tickets[$event_id] = [
            'event_title' => $ticket['event_title'],
            'event_date' => $ticket['event_date'],
            'location' => $ticket['location'],
            'ticket_price' => $ticket['ticket_price'],
            'organizer' => $ticket['organizer_first_name'] . ' ' . $ticket['organizer_last_name'],
            'tickets' => []
        ];
    }
    $grouped_tickets[$event_id]['tickets'][] = $ticket;
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
                            <i class="fas fa-user-circle fa-4x text-warning"></i>
                        </div>
                    </div>
                    <h5 class="card-title text-warning mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                    <p class="text-light mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                    <hr class="border-light">
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="events.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-alt me-2"></i>Browse Events
                    </a>
                    <a href="tickets.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-ticket-alt me-2"></i>My Tickets
                    </a>
                    <a href="wallet.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-wallet me-2"></i>My Wallet
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <?php if (empty($grouped_tickets)): ?>
                <div class="card fade-in">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-ticket-alt fa-3x text-warning mb-3"></i>
                        <h4 class="text-warning">No Tickets Found</h4>
                        <p class="text-light mb-4">You haven't purchased any tickets yet.</p>
                        <a href="events.php" class="btn btn-warning">
                            <i class="fas fa-calendar-alt me-2"></i>Browse Events
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($grouped_tickets as $event_id => $event): ?>
                    <div class="card event-tickets-card fade-in mb-4">
                        <div class="card-header bg-transparent border-warning">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="text-warning mb-0">
                                    <i class="fas fa-calendar-alt me-2"></i><?php echo htmlspecialchars($event['event_title']); ?>
                                </h5>
                                <span class="badge bg-warning">
                                    <?php echo count($event['tickets']); ?> ticket(s)
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="event-meta text-light mb-4">
                                <p class="mb-2">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo formatDate($event['event_date']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo htmlspecialchars($event['location']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-user me-2"></i>
                                    <?php echo htmlspecialchars($event['organizer']); ?>
                                </p>
                                <p class="mb-0">
                                    <i class="fas fa-tag me-2"></i>
                                    <?php echo formatCurrency($event['ticket_price']); ?> per ticket
                                </p>
                            </div>

                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Ticket Code</th>
                                            <th>Purchase Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($event['tickets'] as $ticket): ?>
                                            <tr>
                                                <td>
                                                    <code class="text-warning"><?php echo $ticket['ticket_code']; ?></code>
                                                </td>
                                                <td class="text-light">
                                                    <?php echo formatDate($ticket['purchase_date']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($ticket['is_used']): ?>
                                                        <span class="badge bg-secondary">Used</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Valid</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" 
                                                            onclick="showQRCode('<?php echo $ticket['ticket_code']; ?>', '<?php echo $ticket['ticket_code']; ?>')">
                                                        <i class="fas fa-qrcode"></i>
                                                    </button>
                                                    <a href="download_ticket.php?code=<?php echo $ticket['ticket_code']; ?>" 
                                                       class="btn btn-sm btn-outline-warning">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
                <div id="qrcode" class="mb-3 p-3 bg-white d-inline-block rounded"></div>
                <p class="text-warning mb-0" id="ticketCode"></p>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styles */
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

.event-tickets-card {
    background: var(--dark-card);
    border: none;
    border-radius: 15px;
    overflow: hidden;
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

#qrcode {
    background: white !important;
    padding: 15px !important;
    border-radius: 10px;
    margin: 0 auto;
}

#qrcode img {
    display: block !important;
    margin: 0 auto;
}
</style>

<script>
function showQRCode(data, ticketCode) {
    const modal = new bootstrap.Modal(document.getElementById('qrModal'));
    const qrcodeDiv = document.getElementById('qrcode');
    const ticketCodeDiv = document.getElementById('ticketCode');
    
    // Clear previous QR code
    qrcodeDiv.innerHTML = '';
    ticketCodeDiv.textContent = 'Ticket Code: ' + ticketCode;
    
    // Generate new QR code with better settings
    new QRCode(qrcodeDiv, {
        text: data,
        width: 256,
        height: 256,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });
    
    modal.show();
}

function downloadTicket(ticketCode) {
    // This would typically generate a PDF ticket
    alert('Ticket download feature will be implemented soon!');
}
</script>

<?php require_once '../includes/footer.php'; ?> 
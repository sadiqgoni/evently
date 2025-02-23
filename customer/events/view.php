<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// Require customer privileges
requireCustomer();

// Get event ID
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get customer information
$user_id = $_SESSION['user_id'];
$user = getUserDetails($user_id);

// Get event details
$sql = "SELECT e.*, 
        u.first_name as organizer_first_name, 
        u.last_name as organizer_last_name,
        u.email as organizer_email,
        (SELECT COUNT(*) FROM tickets WHERE event_id = e.id) as tickets_sold
        FROM events e
        JOIN users u ON e.vendor_id = u.id
        WHERE e.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    redirectWith('../events.php', 'Event not found.', 'error');
}

// Calculate remaining tickets
$tickets_remaining = $event['available_tickets'] - $event['tickets_sold'];

// Handle ticket purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_ticket'])) {
    $quantity = intval($_POST['quantity']);
    $total_cost = $quantity * $event['ticket_price'];
    
    // Validate quantity
    if ($quantity <= 0 || $quantity > $tickets_remaining) {
        redirectWith("view.php?id=$event_id", 'Invalid ticket quantity.', 'error');
    }
    
    // Check user balance
    if ($user['balance'] < $total_cost) {
        redirectWith("view.php?id=$event_id", 'Insufficient balance. Please add funds to your wallet.', 'error');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update user balance
        $sql = "UPDATE users SET balance = balance - ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $total_cost, $user_id);
        $stmt->execute();
        
        // Create tickets
        for ($i = 0; $i < $quantity; $i++) {
            $ticket_code = generateTicketCode();
            $qr_data = generateTicketQRCode($ticket_code, $event_id, $user_id);
            
            $sql = "INSERT INTO tickets (event_id, user_id, ticket_code, qr_code, qr_code_path, price) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisssd", $event_id, $user_id, $ticket_code, $qr_data['qr_data'], $qr_data['qr_path'], $event['ticket_price']);
            $stmt->execute();
        }
        
        // Record transaction for customer
        $description = "Purchased $quantity ticket(s) for " . $event['title'];
        $sql = "INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ids", $user_id, $total_cost, $description);
        $stmt->execute();
        
        // Credit vendor
        $sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $total_cost, $event['vendor_id']);
        $stmt->execute();
        
        // Record vendor transaction
        $vendor_description = "Sold $quantity ticket(s) for " . $event['title'];
        $sql = "INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ids", $event['vendor_id'], $total_cost, $vendor_description);
        $stmt->execute();
        
        $conn->commit();
        redirectWith("../tickets.php", 'Tickets purchased successfully! Check your tickets below.', 'success');
    } catch (Exception $e) {
        $conn->rollback();
        redirectWith("view.php?id=$event_id", 'Failed to purchase tickets: ' . $e->getMessage(), 'error');
    }
}
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
                    <h5 class="card-title text-warning mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                    <p class="text-light mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                    <hr class="border-light">
                </div>
                <div class="list-group list-group-flush">
                    <a href="../dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="../events.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-calendar-alt me-2"></i>Browse Events
                    </a>
                    <a href="../tickets.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-ticket-alt me-2"></i>My Tickets
                    </a>
                    <a href="../wallet.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-wallet me-2"></i>My Wallet
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <div class="card event-details-card fade-in">
                <div class="card-header bg-transparent border-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="text-warning mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>Event Details
                        </h5>
                        <a href="../events.php" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-arrow-left me-2"></i>Back to Events
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Event Image -->
                        <div class="col-md-6 mb-4">
                            <img src="<?php echo $event['image_url'] ?: 'https://via.placeholder.com/600x400?text=Event+Image'; ?>" 
                                 class="img-fluid rounded" 
                                 alt="<?php echo htmlspecialchars($event['title']); ?>">
                        </div>

                        <!-- Event Info -->
                        <div class="col-md-6 mb-4">
                            <h3 class="text-warning mb-3"><?php echo htmlspecialchars($event['title']); ?></h3>
                            
                            <div class="event-meta text-light mb-4">
                                <p class="mb-2">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo formatDate($event['date']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo htmlspecialchars($event['location']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-user me-2"></i>
                                    <?php echo htmlspecialchars($event['organizer_first_name'] . ' ' . $event['organizer_last_name']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-envelope me-2"></i>
                                    <?php echo htmlspecialchars($event['organizer_email']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-tag me-2"></i>
                                    <?php echo htmlspecialchars($event['category']); ?>
                                </p>
                            </div>

                            <div class="ticket-info mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h4 class="text-warning mb-0">
                                        <?php if ($event['ticket_price'] > 0): ?>
                                            <?php echo formatCurrency($event['ticket_price']); ?>
                                        <?php else: ?>
                                            Free
                                        <?php endif; ?>
                                    </h4>
                                    <span class="badge bg-<?php echo $tickets_remaining > 0 ? 'success' : 'danger'; ?>">
                                        <?php echo $tickets_remaining > 0 ? $tickets_remaining . ' tickets left' : 'Sold Out'; ?>
                                    </span>
                                </div>

                                <?php if ($tickets_remaining > 0): ?>
                                    <form method="POST" class="ticket-form">
                                        <div class="mb-3">
                                            <label class="form-label text-warning">Quantity</label>
                                            <div class="input-group">
                                                <button type="button" class="btn btn-outline-warning" onclick="updateQuantity(-1)">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" class="form-control text-center" 
                                                       id="quantity" name="quantity" 
                                                       value="1" min="1" max="<?php echo $tickets_remaining; ?>"
                                                       onchange="updateTotal()">
                                                <button type="button" class="btn btn-outline-warning" onclick="updateQuantity(1)">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="text-light">Total Amount:</span>
                                            <span class="text-warning h4 mb-0" id="totalAmount">
                                                <?php echo formatCurrency($event['ticket_price']); ?>
                                            </span>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="text-light">Your Balance:</span>
                                            <span class="text-warning">
                                                <?php echo formatCurrency($user['balance']); ?>
                                            </span>
                                        </div>

                                        <div class="d-grid">
                                            <button type="submit" name="purchase_ticket" class="btn btn-warning btn-lg">
                                                <i class="fas fa-shopping-cart me-2"></i>Purchase Tickets
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Event Description -->
                        <div class="col-12">
                            <h4 class="text-warning mb-3">About This Event</h4>
                            <div class="event-description text-light">
                                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
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

.event-details-card {
    background: var(--dark-card);
    border: none;
    border-radius: 15px;
    overflow: hidden;
}

.event-meta p {
    font-size: 1rem;
    opacity: 0.9;
}

.ticket-form .form-control {
    background-color: var(--dark-card);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--text-color);
}

.ticket-form .form-control:focus {
    background-color: var(--dark-card);
    border-color: var(--primary-color);
    color: var(--text-color);
    box-shadow: none;
}

.event-description {
    line-height: 1.8;
    opacity: 0.9;
}
</style>

<script>
function updateQuantity(change) {
    const quantityInput = document.getElementById('quantity');
    const currentValue = parseInt(quantityInput.value);
    const newValue = currentValue + change;
    const maxValue = parseInt(quantityInput.max);
    
    if (newValue >= 1 && newValue <= maxValue) {
        quantityInput.value = newValue;
        updateTotal();
    }
}

function updateTotal() {
    const quantity = parseInt(document.getElementById('quantity').value);
    const price = <?php echo $event['ticket_price']; ?>;
    const total = quantity * price;
    document.getElementById('totalAmount').textContent = '₦' + total.toFixed(2);
}
</script>


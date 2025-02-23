<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require customer privileges
requireCustomer();

// Get customer information
$user_id = $_SESSION['user_id'];
$user = getUserDetails($user_id);
// Get customer information

// Handle add funds
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_funds'])) {
    $amount = floatval($_POST['amount']);
    
    if ($amount <= 0) {
        redirectWith('wallet.php', 'Invalid amount.', 'error');
    }
    
    // Simulate successful payment
    $conn->begin_transaction();
    
    try {
        // Update user balance
        $sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $amount, $user_id);
        $stmt->execute();
        
        // Record transaction
        $description = "Added funds to wallet";
        $sql = "INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'credit', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ids", $user_id, $amount, $description);
        $stmt->execute();
        
        $conn->commit();
        redirectWith('wallet.php', 'Funds added successfully!', 'success');
    } catch (Exception $e) {
        $conn->rollback();
        redirectWith('wallet.php', 'Failed to add funds. Please try again.', 'error');
    }
}
// Get upcoming events from purchased tickets
$sql = "SELECT e.*, t.ticket_code, t.purchase_date, t.is_used,
        v.first_name as vendor_first_name, v.last_name as vendor_last_name
        FROM tickets t 
        JOIN events e ON t.event_id = e.id 
        JOIN users v ON e.vendor_id = v.id
        WHERE t.user_id = ? AND e.date > NOW() 
        ORDER BY e.date ASC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_events = $stmt->get_result();

// Get recent transactions
$sql = "SELECT t.*, e.title as event_title 
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE t.user_id = ? 
        ORDER BY t.purchase_date DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_transactions = $stmt->get_result();

// Get total tickets purchased
$sql = "SELECT 
        COUNT(*) as total_tickets,
        COUNT(CASE WHEN t.is_used = 1 THEN 1 END) as used_tickets,
        SUM(e.ticket_price) as total_spent
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE t.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get monthly spending data
$sql = "SELECT 
        DATE_FORMAT(t.purchase_date, '%Y-%m') as month,
        COUNT(*) as tickets_bought,
        SUM(e.ticket_price) as amount_spent
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE t.user_id = ?
        GROUP BY month
        ORDER BY month DESC
        LIMIT 12";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$monthly_spending = $stmt->get_result();
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
                    <a href="dashboard.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="events.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-alt me-2"></i>Browse Events
                    </a>
                    <a href="tickets.php" class="list-group-item list-group-item-action">
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
            <!-- Wallet Overview -->
            <div class="card fade-in mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-4">
                                <div class="wallet-icon me-3">
                                    <i class="fas fa-wallet fa-3x text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="text-light mb-1">Wallet Balance</h6>
                                    <h2 class="text-warning mb-0"><?php echo formatCurrency($user['balance']); ?></h2>
                                </div>
                            </div>
                            <p class="text-light mb-0">
                                <i class="fas fa-ticket-alt me-2"></i>
                                <?php echo number_format($stats['total_tickets']); ?> tickets purchased
                            </p>
                        </div>
                        <div class="col-md-6 text-end">
                            <button class="btn btn-warning btn-lg" data-bs-toggle="modal" data-bs-target="#addFundsModal">
                                <i class="fas fa-plus-circle me-2"></i>Add Funds
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-ticket-alt text-warning"></i>
                            </div>
                            <h3 class="text-warning mb-1"><?php echo number_format($stats['total_tickets']); ?></h3>
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
                            <h3 class="text-warning mb-1"><?php echo number_format($stats['used_tickets']); ?></h3>
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
                            <h3 class="text-warning mb-1"><?php echo formatCurrency($stats['total_spent']); ?></h3>
                            <p class="text-light mb-0">Total Spent</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="card fade-in mb-4">
                <div class="card-header bg-transparent border-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="text-warning mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>Upcoming Events
                        </h5>
                        <a href="tickets.php" class="btn btn-warning btn-sm">View All Tickets</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($upcoming_events->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date</th>
                                        <th>Organizer</th>
                                        <th>Ticket Code</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($event = $upcoming_events->fetch_assoc()): ?>
                                        <tr>
                                            <td class="text-warning"><?php echo htmlspecialchars($event['title']); ?></td>
                                            <td class="text-light"><?php echo formatDate($event['date']); ?></td>
                                            <td class="text-light">
                                                <?php echo htmlspecialchars($event['vendor_first_name'] . ' ' . $event['vendor_last_name']); ?>
                                            </td>
                                            <td><code class="text-warning"><?php echo $event['ticket_code']; ?></code></td>
                                            <td>
                                                <?php if ($event['is_used']): ?>
                                                    <span class="badge bg-success">Used</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Valid</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="tickets/view.php?code=<?php echo $event['ticket_code']; ?>" 
                                                   class="btn btn-warning btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="tickets/download.php?code=<?php echo $event['ticket_code']; ?>" 
                                                   class="btn btn-outline-warning btn-sm">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-warning mb-3"></i>
                            <p class="text-light mb-3">No upcoming events found.</p>
                            <a href="events.php" class="btn btn-warning">Browse Events</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Monthly Spending -->
            <div class="card fade-in">
                <div class="card-header bg-transparent border-warning">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Monthly Spending
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Tickets Bought</th>
                                    <th>Amount Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($month = $monthly_spending->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-warning">
                                            <?php echo date('F Y', strtotime($month['month'] . '-01')); ?>
                                        </td>
                                        <td class="text-light">
                                            <?php echo number_format($month['tickets_bought']); ?>
                                        </td>
                                        <td class="text-warning">
                                            <?php echo formatCurrency($month['amount_spent']); ?>
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

<!-- Add Funds Modal -->
<div class="modal fade" id="addFundsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-warning">
                <h5 class="modal-title text-warning">Add Funds to Wallet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addFundsForm" method="POST" onsubmit="return simulatePayment(event)">
                    <div class="mb-3">
                        <label for="amount" class="form-label text-warning">Amount to Add</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-warning text-warning">$</span>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   min="1" step="0.01" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-warning">Payment Method</label>
                        <div class="d-flex gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" 
                                       id="credit_card" value="credit_card" checked>
                                <label class="form-check-label text-light" for="credit_card">
                                    <i class="fas fa-credit-card me-1"></i>Credit Card
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" 
                                       id="paypal" value="paypal">
                                <label class="form-check-label text-light" for="paypal">
                                    <i class="fab fa-paypal me-1"></i>PayPal
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="creditCardFields">
                        <div class="mb-3">
                            <label class="form-label text-warning">Card Number</label>
                            <input type="text" class="form-control" placeholder="1234 5678 9012 3456">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-warning">Expiry Date</label>
                                <input type="text" class="form-control" placeholder="MM/YY">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-warning">CVV</label>
                                <input type="text" class="form-control" placeholder="123">
                            </div>
                        </div>
                    </div>

                    <div id="paypalFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label text-warning">PayPal Email</label>
                            <input type="email" class="form-control" placeholder="your@email.com">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-warning">
                <button type="button" class="btn btn-outline-warning" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addFundsForm" class="btn btn-warning">
                    <i class="fas fa-plus-circle me-2"></i>Add Funds
                </button>
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

.wallet-icon {
    width: 80px;
    height: 80px;
    background: rgba(255, 215, 0, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
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

.form-control, .form-select {
    background-color: var(--dark-card);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--text-color);
}

.form-control:focus, .form-select:focus {
    background-color: var(--dark-card);
    border-color: var(--primary-color);
    color: var(--text-color);
    box-shadow: none;
}

.form-select option {
    background-color: var(--dark-card);
    color: var(--text-color);
}
</style>

<script>
// Toggle payment method fields
document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('creditCardFields').style.display = 
            this.value === 'credit_card' ? 'block' : 'none';
        document.getElementById('paypalFields').style.display = 
            this.value === 'paypal' ? 'block' : 'none';
    });
});

// Simulate payment processing
function simulatePayment(event) {
    event.preventDefault();
    
    const amount = document.getElementById('amount').value;
    const loadingBtn = event.submitter;
    const originalText = loadingBtn.innerHTML;
    
    // Show loading state
    loadingBtn.disabled = true;
    loadingBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    
    // Simulate payment processing
    setTimeout(() => {
        loadingBtn.disabled = false;
        loadingBtn.innerHTML = originalText;
        
        // Add a hidden input for the add_funds flag
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'add_funds';
        input.value = '1';
        event.target.appendChild(input);
        
        // Submit the form
        event.target.submit();
    }, 2000);
    
    return false;
}
</script>

 
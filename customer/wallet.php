<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require customer privileges
requireCustomer();

// Get customer information
$user_id = $_SESSION['user_id'];
$user = getUserDetails($user_id);

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

// Get recent transactions
$sql = "SELECT * FROM transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result();
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
                    <a href="tickets.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-ticket-alt me-2"></i>My Tickets
                    </a>
                    <a href="wallet.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-wallet me-2"></i>My Wallet
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Wallet Balance -->
            <div class="card wallet-card fade-in mb-4">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <div class="wallet-icon me-4">
                                    <i class="fas fa-wallet fa-3x text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="text-light mb-1">Available Balance</h6>
                                    <h2 class="text-warning mb-0"><?php echo formatCurrency($user['balance']); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end mt-3 mt-md-0">
                            <button class="btn btn-warning btn-lg" data-bs-toggle="modal" data-bs-target="#addFundsModal">
                                <i class="fas fa-plus-circle me-2"></i>Add Funds
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card transaction-card fade-in">
                <div class="card-header bg-transparent border-warning">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-history me-2"></i>Recent Transactions
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($transactions->num_rows > 0): ?>
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
                                    <?php while ($transaction = $transactions->fetch_assoc()): ?>
                                        <tr>
                                            <td class="text-light">
                                                <?php echo formatDate($transaction['created_at']); ?>
                                            </td>
                                            <td class="text-light">
                                                <?php echo htmlspecialchars($transaction['description']); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $transaction['type'] === 'credit' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($transaction['type']); ?>
                                                </span>
                                            </td>
                                            <td class="text-<?php echo $transaction['type'] === 'credit' ? 'success' : 'danger'; ?>">
                                                <?php echo $transaction['type'] === 'credit' ? '+' : '-'; ?>
                                                <?php echo formatCurrency($transaction['amount']); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-receipt fa-3x text-warning mb-3"></i>
                            <h4 class="text-warning">No Transactions</h4>
                            <p class="text-light">You haven't made any transactions yet.</p>
                        </div>
                    <?php endif; ?>
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
                            <span class="input-group-text bg-dark border-warning text-warning">₦</span>
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

.wallet-card, .transaction-card {
    background: var(--dark-card);
    border: none;
    border-radius: 15px;
    overflow: hidden;
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

.form-control {
    background-color: var(--dark-card);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--text-color);
}

.form-control:focus {
    background-color: var(--dark-card);
    border-color: var(--primary-color);
    color: var(--text-color);
    box-shadow: none;
}

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
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

 
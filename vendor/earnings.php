<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require vendor privileges
requireVendor();

// Get vendor details
$vendor_id = $_SESSION['user_id'];
$vendor = getUserDetails($vendor_id);

// Get total earnings
$sql = "SELECT 
        COALESCE(SUM(e.ticket_price), 0) as total_earnings,
        COUNT(*) as total_sales
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE e.vendor_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$earnings = $stmt->get_result()->fetch_assoc();

// Get monthly earnings
$sql = "SELECT 
        DATE_FORMAT(t.purchase_date, '%Y-%m') as month,
        COUNT(*) as tickets_sold,
        SUM(e.ticket_price) as revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE e.vendor_id = ?
        GROUP BY month
        ORDER BY month DESC
        LIMIT 12";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$monthly_earnings = $stmt->get_result();

// Get recent transactions
$sql = "SELECT t.*, e.title as event_title
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE e.vendor_id = ?
        ORDER BY t.purchase_date DESC
        LIMIT 10";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$recent_transactions = $stmt->get_result();
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
                    <a href="dashboard.php" class="list-group-item list-group-item-action">
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
                    <a href="earnings.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-wallet me-2"></i>Earnings
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Earnings Overview -->
            <div class="card fade-in mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-4">
                                <div class="earnings-icon me-3">
                                    <i class="fas fa-wallet fa-3x text-warning"></i>
                                </div>
                                <div>
                                    <h6 class="text-light mb-1">Total Earnings</h6>
                                    <h2 class="text-warning mb-0">₦<?php echo number_format($earnings['total_earnings']); ?></h2>
                                </div>
                            </div>
                            <p class="text-light mb-0">
                                <i class="fas fa-ticket-alt me-2"></i>
                                <?php echo number_format($earnings['total_sales']); ?> tickets sold
                            </p>
                        </div>
                        <div class="col-md-6 text-end">
                            <button class="btn btn-warning btn-lg" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                                <i class="fas fa-money-bill-wave me-2"></i>Withdraw Earnings
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Earnings -->
            <div class="card fade-in mb-4">
                <div class="card-header bg-transparent border-warning">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Monthly Earnings
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Tickets Sold</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($month = $monthly_earnings->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-warning">
                                            <?php echo date('F Y', strtotime($month['month'] . '-01')); ?>
                                        </td>
                                        <td class="text-light">
                                            <?php echo number_format($month['tickets_sold']); ?>
                                        </td>
                                        <td class="text-warning">
                                            ₦<?php echo number_format($month['revenue']); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card fade-in">
                <div class="card-header bg-transparent border-warning">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-receipt me-2"></i>Recent Transactions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Event</th>
                                    <th>Ticket Code</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($transaction = $recent_transactions->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-light">
                                            <?php echo formatDate($transaction['purchase_date']); ?>
                                        </td>
                                        <td class="text-warning">
                                            <?php echo htmlspecialchars($transaction['event_title']); ?>
                                        </td>
                                        <td>
                                            <code class="text-warning"><?php echo $transaction['ticket_code']; ?></code>
                                        </td>
                                        <td class="text-warning">
                                            ₦<?php echo number_format($transaction['price']); ?>
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

<!-- Withdraw Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-warning">
                <h5 class="modal-title text-warning">Withdraw Earnings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="withdrawForm" onsubmit="handleWithdrawal(event)">
                    <div class="mb-3">
                        <label for="amount" class="form-label text-warning">Withdrawal Amount</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-warning text-warning">₦</span>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   min="1000" step="0.01" required>
                        </div>
                        <small class="text-light">Minimum withdrawal: ₦1,000</small>
                    </div>

                    <div class="mb-3">
                        <label for="bank_name" class="form-label text-warning">Bank Name</label>
                        <input type="text" class="form-control" id="bank_name" name="bank_name" required>
                    </div>

                    <div class="mb-3">
                        <label for="account_number" class="form-label text-warning">Account Number</label>
                        <input type="text" class="form-control" id="account_number" name="account_number" 
                               pattern="[0-9]{10}" title="Please enter a valid 10-digit account number" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-money-bill-wave me-2"></i>Withdraw
                        </button>
                    </div>
                </form>
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

.earnings-icon {
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
async function handleWithdrawal(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    
    try {
        const response = await fetch('process_withdrawal.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Withdrawal request processed successfully! The funds will be transferred to your account within 24 hours.');
            location.reload(); // Reload page to update balance
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred while processing your withdrawal request. Please try again.');
    }
    
    // Hide modal
    bootstrap.Modal.getInstance(document.getElementById('withdrawModal')).hide();
}
</script>

 
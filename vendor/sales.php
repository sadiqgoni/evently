<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require vendor privileges
requireVendor();

// Get vendor details
$vendor_id = $_SESSION['user_id'];
$vendor = getUserDetails($vendor_id);

// Get date range from query parameters or default to current month
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get sales statistics
$sql = "SELECT 
        COUNT(DISTINCT t.event_id) as total_events,
        COUNT(t.id) as total_tickets,
        COALESCE(SUM(e.ticket_price), 0) as total_revenue,
        COUNT(DISTINCT t.user_id) as unique_customers
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE e.vendor_id = ? 
        AND t.purchase_date BETWEEN ? AND ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $vendor_id, $start_date, $end_date);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get daily sales data for chart
$sql = "SELECT 
        DATE(t.purchase_date) as sale_date,
        COUNT(*) as tickets_sold,
        SUM(e.ticket_price) as revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE e.vendor_id = ?
        AND t.purchase_date BETWEEN ? AND ?
        GROUP BY DATE(t.purchase_date)
        ORDER BY sale_date";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $vendor_id, $start_date, $end_date);
$stmt->execute();
$daily_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get sales by event
$sql = "SELECT 
        e.title,
        COUNT(t.id) as tickets_sold,
        SUM(e.ticket_price) as revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE e.vendor_id = ?
        AND t.purchase_date BETWEEN ? AND ?
        GROUP BY e.id
        ORDER BY tickets_sold DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $vendor_id, $start_date, $end_date);
$stmt->execute();
$event_sales = $stmt->get_result();
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
                    <a href="sales.php" class="list-group-item list-group-item-action active">
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
            <!-- Date Range Filter -->
            <div class="card fade-in mb-4">
                <div class="card-body">
                    <form class="row g-3" method="GET">
                        <div class="col-md-4">
                            <label class="form-label text-warning">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-warning">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-calendar-alt text-warning"></i>
                            </div>
                            <h3 class="text-warning mb-1"><?php echo number_format($stats['total_events']); ?></h3>
                            <p class="text-light mb-0">Events</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-ticket-alt text-warning"></i>
                            </div>
                            <h3 class="text-warning mb-1"><?php echo number_format($stats['total_tickets']); ?></h3>
                            <p class="text-light mb-0">Tickets Sold</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-dollar-sign text-warning"></i>
                            </div>
                            <h3 class="text-warning mb-1"><?php echo formatCurrency($stats['total_revenue']); ?></h3>
                            <p class="text-light mb-0">Revenue</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body text-center">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-users text-warning"></i>
                            </div>
                            <h3 class="text-warning mb-1"><?php echo number_format($stats['unique_customers']); ?></h3>
                            <p class="text-light mb-0">Customers</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Chart -->
            <div class="card fade-in mb-4">
                <div class="card-header bg-transparent border-warning">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-chart-line me-2"></i>Sales Trend
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="300"></canvas>
                </div>
            </div>

            <!-- Sales by Event -->
            <div class="card fade-in">
                <div class="card-header bg-transparent border-warning">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-list me-2"></i>Sales by Event
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Tickets Sold</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($event = $event_sales->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-warning"><?php echo htmlspecialchars($event['title']); ?></td>
                                        <td class="text-light"><?php echo number_format($event['tickets_sold']); ?></td>
                                        <td class="text-warning"><?php echo formatCurrency($event['revenue']); ?></td>
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
</style>

<script>
// Prepare data for the chart
const dates = <?php echo json_encode(array_column($daily_sales, 'sale_date')); ?>;
const revenue = <?php echo json_encode(array_column($daily_sales, 'revenue')); ?>;
const tickets = <?php echo json_encode(array_column($daily_sales, 'tickets_sold')); ?>;

// Create the chart
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: dates,
        datasets: [{
            label: 'Revenue',
            data: revenue,
            borderColor: '#FFD700',
            backgroundColor: 'rgba(255, 215, 0, 0.1)',
            yAxisID: 'y',
            fill: true
        }, {
            label: 'Tickets Sold',
            data: tickets,
            borderColor: '#FFA500',
            backgroundColor: 'rgba(255, 165, 0, 0.1)',
            yAxisID: 'y1',
            fill: true
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                },
                ticks: {
                    color: '#FFD700',
                    callback: function(value) {
                        return '$' + value;
                    }
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false
                },
                ticks: {
                    color: '#FFA500'
                }
            },
            x: {
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                },
                ticks: {
                    color: '#FFFFFF'
                }
            }
        },
        plugins: {
            legend: {
                labels: {
                    color: '#FFFFFF'
                }
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?> 
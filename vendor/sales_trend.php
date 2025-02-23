<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require vendor privileges
requireVendor();

// Get vendor details
$vendor_id = $_SESSION['user_id'];
$vendor = getUserDetails($vendor_id);

// Get date range from query parameters or default to last 30 days
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get sales trend data
$sql = "SELECT 
            DATE(t.purchase_date) as sale_date,
            COUNT(*) as tickets_sold,
            SUM(t.price) as revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE e.vendor_id = ? 
        AND DATE(t.purchase_date) BETWEEN ? AND ?
        GROUP BY DATE(t.purchase_date)
        ORDER BY sale_date";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $vendor_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$sales_data = [];
$total_revenue = 0;
$total_tickets = 0;

while ($row = $result->fetch_assoc()) {
    $sales_data[] = $row;
    $total_revenue += $row['revenue'];
    $total_tickets += $row['tickets_sold'];
}

// Get event-wise sales
$sql = "SELECT 
            e.title,
            COUNT(*) as tickets_sold,
            SUM(t.price) as revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE e.vendor_id = ? 
        AND DATE(t.purchase_date) BETWEEN ? AND ?
        GROUP BY e.id
        ORDER BY revenue DESC";

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
                    <a href="sales_trend.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-chart-line me-2"></i>Sales Trend
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
            <div class="card mb-4 fade-in">
                <div class="card-body">
                    <form method="GET" class="row align-items-end">
                        <div class="col-md-4">
                            <label class="form-label text-warning">Start Date</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-warning">End Date</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fas fa-filter me-2"></i>Apply Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card stat-card fade-in">
                        <div class="card-body text-center p-4">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-ticket-alt fa-2x text-warning"></i>
                            </div>
                            <h2 class="stat-value text-warning mb-2"><?php echo $total_tickets; ?></h2>
                            <p class="stat-label text-light mb-0">Total Tickets Sold</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card stat-card fade-in">
                        <div class="card-body text-center p-4">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-money-bill-wave fa-2x text-warning"></i>
                            </div>
                            <h2 class="stat-value text-warning mb-2"><?php echo formatCurrency($total_revenue); ?></h2>
                            <p class="stat-label text-light mb-0">Total Revenue</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Trend Chart -->
            <div class="card mb-4 fade-in">
                <div class="card-header bg-transparent border-warning">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-chart-line me-2"></i>Sales Trend
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>

            <!-- Event-wise Sales -->
            <div class="card fade-in">
                <div class="card-header bg-transparent border-warning">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-chart-pie me-2"></i>Event-wise Sales
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
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
                                        <td class="text-light"><?php echo $event['tickets_sold']; ?></td>
                                        <td class="text-light"><?php echo formatCurrency($event['revenue']); ?></td>
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

.form-control {
    background-color: var(--dark-card);
    border: 1px solid rgba(255, 215, 0, 0.2);
    color: var(--text-color);
}

.form-control:focus {
    background-color: var(--dark-card);
    border-color: var(--primary-color);
    color: var(--text-color);
    box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.25);
}
</style>

<script>
// Prepare data for the chart
const salesData = <?php echo json_encode($sales_data); ?>;
const dates = salesData.map(item => item.sale_date);
const revenue = salesData.map(item => item.revenue);
const tickets = salesData.map(item => item.tickets_sold);

// Create the chart
const ctx = document.getElementById('salesTrendChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: dates,
        datasets: [
            {
                label: 'Revenue (₦)',
                data: revenue,
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                fill: true,
                yAxisID: 'y'
            },
            {
                label: 'Tickets Sold',
                data: tickets,
                borderColor: '#17a2b8',
                backgroundColor: 'rgba(23, 162, 184, 0.1)',
                fill: true,
                yAxisID: 'y1'
            }
        ]
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
                title: {
                    display: true,
                    text: 'Revenue (₦)',
                    color: '#ffc107'
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                },
                ticks: {
                    color: '#ffc107'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Tickets Sold',
                    color: '#17a2b8'
                },
                grid: {
                    drawOnChartArea: false
                },
                ticks: {
                    color: '#17a2b8'
                }
            },
            x: {
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                },
                ticks: {
                    color: '#ffffff'
                }
            }
        },
        plugins: {
            legend: {
                labels: {
                    color: '#ffffff'
                }
            }
        }
    }
});
</script> 
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require vendor privileges
requireVendor();

// Get vendor details
$vendor_id = $_SESSION['user_id'];
$vendor = getUserDetails($vendor_id);

// Get daily sales for the last 30 days
$sql = "SELECT 
        DATE(t.purchase_date) as sale_date,
        COUNT(*) as tickets_sold,
        SUM(t.price) as revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE e.vendor_id = ? 
        AND t.purchase_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(t.purchase_date)
        ORDER BY sale_date";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$daily_sales = $stmt->get_result();

// Get sales by event
$sql = "SELECT 
        e.title,
        COUNT(*) as tickets_sold,
        SUM(t.price) as revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE e.vendor_id = ?
        GROUP BY e.id
        ORDER BY revenue DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$event_sales = $stmt->get_result();

// Get sales by category
$sql = "SELECT 
        e.category,
        COUNT(*) as tickets_sold,
        SUM(t.price) as revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE e.vendor_id = ?
        GROUP BY e.category
        ORDER BY revenue DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$category_sales = $stmt->get_result();

// Prepare data for charts
$dates = [];
$revenues = [];
$tickets = [];

while ($row = $daily_sales->fetch_assoc()) {
    $dates[] = date('M j', strtotime($row['sale_date']));
    $revenues[] = $row['revenue'];
    $tickets[] = $row['tickets_sold'];
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
                    <a href="sales_trend.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-chart-bar me-2"></i>Sales Trend
                    </a>
                    <a href="earnings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-wallet me-2"></i>Earnings
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Debug Information -->
            <div class="card fade-in mb-4">
                <div class="card-header bg-transparent border-warning">
                    <h5 class="text-warning mb-0">Debug Information</h5>
                </div>
                <div class="card-body">
                    <pre class="text-light">
<?php
echo "Dates: " . json_encode($dates) . "\n";
echo "Revenues: " . json_encode($revenues) . "\n";
echo "Tickets: " . json_encode($tickets) . "\n";
?>
                    </pre>
                </div>
            </div>

            <!-- Revenue Trend Chart -->
            <div class="card fade-in mb-4">
                <div class="card-header bg-transparent border-warning">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-chart-line me-2"></i>Revenue Trend (Last 30 Days)
                    </h5>
                </div>
                <div class="card-body" style="height: 400px;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <div class="row">
                <!-- Sales by Event -->
                <div class="col-md-6">
                    <div class="card fade-in mb-4">
                        <div class="card-header bg-transparent border-warning">
                            <h5 class="text-warning mb-0">
                                <i class="fas fa-ticket-alt me-2"></i>Sales by Event
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Tickets</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($event = $event_sales->fetch_assoc()): ?>
                                            <tr>
                                                <td class="text-warning"><?php echo htmlspecialchars($event['title']); ?></td>
                                                <td class="text-light"><?php echo number_format($event['tickets_sold']); ?></td>
                                                <td class="text-warning">₦<?php echo number_format($event['revenue']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales by Category -->
                <div class="col-md-6">
                    <div class="card fade-in mb-4">
                        <div class="card-header bg-transparent border-warning">
                            <h5 class="text-warning mb-0">
                                <i class="fas fa-tags me-2"></i>Sales by Category
                            </h5>
                        </div>
                        <div class="card-body" style="height: 400px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Wait for the page to load
window.onload = function() {
    console.log('Page loaded, initializing charts...');
    
    // Get the chart data
    const dates = <?php echo json_encode($dates); ?>;
    const revenues = <?php echo json_encode($revenues); ?>;
    const tickets = <?php echo json_encode($tickets); ?>;
    
    console.log('Chart data:', { dates, revenues, tickets });

    // Initialize Revenue Chart
    const revenueChart = new Chart(
        document.getElementById('revenueChart'),
        {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Revenue (₦)',
                        data: revenues,
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        fill: true
                    },
                    {
                        label: 'Tickets Sold',
                        data: tickets,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        }
    );

    // Initialize Category Chart
    const categoryLabels = [
        <?php 
        $category_sales->data_seek(0);
        while ($category = $category_sales->fetch_assoc()) {
            echo "'" . addslashes($category['category']) . "',";
        }
        ?>
    ];
    
    const categoryValues = [
        <?php 
        $category_sales->data_seek(0);
        while ($category = $category_sales->fetch_assoc()) {
            echo $category['revenue'] . ",";
        }
        ?>
    ];

    console.log('Category data:', { categoryLabels, categoryValues });

    const categoryChart = new Chart(
        document.getElementById('categoryChart'),
        {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryValues,
                    backgroundColor: ['#ffc107', '#28a745', '#17a2b8', '#dc3545', '#6610f2', '#fd7e14']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        }
    );
}
</script>

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

/* Additional styles for charts */
.card-body {
    padding: 1rem;
}

canvas {
    background-color: transparent;
}
</style> 
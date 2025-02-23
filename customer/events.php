<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require customer privileges
requireCustomer();

// Get customer information
$user_id = $_SESSION['user_id'];
$user = getUserDetails($user_id);

// Initialize filters
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$date_filter = isset($_GET['date_filter']) ? sanitize($_GET['date_filter']) : 'all';
$price_filter = isset($_GET['price_filter']) ? sanitize($_GET['price_filter']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build the SQL query
$sql = "SELECT e.*, 
        u.first_name as organizer_first_name, 
        u.last_name as organizer_last_name,
        (SELECT COUNT(*) FROM tickets WHERE event_id = e.id) as tickets_sold
        FROM events e
        JOIN users u ON e.vendor_id = u.id
        WHERE e.date >= CURDATE()";

// Apply filters
if ($category) {
    $sql .= " AND e.category = ?";
}

switch ($date_filter) {
    case 'today':
        $sql .= " AND DATE(e.date) = CURDATE()";
        break;
    case 'tomorrow':
        $sql .= " AND DATE(e.date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'this_week':
        $sql .= " AND YEARWEEK(e.date) = YEARWEEK(CURDATE())";
        break;
    case 'this_month':
        $sql .= " AND MONTH(e.date) = MONTH(CURDATE()) AND YEAR(e.date) = YEAR(CURDATE())";
        break;
}

switch ($price_filter) {
    case 'free':
        $sql .= " AND e.ticket_price = 0";
        break;
    case 'paid':
        $sql .= " AND e.ticket_price > 0";
        break;
}

if ($search) {
    $sql .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
}

$sql .= " ORDER BY e.date ASC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);

// Bind parameters if needed
if ($category && $search) {
    $search_param = "%$search%";
    $stmt->bind_param("ssss", $category, $search_param, $search_param, $search_param);
} elseif ($category) {
    $stmt->bind_param("s", $category);
} elseif ($search) {
    $search_param = "%$search%";
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
}

$stmt->execute();
$events = $stmt->get_result();

// Get categories for filter
$sql = "SELECT DISTINCT category FROM events ORDER BY category";
$categories = $conn->query($sql);
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
                    <a href="events.php" class="list-group-item list-group-item-action active">
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

            <!-- Filters -->
            <div class="card mt-4 fade-in">
                <div class="card-header bg-transparent border-warning">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-filter me-2"></i>Filters
                    </h5>
                </div>
                <div class="card-body">
                    <form action="" method="GET" id="filterForm">
                        <div class="mb-3">
                            <label class="form-label text-warning">Search</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search events...">
                                <button class="btn btn-warning" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-warning">Category</label>
                            <select class="form-select" name="category" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                            <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-warning">Date</label>
                            <select class="form-select" name="date_filter" onchange="this.form.submit()">
                                <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Dates</option>
                                <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="tomorrow" <?php echo $date_filter === 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
                                <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="this_month" <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-warning">Price</label>
                            <select class="form-select" name="price_filter" onchange="this.form.submit()">
                                <option value="all" <?php echo $price_filter === 'all' ? 'selected' : ''; ?>>All Events</option>
                                <option value="free" <?php echo $price_filter === 'free' ? 'selected' : ''; ?>>Free Events</option>
                                <option value="paid" <?php echo $price_filter === 'paid' ? 'selected' : ''; ?>>Paid Events</option>
                            </select>
                        </div>

                        <button type="button" class="btn btn-outline-warning w-100" onclick="resetFilters()">
                            <i class="fas fa-undo me-2"></i>Reset Filters
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Events Grid -->
            <div class="row">
                <?php if ($events->num_rows > 0): ?>
                    <?php while ($event = $events->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card event-card h-100 fade-in">
                                <img src="<?php echo $event['image_url'] ?: 'https://via.placeholder.com/300x200?text=Event+Image'; ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($event['title']); ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title text-warning mb-0">
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </h5>
                                        <?php if ($event['ticket_price'] > 0): ?>
                                            <span class="badge bg-warning">
                                                <?php echo formatCurrency($event['ticket_price']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Free</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-light mb-3">
                                        <?php echo substr(htmlspecialchars($event['description']), 0, 100) . '...'; ?>
                                    </p>
                                    <div class="event-details text-light">
                                        <p class="mb-1">
                                            <i class="fas fa-calendar me-2"></i>
                                            <?php echo formatDate($event['date']); ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-map-marker-alt me-2"></i>
                                            <?php echo htmlspecialchars($event['location']); ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-user me-2"></i>
                                            <?php echo htmlspecialchars($event['organizer_first_name'] . ' ' . $event['organizer_last_name']); ?>
                                        </p>
                                        <p class="mb-0">
                                            <i class="fas fa-ticket-alt me-2"></i>
                                            <?php 
                                            $tickets_remaining = $event['available_tickets'] - $event['tickets_sold'];
                                            echo $tickets_remaining . ' tickets remaining';
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent border-warning">
                                    <div class="d-grid">
                                        <a href="events/view.php?id=<?php echo $event['id']; ?>" 
                                           class="btn btn-warning">
                                            <i class="fas fa-ticket-alt me-2"></i>Get Tickets
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-warning mb-3"></i>
                            <h4 class="text-warning">No Events Found</h4>
                            <p class="text-light">Try adjusting your filters or search criteria.</p>
                            <button class="btn btn-warning" onclick="resetFilters()">
                                <i class="fas fa-undo me-2"></i>Reset Filters
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
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

.event-card {
    background: var(--dark-card);
    border: none;
    border-radius: 15px;
    overflow: hidden;
    transition: transform 0.3s ease;
}

.event-card:hover {
    transform: translateY(-5px);
}

.event-card .card-img-top {
    height: 200px;
    object-fit: cover;
}

.event-details p {
    font-size: 0.9rem;
    opacity: 0.9;
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
function resetFilters() {
    window.location.href = 'events.php';
}
</script>

 
<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Initialize filters
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';

// Build the SQL query
$sql = "SELECT e.*, u.username as organizer,
        (SELECT COUNT(*) FROM tickets WHERE event_id = e.id) as tickets_sold
        FROM events e
        JOIN users u ON e.vendor_id = u.id
        WHERE e.date >= CURDATE()";

$params = [];
$types = "";

// Apply filters
if ($category) {
    $sql .= " AND e.category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($search) {
    $sql .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
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

$sql .= " ORDER BY e.date ASC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$events = $stmt->get_result();

// Get categories for filter
$sql = "SELECT DISTINCT category FROM events ORDER BY category";
$categories = $conn->query($sql);
?>

<?php require_once 'includes/header.php'; ?>

<div class="container mt-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card welcome-card fade-in">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="welcome-icon me-4">
                            <i class="fas fa-calendar-alt fa-2x text-warning"></i>
                        </div>
                        <div>
                            <h4 class="mb-1 text-warning">
                                <?php echo $category ? htmlspecialchars($category) . ' Events' : 'All Events'; ?>
                            </h4>
                            <p class="text-light mb-0">Discover and book amazing events that match your interests.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Filters Sidebar -->
        <div class="col-md-3">
            <div class="card fade-in mb-4">
                <div class="card-header bg-transparent border-warning">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-filter me-2"></i>Filters
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="search-form">
                        <div class="mb-3">
                            <label class="form-label text-warning">Search</label>
                            <input type="text" class="form-control text-light" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search events...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-warning">Category</label>
                            <select class="form-select text-light" name="category">
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
                            <select class="form-select text-light" name="date">
                                <option value="">Any Date</option>
                                <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="tomorrow" <?php echo $date_filter === 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
                                <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="this_month" <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-search me-2"></i>Apply Filters
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="resetFilters()">
                                <i class="fas fa-undo me-2"></i>Reset Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Events Grid -->
        <div class="col-md-9">
            <div class="row">
                <?php if ($events->num_rows > 0): ?>
                    <?php while ($event = $events->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card event-card h-100 fade-in">
                                <img src="<?php echo $event['image_url'] ?: 'https://via.placeholder.com/300x200?text=Event+Image'; ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($event['title']); ?>">
                                <div class="card-body">
                                    <h5 class="card-title text-warning"><?php echo htmlspecialchars($event['title']); ?></h5>
                                    <p class="event-meta text-light">
                                        <i class="fas fa-calendar me-2"></i><?php echo formatDate($event['date']); ?><br>
                                        <i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($event['location']); ?><br>
                                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($event['organizer']); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="h5 mb-0 text-warning"><?php echo formatCurrency($event['ticket_price']); ?></span>
                                        <a href="events/view.php?id=<?php echo $event['id']; ?>" class="btn btn-warning">
                                            View Details
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
                            <p class="text-light mb-4">No events match your current filters.</p>
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
.welcome-card,
.event-card {
    background: var(--dark-card);
    border: none;
    border-radius: 15px;
    overflow: hidden;
}

.welcome-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 215, 0, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.event-meta {
    font-size: 0.9rem;
    opacity: 0.9;
}

.event-card {
    transition: transform 0.3s ease;
}

.event-card:hover {
    transform: translateY(-5px);
}

.event-card .card-img-top {
    height: 200px;
    object-fit: cover;
}

.form-control, .form-select {
    background-color: var(--dark-card);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--text-color) !important;
}

.form-control:focus, .form-select:focus {
    background-color: var(--dark-card);
    border-color: var(--primary-color);
    color: var(--text-color) !important;
    box-shadow: none;
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.5);
}
</style>

<script>
function resetFilters() {
    window.location.href = 'events.php';
}
</script>

<?php require_once 'includes/footer.php'; ?> 
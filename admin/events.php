<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require admin privileges
requireAdmin();

// Get admin information
$admin_id = $_SESSION['user_id'];
$admin = getUserDetails($admin_id);

// Handle event actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $event_id = intval($_POST['event_id']);
    $action = $_POST['action'];
    
    switch ($action) {
        case 'delete':
            $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
            $stmt->bind_param("i", $event_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Event deleted successfully.";
                $_SESSION['message_type'] = "success";
            }
            break;
            
        case 'feature':
            $stmt = $conn->prepare("UPDATE events SET is_featured = 1 WHERE id = ?");
            $stmt->bind_param("i", $event_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Event featured successfully.";
                $_SESSION['message_type'] = "success";
            }
            break;
            
        case 'unfeature':
            $stmt = $conn->prepare("UPDATE events SET is_featured = 0 WHERE id = ?");
            $stmt->bind_param("i", $event_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Event unfeatured successfully.";
                $_SESSION['message_type'] = "success";
            }
            break;
    }
    
    header("Location: events.php");
    exit();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build the query
$sql = "SELECT e.*, u.username as organizer_username, 
        u.first_name as organizer_first_name, 
        u.last_name as organizer_last_name,
        (SELECT COUNT(*) FROM tickets WHERE event_id = e.id) as tickets_sold
        FROM events e
        JOIN users u ON e.vendor_id = u.id
        WHERE 1=1";

$params = [];
$types = "";

if ($search) {
    $sql .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= "sss";
}

if ($category_filter) {
    $sql .= " AND e.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if ($status_filter === 'upcoming') {
    $sql .= " AND e.date > NOW()";
} elseif ($status_filter === 'past') {
    $sql .= " AND e.date < NOW()";
}

$sql .= " ORDER BY e.date DESC";

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

<?php require_once '../includes/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card sidebar-card fade-in">
                <div class="card-body text-center p-4">
                    <div class="mb-4">
                        <div class="avatar-circle">
                            <i class="fas fa-user-shield fa-4x text-warning"></i>
                        </div>
                    </div>
                    <h5 class="card-title text-warning mb-1">
                        <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>
                    </h5>
                    <p class="text-light mb-3"><?php echo htmlspecialchars($admin['email']); ?></p>
                    <div class="badge bg-warning mb-3">Administrator</div>
                    <hr class="border-light">
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                    <a href="events.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-calendar me-2"></i>Manage Events
                    </a>
                    <a href="transactions.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-money-bill-wave me-2"></i>Transactions
                    </a>
                    <a href="settings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Page Header -->
            <div class="card welcome-card mb-4 fade-in">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="welcome-icon me-4">
                                <i class="fas fa-calendar fa-2x text-warning"></i>
                            </div>
                            <div>
                                <h4 class="mb-1 text-warning">Manage Events</h4>
                                <p class="text-light mb-0">View and manage all events in the system.</p>
                            </div>
                        </div>
                        <div class="btn-group">
                            <a href="../vendor/events/create.php" class="btn btn-warning">
                                <i class="fas fa-plus-circle me-2"></i>Create Event
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4 fade-in">
                <div class="card-body">
                    <form method="GET" class="row g-3 search-form">
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" class="form-control text-light" name="search" 
                                       placeholder="Search events..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-warning" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select text-light" name="category" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php while ($category = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($category['category']); ?>"
                                            <?php echo $category_filter === $category['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select text-light" name="status" onchange="this.form.submit()">
                                <option value="">All Events</option>
                                <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                <option value="past" <?php echo $status_filter === 'past' ? 'selected' : ''; ?>>Past</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-warning w-100" onclick="resetFilters()">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Events Table -->
            <div class="card fade-in">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Organizer</th>
                                    <th>Date</th>
                                    <th>Price</th>
                                    <th>Tickets</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($event = $events->fetch_assoc()): ?>
                                    <?php 
                                    $is_past = strtotime($event['date']) < time();
                                    $tickets_remaining = $event['available_tickets'] - $event['tickets_sold'];
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($event['image_url']): ?>
                                                    <img src="<?php echo $event['image_url']; ?>" 
                                                         class="rounded me-3" 
                                                         style="width: 48px; height: 48px; object-fit: cover;" 
                                                         alt="Event Image">
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-0 text-warning">
                                                        <?php echo htmlspecialchars($event['title']); ?>
                                                    </h6>
                                                    <small class="text-light">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($event['location']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <h6 class="mb-0 text-warning">
                                                    <?php echo htmlspecialchars($event['organizer_username']); ?>
                                                </h6>
                                                <small class="text-light">
                                                    <?php echo htmlspecialchars($event['organizer_first_name'] . ' ' . $event['organizer_last_name']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="badge bg-<?php echo $is_past ? 'secondary' : 'success'; ?>">
                                                    <?php echo $is_past ? 'Past' : 'Upcoming'; ?>
                                                </span>
                                                <div><small class="text-light"><?php echo formatDate($event['date']); ?></small></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-warning">
                                                <?php echo formatCurrency($event['ticket_price']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <h6 class="mb-0 text-warning">
                                                    <?php echo $event['tickets_sold']; ?> / <?php echo $event['available_tickets']; ?>
                                                </h6>
                                                <small class="text-light">
                                                    <?php echo $tickets_remaining; ?> remaining
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="../vendor/events/view.php?id=<?php echo $event['id']; ?>" 
                                                   class="btn btn-sm btn-warning">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="../vendor/events/edit.php?id=<?php echo $event['id']; ?>" 
                                                   class="btn btn-sm btn-outline-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if (!$event['is_featured']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                                            onclick="featureEvent(<?php echo $event['id']; ?>)">
                                                        <i class="fas fa-star"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                            onclick="unfeatureEvent(<?php echo $event['id']; ?>)">
                                                        <i class="fas fa-star"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteEvent(<?php echo $event['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
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

<!-- Event Action Forms -->
<form id="featureForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="feature">
    <input type="hidden" name="event_id" value="">
</form>

<form id="unfeatureForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="unfeature">
    <input type="hidden" name="event_id" value="">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="event_id" value="">
</form>

<style>
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

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
}

.stat-label {
    font-size: 1rem;
    opacity: 0.9;
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
function resetFilters() {
    window.location.href = 'events.php';
}

function featureEvent(eventId) {
    if (confirm('Are you sure you want to feature this event?')) {
        const form = document.getElementById('featureForm');
        form.querySelector('input[name="event_id"]').value = eventId;
        form.submit();
    }
}

function unfeatureEvent(eventId) {
    if (confirm('Are you sure you want to unfeature this event?')) {
        const form = document.getElementById('unfeatureForm');
        form.querySelector('input[name="event_id"]').value = eventId;
        form.submit();
    }
}

function deleteEvent(eventId) {
    if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
        const form = document.getElementById('deleteForm');
        form.querySelector('input[name="event_id"]').value = eventId;
        form.submit();
    }
}
</script>

 
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Get event ID
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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
    redirectWith('/', 'Event not found.', 'error');
}

// Calculate remaining tickets
$tickets_remaining = $event['available_tickets'] - $event['tickets_sold'];

// Check if user is logged in
$is_logged_in = isLoggedIn();

require_once '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <div class="card event-details-card fade-in">
                <div class="card-header bg-transparent border-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="text-warning mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>Event Details
                        </h4>
                        <a href="/evently" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-arrow-left me-2"></i>Back to Events
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Event Image -->
                    <img src="<?php echo $event['image_url'] ?: 'https://via.placeholder.com/800x400?text=Event+Image'; ?>" 
                         class="img-fluid rounded mb-4" 
                         alt="<?php echo htmlspecialchars($event['title']); ?>">

                    <!-- Event Title and Basic Info -->
                    <h2 class="text-warning mb-4"><?php echo htmlspecialchars($event['title']); ?></h2>
                    
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
                        <p class="mb-0">
                            <i class="fas fa-tag me-2"></i>
                            <?php echo htmlspecialchars($event['category']); ?>
                        </p>
                    </div>

                    <!-- Event Description -->
                    <h5 class="text-warning mb-3">About This Event</h5>
                    <div class="event-description text-light mb-4">
                        <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ticket Information -->
        <div class="col-md-4">
            <div class="card ticket-card fade-in">
                <div class="card-body">
                    <h4 class="text-warning mb-4">
                        <i class="fas fa-ticket-alt me-2"></i>Tickets
                    </h4>

                    <div class="ticket-price mb-4">
                        <h2 class="text-warning mb-2">
                            <?php echo formatCurrency($event['ticket_price']); ?>
                        </h2>
                        <span class="badge bg-<?php echo $tickets_remaining > 0 ? 'success' : 'danger'; ?> mb-2">
                            <?php echo $tickets_remaining > 0 ? $tickets_remaining . ' tickets left' : 'Sold Out'; ?>
                        </span>
                        <p class="text-light mb-0">
                            <small><?php echo $event['tickets_sold']; ?> tickets sold</small>
                        </p>
                    </div>

                    <?php if (!$is_logged_in): ?>
                        <div class="text-center">
                            <p class="text-light mb-3">Please login to purchase tickets</p>
                            <a href="/evently/auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                               class="btn btn-warning btn-lg w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Purchase
                            </a>
                        </div>
                    <?php else: ?>
                        <?php if ($tickets_remaining > 0): ?>
                            <a href="/evently/customer/events/view.php?id=<?php echo $event['id']; ?>" 
                               class="btn btn-warning btn-lg w-100">
                                <i class="fas fa-shopping-cart me-2"></i>Purchase Tickets
                            </a>
                        <?php else: ?>
                            <button class="btn btn-danger btn-lg w-100" disabled>
                                <i class="fas fa-times-circle me-2"></i>Sold Out
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.event-details-card,
.ticket-card {
    background: var(--dark-card);
    border: none;
    border-radius: 15px;
    overflow: hidden;
}

.event-meta p {
    font-size: 1rem;
    opacity: 0.9;
}

.event-description {
    line-height: 1.8;
    opacity: 0.9;
}

.ticket-price {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
</style>

<?php require_once '../includes/footer.php'; ?> 
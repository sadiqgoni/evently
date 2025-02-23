<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get featured events
$sql = "SELECT e.*, u.username as organizer FROM events e 
        JOIN users u ON e.vendor_id = u.id 
        WHERE e.date > NOW() 
        ORDER BY e.date ASC LIMIT 6";
$featured_events = $conn->query($sql);

// Get event categories
$sql = "SELECT DISTINCT category FROM events ORDER BY category";
$categories = $conn->query($sql);
?>

<?php require_once 'includes/header.php'; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content text-center" data-aos="fade-up">
            <h1>Discover and Attend<br><span>Amazing Events</span></h1>
            <p class="lead mb-5">Find, book, and enjoy a wide range of events with our easy-to-use platform. From concerts to conferences, we've got you covered.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="events.php" class="btn btn-custom-primary btn-lg">
                    <i class="fas fa-search me-2"></i>Get Started
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Key Features Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-heading">Key Features</h2>
            <p class="lead text-muted">Everything you need to discover, attend, and manage events in one place.</p>
        </div>
        <div class="row">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-card">
                    <i class="fas fa-search"></i>
                    <h3>Event Discovery</h3>
                    <p>Easily find and explore a wide range of events tailored to your interests.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-card">
                    <i class="fas fa-qrcode"></i>
                    <h3>Secure Ticketing</h3>
                    <p>Purchase and store tickets securely with our integrated QR code system.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="feature-card">
                    <i class="fas fa-user-circle"></i>
                    <h3>User Dashboard</h3>
                    <p>Manage your events, tickets, and preferences from a personalized dashboard.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Events Section -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-heading mb-0">Featured <span>Events</span></h2>
            <a href="events.php" class="btn btn-custom-primary">View All Events</a>
        </div>
        <div class="row">
            <?php if ($featured_events->num_rows > 0): ?>
                <?php while ($event = $featured_events->fetch_assoc()): ?>
                    <div class="col-md-4 mb-4" data-aos="fade-up">
                        <div class="card event-card h-100">
                            <img src="<?php echo $event['image_url'] ?: 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&q=80'; ?>" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($event['title']); ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <p class="event-meta">
                                    <i class="fas fa-calendar me-2"></i><?php echo formatDate($event['date']); ?><br>
                                    <i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($event['location']); ?><br>
                                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($event['organizer']); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <span class="h5 mb-0"><?php echo formatCurrency($event['ticket_price']); ?></span>
                                    <a href="events/view.php?id=<?php echo $event['id']; ?>" class="btn btn-custom-primary">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p class="lead">No upcoming events at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="mb-4">Browse by Category</h2>
        <div class="row">
            <?php while ($category = $categories->fetch_assoc()): ?>
                <div class="col-md-3 mb-3">
                    <a href="events.php?category=<?php echo urlencode($category['category']); ?>" 
                       class="card text-center h-100 text-decoration-none">
                        <div class="card-body">
                            <i class="fas fa-folder mb-3 text-primary fa-2x"></i>
                            <h5 class="card-title"><?php echo htmlspecialchars($category['category']); ?></h5>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-5">
    <div class="container text-center">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2 class="display-4 mb-4">Ready to Host Your Event?</h2>
                <p class="lead mb-4">Join thousands of successful event organizers on our platform.</p>
                <?php if (!isLoggedIn()): ?>
                    <a href="auth/register.php?role=vendor" class="btn btn-custom-primary btn-lg">
                        <i class="fas fa-store me-2"></i>Become an Organizer
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4">
                <h5>About Evently</h5>
                <p>Your trusted platform for discovering and booking event tickets. Making memories, one event at a time.</p>
            </div>
            <div class="col-md-4 mb-4">
                <h5>Quick Links</h5>
                <ul>
                    <li><a href="events.php">Browse Events</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="faq.php">FAQs</a></li>
                </ul>
            </div>
            <div class="col-md-4 mb-4">
                <h5>Contact Us</h5>
                <ul>
                    <li><i class="fas fa-envelope me-2"></i>support@evently.com</li>
                    <li><i class="fas fa-phone me-2"></i>(123) 456-7890</li>
                    <li><i class="fas fa-map-marker-alt me-2"></i>123 Event Street, City</li>
                </ul>
            </div>
        </div>
        <hr class="mt-4 mb-4">
        <div class="row">
            <div class="col-md-6 text-center text-md-start">
                <p class="mb-0">&copy; 2024 Evently. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
    </div>
</footer>

<!-- AOS Animation -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 800,
        once: true
    });
</script>


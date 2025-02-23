<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// Require vendor privileges
requireVendor();

// Get vendor details
$vendor_id = $_SESSION['user_id'];
$vendor = getUserDetails($vendor_id);

// Get event ID
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get event details
$sql = "SELECT * FROM events WHERE id = ? AND vendor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $event_id, $vendor_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    redirectWith('index.php', 'Event not found or access denied.', 'error');
}

$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $date = sanitize($_POST['date']);
    $location = sanitize($_POST['location']);
    $category = sanitize($_POST['category']);
    $ticket_price = floatval($_POST['ticket_price']);
    $available_tickets = intval($_POST['available_tickets']);
    
    // Validate inputs
    if (empty($title)) {
        $errors[] = "Event title is required";
    }
    if (empty($description)) {
        $errors[] = "Event description is required";
    }
    if (empty($date)) {
        $errors[] = "Event date is required";
    } elseif (strtotime($date) < time()) {
        $errors[] = "Event date must be in the future";
    }
    if (empty($location)) {
        $errors[] = "Event location is required";
    }
    if (empty($category)) {
        $errors[] = "Event category is required";
    }
    if ($ticket_price <= 0) {
        $errors[] = "Ticket price must be greater than 0";
    }
    if ($available_tickets <= 0) {
        $errors[] = "Available tickets must be greater than 0";
    }

    // Handle image upload
    $image_url = $event['image_url']; // Keep existing image by default
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['image']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Invalid image type. Only JPG, PNG and GIF are allowed.";
        } else {
            $upload_dir = '../../uploads/events/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if ($event['image_url'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $event['image_url'])) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . $event['image_url']);
                }
                $image_url = '/evently/uploads/events/' . $file_name;
            } else {
                $upload_error = error_get_last();
                $errors[] = "Failed to upload image: " . ($upload_error['message'] ?? 'Unknown error');
            }
        }
    }

    // If no errors, update event
    if (empty($errors)) {
        $sql = "UPDATE events SET 
                title = ?, description = ?, date = ?, location = ?, 
                category = ?, ticket_price = ?, available_tickets = ?, 
                image_url = ?, updated_at = NOW()
                WHERE id = ? AND vendor_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssdisii", 
            $title, $description, $date, $location, 
            $category, $ticket_price, $available_tickets, 
            $image_url, $event_id, $vendor_id
        );
        
        if ($stmt->execute()) {
            redirectWith('index.php', 'Event updated successfully!', 'success');
        } else {
            $errors[] = "Failed to update event. Please try again.";
        }
    }
}

// Get categories for dropdown
$categories = [
    'Music', 'Wedding','Sports', 'Theater', 'Conference', 'Workshop', 
    'Exhibition', 'Festival', 'Networking', 'Food & Drink', 'Other'
];
?>

<?php require_once '../../includes/header.php'; ?>

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
                    <a href="../dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="create.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-plus-circle me-2"></i>Create Event
                    </a>
                    <a href="index.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-calendar me-2"></i>My Events
                    </a>
                    <a href="../sales.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-line me-2"></i>Sales Report
                    </a>
                    <a href="../earnings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-wallet me-2"></i>Earnings
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <div class="card fade-in">
                <div class="card-header bg-transparent border-bottom border-warning">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-edit me-2"></i>Edit Event
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger fade-in">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-4">
                                    <label for="title" class="form-label text-warning">Event Title</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($event['title']); ?>" 
                                           required>
                                </div>

                                <div class="mb-4">
                                    <label for="description" class="form-label text-warning">Description</label>
                                    <textarea class="form-control custom-input" id="description" name="description" rows="4" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label for="date" class="form-label text-warning">Event Date & Time</label>
                                            <input type="datetime-local" class="form-control custom-input" id="date" name="date" 
                                                   value="<?php echo date('Y-m-d\TH:i', strtotime($event['date'])); ?>" 
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label for="location" class="form-label text-warning">Location</label>
                                            <input type="text" class="form-control custom-input" id="location" name="location" 
                                                   value="<?php echo htmlspecialchars($event['location']); ?>" 
                                                   required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-4">
                                    <label for="category" class="form-label text-warning">Category</label>
                                    <select class="form-select custom-input" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat; ?>" <?php echo ($event['category'] === $cat) ? 'selected' : ''; ?>>
                                                <?php echo $cat; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label for="ticket_price" class="form-label text-warning">Ticket Price (₦)</label>
                                    <input type="number" class="form-control custom-input" id="ticket_price" name="ticket_price" 
                                           value="<?php echo $event['ticket_price']; ?>" 
                                           min="0" step="0.01" required>
                                </div>

                                <div class="mb-4">
                                    <label for="available_tickets" class="form-label text-warning">Available Tickets</label>
                                    <input type="number" class="form-control custom-input" id="available_tickets" name="available_tickets" 
                                           value="<?php echo $event['available_tickets']; ?>" 
                                           min="1" required>
                                </div>

                                <div class="mb-4">
                                    <label for="image" class="form-label text-warning">Event Image</label>
                                    <?php if ($event['image_url']): ?>
                                        <div class="mb-2">
                                            <img src="<?php echo $event['image_url']; ?>" class="img-fluid rounded" alt="Current Event Image">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control custom-input" id="image" name="image" accept="image/*">
                                    <div id="imagePreview" class="mt-3"></div>
                                    <small class="text-light">Max file size: 5MB. Supported formats: JPG, PNG, GIF</small>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="index.php" class="btn btn-outline-warning">
                                <i class="fas fa-arrow-left me-2"></i>Back to Events
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styles for create event page */
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

.welcome-card, .stat-card, .action-card, .event-card, .sales-card {
    background: var(--dark-card);
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.stat-card {
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
.custom-input {
    background-color: var(--dark-card);
    border: 1px solid rgba(255, 215, 0, 0.2);
    color: var(--text-color);
    transition: all 0.3s ease;
}

.custom-input:focus {
    background-color: var(--dark-card);
    border-color: var(--primary-color);
    color: var(--text-color);
    box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.25);
}

.custom-input::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.btn-warning {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--dark-bg);
    font-weight: 600;
    padding: 0.8rem 2rem;
    transition: all 0.3s ease;
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(255, 215, 0, 0.2);
}

.btn-outline-warning {
    border-color: var(--primary-color);
    color: var(--primary-color);
    font-weight: 600;
    padding: 0.8rem 2rem;
    transition: all 0.3s ease;
}

.btn-outline-warning:hover {
    background-color: var(--primary-color);
    color: var(--dark-bg);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(255, 215, 0, 0.2);
}

#imagePreview img {
    max-width: 100%;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}
</style>

<script>
// Form validation
(function () {
    'use strict'
    const forms = document.querySelectorAll('.needs-validation')
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()

// Preview image before upload
document.getElementById('image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) { // 5MB
            alert('File size must be less than 5MB');
            this.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = `<img src="${e.target.result}" class="img-fluid">`;
        }
        reader.readAsDataURL(file);
    }
});
</script>


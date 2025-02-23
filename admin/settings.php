<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require admin privileges
requireAdmin();

// Get admin information
$admin_id = $_SESSION['user_id'];
$admin = getUserDetails($admin_id);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email exists (excluding current user)
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $admin_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already exists";
    }
    
    // If changing password
    if (!empty($current_password)) {
        if (!password_verify($current_password, $admin['password'])) {
            $errors[] = "Current password is incorrect";
        }
        if (empty($new_password)) {
            $errors[] = "New password is required";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters long";
        }
        if ($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }
    
    if (empty($errors)) {
        // Update profile
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $first_name, $last_name, $email, $hashed_password, $admin_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
            $stmt->bind_param("sssi", $first_name, $last_name, $email, $admin_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Profile updated successfully.";
            $_SESSION['message_type'] = "success";
            header("Location: settings.php");
            exit();
        } else {
            $errors[] = "Failed to update profile.";
        }
    }
}

// Handle system settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $site_name = sanitize($_POST['site_name']);
    $site_email = sanitize($_POST['site_email']);
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $min_withdrawal = floatval($_POST['min_withdrawal']);
    $commission_rate = floatval($_POST['commission_rate']);
    
    // Update settings in database (to be implemented)
    $_SESSION['message'] = "System settings updated successfully.";
    $_SESSION['message_type'] = "success";
    header("Location: settings.php");
    exit();
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
                    <a href="events.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar me-2"></i>Manage Events
                    </a>
                    <a href="transactions.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-money-bill-wave me-2"></i>Transactions
                    </a>
                    <a href="settings.php" class="list-group-item list-group-item-action active">
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
                    <div class="d-flex align-items-center">
                        <div class="welcome-icon me-4">
                            <i class="fas fa-cog fa-2x text-warning"></i>
                        </div>
                        <div>
                            <h4 class="mb-1 text-warning">Settings</h4>
                            <p class="text-light mb-0">Manage your profile and system settings.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Profile Settings -->
                <div class="col-md-6 mb-4">
                    <div class="card fade-in h-100">
                        <div class="card-header bg-transparent border-warning">
                            <h5 class="text-warning mb-0">
                                <i class="fas fa-user-circle me-2"></i>Profile Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label text-warning">First Name</label>
                                    <input type="text" class="form-control" name="first_name" 
                                           value="<?php echo htmlspecialchars($admin['first_name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-warning">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" 
                                           value="<?php echo htmlspecialchars($admin['last_name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-warning">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                </div>

                                <hr class="border-light">

                                <div class="mb-3">
                                    <label class="form-label text-warning">Current Password</label>
                                    <input type="password" class="form-control" name="current_password">
                                    <small class="text-light">Leave blank if not changing password</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-warning">New Password</label>
                                    <input type="password" class="form-control" name="new_password">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-warning">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password">
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="update_profile" class="btn btn-warning">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- System Settings -->
                <div class="col-md-6 mb-4">
                    <div class="card fade-in h-100">
                        <div class="card-header bg-transparent border-warning">
                            <h5 class="text-warning mb-0">
                                <i class="fas fa-sliders-h me-2"></i>System Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label text-warning">Site Name</label>
                                    <input type="text" class="form-control" name="site_name" value="Evently" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-warning">Site Email</label>
                                    <input type="email" class="form-control" name="site_email" value="admin@evently.com" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-warning">Minimum Withdrawal (₦)</label>
                                    <input type="number" class="form-control" name="min_withdrawal" value="1000" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-warning">Commission Rate (%)</label>
                                    <input type="number" class="form-control" name="commission_rate" value="10" required>
                                </div>

                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenance_mode">
                                        <label class="form-check-label text-warning" for="maintenance_mode">
                                            Maintenance Mode
                                        </label>
                                    </div>
                                    <small class="text-light">Enable this to put the site in maintenance mode</small>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="update_settings" class="btn btn-warning">
                                        <i class="fas fa-save me-2"></i>Update Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.form-switch .form-check-input {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%28255, 255, 255, 0.25%29'/%3e%3c/svg%3e");
}

.form-switch .form-check-input:focus {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e");
}
</style>

 
<?php
require_once 'config.php';
require_once 'functions.php';

// Function to check if user is logged in
function requireLogin() {
    if (!isLoggedIn()) {
        redirectWith('/auth/login.php', 'Please login to access this page.', 'warning');
    }
}

// Function to check if user has admin role
function requireAdmin() {
    requireLogin();
    if (!hasRole('admin')) {
        redirectWith('/', 'Access denied. Admin privileges required.', 'error');
    }
}

// Function to check if user has vendor role
function requireVendor() {
    requireLogin();
    if (!hasRole('vendor')) {
        redirectWith('/', 'Access denied. Vendor privileges required.', 'error');
    }
}

// Function to check if user has customer role
function requireCustomer() {
    requireLogin();
    if (!hasRole('customer')) {
        redirectWith('/', 'Access denied. Customer privileges required.', 'error');
    }
}

// Function to check if user owns the resource
function requireOwnership($resourceUserId) {
    requireLogin();
    if (!hasRole('admin') && $_SESSION['user_id'] !== $resourceUserId) {
        redirectWith('/', 'Access denied. You do not own this resource.', 'error');
    }
}

// Function to check if user can access event management
function requireEventAccess($eventId) {
    requireLogin();
    
    if (hasRole('admin')) {
        return true;
    }
    
    if (hasRole('vendor')) {
        global $conn;
        $stmt = $conn->prepare("SELECT vendor_id FROM events WHERE id = ?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($event = $result->fetch_assoc()) {
            if ($event['vendor_id'] !== $_SESSION['user_id']) {
                redirectWith('/', 'Access denied. You do not own this event.', 'error');
            }
        } else {
            redirectWith('/', 'Event not found.', 'error');
        }
    } else {
        redirectWith('/', 'Access denied. Vendor privileges required.', 'error');
    }
} 
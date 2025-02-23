<?php
require_once 'config.php';

// Function to sanitize user input
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check user role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Function to redirect with message
function redirectWith($url, $message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: $url");
    exit();
}

// Function to display flash messages
function showMessage() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'info';
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        return "<div class='alert alert-$type'>$message</div>";
    }
    return '';
}

// Function to generate random ticket code
function generateTicketCode() {
    return strtoupper(uniqid() . bin2hex(random_bytes(4)));
}

// Function to update user balance
function updateBalance($userId, $amount, $type, $description) {
    global $conn;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update user balance
        $sql = "UPDATE users SET balance = balance " . ($type === 'credit' ? '+' : '-') . " ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();
        
        // Record transaction
        $sql = "INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("idss", $userId, $amount, $type, $description);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        return false;
    }
}

// Function to get user details
function getUserDetails($userId) {
    global $conn;
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Function to format date
function formatDate($date) {
    return date('F j, Y, g:i a', strtotime($date));
}

// Function to check if user is authenticated
function checkAuth() {
    if (!isLoggedIn()) {
        redirectWith('/evently/auth/login.php', 'Please login to continue.', 'warning');
    }
}

// Function to logout user
function logout() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    redirectWith('/evently/auth/login.php', 'You have been successfully logged out.', 'success');
}

// Function to generate QR code for ticket
function generateTicketQRCode($ticketCode, $eventId, $userId) {
    // Create QR code data
    $qrData = [
        'ticket_code' => $ticketCode,
        'event_id' => $eventId,
        'user_id' => $userId,
        'timestamp' => time()
    ];
    
    // Convert to JSON and encrypt
    $qrJson = json_encode($qrData);
    $encryptedData = base64_encode($qrJson); // In production, use proper encryption
    
    // Generate QR code path
    $qrCodePath = '/evently/uploads/qrcodes/' . $ticketCode . '.png';
    
    return [
        'qr_data' => $encryptedData,
        'qr_path' => $qrCodePath
    ];
}

// Function to verify ticket QR code
function verifyTicketQRCode($ticketCode) {
    global $conn;
    
    // Get ticket from database
    $sql = "SELECT t.*, e.title as event_title, e.date as event_date,
            u.first_name, u.last_name, u.email 
            FROM tickets t 
            JOIN events e ON t.event_id = e.id 
            JOIN users u ON t.user_id = u.id 
            WHERE t.ticket_code = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $ticketCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($ticket = $result->fetch_assoc()) {
        if ($ticket['is_used']) {
            return [
                'valid' => false, 
                'message' => 'Ticket has already been used on ' . formatDate($ticket['used_at'])
            ];
        }
        
        if (strtotime($ticket['event_date']) < time()) {
            return [
                'valid' => false, 
                'message' => 'Event has already ended'
            ];
        }
        
        // Mark ticket as used
        $sql = "UPDATE tickets SET is_used = 1, used_at = NOW() WHERE ticket_code = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $ticketCode);
        $stmt->execute();
        
        return [
            'valid' => true,
            'message' => 'Valid ticket',
            'ticket' => [
                'code' => $ticket['ticket_code'],
                'event_id' => $ticket['event_id'],
                'event' => $ticket['event_title'],
                'date' => formatDate($ticket['event_date']),
                'customer' => $ticket['first_name'] . ' ' . $ticket['last_name'],
                'email' => $ticket['email']
            ]
        ];
    }
    
    return ['valid' => false, 'message' => 'Ticket not found'];
} 
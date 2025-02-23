<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';

// Require vendor privileges
requireVendor();

// Set JSON response header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $amount = floatval($_POST['amount']);
    $bankName = sanitize($_POST['bank_name']);
    $accountNumber = sanitize($_POST['account_number']);
    
    // Validate input
    if ($amount < 1000) {
        echo json_encode([
            'success' => false,
            'message' => 'Minimum withdrawal amount is ₦1,000'
        ]);
        exit;
    }
    
    if (empty($bankName) || empty($accountNumber)) {
        echo json_encode([
            'success' => false,
            'message' => 'Bank details are required'
        ]);
        exit;
    }
    
    // Process withdrawal
    $result = processWithdrawal($_SESSION['user_id'], $amount, $bankName, $accountNumber);
    
    echo json_encode($result);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
} 
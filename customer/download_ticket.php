<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_middleware.php';
require_once '../vendor/autoload.php';

// Require customer privileges
requireCustomer();

// Get ticket code
$ticket_code = isset($_GET['code']) ? sanitize($_GET['code']) : '';

if (empty($ticket_code)) {
    redirectWith('tickets.php', 'Invalid ticket code.', 'error');
}

// Get ticket details
$sql = "SELECT t.*, e.title as event_title, e.date as event_date, e.location,
        u.first_name as organizer_first_name, u.last_name as organizer_last_name,
        c.first_name as customer_first_name, c.last_name as customer_last_name
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        JOIN users u ON e.vendor_id = u.id
        JOIN users c ON t.user_id = c.id
        WHERE t.ticket_code = ? AND t.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $ticket_code, $_SESSION['user_id']);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    redirectWith('tickets.php', 'Ticket not found.', 'error');
}

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Evently');
$pdf->SetAuthor('Evently');
$pdf->SetTitle('Event Ticket - ' . $ticket['event_title']);

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Add content
$html = '
<h1 style="color: #ffc107;">' . htmlspecialchars($ticket['event_title']) . '</h1>
<hr>
<table>
    <tr>
        <td><strong>Ticket Code:</strong></td>
        <td>' . htmlspecialchars($ticket['ticket_code']) . '</td>
    </tr>
    <tr>
        <td><strong>Date:</strong></td>
        <td>' . formatDate($ticket['event_date']) . '</td>
    </tr>
    <tr>
        <td><strong>Location:</strong></td>
        <td>' . htmlspecialchars($ticket['location']) . '</td>
    </tr>
    <tr>
        <td><strong>Attendee:</strong></td>
        <td>' . htmlspecialchars($ticket['customer_first_name'] . ' ' . $ticket['customer_last_name']) . '</td>
    </tr>
    <tr>
        <td><strong>Organizer:</strong></td>
        <td>' . htmlspecialchars($ticket['organizer_first_name'] . ' ' . $ticket['organizer_last_name']) . '</td>
    </tr>
</table>
<br><br>
';

// Add QR Code
$qrStyle = array(
    'border' => 2,
    'vpadding' => 'auto',
    'hpadding' => 'auto',
    'fgcolor' => array(0,0,0),
    'bgcolor' => array(255,255,255),
    'module_width' => 1,
    'module_height' => 1
);

$pdf->write2DBarcode($ticket['ticket_code'], 'QRCODE,H', 80, 120, 50, 50, $qrStyle, 'N');

// Output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output('ticket_' . $ticket['ticket_code'] . '.pdf', 'D'); 
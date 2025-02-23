<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// Require vendor privileges
requireVendor();

// Get vendor details
$vendor_id = $_SESSION['user_id'];
$vendor = getUserDetails($vendor_id);

// Get event ID from URL if provided
$selected_event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Handle AJAX ticket verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data']) && isset($_POST['event_id'])) {
    header('Content-Type: application/json');
    $qr_data = $_POST['qr_data'];
    $event_id = intval($_POST['event_id']);
    
    try {
        // Get ticket details
        $sql = "SELECT t.*, e.title as event_title, e.date as event_date,
                u.first_name, u.last_name, u.email
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                JOIN users u ON t.user_id = u.id
                WHERE t.ticket_code = ? AND e.id = ? AND e.vendor_id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $qr_data, $event_id, $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($ticket = $result->fetch_assoc()) {
            if ($ticket['is_used']) {
                echo json_encode([
                    'valid' => false,
                    'message' => 'Ticket has already been used on ' . formatDate($ticket['used_at'])
                ]);
                exit;
            }
            
            if (strtotime($ticket['event_date']) < time()) {
                echo json_encode([
                    'valid' => false,
                    'message' => 'Event has already ended'
                ]);
                exit;
            }
            
            // Mark ticket as used
            $sql = "UPDATE tickets SET is_used = 1, used_at = NOW() WHERE ticket_code = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $ticket['ticket_code']);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'valid' => true,
                    'message' => 'Valid ticket!',
                    'ticket' => [
                        'code' => $ticket['ticket_code'],
                        'event' => $ticket['event_title'],
                        'date' => formatDate($ticket['event_date']),
                        'customer' => $ticket['first_name'] . ' ' . $ticket['last_name'],
                        'email' => $ticket['email']
                    ]
                ]);
            } else {
                echo json_encode([
                    'valid' => false,
                    'message' => 'Error updating ticket status'
                ]);
            }
        } else {
            echo json_encode([
                'valid' => false,
                'message' => 'Invalid ticket or wrong event selected'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'valid' => false,
            'message' => 'Error verifying ticket: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Get vendor's events for dropdown
$sql = "SELECT id, title, date FROM events WHERE vendor_id = ? AND date >= CURDATE() ORDER BY date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$events = $stmt->get_result();
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
                    <a href="../events/index.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar me-2"></i>My Events
                    </a>
                    <a href="scan.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-qrcode me-2"></i>Scan Tickets
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <div class="card fade-in">
                <div class="card-header bg-transparent border-warning">
                    <h5 class="text-warning mb-0">
                        <i class="fas fa-qrcode me-2"></i>Scan Tickets
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Event Selection -->
                    <div class="mb-4">
                        <label for="event_id" class="form-label text-warning">Select Event</label>
                        <select class="form-select" id="event_id" required>
                            <option value="">Choose event...</option>
                            <?php while ($event = $events->fetch_assoc()): ?>
                                <option value="<?php echo $event['id']; ?>" 
                                        <?php echo $event['id'] == $selected_event_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($event['title']); ?> - 
                                    <?php echo formatDate($event['date']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Scanner -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="scanner-container mb-4">
                                <video id="scanner" class="w-100 rounded"></video>
                                <div class="scanner-overlay">
                                    <div class="scanner-laser"></div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mb-4">
                                <button class="btn btn-warning w-50" onclick="startScanner()">
                                    <i class="fas fa-camera me-2"></i>Start Scanner
                                </button>
                                <button class="btn btn-outline-warning w-50" onclick="stopScanner()">
                                    <i class="fas fa-stop me-2"></i>Stop Scanner
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div id="result" class="text-center p-4 d-none">
                                <div id="result-icon" class="mb-3">
                                    <i class="fas fa-spinner fa-spin fa-3x text-warning"></i>
                                </div>
                                <h4 id="result-title" class="text-warning mb-3">Scanning...</h4>
                                <div id="result-details" class="text-light"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Entry -->
                    <div class="mt-4">
                        <h5 class="text-warning mb-3">Manual Ticket Entry</h5>
                        <div class="input-group">
                            <input type="text" class="form-control" id="ticket_code" 
                                   placeholder="Enter ticket code">
                            <button class="btn btn-warning" onclick="verifyTicket()">
                                <i class="fas fa-check me-2"></i>Verify
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.scanner-container {
    position: relative;
    max-width: 500px;
    margin: 0 auto;
}

#scanner {
    border: 2px solid var(--primary-color);
    border-radius: 10px;
    height: 375px;
    object-fit: cover;
}

.scanner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border: 2px solid var(--primary-color);
    border-radius: 10px;
    overflow: hidden;
}

.scanner-laser {
    position: absolute;
    width: 100%;
    height: 2px;
    background: var(--primary-color);
    animation: scan 2s infinite;
}

@keyframes scan {
    0% { top: 0; }
    50% { top: 100%; }
    100% { top: 0; }
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
</style>

<script>
let scanner = null;
let scanning = false;

async function startScanner() {
    if (scanning) return;
    
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: "environment" } 
        });
        const video = document.getElementById('scanner');
        video.srcObject = stream;
        await video.play();

        scanning = true;
        requestAnimationFrame(scan);
    } catch (error) {
        console.error('Error accessing camera:', error);
        alert('Error accessing camera. Please check permissions.');
    }
}

function scan() {
    if (!scanning) return;

    const video = document.getElementById('scanner');
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        
        const code = jsQR(imageData.data, imageData.width, imageData.height);
        if (code) {
            processQRCode(code.data);
        }
    }
    
    if (scanning) {
        requestAnimationFrame(scan);
    }
}

function stopScanner() {
    scanning = false;
    const video = document.getElementById('scanner');
    if (video.srcObject) {
        const tracks = video.srcObject.getTracks();
        tracks.forEach(track => track.stop());
        video.srcObject = null;
    }
}

async function processQRCode(data) {
    const eventId = document.getElementById('event_id').value;
    if (!eventId) {
        alert('Please select an event first');
        return;
    }

    showResult('scanning');
    
    try {
        const formData = new FormData();
        formData.append('qr_data', data);
        formData.append('event_id', eventId);
        
        const response = await fetch('scan.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        showResult(result.valid ? 'success' : 'error', result);
    } catch (error) {
        console.error('Error verifying ticket:', error);
        showResult('error', { message: 'Failed to verify ticket' });
    }
}

function showResult(status, data = {}) {
    const resultDiv = document.getElementById('result');
    const iconDiv = document.getElementById('result-icon');
    const titleDiv = document.getElementById('result-title');
    const detailsDiv = document.getElementById('result-details');
    
    resultDiv.classList.remove('d-none');
    
    switch (status) {
        case 'scanning':
            iconDiv.innerHTML = '<i class="fas fa-spinner fa-spin fa-3x text-warning"></i>';
            titleDiv.textContent = 'Scanning...';
            titleDiv.className = 'text-warning mb-3';
            detailsDiv.innerHTML = '';
            break;
            
        case 'success':
            iconDiv.innerHTML = '<i class="fas fa-check-circle fa-3x text-success"></i>';
            titleDiv.textContent = data.message;
            titleDiv.className = 'text-success mb-3';
            detailsDiv.innerHTML = `
                <p><strong>Ticket Code:</strong> ${data.ticket.code}</p>
                <p><strong>Event:</strong> ${data.ticket.event}</p>
                <p><strong>Date:</strong> ${data.ticket.date}</p>
                <p><strong>Customer:</strong> ${data.ticket.customer}</p>
                <p><strong>Email:</strong> ${data.ticket.email}</p>
            `;
            break;
            
        case 'error':
            iconDiv.innerHTML = '<i class="fas fa-times-circle fa-3x text-danger"></i>';
            titleDiv.textContent = 'Invalid Ticket';
            titleDiv.className = 'text-danger mb-3';
            detailsDiv.innerHTML = `<p>${data.message}</p>`;
            break;
    }
}

async function verifyTicket() {
    const ticketCode = document.getElementById('ticket_code').value;
    if (!ticketCode) {
        alert('Please enter a ticket code');
        return;
    }
    
    processQRCode(ticketCode);
}
</script>

<?php require_once '../../includes/footer.php'; ?> 
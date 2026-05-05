<?php
require_once 'config.php';
require_once 'email_config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/* ==================== FIXED DB CONNECTION ==================== */
$db = Database::getInstance()->conn();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$action = $_GET['action'] ?? '';

// Log API calls
error_log("API Called: action=$action, method=" . $_SERVER['REQUEST_METHOD']);

try {

    /* ==================== SAFE JSON INPUT HELPER ==================== */
    function getJsonInput() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
            exit();
        }
        return $data;
    }

    /* ==================== ROOM FETCH ==================== */
    switch ($action) {

        case 'getRooms':
            $stmt = $db->prepare("SELECT * FROM rooms WHERE status = 'available' ORDER BY price ASC");
            $stmt->execute();
            $rooms = $stmt->fetchAll();

            foreach ($rooms as &$room) {
                $room['amenities'] = json_decode($room['amenities'], true);
            }

            echo json_encode(['success' => true, 'rooms' => $rooms]);
            break;

        case 'getAllRooms':
            $stmt = $db->query("SELECT * FROM rooms ORDER BY price ASC");
            $rooms = $stmt->fetchAll();

            foreach ($rooms as &$room) {
                $room['amenities'] = json_decode($room['amenities'], true);
            }

            echo json_encode(['success' => true, 'rooms' => $rooms]);
            break;

        case 'getRoom':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid room ID']);
                break;
            }

            $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
            $stmt->execute([$id]);
            $room = $stmt->fetch();

            if ($room) {
                $room['amenities'] = json_decode($room['amenities'], true);
                echo json_encode(['success' => true, 'room' => $room]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Room not found']);
            }
            break;

        /* ==================== FIXED AVAILABILITY CHECK ==================== */
        case 'checkAvailability':
            $room_id = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT);
            $check_in = $_GET['check_in'] ?? '';
            $check_out = $_GET['check_out'] ?? '';

            if (!$room_id || !$check_in || !$check_out) {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                break;
            }

            $stmt = $db->prepare("
                SELECT COUNT(*) FROM bookings
                WHERE room_id = ?
                AND status IN ('pending','confirmed')
                AND NOT (check_out <= ? OR check_in >= ?)
            ");
            $stmt->execute([$room_id, $check_in, $check_out]);

            $booked = $stmt->fetchColumn() > 0;

            echo json_encode(['success' => true, 'available' => !$booked]);
            break;

        /* ==================== FIXED BOOKING WITH EMAIL ==================== */
        case 'bookRoom':
            $data = getJsonInput();

            $required = ['name','email','phone','room_id','check_in','check_out'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    echo json_encode(['success' => false, 'message' => "$field is required"]);
                    exit();
                }
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email']);
                break;
            }

            $check_in = new DateTime($data['check_in']);
            $check_out = new DateTime($data['check_out']);
            $today = new DateTime();

            if ($check_in < $today) {
                echo json_encode(['success' => false, 'message' => 'Past date not allowed']);
                break;
            }

            if ($check_out <= $check_in) {
                echo json_encode(['success' => false, 'message' => 'Invalid date range']);
                break;
            }

            /* Check availability */
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM bookings
                WHERE room_id = ?
                AND status IN ('pending','confirmed')
                AND NOT (check_out <= ? OR check_in >= ?)
            ");
            $stmt->execute([$data['room_id'], $data['check_in'], $data['check_out']]);

            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Room not available']);
                break;
            }

            /* Get room */
            $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
            $stmt->execute([$data['room_id']]);
            $room = $stmt->fetch();

            if (!$room) {
                echo json_encode(['success' => false, 'message' => 'Room not found']);
                break;
            }

            $days = $check_in->diff($check_out)->days;
            $subtotal = $room['price'] * $days;
            $serviceFee = $subtotal * 0.10;
            $tax = $subtotal * 0.16;
            $total = $subtotal + $serviceFee + $tax;

            /* FIXED BOOKING REFERENCE */
            $booking_ref = 'BKG' . strtoupper(bin2hex(random_bytes(4)));

            $stmt = $db->prepare("
                INSERT INTO bookings
                (room_id, guest_name, guest_email, guest_phone, check_in, check_out,
                 subtotal, service_fee, tax, total_price, booking_reference, special_requests, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");

            $stmt->execute([
                $data['room_id'],
                $data['name'],
                $data['email'],
                $data['phone'],
                $data['check_in'],
                $data['check_out'],
                $subtotal,
                $serviceFee,
                $tax,
                $total,
                $booking_ref,
                $data['special_requests'] ?? ''
            ]);

            // ========== SEND EMAIL CONFIRMATION ==========
            $booking_data = [
                'guest_name' => $data['name'],
                'guest_email' => $data['email'],
                'guest_phone' => $data['phone'],
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'booking_reference' => $booking_ref,
                'subtotal' => $subtotal,
                'service_fee' => $serviceFee,
                'tax' => $tax,
                'total_price' => $total,
                'status' => 'pending',
                'special_requests' => $data['special_requests'] ?? ''
            ];
            
            $room_data = [
                'name' => $room['name'],
                'price' => $room['price']
            ];
            
            $emailSender = new EmailSender();
            $email_sent = $emailSender->sendBookingConfirmation($booking_data, $room_data);
            $admin_email_sent = $emailSender->sendAdminNotification($booking_data, $room_data);
            
            if ($email_sent) {
                logActivity('Email Sent', "Booking confirmation email sent to {$data['email']} for reference {$booking_ref}");
            } else {
                logActivity('Email Failed', "Failed to send booking confirmation email to {$data['email']}");
            }
            // ========== END EMAIL CONFIRMATION ==========

            echo json_encode([
                'success' => true,
                'message' => 'Booking successful! A confirmation email has been sent to your email address.',
                'reference' => $booking_ref,
                'email_sent' => $email_sent
            ]);
            break;

        /* ==================== CONTACT ==================== */
        case 'contact':
            $data = getJsonInput();

            if (empty($data['name']) || empty($data['email']) || empty($data['message'])) {
                echo json_encode(['success' => false, 'message' => 'Missing fields']);
                break;
            }

            $stmt = $db->prepare("
                INSERT INTO contacts (name, email, message, phone, subject)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['message'],
                $data['phone'] ?? '',
                $data['subject'] ?? 'General Inquiry'
            ]);

            echo json_encode(['success' => true, 'message' => 'Message sent']);
            break;

        /* ==================== ADMIN LOGIN - FIXED FOR CUSTOM USERNAME ==================== */
        case 'adminLogin':
            $data = getJsonInput();
            
            if (empty($data['username']) || empty($data['password'])) {
                echo json_encode(['success' => false, 'message' => 'Username and password required']);
                break;
            }
            
            // Get admin username and password from database
            $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'admin_username'");
            $stmt->execute();
            $stored_username = $stmt->fetchColumn();
            
            $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'admin_password'");
            $stmt->execute();
            $stored_hash = $stmt->fetchColumn();
            
            // If no admin username exists, create default
            if (!$stored_username) {
                $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('admin_username', 'admin')");
                $stmt->execute();
                $stored_username = 'admin';
            }
            
            // If no password exists, create default
            if (!$stored_hash) {
                $default_hash = password_hash('admin123', PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('admin_password', ?)");
                $stmt->execute([$default_hash]);
                $stored_hash = $default_hash;
            }
            
            // Verify credentials using database values
            if ($data['username'] === $stored_username && password_verify($data['password'], $stored_hash)) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $data['username'];
                logActivity('Admin Login', 'Admin logged in successfully');
                echo json_encode(['success' => true, 'message' => 'Login successful']);
            } else {
                logActivity('Failed Login', 'Invalid admin login attempt for username: ' . $data['username']);
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
            break;

        /* ==================== ADMIN GET STATS ==================== */
        case 'adminGetStats':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            // Total bookings
            $stmt = $db->query("SELECT COUNT(*) FROM bookings");
            $total_bookings = $stmt->fetchColumn();
            
            // Total revenue
            $stmt = $db->query("SELECT SUM(total_price) FROM bookings WHERE status = 'confirmed'");
            $total_revenue = $stmt->fetchColumn() ?: 0;
            
            // Available rooms
            $stmt = $db->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'");
            $available_rooms = $stmt->fetchColumn();
            
            // Pending bookings
            $stmt = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
            $pending_bookings = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_bookings' => $total_bookings,
                    'total_revenue' => $total_revenue,
                    'available_rooms' => $available_rooms,
                    'pending_bookings' => $pending_bookings
                ]
            ]);
            break;

        /* ==================== ADMIN GET ROOMS ==================== */
        case 'adminGetRooms':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $stmt = $db->query("SELECT * FROM rooms ORDER BY id DESC");
            $rooms = $stmt->fetchAll();
            
            foreach ($rooms as &$room) {
                $room['amenities'] = json_decode($room['amenities'], true) ?: [];
            }
            
            echo json_encode(['success' => true, 'rooms' => $rooms]);
            break;

        /* ==================== ADMIN ADD ROOM ==================== */
        case 'adminAddRoom':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $data = getJsonInput();
            
            if (empty($data['name']) || empty($data['price']) || empty($data['description'])) {
                echo json_encode(['success' => false, 'message' => 'Name, price, and description are required']);
                break;
            }
            
            $amenities = json_encode($data['amenities'] ?? []);
            
            $stmt = $db->prepare("
                INSERT INTO rooms (name, price, short_description, description, image_url, amenities, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $data['price'],
                $data['short_description'] ?? '',
                $data['description'],
                $data['image_url'] ?? '',
                $amenities,
                $data['status'] ?? 'available'
            ]);
            
            logActivity('Add Room', "Added room: {$data['name']}");
            echo json_encode(['success' => true, 'message' => 'Room added successfully']);
            break;

        /* ==================== ADMIN UPDATE ROOM ==================== */
        case 'adminUpdateRoom':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $data = getJsonInput();
            
            if (empty($data['id'])) {
                echo json_encode(['success' => false, 'message' => 'Room ID required']);
                break;
            }
            
            $amenities = json_encode($data['amenities'] ?? []);
            
            $stmt = $db->prepare("
                UPDATE rooms 
                SET name = ?, price = ?, short_description = ?, description = ?, 
                    image_url = ?, amenities = ?, status = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['price'],
                $data['short_description'] ?? '',
                $data['description'],
                $data['image_url'] ?? '',
                $amenities,
                $data['status'] ?? 'available',
                $data['id']
            ]);
            
            logActivity('Update Room', "Updated room ID: {$data['id']}");
            echo json_encode(['success' => true, 'message' => 'Room updated successfully']);
            break;

        /* ==================== ADMIN DELETE ROOM ==================== */
        case 'adminDeleteRoom':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $data = getJsonInput();
            
            if (empty($data['id'])) {
                echo json_encode(['success' => false, 'message' => 'Room ID required']);
                break;
            }
            
            $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            logActivity('Delete Room', "Deleted room ID: {$data['id']}");
            echo json_encode(['success' => true, 'message' => 'Room deleted successfully']);
            break;

        /* ==================== ADMIN GET BOOKINGS ==================== */
        case 'adminGetBookings':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $stmt = $db->query("
                SELECT b.*, r.name as room_name, r.price as room_price 
                FROM bookings b
                LEFT JOIN rooms r ON b.room_id = r.id
                ORDER BY b.created_at DESC
            ");
            $bookings = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'bookings' => $bookings]);
            break;

        /* ==================== ADMIN GET SINGLE BOOKING ==================== */
        case 'adminGetBooking':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
                break;
            }
            
            $stmt = $db->prepare("
                SELECT b.*, r.name as room_name, r.price as room_price 
                FROM bookings b
                LEFT JOIN rooms r ON b.room_id = r.id
                WHERE b.id = ?
            ");
            $stmt->execute([$id]);
            $booking = $stmt->fetch();
            
            if ($booking) {
                echo json_encode(['success' => true, 'booking' => $booking]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Booking not found']);
            }
            break;

        /* ==================== ADMIN UPDATE BOOKING STATUS WITH EMAIL ==================== */
        case 'adminUpdateBookingStatus':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $data = getJsonInput();
            
            if (empty($data['id']) || empty($data['status'])) {
                echo json_encode(['success' => false, 'message' => 'ID and status required']);
                break;
            }
            
            // Get old status and booking details before update
            $stmt = $db->prepare("SELECT b.*, r.name as room_name FROM bookings b LEFT JOIN rooms r ON b.room_id = r.id WHERE b.id = ?");
            $stmt->execute([$data['id']]);
            $booking = $stmt->fetch();
            $old_status = $booking['status'];
            
            $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$data['status'], $data['id']]);
            
            // Send email on status change (if status actually changed)
            if ($old_status !== $data['status']) {
                $emailSender = new EmailSender();
                
                $booking_data = [
                    'guest_name' => $booking['guest_name'],
                    'guest_email' => $booking['guest_email'],
                    'check_in' => $booking['check_in'],
                    'check_out' => $booking['check_out'],
                    'booking_reference' => $booking['booking_reference'],
                    'total_price' => $booking['total_price'],
                    'status' => $data['status']
                ];
                
                $email_sent = $emailSender->sendBookingStatusUpdate($booking_data, $booking['room_name'], $old_status, $data['status']);
                
                if ($email_sent) {
                    logActivity('Email Sent', "Status update email sent to {$booking['guest_email']} for booking {$booking['booking_reference']}");
                }
            }
            
            logActivity('Update Booking', "Updated booking ID: {$data['id']} from {$old_status} to {$data['status']}");
            echo json_encode(['success' => true, 'message' => 'Booking status updated']);
            break;

        /* ==================== ADMIN DELETE BOOKING ==================== */
        case 'adminDeleteBooking':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $data = getJsonInput();
            
            if (empty($data['id'])) {
                echo json_encode(['success' => false, 'message' => 'Booking ID required']);
                break;
            }
            
            // Get booking details before deleting for log
            $stmt = $db->prepare("SELECT booking_reference FROM bookings WHERE id = ?");
            $stmt->execute([$data['id']]);
            $booking = $stmt->fetch();
            
            if ($booking) {
                $stmt = $db->prepare("DELETE FROM bookings WHERE id = ?");
                $stmt->execute([$data['id']]);
                
                logActivity('Delete Booking', "Deleted booking: {$booking['booking_reference']}");
                echo json_encode(['success' => true, 'message' => 'Booking deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Booking not found']);
            }
            break;

        /* ==================== ADMIN GET MESSAGES ==================== */
        case 'adminGetMessages':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $stmt = $db->query("SELECT * FROM contacts ORDER BY created_at DESC");
            $messages = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;

        /* ==================== ADMIN MARK MESSAGE READ ==================== */
        case 'adminMarkMessageRead':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
                break;
            }
            
            $stmt = $db->prepare("UPDATE contacts SET status = 'read' WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Message marked as read']);
            break;

        /* ==================== ADMIN GET AUDIT LOGS ==================== */
        case 'adminGetAuditLogs':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $stmt = $db->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 500");
            $logs = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        /* ==================== ADMIN CHANGE PASSWORD ==================== */
        case 'adminChangePassword':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $data = getJsonInput();
            
            if (empty($data['current_password']) || empty($data['new_password'])) {
                echo json_encode(['success' => false, 'message' => 'Current and new password required']);
                break;
            }
            
            if (strlen($data['new_password']) < 8) {
                echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters']);
                break;
            }
            
            // Verify current password
            $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'admin_password'");
            $stmt->execute();
            $stored_hash = $stmt->fetchColumn();
            
            if (!password_verify($data['current_password'], $stored_hash)) {
                echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                break;
            }
            
            $new_hash = password_hash($data['new_password'], PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'admin_password'");
            $stmt->execute([$new_hash]);
            
            logActivity('Password Change', 'Admin password was changed');
            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
            break;

        /* ==================== ADMIN FORGOT PASSWORD ==================== */
        case 'adminForgotPassword':
            $data = getJsonInput();
            
            if (empty($data['email'])) {
                echo json_encode(['success' => false, 'message' => 'Email required']);
                break;
            }
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('reset_token', ?) 
                                  ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$token, $token]);
            
            // In production, send email here
            // For now, just show the reset link
            $reset_link = SITE_URL . "admin.php?reset=" . $token;
            
            logActivity('Password Reset Request', "Reset token generated for admin");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Reset link generated. In production, this would be emailed to you.',
                'reset_link' => $reset_link
            ]);
            break;

        /* ==================== DEFAULT ==================== */
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (PDOException $e) {
    error_log("DB ERROR: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("API ERROR: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>

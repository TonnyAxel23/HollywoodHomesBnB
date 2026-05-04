<?php
require_once 'config.php';

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

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? '';

// Log all API calls for debugging
error_log("API Called: action=$action, Method=" . $_SERVER['REQUEST_METHOD']);

try {
    switch($action) {
        // ============ PUBLIC ENDPOINTS ============
        
        case 'getRooms':
            $stmt = $db->prepare("SELECT * FROM rooms WHERE status = 'available' ORDER BY price ASC");
            $stmt->execute();
            $rooms = $stmt->fetchAll();
            // Parse amenities for each room
            foreach ($rooms as &$room) {
                if ($room['amenities'] && is_string($room['amenities'])) {
                    $room['amenities'] = json_decode($room['amenities'], true);
                }
            }
            echo json_encode(['success' => true, 'rooms' => $rooms]);
            break;
            
        case 'getAllRooms':
            $stmt = $db->prepare("SELECT * FROM rooms ORDER BY price ASC");
            $stmt->execute();
            $rooms = $stmt->fetchAll();
            foreach ($rooms as &$room) {
                if ($room['amenities'] && is_string($room['amenities'])) {
                    $room['amenities'] = json_decode($room['amenities'], true);
                }
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
                if ($room['amenities'] && is_string($room['amenities'])) {
                    $room['amenities'] = json_decode($room['amenities'], true);
                }
                echo json_encode(['success' => true, 'room' => $room]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Room not found']);
            }
            break;
            
        case 'getFeaturedRooms':
            $stmt = $db->prepare("SELECT * FROM rooms WHERE status = 'available' ORDER BY id DESC LIMIT 3");
            $stmt->execute();
            $rooms = $stmt->fetchAll();
            foreach ($rooms as &$room) {
                if ($room['amenities'] && is_string($room['amenities'])) {
                    $room['amenities'] = json_decode($room['amenities'], true);
                }
            }
            echo json_encode(['success' => true, 'rooms' => $rooms]);
            break;
            
        case 'checkAvailability':
            $room_id = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT);
            $check_in = $_GET['check_in'] ?? '';
            $check_out = $_GET['check_out'] ?? '';
            
            if (!$room_id || !$check_in || !$check_out) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                break;
            }
            
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE room_id = ? 
                AND status IN ('pending', 'confirmed')
                AND ((check_in <= ? AND check_out >= ?) OR (check_in <= ? AND check_out >= ?))
            ");
            $stmt->execute([$room_id, $check_out, $check_in, $check_in, $check_out]);
            $isBooked = $stmt->fetchColumn() > 0;
            
            echo json_encode(['success' => true, 'available' => !$isBooked]);
            break;
            
        case 'bookRoom':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $errors = [];
            if (empty($data['name'])) $errors[] = 'Name is required';
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
            if (empty($data['phone'])) $errors[] = 'Phone number is required';
            if (empty($data['room_id'])) $errors[] = 'Room selection is required';
            if (empty($data['check_in']) || empty($data['check_out'])) $errors[] = 'Dates are required';
            
            if (!empty($errors)) {
                echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
                break;
            }
            
            $check_in = new DateTime($data['check_in']);
            $check_out = new DateTime($data['check_out']);
            $today = new DateTime();
            
            if ($check_in < $today) {
                echo json_encode(['success' => false, 'message' => 'Check-in date cannot be in the past']);
                break;
            }
            if ($check_out <= $check_in) {
                echo json_encode(['success' => false, 'message' => 'Check-out must be after check-in']);
                break;
            }
            
            // Check availability
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE room_id = ? 
                AND status IN ('pending', 'confirmed')
                AND ((check_in <= ? AND check_out >= ?) OR (check_in <= ? AND check_out >= ?))
            ");
            $stmt->execute([$data['room_id'], $data['check_out'], $data['check_in'], $data['check_in'], $data['check_out']]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Room not available for selected dates']);
                break;
            }
            
            // Get room price
            $stmt = $db->prepare("SELECT price, name FROM rooms WHERE id = ?");
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
            $total_price = $subtotal + $serviceFee + $tax;
            $booking_ref = 'BKG' . strtoupper(substr(uniqid(), 0, 7));
            
            $stmt = $db->prepare("
                INSERT INTO bookings (room_id, guest_name, guest_email, guest_phone, check_in, check_out, subtotal, service_fee, tax, total_price, booking_reference, special_requests)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $total_price, 
                $booking_ref,
                $data['special_requests'] ?? ''
            ]);
            
            // Send confirmation email (log for now)
            error_log("Booking created: $booking_ref for {$data['name']} - Total: $$total_price");
            
            echo json_encode([
                'success' => true,
                'message' => "Booking confirmed! Your reference: $booking_ref",
                'reference' => $booking_ref,
                'booking_id' => $db->lastInsertId()
            ]);
            break;
            
        case 'getBooking':
            $reference = $_GET['reference'] ?? '';
            if (!$reference) {
                echo json_encode(['success' => false, 'message' => 'Booking reference required']);
                break;
            }
            $stmt = $db->prepare("
                SELECT b.*, r.name as room_name, r.price as room_price 
                FROM bookings b 
                JOIN rooms r ON b.room_id = r.id 
                WHERE b.booking_reference = ?
            ");
            $stmt->execute([$reference]);
            $booking = $stmt->fetch();
            if ($booking) {
                echo json_encode(['success' => true, 'booking' => $booking]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Booking not found']);
            }
            break;
            
        case 'contact':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['name']) || empty($data['email']) || empty($data['message'])) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                break;
            }
            
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email address']);
                break;
            }
            
            $stmt = $db->prepare("INSERT INTO contacts (name, email, message, phone, subject) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'], 
                $data['email'], 
                $data['message'],
                $data['phone'] ?? '',
                $data['subject'] ?? 'General Inquiry'
            ]);
            
            error_log("New contact message from: {$data['name']} ({$data['email']})");
            
            echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
            break;
            
        case 'subscribeNewsletter':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Valid email required']);
                break;
            }
            $stmt = $db->prepare("INSERT IGNORE INTO newsletters (email) VALUES (?)");
            $stmt->execute([$data['email']]);
            echo json_encode(['success' => true, 'message' => 'Subscribed successfully']);
            break;
            
        case 'getReviews':
            $stmt = $db->prepare("SELECT * FROM reviews WHERE is_approved = 1 ORDER BY created_at DESC LIMIT 6");
            $stmt->execute();
            $reviews = $stmt->fetchAll();
            echo json_encode(['success' => true, 'reviews' => $reviews]);
            break;
            
        case 'addReview':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $db->prepare("INSERT INTO reviews (booking_id, guest_name, guest_email, rating, title, comment) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['booking_id'] ?? null, 
                $data['name'], 
                $data['email'], 
                $data['rating'], 
                $data['title'] ?? '', 
                $data['comment']
            ]);
            echo json_encode(['success' => true, 'message' => 'Review submitted. Thank you!']);
            break;
            
        // ============ ADMIN ENDPOINTS ============
        
        case 'adminLogin':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['username']) || !isset($data['password'])) {
                echo json_encode(['success' => false, 'message' => 'Username and password required']);
                break;
            }
            
            $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'admin_password'");
            $stmt->execute();
            $stored_hash = $stmt->fetchColumn();
            
            if (!$stored_hash) {
                $default_hash = password_hash('Hollywood@2024!', PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('admin_password', ?)");
                $stmt->execute([$default_hash]);
                $stored_hash = $default_hash;
            }
            
            if ($data['username'] === 'admin' && password_verify($data['password'], $stored_hash)) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_login_time'] = time();
                error_log("Admin login successful: {$data['username']}");
                echo json_encode(['success' => true]);
            } else {
                error_log("Admin login failed: {$data['username']}");
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
            break;
            
        case 'adminLogout':
            session_destroy();
            echo json_encode(['success' => true]);
            break;
            
        case 'adminCheckAuth':
            echo json_encode(['success' => true, 'logged_in' => isAdminLoggedIn()]);
            break;
            
        case 'adminGetStats':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            $stats = [];
            
            // Total bookings
            $stmt = $db->query("SELECT COUNT(*) FROM bookings");
            $stats['total_bookings'] = (int)$stmt->fetchColumn();
            
            // Total revenue
            $stmt = $db->query("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE status = 'confirmed'");
            $stats['total_revenue'] = (float)$stmt->fetchColumn();
            
            // Total rooms
            $stmt = $db->query("SELECT COUNT(*) FROM rooms");
            $stats['total_rooms'] = (int)$stmt->fetchColumn();
            
            // Available rooms
            $stmt = $db->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'");
            $stats['available_rooms'] = (int)$stmt->fetchColumn();
            
            // Unavailable rooms
            $stmt = $db->query("SELECT COUNT(*) FROM rooms WHERE status = 'unavailable'");
            $stats['unavailable_rooms'] = (int)$stmt->fetchColumn();
            
            // Pending bookings
            $stmt = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
            $stats['pending_bookings'] = (int)$stmt->fetchColumn();
            
            // Confirmed bookings
            $stmt = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
            $stats['confirmed_bookings'] = (int)$stmt->fetchColumn();
            
            // Cancelled bookings
            $stmt = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'");
            $stats['cancelled_bookings'] = (int)$stmt->fetchColumn();
            
            // Total messages
            $stmt = $db->query("SELECT COUNT(*) FROM contacts");
            $stats['total_messages'] = (int)$stmt->fetchColumn();
            
            // Unread messages
            $stmt = $db->query("SELECT COUNT(*) FROM contacts WHERE status = 'unread'");
            $stats['unread_messages'] = (int)$stmt->fetchColumn();
            
            // Recent bookings
            $stmt = $db->query("
                SELECT b.*, r.name as room_name 
                FROM bookings b 
                LEFT JOIN rooms r ON b.room_id = r.id 
                ORDER BY b.id DESC 
                LIMIT 5
            ");
            $stats['recent_bookings'] = $stmt->fetchAll() ?: [];
            
            // Monthly revenue for chart
            $stmt = $db->query("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COALESCE(SUM(total_price), 0) as revenue,
                    COUNT(*) as bookings
                FROM bookings 
                WHERE status = 'confirmed'
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC
                LIMIT 6
            ");
            $stats['monthly_stats'] = $stmt->fetchAll() ?: [];
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'adminGetRooms':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $stmt = $db->prepare("SELECT * FROM rooms ORDER BY id DESC");
            $stmt->execute();
            $rooms = $stmt->fetchAll();
            foreach ($rooms as &$room) {
                if ($room['amenities'] && is_string($room['amenities'])) {
                    $room['amenities'] = json_decode($room['amenities'], true);
                }
            }
            echo json_encode(['success' => true, 'rooms' => $rooms]);
            break;
            
        case 'adminAddRoom':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $db->prepare("
                INSERT INTO rooms (name, price, short_description, description, image_url, amenities, status, capacity, bed_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'], 
                (float)$data['price'], 
                $data['short_description'] ?? '',
                $data['description'] ?? '',
                $data['image_url'] ?? '',
                json_encode($data['amenities'] ?? []),
                $data['status'] ?? 'available',
                $data['capacity'] ?? 2,
                $data['bed_type'] ?? 'Queen'
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Room added successfully', 'id' => $db->lastInsertId()]);
            break;
            
        case 'adminUpdateRoom':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $db->prepare("
                UPDATE rooms SET 
                    name = ?, 
                    price = ?, 
                    short_description = ?, 
                    description = ?, 
                    image_url = ?, 
                    amenities = ?, 
                    status = ?,
                    capacity = ?,
                    bed_type = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                (float)$data['price'],
                $data['short_description'] ?? '',
                $data['description'] ?? '',
                $data['image_url'] ?? '',
                json_encode($data['amenities'] ?? []),
                $data['status'] ?? 'available',
                $data['capacity'] ?? 2,
                $data['bed_type'] ?? 'Queen',
                (int)$data['id']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Room updated successfully']);
            break;
            
        case 'adminDeleteRoom':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Check if room has bookings
            $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE room_id = ?");
            $stmt->execute([(int)$data['id']]);
            $bookingCount = $stmt->fetchColumn();
            
            if ($bookingCount > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete room with existing bookings']);
                break;
            }
            
            $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([(int)$data['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Room deleted successfully']);
            break;
            
        case 'adminGetBookings':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $stmt = $db->prepare("
                SELECT b.*, r.name as room_name, r.price as room_price 
                FROM bookings b 
                LEFT JOIN rooms r ON b.room_id = r.id 
                ORDER BY b.id DESC
            ");
            $stmt->execute();
            $bookings = $stmt->fetchAll();
            echo json_encode(['success' => true, 'bookings' => $bookings]);
            break;
            
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
            echo json_encode(['success' => true, 'booking' => $booking]);
            break;
            
        case 'adminUpdateBookingStatus':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$data['status'], (int)$data['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            break;
            
        case 'adminDeleteBooking':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $db->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([(int)$data['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Booking deleted successfully']);
            break;
            
        case 'adminGetMessages':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $stmt = $db->prepare("SELECT * FROM contacts ORDER BY id DESC");
            $stmt->execute();
            echo json_encode(['success' => true, 'messages' => $stmt->fetchAll()]);
            break;
            
        case 'adminGetMessage':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
                break;
            }
            $stmt = $db->prepare("SELECT * FROM contacts WHERE id = ?");
            $stmt->execute([$id]);
            $message = $stmt->fetch();
            echo json_encode(['success' => true, 'message' => $message]);
            break;
            
        case 'adminMarkMessageRead':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $db->prepare("UPDATE contacts SET status = 'read' WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
            }
            break;
            
        case 'adminDeleteMessage':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $db->prepare("DELETE FROM contacts WHERE id = ?");
            $stmt->execute([(int)$data['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Message deleted successfully']);
            break;
            
        case 'adminGetNewsletterSubscribers':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $stmt = $db->prepare("SELECT * FROM newsletters ORDER BY subscribed_at DESC");
            $stmt->execute();
            echo json_encode(['success' => true, 'subscribers' => $stmt->fetchAll()]);
            break;
            
        case 'adminGetSiteSettings':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $stmt = $db->prepare("SELECT * FROM site_settings");
            $stmt->execute();
            $settings = [];
            foreach ($stmt->fetchAll() as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;
            
        case 'adminUpdateSiteSetting':
            if (!isAdminLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$data['key'], $data['value'], $data['value']]);
            
            echo json_encode(['success' => true, 'message' => 'Setting updated']);
            break;
            
            // Add these cases to your api.php

case 'adminForgotPassword':
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    
    if ($email === ADMIN_EMAIL) {
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('reset_token', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$token, $token]);
        
        $resetLink = SITE_URL . "/admin.php?reset=" . $token;
        
        // In production, send email
        error_log("Password reset link: $resetLink");
        
        echo json_encode(['success' => true, 'message' => 'Reset link sent to your email']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
    }
    break;

case 'adminChangePassword':
    if (!isAdminLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        break;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $current = $data['current_password'] ?? '';
    $new = $data['new_password'] ?? '';
    
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'admin_password'");
    $stmt->execute();
    $stored_hash = $stmt->fetchColumn();
    
    if (password_verify($current, $stored_hash)) {
        $new_hash = password_hash($new, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'admin_password'");
        $stmt->execute([$new_hash]);
        logActivity('Password Changed', 'Admin password was changed');
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    }
    break;

case 'adminGetAuditLogs':
    if (!isAdminLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        break;
    }
    $logs = [];
    $logFile = __DIR__ . '/logs/activity.log';
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $lines = array_reverse($lines);
        foreach (array_slice($lines, 0, 100) as $line) {
            $log = json_decode($line, true);
            if ($log) {
                $logs[] = $log;
            }
        }
    }
    echo json_encode(['success' => true, 'logs' => $logs]);
    break;
    
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
<?php
/**
 * Hollywood Homes BnB - Enterprise Configuration File
 * Version: 2.0
 * Last Updated: 2025
 */

// ==================== SESSION CONFIGURATION ====================
session_start();

// ==================== ERROR REPORTING ====================
// Production settings - change to E_ALL for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// ==================== TIME ZONE ====================
date_default_timezone_set('Africa/Nairobi');

// ==================== DATABASE CONFIGURATION ====================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hollywood_bnb');
define('DB_CHARSET', 'utf8mb4');

// ==================== SECURITY CONSTANTS ====================
define('CSRF_TOKEN_KEY', 'csrf_token');
define('SESSION_TIMEOUT', 7200); // 2 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('JWT_SECRET', 'hollywood-homes-bnb-secret-key-2025');
define('ENCRYPTION_KEY', 'hollywood-homes-32-character-encryption-key!');
define('BCRYPT_ROUNDS', 12);

// ==================== ADMIN CREDENTIALS ====================
define('ADMIN_USERNAME', 'admin');
define('ADMIN_EMAIL', 'admin@hollywoodhomesbnb.com');

// ==================== SITE CONFIGURATION ====================
define('SITE_NAME', 'Hollywood Homes BnB');
define('SITE_URL', 'http://localhost');
define('SITE_DOMAIN', 'hollywoodhomesbnb.com');
define('SITE_DESCRIPTION', 'Luxury Boutique B&B in Bungoma Town, Kenya');
define('SITE_KEYWORDS', 'Luxury B&B, Hollywood Homes, Bungoma hotel, premium suites');

// ==================== CONTACT INFORMATION ====================
define('CONTACT_EMAIL', 'stay@hollywoodhomesbnb.com');
define('CONTACT_PHONE', '+254712345678');
define('CONTACT_PHONE_FORMATTED', '+254 712 345 678');
define('WHATSAPP_NUMBER', '254712345679');
define('WHATSAPP_MESSAGE', 'Hello! I would like to book a room at Hollywood Homes BnB');
define('BUSINESS_HOURS', '24/7');
define('CHECKIN_TIME', '14:00');
define('CHECKOUT_TIME', '11:00');

// ==================== ADDRESS ====================
define('ADDRESS_STREET', 'Opposite Golf Hotel');
define('ADDRESS_CITY', 'Bungoma');
define('ADDRESS_COUNTRY', 'Kenya');
define('ADDRESS_POSTAL_CODE', '50200');
define('GOOGLE_MAPS_URL', 'https://goo.gl/maps/example');

// ==================== PAYMENT SETTINGS ====================
define('CURRENCY', 'KES');
define('CURRENCY_SYMBOL', 'KSh');
define('TAX_RATE', 0.16); // 16% VAT
define('SERVICE_FEE', 0.10); // 10% service fee
define('DEPOSIT_PERCENTAGE', 50); // 50% deposit required

// ==================== EMAIL CONFIGURATION ====================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM', 'noreply@hollywoodhomesbnb.com');
define('SMTP_FROM_NAME', 'Hollywood Homes BnB');
define('SMTP_SECURE', 'tls'); // tls or ssl

// ==================== SOCIAL MEDIA ====================
define('FACEBOOK_URL', 'https://facebook.com/hollywoodhomesbnb');
define('INSTAGRAM_URL', 'https://instagram.com/hollywoodhomesbnb');
define('TWITTER_URL', 'https://twitter.com/hollywoodhomes');
define('TRIPADVISOR_URL', 'https://tripadvisor.com/hollywoodhomesbnb');

// ==================== FILE UPLOAD SETTINGS ====================
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,webp');
define('UPLOAD_PATH', __DIR__ . '/uploads/');

// ==================== CACHE SETTINGS ====================
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 3600); // 1 hour
define('CACHE_PATH', __DIR__ . '/cache/');

// ==================== API SETTINGS ====================
define('API_RATE_LIMIT', 100); // requests per minute
define('API_VERSION', 'v1');

// ==================== BOOKING SETTINGS ====================
define('MAX_ADVANCE_BOOKING_DAYS', 365); // Can book up to 1 year in advance
define('MIN_ADVANCE_BOOKING_DAYS', 1); // Can book same day
define('MAX_STAY_NIGHTS', 30); // Maximum 30 nights per booking
define('CANCELLATION_DAYS', 7); // Free cancellation up to 7 days before

// Create necessary directories
$directories = ['logs', 'uploads', 'cache', 'backups', 'temp', 'uploads/rooms', 'uploads/gallery'];
foreach ($directories as $dir) {
    $fullPath = __DIR__ . '/' . $dir;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
    }
}

// Create .htaccess for uploads directory
$htaccessContent = "
<FilesMatch \"\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|htm|shtml|sh|cgi)\">
    Order Deny,Allow
    Deny from all
</FilesMatch>
Options -Indexes
";
file_put_contents(__DIR__ . '/uploads/.htaccess', $htaccessContent);

// ==================== DATABASE CLASS ====================
class Database {
    private static $instance = null;
    private $connection;
    private $queryCache = [];
    private $cacheEnabled = CACHE_ENABLED;
    private $transactionCount = 0;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => true,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
                ]
            );
            
            // Set timezone for connection
            $this->connection->exec("SET time_zone = '+03:00'");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("System under maintenance. Please try again later.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = [], $useCache = false) {
        $cacheKey = md5($sql . serialize($params));
        
        if ($useCache && $this->cacheEnabled && isset($this->queryCache[$cacheKey])) {
            return $this->queryCache[$cacheKey];
        }
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll();
        
        if ($useCache && $this->cacheEnabled) {
            $this->queryCache[$cacheKey] = $result;
        }
        
        return $result;
    }
    
    public function queryOne($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    public function queryValue($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    public function execute($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        if ($this->transactionCount == 0) {
            $this->connection->beginTransaction();
        }
        $this->transactionCount++;
        return true;
    }
    
    public function commit() {
        if ($this->transactionCount == 1) {
            $this->connection->commit();
        }
        $this->transactionCount--;
        return true;
    }
    
    public function rollback() {
        if ($this->transactionCount == 1) {
            $this->connection->rollback();
        }
        $this->transactionCount--;
        return true;
    }
    
    public function clearCache() {
        $this->queryCache = [];
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
}

// ==================== CACHE CLASS ====================
class Cache {
    private $cacheDir;
    private $defaultTTL;
    
    public function __construct($ttl = CACHE_DURATION) {
        $this->cacheDir = CACHE_PATH;
        $this->defaultTTL = $ttl;
    }
    
    public function get($key) {
        $file = $this->cacheDir . md5($key) . '.cache';
        if (file_exists($file) && (time() - filemtime($file)) < $this->defaultTTL) {
            return unserialize(file_get_contents($file));
        }
        return null;
    }
    
    public function set($key, $data, $ttl = null) {
        $file = $this->cacheDir . md5($key) . '.cache';
        $expiry = $ttl ?? $this->defaultTTL;
        file_put_contents($file, serialize($data));
        touch($file, time() + $expiry);
        return true;
    }
    
    public function delete($key) {
        $file = $this->cacheDir . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    public function remember($key, $callback, $ttl = null) {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }
        
        $data = $callback();
        $this->set($key, $data, $ttl);
        return $data;
    }
}

// ==================== HELPER FUNCTIONS ====================

function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function isSessionValid() {
    if (!isset($_SESSION['admin_login_time'])) {
        return false;
    }
    return (time() - $_SESSION['admin_login_time']) < SESSION_TIMEOUT;
}

function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_KEY]) && hash_equals($_SESSION[CSRF_TOKEN_KEY], $token);
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[\+]?[0-9]{10,15}$/', $phone);
}

function logActivity($action, $details = '', $level = 'INFO') {
    $logEntry = json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
        'action' => $action,
        'details' => $details,
        'level' => $level
    ]) . PHP_EOL;
    
    file_put_contents(__DIR__ . '/logs/activity.log', $logEntry, FILE_APPEND);
}

function checkLoginAttempts($username) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    $now = time();
    $attempts = array_filter($_SESSION['login_attempts'], function($time) use ($now) {
        return ($now - $time) < LOGIN_LOCKOUT_TIME;
    });
    
    $_SESSION['login_attempts'] = $attempts;
    return count($attempts) < MAX_LOGIN_ATTEMPTS;
}

function recordLoginAttempt($username, $success) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    if (!$success) {
        $_SESSION['login_attempts'][] = time();
        logActivity('Login Failed', "Username: $username", 'WARNING');
    } else {
        $_SESSION['login_attempts'] = [];
        logActivity('Login Success', "Username: $username", 'INFO');
    }
}

function formatPrice($price, $withSymbol = true) {
    $formatted = number_format($price, 0);
    return $withSymbol ? CURRENCY_SYMBOL . ' ' . $formatted : $formatted;
}

function calculateNights($checkin, $checkout) {
    $start = new DateTime($checkin);
    $end = new DateTime($checkout);
    return $start->diff($end)->days;
}

function calculateTotalPrice($pricePerNight, $nights) {
    $subtotal = $pricePerNight * $nights;
    $serviceFee = $subtotal * SERVICE_FEE;
    $tax = $subtotal * TAX_RATE;
    return [
        'subtotal' => $subtotal,
        'service_fee' => $serviceFee,
        'tax' => $tax,
        'total' => $subtotal + $serviceFee + $tax
    ];
}

function sendEmail($to, $subject, $body, $isHtml = true) {
    $headers = [];
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8";
    $headers[] = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">";
    $headers[] = "Reply-To: " . CONTACT_EMAIL;
    $headers[] = "X-Mailer: PHP/" . phpversion();
    
    error_log("Email would be sent to: $to - Subject: $subject");
    
    // For production, use SMTP
    if (SMTP_HOST !== 'smtp.gmail.com') {
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    return true;
}

function generateBookingReference() {
    return 'BKG' . strtoupper(uniqid()) . rand(100, 999);
}

function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return $ip;
}

function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function redirect($url, $statusCode = 302) {
    header("Location: $url", true, $statusCode);
    exit();
}

function jsonResponse($success, $message = '', $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// ==================== DATABASE INSTALLATION/UPDATE ====================
function installDatabase() {
    $db = Database::getInstance()->getConnection();
    
    // Main tables creation
    $sql = "
    CREATE TABLE IF NOT EXISTS rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        short_description TEXT,
        description TEXT,
        image_url VARCHAR(500),
        gallery JSON,
        amenities TEXT,
        capacity INT DEFAULT 2,
        bed_type VARCHAR(50) DEFAULT 'Queen',
        size_sqm INT,
        status ENUM('available', 'unavailable', 'maintenance') DEFAULT 'available',
        featured BOOLEAN DEFAULT FALSE,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_featured (featured),
        INDEX idx_price (price)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        guest_name VARCHAR(100) NOT NULL,
        guest_email VARCHAR(100) NOT NULL,
        guest_phone VARCHAR(20) NOT NULL,
        special_requests TEXT,
        check_in DATE NOT NULL,
        check_out DATE NOT NULL,
        nights INT,
        subtotal DECIMAL(10,2),
        service_fee DECIMAL(10,2),
        tax DECIMAL(10,2),
        total_price DECIMAL(10,2),
        status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
        booking_reference VARCHAR(20) UNIQUE,
        payment_method VARCHAR(50),
        payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        INDEX idx_dates (check_in, check_out),
        INDEX idx_status (status),
        INDEX idx_reference (booking_reference),
        INDEX idx_email (guest_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        subject VARCHAR(200),
        message TEXT NOT NULL,
        status ENUM('unread', 'read', 'replied', 'archived') DEFAULT 'unread',
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS newsletters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) UNIQUE NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        unsubscribed_at TIMESTAMP NULL,
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS site_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('text', 'json', 'boolean', 'number') DEFAULT 'text',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_key (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT,
        guest_name VARCHAR(100) NOT NULL,
        guest_email VARCHAR(100) NOT NULL,
        rating INT CHECK (rating >= 1 AND rating <= 5),
        title VARCHAR(200),
        comment TEXT,
        is_approved BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
        INDEX idx_approved (is_approved),
        INDEX idx_rating (rating)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    try {
        $db->exec($sql);
        
        // Insert default settings
        $defaultSettings = [
            ['site_name', SITE_NAME, 'text'],
            ['site_description', SITE_DESCRIPTION, 'text'],
            ['contact_email', CONTACT_EMAIL, 'text'],
            ['contact_phone', CONTACT_PHONE, 'text'],
            ['business_hours', BUSINESS_HOURS, 'text'],
            ['checkin_time', CHECKIN_TIME, 'text'],
            ['checkout_time', CHECKOUT_TIME, 'text'],
            ['tax_rate', TAX_RATE, 'number'],
            ['service_fee', SERVICE_FEE, 'number'],
            ['currency', CURRENCY, 'text'],
            ['maintenance_mode', 'false', 'boolean']
        ];
        
        $stmt = $db->prepare("INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?)");
        foreach ($defaultSettings as $setting) {
            $stmt->execute($setting);
        }
        
        // Set admin password if not exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM site_settings WHERE setting_key = 'admin_password'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('admin_password', ?)");
            $stmt->execute([password_hash('Hollywood@2024!', PASSWORD_BCRYPT)]);
        }
        
        // Insert sample rooms if none exist
        $stmt = $db->query("SELECT COUNT(*) FROM rooms");
        if ($stmt->fetchColumn() == 0) {
            $sampleRooms = [
                ['Hollywood Executive Suite', 15000, 'Luxury executive suite with premium amenities', 'https://images.pexels.com/photos/271618/pexels-photo-271618.jpeg', '["WiFi","Netflix","Mini Bar","Safe"]', 'available', 2, 'King'],
                ['Silver Screen Double', 10000, 'Comfortable double room with city view', 'https://images.pexels.com/photos/271624/pexels-photo-271624.jpeg', '["WiFi","Netflix","Coffee Maker"]', 'available', 2, 'Queen'],
                ['Starlet Single', 7000, 'Cozy single room for solo travelers', 'https://images.pexels.com/photos/271619/pexels-photo-271619.jpeg', '["WiFi","Netflix","Basic Amenities"]', 'available', 1, 'Single']
            ];
            
            $stmt = $db->prepare("INSERT INTO rooms (name, price, description, image_url, amenities, status, capacity, bed_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($sampleRooms as $room) {
                $stmt->execute($room);
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Database installation failed: " . $e->getMessage());
        return false;
    }
}

// Run database installation
installDatabase();

// Initialize cache
$cache = new Cache();

// Log script start for debugging
error_log("Config loaded successfully - " . date('Y-m-d H:i:s'));
?>
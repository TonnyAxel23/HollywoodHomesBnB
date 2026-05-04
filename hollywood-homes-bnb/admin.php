<?php
require_once 'config.php';

// Redirect if not logged in
if (!isAdminLoggedIn() && !isset($_GET['login']) && !isset($_GET['forgot']) && !isset($_GET['reset'])) {
    // Show login form
} elseif (!isAdminLoggedIn() && !isset($_GET['login']) && !isset($_GET['forgot']) && !isset($_GET['reset'])) {
    header('Location: admin.php');
    exit();
}

// Handle password reset request
if (isset($_GET['reset']) && isset($_POST['reset_password'])) {
    $token = $_GET['reset'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password === $confirm_password && strlen($new_password) >= 8) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'reset_token'");
        $stmt->execute();
        $stored_token = $stmt->fetchColumn();
        
        if ($stored_token === $token) {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'admin_password'");
            $stmt->execute([$hashed]);
            $stmt = $db->prepare("DELETE FROM site_settings WHERE setting_key = 'reset_token'");
            $stmt->execute();
            
            logActivity('Password Reset', 'Admin password was reset successfully');
            $reset_success = true;
        } else {
            $reset_error = 'Invalid or expired reset token';
        }
    } else {
        $reset_error = 'Passwords do not match or are too short (min 8 characters)';
    }
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hollywood Homes BnB</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --gold: #D4AF37;
            --gold-dark: #b8942e;
            --gold-light: #f5e6a3;
            --dark: #0a0a0a;
            --darker: #000000;
            --gray: #1a1a1a;
            --gray-light: #2a2a2a;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --primary: #D4AF37;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark);
            color: #e0e0e0;
            overflow-x: hidden;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: var(--darker);
            border-right: 1px solid rgba(212, 175, 55, 0.2);
            overflow-y: auto;
            z-index: 100;
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 30px 24px;
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
        }

        .sidebar-header h2 {
            color: var(--gold);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-nav {
            padding: 20px 16px;
        }

        .nav-item {
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #a0a0a0;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(212, 175, 55, 0.1);
            color: var(--gold);
        }

        .nav-link i {
            width: 24px;
            font-size: 1.2rem;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            background: linear-gradient(135deg, #fff, var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .admin-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(212, 175, 55, 0.1);
            padding: 8px 16px;
            border-radius: 50px;
            cursor: pointer;
            position: relative;
        }

        .admin-badge:hover {
            background: rgba(212, 175, 55, 0.2);
        }

        .admin-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--gray);
            border-radius: 10px;
            border: 1px solid var(--gold);
            min-width: 200px;
            display: none;
            z-index: 1000;
            margin-top: 10px;
        }

        .admin-badge:hover .admin-dropdown {
            display: block;
        }

        .dropdown-item {
            padding: 10px 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dropdown-item:hover {
            background: rgba(212, 175, 55, 0.1);
            color: var(--gold);
        }

        .logout-btn {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--darker);
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--gray) 0%, var(--darker) 100%);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid rgba(212, 175, 55, 0.2);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--gold), transparent);
            transform: scaleX(0);
            transition: transform 0.3s;
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--gold);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-header h3 {
            color: var(--gold);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-header i {
            font-size: 2rem;
            color: var(--gold);
            opacity: 0.5;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .card {
            background: linear-gradient(135deg, var(--gray) 0%, var(--darker) 100%);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid rgba(212, 175, 55, 0.2);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--gold);
        }

        /* ========== IMPROVED BOOKINGS STYLES ========== */
        .bookings-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
        }

        .bookings-search {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .bookings-search i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gold);
        }

        .bookings-search input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            background: var(--darker);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 12px;
            color: white;
            font-size: 0.9rem;
        }

        .bookings-search input:focus {
            outline: none;
            border-color: var(--gold);
        }

        .bookings-filter-group {
            display: flex;
            gap: 10px;
        }

        .bookings-filter-group select {
            padding: 12px 20px;
            background: var(--darker);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 12px;
            color: white;
            cursor: pointer;
        }

        .bookings-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .booking-stat-badge {
            background: rgba(212, 175, 55, 0.1);
            border-radius: 12px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid rgba(212, 175, 55, 0.2);
            cursor: pointer;
            transition: all 0.3s;
        }

        .booking-stat-badge:hover {
            background: rgba(212, 175, 55, 0.2);
            transform: translateY(-2px);
        }

        .booking-stat-badge.active {
            background: rgba(212, 175, 55, 0.3);
            border-color: var(--gold);
        }

        .booking-stat-badge i {
            font-size: 1.5rem;
            color: var(--gold);
        }

        .booking-stat-badge span {
            font-size: 0.85rem;
            color: #aaa;
        }

        .booking-stat-badge strong {
            font-size: 1.3rem;
            color: var(--gold);
            margin-left: 10px;
        }

        .bookings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
        }

        .booking-card {
            background: rgba(26, 26, 26, 0.8);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s;
            border: 1px solid rgba(212, 175, 55, 0.2);
        }

        .booking-card:hover {
            transform: translateY(-5px);
            border-color: var(--gold);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .booking-card-header {
            padding: 16px;
            background: rgba(0,0,0,0.3);
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .booking-ref {
            font-family: monospace;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gold);
        }

        .booking-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .booking-status.pending { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .booking-status.confirmed { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .booking-status.cancelled { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .booking-status.completed { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }

        .booking-card-body {
            padding: 16px;
        }

        .booking-guest {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .guest-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #000;
        }

        .guest-info h4 {
            color: white;
            margin-bottom: 4px;
        }

        .guest-info p {
            font-size: 0.75rem;
            color: #aaa;
        }

        .booking-details {
            margin-bottom: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.85rem;
        }

        .detail-row .label {
            color: #888;
        }

        .detail-row .value {
            color: var(--gold);
            font-weight: 500;
        }

        .booking-amount {
            background: rgba(212, 175, 55, 0.1);
            padding: 12px;
            border-radius: 12px;
            margin: 15px 0;
            text-align: center;
        }

        .booking-amount .amount {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gold);
        }

        .booking-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .booking-actions select {
            flex: 1;
            padding: 8px;
            background: var(--darker);
            border: 1px solid rgba(212,175,55,0.3);
            border-radius: 8px;
            color: white;
        }

        /* Room Grid */
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }

        .room-item {
            background: rgba(26, 26, 26, 0.8);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s;
            border: 1px solid rgba(212, 175, 55, 0.2);
        }

        .room-item:hover {
            transform: translateY(-5px);
            border-color: var(--gold);
        }

        .room-image {
            height: 200px;
            overflow: hidden;
            position: relative;
        }

        .room-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .room-item:hover .room-image img {
            transform: scale(1.1);
        }

        .room-status {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .room-details {
            padding: 16px;
        }

        .room-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--gold);
        }

        .room-price {
            font-size: 1rem;
            color: #888;
            margin-bottom: 8px;
        }

        .room-price strong {
            color: var(--gold);
            font-size: 1.3rem;
        }

        .room-amenities {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 12px 0;
        }

        .amenity-tag {
            background: rgba(212, 175, 55, 0.1);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            color: var(--gold);
        }

        .room-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        th {
            color: var(--gold);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background: rgba(212, 175, 55, 0.05);
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .badge-info { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--darker);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.75rem;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--gray);
            border-radius: 20px;
            max-width: 700px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            padding: 30px;
            border: 1px solid var(--gold);
        }

        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--gold);
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            background: var(--darker);
            border: 1px solid #333;
            color: white;
            border-radius: 10px;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 40px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gold);
        }

        /* Login Form */
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--darker) 0%, var(--dark) 100%);
        }

        .login-box {
            background: var(--gray);
            padding: 40px;
            border-radius: 24px;
            border: 1px solid var(--gold);
            width: 100%;
            max-width: 420px;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo h2 {
            color: var(--gold);
            font-size: 1.8rem;
        }

        .forgot-link {
            text-align: right;
            margin-top: 10px;
        }

        .forgot-link a {
            color: var(--gold);
            text-decoration: none;
            font-size: 0.85rem;
        }

        /* Back to Home Button */
        .back-home-link {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid rgba(212, 175, 55, 0.2);
        }

        .back-home-link a {
            color: #888;
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .back-home-link a:hover {
            color: var(--gold);
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--gray);
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            border-left: 4px solid var(--gold);
            z-index: 1100;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 50px;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(212, 175, 55, 0.3);
            border-top-color: var(--gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #888;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--gold);
        }

        /* ========== AUDIT LOG STYLES (KEPT AS ORIGINAL) ========== */
        .audit-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
        }

        .audit-search {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .audit-search i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gold);
        }

        .audit-search input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            background: var(--darker);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 12px;
            color: white;
            font-size: 0.9rem;
        }

        .audit-search input:focus {
            outline: none;
            border-color: var(--gold);
        }

        .audit-filter-group {
            display: flex;
            gap: 10px;
        }

        .audit-filter-group select {
            padding: 12px 20px;
            background: var(--darker);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 12px;
            color: white;
            cursor: pointer;
        }

        .audit-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .audit-stat-badge {
            background: rgba(212, 175, 55, 0.1);
            border-radius: 12px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid rgba(212, 175, 55, 0.2);
        }

        .audit-stat-badge i {
            font-size: 1.5rem;
            color: var(--gold);
        }

        .audit-stat-badge span {
            font-size: 0.85rem;
            color: #aaa;
        }

        .audit-stat-badge strong {
            font-size: 1.3rem;
            color: var(--gold);
            margin-left: 10px;
        }

        .timeline-view {
            display: none;
        }

        .timeline-view.active {
            display: block;
        }

        .timeline-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: rgba(26, 26, 26, 0.6);
            border-radius: 16px;
            margin-bottom: 15px;
            border-left: 3px solid var(--gold);
            transition: all 0.3s;
        }

        .timeline-item:hover {
            background: rgba(26, 26, 26, 0.9);
            transform: translateX(5px);
        }

        .timeline-icon {
            width: 50px;
            height: 50px;
            background: rgba(212, 175, 55, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .timeline-icon.info { color: var(--info); }
        .timeline-icon.warning { color: var(--warning); }
        .timeline-icon.danger { color: var(--danger); }
        .timeline-icon.success { color: var(--success); }

        .timeline-content {
            flex: 1;
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .timeline-action {
            font-weight: 700;
            color: var(--gold);
        }

        .timeline-date {
            font-size: 0.75rem;
            color: #888;
        }

        .timeline-details {
            color: #bbb;
            font-size: 0.85rem;
            margin: 5px 0;
        }

        .timeline-meta {
            display: flex;
            gap: 15px;
            margin-top: 8px;
            font-size: 0.7rem;
            color: #666;
        }

        .table-view {
            display: block;
        }

        .table-view.hide {
            display: none;
        }

        .view-toggle {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }

        .view-btn {
            background: var(--darker);
            border: 1px solid rgba(212, 175, 55, 0.3);
            padding: 8px 16px;
            border-radius: 10px;
            color: #aaa;
            cursor: pointer;
            transition: all 0.3s;
        }

        .view-btn.active {
            background: var(--gold);
            color: #000;
            border-color: var(--gold);
        }

        .view-btn:hover {
            border-color: var(--gold);
            color: var(--gold);
        }

        .log-level {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .log-level-info { background: var(--info); }
        .log-level-warning { background: var(--warning); }
        .log-level-danger { background: var(--danger); }
        .log-level-success { background: var(--success); }

        /* Mobile */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 101;
            background: var(--gold);
            color: #000;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .mobile-toggle { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding-top: 70px; }
            .rooms-grid { grid-template-columns: 1fr; }
            .bookings-filters { flex-direction: column; }
            .bookings-filter-group { width: 100%; }
            .bookings-filter-group select { flex: 1; }
            .bookings-grid { grid-template-columns: 1fr; }
            .audit-filters { flex-direction: column; }
            .audit-filter-group { width: 100%; }
            .audit-filter-group select { flex: 1; }
            .timeline-header { flex-direction: column; align-items: flex-start; gap: 5px; }
        }
    </style>
</head>
<body>

<div class="mobile-toggle" id="mobileToggle">
    <i class="fas fa-bars"></i>
</div>

<?php if (!isAdminLoggedIn() && !isset($_GET['login']) && !isset($_GET['forgot']) && !isset($_GET['reset'])): ?>
<div class="login-container">
    <div class="login-box">
        <div class="login-logo" style="text-align: center; margin-bottom: 30px;">
            <div class="logo" onclick="window.location.href='index.html'" style="cursor: pointer;">
                <img src="images/logo.png" alt="Hollywood Homes BnB" style="height: 120px; width: auto; display: block; margin: 0 auto;">
            </div>
            <p style="color: #888; margin-top: 15px;">Admin Login</p>
        </div>
        <form id="loginForm">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" required autocomplete="off" placeholder="Enter username">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" required placeholder="Enter password">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('password')"></i>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            <div class="forgot-link">
                <a href="?forgot=1">Forgot Password?</a>
            </div>
            <div id="loginMsg" style="margin-top: 15px; text-align: center;"></div>
            <!-- Back to Home Button -->
            <div class="back-home-link">
                <a href="index.html"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </form>
    </div>
</div>

<?php elseif (isset($_GET['forgot'])): ?>
<div class="login-container">
    <div class="login-box">
        <div class="login-logo" style="text-align: center; margin-bottom: 30px;">
            <div class="logo" onclick="window.location.href='index.html'" style="cursor: pointer;">
                <img src="images/logo.png" alt="Hollywood Homes BnB" style="height: 120px; width: auto; display: block; margin: 0 auto;">
            </div>
            <h2 style="color: var(--gold); margin-top: 15px;">Reset Password</h2>
            <p style="color: #888; margin-top: 10px;">Enter your email to receive reset instructions</p>
        </div>
        <form id="forgotForm">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" id="resetEmail" required placeholder="admin@hollywoodhomesbnb.com">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Send Reset Link</button>
            <div class="forgot-link" style="text-align: center; margin-top: 15px;">
                <a href="admin.php">Back to Login</a>
            </div>
            <div id="forgotMsg" style="margin-top: 15px; text-align: center;"></div>
            <!-- Back to Home Button -->
            <div class="back-home-link">
                <a href="index.html"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </form>
    </div>
</div>

<?php elseif (isset($_GET['reset'])): ?>
<div class="login-container">
    <div class="login-box">
        <div class="login-logo" style="text-align: center; margin-bottom: 30px;">
            <div class="logo" onclick="window.location.href='index.html'" style="cursor: pointer;">
                <img src="images/logo.png" alt="Hollywood Homes BnB" style="height: 120px; width: auto; display: block; margin: 0 auto;">
            </div>
            <h2 style="color: var(--gold); margin-top: 15px;">Create New Password</h2>
            <p style="color: #888; margin-top: 10px;">Enter your new password below</p>
        </div>
        <?php if (isset($reset_success)): ?>
            <div style="background: rgba(16, 185, 129, 0.2); color: #10b981; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                Password reset successful! <a href="admin.php" style="color: var(--gold);">Click here to login</a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> New Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="newPassword" required placeholder="Min 8 characters">
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('newPassword')"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirmPassword" required placeholder="Confirm your password">
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('confirmPassword')"></i>
                    </div>
                </div>
                <button type="submit" name="reset_password" class="btn btn-primary" style="width: 100%;">Reset Password</button>
                <?php if (isset($reset_error)): ?>
                    <div style="color: #ef4444; margin-top: 15px; text-align: center;"><?php echo $reset_error; ?></div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
        <div class="forgot-link" style="text-align: center; margin-top: 15px;">
            <a href="admin.php">Back to Login</a>
        </div>
        <!-- Back to Home Button -->
        <div class="back-home-link">
            <a href="index.html"><i class="fas fa-home"></i> Back to Home</a>
        </div>
    </div>
</div>

<?php else: ?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo" onclick="window.location.href='index.html'" style="cursor: pointer;">
            <img src="images/logo.png" alt="Hollywood Homes BnB" style="height: 100px; width: auto; display: block; margin: 0 auto;">
        </div>
    </div>
    <div class="sidebar-nav">
        <div class="nav-item">
            <div class="nav-link active" data-section="dashboard">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </div>
        </div>
        <div class="nav-item">
            <div class="nav-link" data-section="rooms">
                <i class="fas fa-hotel"></i>
                <span>Manage Rooms</span>
            </div>
        </div>
        <div class="nav-item">
            <div class="nav-link" data-section="bookings">
                <i class="fas fa-calendar-check"></i>
                <span>Bookings</span>
            </div>
        </div>
        <div class="nav-item">
            <div class="nav-link" data-section="messages">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
            </div>
        </div>
        <div class="nav-item">
            <div class="nav-link" data-section="audit">
                <i class="fas fa-history"></i>
                <span>Audit Logs</span>
            </div>
        </div>
        <div class="nav-item">
            <div class="nav-link" data-section="settings">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </div>
        </div>
    </div>
</div>

<div class="main-content">
    <div class="top-bar">
        <h1 class="page-title" id="pageTitle">Dashboard</h1>
        <div class="admin-info">
            <div class="admin-badge">
                <i class="fas fa-user-shield"></i>
                <span>Admin</span>
                <div class="admin-dropdown">
                    <div class="dropdown-item" onclick="showChangePasswordModal()">
                        <i class="fas fa-key"></i> Change Password
                    </div>
                    <div class="dropdown-item" onclick="loadSection('audit')">
                        <i class="fas fa-history"></i> View Audit Logs
                    </div>
                </div>
            </div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    <div id="contentArea">
        <div class="loading">
            <div class="spinner"></div>
            <p>Loading dashboard...</p>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal" id="changePasswordModal">
    <div class="modal-content">
        <h2 style="color: var(--gold); margin-bottom: 20px;">Change Password</h2>
        <form id="changePasswordForm">
            <div class="form-group">
                <label>Current Password</label>
                <div class="password-wrapper">
                    <input type="password" id="currentPassword" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('currentPassword')"></i>
                </div>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <div class="password-wrapper">
                    <input type="password" id="newAdminPassword" required minlength="8">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('newAdminPassword')"></i>
                </div>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <div class="password-wrapper">
                    <input type="password" id="confirmAdminPassword" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('confirmAdminPassword')"></i>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Update Password</button>
                <button type="button" class="btn btn-danger" onclick="closeChangePasswordModal()">Cancel</button>
            </div>
            <div id="passwordChangeMsg" style="margin-top: 15px;"></div>
        </form>
    </div>
</div>

<!-- View Booking Modal -->
<div class="modal" id="viewBookingModal">
    <div class="modal-content" id="viewBookingContent">
        <h2 style="color: var(--gold); margin-bottom: 20px;">Booking Details</h2>
        <div id="bookingDetailsContent"></div>
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button class="btn btn-primary" onclick="closeViewBookingModal()">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = field.nextElementSibling;
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function showChangePasswordModal() {
    document.getElementById('changePasswordModal').style.display = 'flex';
}

function closeChangePasswordModal() {
    document.getElementById('changePasswordModal').style.display = 'none';
    document.getElementById('changePasswordForm').reset();
}

function closeViewBookingModal() {
    document.getElementById('viewBookingModal').style.display = 'none';
}

function formatKES(amount) {
    return 'KSh ' + parseInt(amount).toLocaleString();
}

<?php if (isAdminLoggedIn()): ?>
let currentSection = 'dashboard';
let allBookings = [];
let allLogs = [];

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.borderLeftColor = type === 'success' ? '#10b981' : '#ef4444';
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function loadSection(section) {
    currentSection = section;
    const titles = {
        'dashboard': 'Dashboard',
        'rooms': 'Manage Rooms',
        'bookings': 'Bookings',
        'messages': 'Messages',
        'audit': 'Audit Logs',
        'settings': 'Settings'
    };
    document.getElementById('pageTitle').innerHTML = titles[section] || 'Dashboard';
    
    document.getElementById('contentArea').innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading...</p></div>';
    
    if (section === 'dashboard') loadDashboard();
    else if (section === 'rooms') loadRooms();
    else if (section === 'bookings') loadBookings();
    else if (section === 'messages') loadMessages();
    else if (section === 'audit') loadAuditLogs();
    else if (section === 'settings') loadSettings();
    
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
        if (link.dataset.section === section) link.classList.add('active');
    });
}

async function loadDashboard() {
    try {
        const response = await fetch('api.php?action=adminGetStats');
        const data = await response.json();
        
        if (data.success && data.stats) {
            const stats = data.stats;
            const html = `
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-header"><h3>Total Bookings</h3><i class="fas fa-calendar-check"></i></div><div class="stat-value">${stats.total_bookings || 0}</div></div>
                    <div class="stat-card"><div class="stat-header"><h3>Total Revenue</h3><i class="fas fa-dollar-sign"></i></div><div class="stat-value">${formatKES(stats.total_revenue || 0)}</div></div>
                    <div class="stat-card"><div class="stat-header"><h3>Available Rooms</h3><i class="fas fa-bed"></i></div><div class="stat-value">${stats.available_rooms || 0}</div></div>
                    <div class="stat-card"><div class="stat-header"><h3>Pending Bookings</h3><i class="fas fa-clock"></i></div><div class="stat-value">${stats.pending_bookings || 0}</div></div>
                </div>
                <div class="card"><div class="card-header"><h3 class="card-title">Quick Actions</h3></div>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <button class="btn btn-primary" onclick="loadSection('rooms')"><i class="fas fa-plus"></i> Add New Room</button>
                        <button class="btn btn-primary" onclick="loadSection('bookings')"><i class="fas fa-list"></i> View All Bookings</button>
                        <button class="btn btn-primary" onclick="loadSection('audit')"><i class="fas fa-history"></i> View Audit Logs</button>
                    </div>
                </div>
            `;
            document.getElementById('contentArea').innerHTML = html;
        }
    } catch (error) {
        console.error(error);
    }
}

// ========== ROOM MANAGEMENT ==========
async function loadRooms() {
    try {
        const response = await fetch('api.php?action=adminGetRooms');
        const data = await response.json();
        
        if (data.success && data.rooms) {
            if (data.rooms.length === 0) {
                document.getElementById('contentArea').innerHTML = `
                    <div class="card"><button class="btn btn-primary" onclick="showRoomModal()"><i class="fas fa-plus"></i> Add New Room</button></div>
                    <div class="card"><div class="empty-state"><i class="fas fa-bed"></i><p>No rooms yet. Click the button above to add your first room.</p></div></div>
                `;
                return;
            }
            
            let html = `<div style="margin-bottom: 20px;"><button class="btn btn-primary" onclick="showRoomModal()"><i class="fas fa-plus"></i> Add New Room</button></div>
                        <div class="rooms-grid">`;
            
            data.rooms.forEach(room => {
                const amenities = room.amenities ? (typeof room.amenities === 'string' ? JSON.parse(room.amenities) : room.amenities) : [];
                html += `
                    <div class="room-item">
                        <div class="room-image">
                            <img src="${room.image_url || 'https://images.pexels.com/photos/1648771/pexels-photo-1648771.jpeg'}" alt="${escapeHtml(room.name)}" onerror="this.src='https://images.pexels.com/photos/1648771/pexels-photo-1648771.jpeg'">
                            <span class="room-status badge badge-${room.status}">${room.status}</span>
                        </div>
                        <div class="room-details">
                            <div class="room-name">${escapeHtml(room.name)}</div>
                            <div class="room-price"><strong>${formatKES(room.price)}</strong> / night</div>
                            <div class="room-amenities">
                                ${amenities.slice(0, 4).map(a => `<span class="amenity-tag"><i class="fas fa-check"></i> ${escapeHtml(a)}</span>`).join('')}
                            </div>
                            <div class="room-actions">
                                <button class="btn btn-primary btn-sm" onclick="editRoom(${room.id})"><i class="fas fa-edit"></i> Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteRoom(${room.id})"><i class="fas fa-trash"></i> Delete</button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `</div>`;
            document.getElementById('contentArea').innerHTML = html;
        }
    } catch (error) {
        console.error(error);
    }
}

// ========== IMPROVED BOOKINGS MANAGEMENT ==========
async function loadBookings() {
    try {
        const response = await fetch('api.php?action=adminGetBookings');
        const data = await response.json();
        
        if (data.success && data.bookings) {
            allBookings = data.bookings;
            renderBookingsUI(allBookings);
        } else {
            document.getElementById('contentArea').innerHTML = `<div class="card"><div class="empty-state"><i class="fas fa-calendar-check"></i><p>No bookings yet</p></div></div>`;
        }
    } catch (error) {
        console.error(error);
        document.getElementById('contentArea').innerHTML = `<div class="card"><div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading bookings</p></div></div>`;
    }
}

function renderBookingsUI(bookings) {
    const totalBookings = bookings.length;
    const pendingCount = bookings.filter(b => b.status === 'pending').length;
    const confirmedCount = bookings.filter(b => b.status === 'confirmed').length;
    const cancelledCount = bookings.filter(b => b.status === 'cancelled').length;
    const totalRevenue = bookings.filter(b => b.status === 'confirmed').reduce((sum, b) => sum + parseFloat(b.total_price || 0), 0);
    
    let html = `
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-calendar-check"></i> All Bookings</h3>
            </div>
            
            <div class="bookings-stats">
                <div class="booking-stat-badge" onclick="filterBookingsByStatus('all')" id="filterAll">
                    <i class="fas fa-database"></i> Total <strong>${totalBookings}</strong>
                </div>
                <div class="booking-stat-badge" onclick="filterBookingsByStatus('pending')" id="filterPending">
                    <i class="fas fa-clock"></i> Pending <strong>${pendingCount}</strong>
                </div>
                <div class="booking-stat-badge" onclick="filterBookingsByStatus('confirmed')" id="filterConfirmed">
                    <i class="fas fa-check-circle"></i> Confirmed <strong>${confirmedCount}</strong>
                </div>
                <div class="booking-stat-badge" onclick="filterBookingsByStatus('cancelled')" id="filterCancelled">
                    <i class="fas fa-times-circle"></i> Cancelled <strong>${cancelledCount}</strong>
                </div>
                <div class="booking-stat-badge">
                    <i class="fas fa-dollar-sign"></i> Revenue <strong>${formatKES(totalRevenue)}</strong>
                </div>
            </div>
            
            <div class="bookings-filters">
                <div class="bookings-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="bookingSearchInput" placeholder="Search by name, email, or reference..." onkeyup="filterBookings()">
                </div>
                <div class="bookings-filter-group">
                    <select id="sortBookings" onchange="filterBookings()">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="amount_high">Highest Amount</option>
                        <option value="amount_low">Lowest Amount</option>
                    </select>
                </div>
            </div>
            
            <div id="bookingsGrid" class="bookings-grid"></div>
        </div>
    `;
    
    document.getElementById('contentArea').innerHTML = html;
    window.currentBookings = bookings;
    window.currentStatusFilter = 'all';
    filterBookings();
}

function filterBookingsByStatus(status) {
    window.currentStatusFilter = status;
    document.querySelectorAll('.booking-stat-badge').forEach(badge => badge.classList.remove('active'));
    if (status === 'all') document.getElementById('filterAll')?.classList.add('active');
    else if (status === 'pending') document.getElementById('filterPending')?.classList.add('active');
    else if (status === 'confirmed') document.getElementById('filterConfirmed')?.classList.add('active');
    else if (status === 'cancelled') document.getElementById('filterCancelled')?.classList.add('active');
    filterBookings();
}

function filterBookings() {
    const searchTerm = document.getElementById('bookingSearchInput')?.value.toLowerCase() || '';
    const sortBy = document.getElementById('sortBookings')?.value || 'newest';
    const statusFilter = window.currentStatusFilter || 'all';
    
    let filtered = [...(window.currentBookings || [])];
    if (statusFilter !== 'all') filtered = filtered.filter(b => b.status === statusFilter);
    if (searchTerm) {
        filtered = filtered.filter(b => 
            (b.booking_reference || '').toLowerCase().includes(searchTerm) ||
            (b.guest_name || '').toLowerCase().includes(searchTerm) ||
            (b.guest_email || '').toLowerCase().includes(searchTerm)
        );
    }
    
    filtered.sort((a, b) => {
        if (sortBy === 'newest') return new Date(b.created_at) - new Date(a.created_at);
        if (sortBy === 'oldest') return new Date(a.created_at) - new Date(b.created_at);
        if (sortBy === 'amount_high') return parseFloat(b.total_price || 0) - parseFloat(a.total_price || 0);
        if (sortBy === 'amount_low') return parseFloat(a.total_price || 0) - parseFloat(b.total_price || 0);
        return 0;
    });
    
    const grid = document.getElementById('bookingsGrid');
    if (!grid) return;
    if (filtered.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #888;">No bookings found</div>';
        return;
    }
    
    grid.innerHTML = filtered.map(booking => `
        <div class="booking-card">
            <div class="booking-card-header">
                <span class="booking-ref"><i class="fas fa-hashtag"></i> ${escapeHtml(booking.booking_reference)}</span>
                <span class="booking-status ${booking.status}">${booking.status.toUpperCase()}</span>
            </div>
            <div class="booking-card-body">
                <div class="booking-guest">
                    <div class="guest-avatar"><i class="fas fa-user"></i></div>
                    <div class="guest-info">
                        <h4>${escapeHtml(booking.guest_name)}</h4>
                        <p><i class="fas fa-envelope"></i> ${escapeHtml(booking.guest_email)}</p>
                        <p><i class="fas fa-phone"></i> ${escapeHtml(booking.guest_phone)}</p>
                    </div>
                </div>
                <div class="booking-details">
                    <div class="detail-row"><span class="label"><i class="fas fa-hotel"></i> Room:</span><span class="value">${escapeHtml(booking.room_name || 'N/A')}</span></div>
                    <div class="detail-row"><span class="label"><i class="fas fa-calendar-alt"></i> Dates:</span><span class="value">${booking.check_in} → ${booking.check_out}</span></div>
                    <div class="detail-row"><span class="label"><i class="fas fa-moon"></i> Nights:</span><span class="value">${booking.nights || calculateNights(booking.check_in, booking.check_out)} nights</span></div>
                </div>
                <div class="booking-amount"><div class="amount">${formatKES(booking.total_price || 0)}</div><div style="font-size: 0.7rem;">Total Amount</div></div>
                <div class="booking-actions">
                    <select onchange="updateBookingStatus(${booking.id}, this.value)">
                        <option value="pending" ${booking.status === 'pending' ? 'selected' : ''}>Pending</option>
                        <option value="confirmed" ${booking.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                        <option value="cancelled" ${booking.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                    </select>
                    <button class="btn btn-primary btn-sm" onclick="viewBookingDetails(${booking.id})"><i class="fas fa-eye"></i> View</button>
                </div>
            </div>
        </div>
    `).join('');
}

function calculateNights(checkin, checkout) {
    const start = new Date(checkin), end = new Date(checkout);
    return Math.ceil(Math.abs(end - start) / (1000 * 60 * 60 * 24));
}

async function updateBookingStatus(id, status) {
    try {
        const response = await fetch('api.php?action=adminUpdateBookingStatus', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, status })
        });
        const result = await response.json();
        if (result.success) { showToast(`Booking status updated to ${status}`, 'success'); loadBookings(); }
        else showToast(result.message || 'Failed to update status', 'error');
    } catch (error) { showToast('Error updating status', 'error'); }
}

async function viewBookingDetails(id) {
    try {
        const response = await fetch(`api.php?action=adminGetBooking&id=${id}`);
        const data = await response.json();
        if (data.success && data.booking) {
            const b = data.booking;
            const nights = b.nights || calculateNights(b.check_in, b.check_out);
            const subtotal = parseFloat(b.subtotal) || (parseFloat(b.room_price) * nights);
            const serviceFee = parseFloat(b.service_fee) || (subtotal * 0.10);
            const tax = parseFloat(b.tax) || (subtotal * 0.16);
            const total = parseFloat(b.total_price) || (subtotal + serviceFee + tax);
            
            const detailsHtml = `
                <h3 style="color: var(--gold); margin-bottom: 15px;">Ref: ${escapeHtml(b.booking_reference)}</h3>
                <h4 style="color: var(--gold); margin: 15px 0 10px;"><i class="fas fa-user"></i> Guest Information</h4>
                <div class="detail-row"><span class="label">Name:</span><span class="value">${escapeHtml(b.guest_name)}</span></div>
                <div class="detail-row"><span class="label">Email:</span><span class="value">${escapeHtml(b.guest_email)}</span></div>
                <div class="detail-row"><span class="label">Phone:</span><span class="value">${escapeHtml(b.guest_phone)}</span></div>
                <h4 style="color: var(--gold); margin: 15px 0 10px;"><i class="fas fa-hotel"></i> Room Details</h4>
                <div class="detail-row"><span class="label">Room:</span><span class="value">${escapeHtml(b.room_name)}</span></div>
                <div class="detail-row"><span class="label">Price per night:</span><span class="value">${formatKES(b.room_price)}</span></div>
                <h4 style="color: var(--gold); margin: 15px 0 10px;"><i class="fas fa-calendar"></i> Stay Details</h4>
                <div class="detail-row"><span class="label">Check-in:</span><span class="value">${b.check_in}</span></div>
                <div class="detail-row"><span class="label">Check-out:</span><span class="value">${b.check_out}</span></div>
                <div class="detail-row"><span class="label">Nights:</span><span class="value">${nights}</span></div>
                <h4 style="color: var(--gold); margin: 15px 0 10px;"><i class="fas fa-receipt"></i> Payment Breakdown</h4>
                <div class="detail-row"><span class="label">Subtotal:</span><span class="value">${formatKES(subtotal)}</span></div>
                <div class="detail-row"><span class="label">Service Fee (10%):</span><span class="value">${formatKES(serviceFee)}</span></div>
                <div class="detail-row"><span class="label">Tax (16% VAT):</span><span class="value">${formatKES(tax)}</span></div>
                <div class="detail-row" style="border-top: 1px solid var(--gold); margin-top: 10px; padding-top: 10px;"><span class="label"><strong>Total:</strong></span><span class="value"><strong>${formatKES(total)}</strong></span></div>
                ${b.special_requests ? `<h4 style="color: var(--gold); margin: 15px 0 10px;"><i class="fas fa-pen"></i> Special Requests</h4><p style="background: rgba(0,0,0,0.3); padding: 10px; border-radius: 10px;">${escapeHtml(b.special_requests)}</p>` : ''}
                <label style="color: var(--gold);">Update Status:</label>
                <select id="modalStatusSelect" style="width: 100%; padding: 10px; margin-top: 10px; background: var(--darker); border: 1px solid rgba(212,175,55,0.3); border-radius: 8px;">
                    <option value="pending" ${b.status === 'pending' ? 'selected' : ''}>Pending</option>
                    <option value="confirmed" ${b.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                    <option value="cancelled" ${b.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                </select>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button class="btn btn-primary" onclick="updateStatusFromModal(${b.id})">Update Status</button>
                    <button class="btn btn-danger" onclick="closeViewBookingModal()">Close</button>
                </div>
            `;
            document.getElementById('bookingDetailsContent').innerHTML = detailsHtml;
            document.getElementById('viewBookingModal').style.display = 'flex';
        }
    } catch (error) { showToast('Error loading booking details', 'error'); }
}

async function updateStatusFromModal(id) {
    await updateBookingStatus(id, document.getElementById('modalStatusSelect').value);
    closeViewBookingModal();
}

// ========== MESSAGES ==========
async function loadMessages() {
    try {
        const response = await fetch('api.php?action=adminGetMessages');
        const data = await response.json();
        if (data.success && data.messages) {
            if (data.messages.length === 0) {
                document.getElementById('contentArea').innerHTML = `<div class="card"><div class="empty-state"><i class="fas fa-envelope"></i><p>No messages yet</p></div></div>`;
                return;
            }
            let html = '';
            data.messages.forEach(msg => {
                html += `<div class="card"><div style="display: flex; justify-content: space-between; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                            <div><strong>${escapeHtml(msg.name)}</strong><br><small>${escapeHtml(msg.email)}</small></div>
                            <div><span class="badge badge-${msg.status}">${msg.status}</span><br><small>${new Date(msg.created_at).toLocaleString()}</small></div>
                        </div>
                        <p style="line-height: 1.6;">${escapeHtml(msg.message)}</p>
                        ${msg.status === 'unread' ? `<div style="margin-top: 15px;"><button class="btn btn-primary btn-sm" onclick="markRead(${msg.id})"><i class="fas fa-check"></i> Mark as Read</button></div>` : ''}
                    </div>`;
            });
            document.getElementById('contentArea').innerHTML = html;
        }
    } catch (error) { console.error(error); }
}

window.markRead = async (id) => {
    try {
        const response = await fetch(`api.php?action=adminMarkMessageRead&id=${id}`);
        const result = await response.json();
        if (result.success) { showToast('Message marked as read', 'success'); loadMessages(); }
    } catch (error) { showToast('Failed to mark as read', 'error'); }
};

// ========== AUDIT LOGS (KEPT AS ORIGINAL - NOT CHANGED) ==========
async function loadAuditLogs() {
    try {
        const response = await fetch('api.php?action=adminGetAuditLogs');
        const data = await response.json();
        if (data.success && data.logs) {
            allLogs = data.logs;
            renderAuditLogs(allLogs);
        } else {
            document.getElementById('contentArea').innerHTML = `<div class="card"><div class="empty-state"><i class="fas fa-history"></i><p>No audit logs yet</p></div></div>`;
        }
    } catch (error) {
        console.error(error);
        document.getElementById('contentArea').innerHTML = `<div class="card"><div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading audit logs</p></div></div>`;
    }
}

function renderAuditLogs(logs) {
    const totalLogs = logs.length;
    const uniqueIPs = [...new Set(logs.map(l => l.ip).filter(ip => ip && ip !== 'CLI'))].length;
    const warningCount = logs.filter(l => (l.level || '').toLowerCase() === 'warning').length;
    const dangerCount = logs.filter(l => (l.level || '').toLowerCase() === 'danger').length;
    
    let html = `
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"></i> System Activity Logs</h3>
                <div class="view-toggle">
                    <button class="view-btn active" onclick="toggleAuditView('table')"><i class="fas fa-table"></i> Table</button>
                    <button class="view-btn" onclick="toggleAuditView('timeline')"><i class="fas fa-stream"></i> Timeline</button>
                </div>
            </div>
            <div class="audit-stats">
                <div class="audit-stat-badge"><i class="fas fa-database"></i> Total Events <strong>${totalLogs}</strong></div>
                <div class="audit-stat-badge"><i class="fas fa-network-wired"></i> Unique IPs <strong>${uniqueIPs}</strong></div>
                <div class="audit-stat-badge"><i class="fas fa-exclamation-triangle"></i> Warnings <strong>${warningCount}</strong></div>
                <div class="audit-stat-badge"><i class="fas fa-skull-crosswalk"></i> Critical <strong>${dangerCount}</strong></div>
            </div>
            <div class="audit-filters">
                <div class="audit-search"><i class="fas fa-search"></i><input type="text" id="auditSearchInput" placeholder="Search by action, details, or IP..." onkeyup="filterAuditLogs()"></div>
                <div class="audit-filter-group">
                    <select id="levelFilter" onchange="filterAuditLogs()"><option value="all">All Levels</option><option value="info">Info</option><option value="warning">Warning</option><option value="danger">Danger</option><option value="success">Success</option></select>
                    <select id="dateFilter" onchange="filterAuditLogs()"><option value="all">All Time</option><option value="today">Today</option><option value="week">Last 7 Days</option><option value="month">Last 30 Days</option></select>
                </div>
            </div>
            <div id="tableView" class="table-view"><div class="table-container"><table><thead><tr><th>Timestamp</th><th>Level</th><th>Action</th><th>Details</th><th>IP Address</th></tr></thead><tbody id="auditTableBody"></tbody></table></div></div>
            <div id="timelineView" class="timeline-view"><div id="timelineBody"></div></div>
        </div>
    `;
    document.getElementById('contentArea').innerHTML = html;
    window.currentLogs = logs;
    filterAuditLogs();
}

function filterAuditLogs() {
    const searchTerm = document.getElementById('auditSearchInput')?.value.toLowerCase() || '';
    const levelFilter = document.getElementById('levelFilter')?.value || 'all';
    const dateFilter = document.getElementById('dateFilter')?.value || 'all';
    let filtered = [...(window.currentLogs || [])];
    if (searchTerm) filtered = filtered.filter(log => (log.action || '').toLowerCase().includes(searchTerm) || (log.details || '').toLowerCase().includes(searchTerm) || (log.ip || '').toLowerCase().includes(searchTerm));
    if (levelFilter !== 'all') filtered = filtered.filter(log => (log.level || 'info').toLowerCase() === levelFilter.toLowerCase());
    if (dateFilter !== 'all') {
        const now = new Date(), today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
        const monthAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
        filtered = filtered.filter(log => { const d = new Date(log.timestamp); if (dateFilter === 'today') return d >= today; if (dateFilter === 'week') return d >= weekAgo; if (dateFilter === 'month') return d >= monthAgo; return true; });
    }
    const tableBody = document.getElementById('auditTableBody');
    if (tableBody) {
        if (filtered.length === 0) tableBody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px;">No logs found</td></tr>';
        else tableBody.innerHTML = filtered.map(log => `<tr><td><small>${new Date(log.timestamp).toLocaleString()}</small></td><td><span class="badge badge-${(log.level || 'info').toLowerCase()}">${log.level || 'INFO'}</span></td><td><strong>${escapeHtml(log.action)}</strong></td><td><small>${escapeHtml(log.details)}</small></td><td><code>${escapeHtml(log.ip)}</code></td></tr>`).join('');
    }
    const timelineBody = document.getElementById('timelineBody');
    if (timelineBody) {
        if (filtered.length === 0) timelineBody.innerHTML = '<div style="text-align: center; padding: 40px;">No logs found</div>';
        else timelineBody.innerHTML = filtered.map(log => { const logLevel = (log.level || 'info').toLowerCase(); return `<div class="timeline-item"><div class="timeline-icon ${logLevel}">${logLevel === 'warning' ? '<i class="fas fa-exclamation-triangle"></i>' : logLevel === 'danger' ? '<i class="fas fa-skull-crosswalk"></i>' : logLevel === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-info-circle"></i>'}</div><div class="timeline-content"><div class="timeline-header"><span class="timeline-action">${escapeHtml(log.action)}</span><span class="timeline-date">${new Date(log.timestamp).toLocaleString()}</span></div><div class="timeline-details">${escapeHtml(log.details)}</div><div class="timeline-meta"><span><i class="fas fa-network-wired"></i> ${escapeHtml(log.ip)}</span><span><i class="fas fa-tag"></i> ${escapeHtml(log.level || 'INFO')}</span></div></div></div>`; }).join('');
    }
}

function toggleAuditView(view) {
    const tableView = document.getElementById('tableView'), timelineView = document.getElementById('timelineView');
    const tableBtn = document.querySelector('.view-toggle .view-btn:first-child'), timelineBtn = document.querySelector('.view-toggle .view-btn:last-child');
    if (view === 'table') { tableView.style.display = 'block'; timelineView.style.display = 'none'; tableBtn?.classList.add('active'); timelineBtn?.classList.remove('active'); }
    else { tableView.style.display = 'none'; timelineView.style.display = 'block'; tableBtn?.classList.remove('active'); timelineBtn?.classList.add('active'); }
}

window.filterAuditLogs = filterAuditLogs;
window.toggleAuditView = toggleAuditView;

// ========== SETTINGS ==========
async function loadSettings() {
    const html = `
        <div class="card"><div class="card-header"><h3 class="card-title">Account Settings</h3></div><button class="btn btn-primary" onclick="showChangePasswordModal()"><i class="fas fa-key"></i> Change Password</button></div>
        <div class="card"><div class="card-header"><h3 class="card-title">Site Settings</h3></div><div class="form-group"><label>Site Name</label><input type="text" id="siteName" value="Hollywood Homes BnB"></div><div class="form-group"><label>Contact Email</label><input type="email" id="contactEmail" value="stay@hollywoodhomesbnb.com"></div><div class="form-group"><label>Contact Phone</label><input type="text" id="contactPhone" value="+254712345678"></div><button class="btn btn-primary" onclick="saveSettings()"><i class="fas fa-save"></i> Save Settings</button></div>
    `;
    document.getElementById('contentArea').innerHTML = html;
}

function showRoomModal(room = null) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    modal.innerHTML = `<div class="modal-content"><h2 style="color: var(--gold); margin-bottom: 20px;">${room ? 'Edit Room' : 'Add New Room'}</h2>
        <form id="roomForm"><div class="form-group"><label>Room Name</label><input type="text" id="roomName" value="${room ? escapeHtml(room.name) : ''}" required></div>
        <div class="form-group"><label>Price per Night (KES)</label><input type="number" id="roomPrice" step="1" value="${room ? room.price : ''}" required></div>
        <div class="form-group"><label>Short Description</label><input type="text" id="roomShortDesc" value="${room ? escapeHtml(room.short_description || '') : ''}"></div>
        <div class="form-group"><label>Full Description</label><textarea id="roomDesc" rows="4" required>${room ? escapeHtml(room.description || '') : ''}</textarea></div>
        <div class="form-group"><label>Image URL</label><input type="text" id="roomImage" value="${room ? escapeHtml(room.image_url || '') : ''}" placeholder="https://images.pexels.com/photos/..."></div>
        <div class="form-group"><label>Amenities (comma separated)</label><input type="text" id="roomAmenities" value="${room && room.amenities ? (typeof room.amenities === 'string' ? JSON.parse(room.amenities).join(',') : room.amenities.join(',')) : ''}" placeholder="WiFi, Netflix, Parking"></div>
        <div class="form-group"><label>Status</label><select id="roomStatus"><option value="available" ${room && room.status=='available' ? 'selected' : ''}>Available</option><option value="unavailable" ${room && room.status=='unavailable' ? 'selected' : ''}>Unavailable</option></select></div>
        <div style="display: flex; gap: 10px;"><button type="submit" class="btn btn-primary">${room ? 'Update' : 'Create'} Room</button><button type="button" class="btn btn-danger" onclick="this.closest('.modal').remove()">Cancel</button></div></form></div>`;
    document.body.appendChild(modal);
    document.getElementById('roomForm').onsubmit = async (e) => {
        e.preventDefault();
        const submitBtn = e.target.querySelector('button[type="submit"]'), originalText = submitBtn.textContent;
        submitBtn.textContent = 'Saving...'; submitBtn.disabled = true;
        const roomData = { name: document.getElementById('roomName').value, price: parseFloat(document.getElementById('roomPrice').value), short_description: document.getElementById('roomShortDesc').value, description: document.getElementById('roomDesc').value, image_url: document.getElementById('roomImage').value, amenities: document.getElementById('roomAmenities').value.split(',').map(a => a.trim()).filter(a => a), status: document.getElementById('roomStatus').value };
        if (room) roomData.id = room.id;
        const action = room ? 'adminUpdateRoom' : 'adminAddRoom';
        try { const response = await fetch(`api.php?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(roomData) }); const result = await response.json(); showToast(result.message, result.success ? 'success' : 'error'); if (result.success) { modal.remove(); loadRooms(); } } 
        catch (error) { showToast('Failed to save room', 'error'); } 
        finally { submitBtn.textContent = originalText; submitBtn.disabled = false; }
    };
}

document.getElementById('changePasswordForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const currentPassword = document.getElementById('currentPassword').value, newPassword = document.getElementById('newAdminPassword').value, confirmPassword = document.getElementById('confirmAdminPassword').value;
    if (newPassword !== confirmPassword) { document.getElementById('passwordChangeMsg').innerHTML = '<div style="color: #ef4444;">New passwords do not match</div>'; return; }
    if (newPassword.length < 8) { document.getElementById('passwordChangeMsg').innerHTML = '<div style="color: #ef4444;">Password must be at least 8 characters</div>'; return; }
    try { const response = await fetch('api.php?action=adminChangePassword', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ current_password: currentPassword, new_password: newPassword }) }); const result = await response.json(); if (result.success) { showToast('Password changed successfully', 'success'); closeChangePasswordModal(); } else { document.getElementById('passwordChangeMsg').innerHTML = `<div style="color: #ef4444;">${result.message}</div>`; } } 
    catch (error) { document.getElementById('passwordChangeMsg').innerHTML = '<div style="color: #ef4444;">Error changing password</div>'; }
});

window.editRoom = async (id) => { try { const response = await fetch(`api.php?action=getRoom&id=${id}`); const data = await response.json(); if (data.success) showRoomModal(data.room); } catch (error) { showToast('Error loading room details', 'error'); } };
window.deleteRoom = async (id) => { if (confirm('Are you sure you want to delete this room? This cannot be undone.')) { try { const response = await fetch('api.php?action=adminDeleteRoom', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) }); const result = await response.json(); showToast(result.message, 'success'); loadRooms(); } catch (error) { showToast('Failed to delete room', 'error'); } } };
window.filterBookings = filterBookings;
window.filterBookingsByStatus = filterBookingsByStatus;
window.updateBookingStatus = updateBookingStatus;
window.viewBookingDetails = viewBookingDetails;
window.updateStatusFromModal = updateStatusFromModal;

function saveSettings() { showToast('Settings saved successfully', 'success'); }
function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m])); }

document.getElementById('mobileToggle')?.addEventListener('click', () => { document.getElementById('sidebar').classList.toggle('active'); });
document.querySelectorAll('.nav-link').forEach(link => { link.addEventListener('click', (e) => { e.preventDefault(); loadSection(link.dataset.section); document.getElementById('sidebar')?.classList.remove('active'); }); });
loadSection('dashboard');

<?php else: ?>
document.getElementById('forgotForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('resetEmail').value, btn = e.target.querySelector('button'), originalText = btn.textContent;
    btn.textContent = 'Sending...'; btn.disabled = true;
    try { const response = await fetch('api.php?action=adminForgotPassword', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email }) }); const data = await response.json(); document.getElementById('forgotMsg').innerHTML = `<div style="color: ${data.success ? '#10b981' : '#ef4444'};">${data.message}</div>`; if (data.success) document.getElementById('forgotForm').reset(); } 
    catch (error) { document.getElementById('forgotMsg').innerHTML = '<div style="color: #ef4444;">Error sending reset link</div>'; } 
    finally { btn.textContent = originalText; btn.disabled = false; }
});
document.getElementById('loginForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button'), originalText = btn.textContent;
    btn.textContent = 'Logging in...'; btn.disabled = true;
    try { const response = await fetch('api.php?action=adminLogin', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ username: document.getElementById('username').value, password: document.getElementById('password').value }) }); const data = await response.json(); if (data.success) location.reload(); else document.getElementById('loginMsg').innerHTML = `<div style="color: #ef4444;">${data.message}</div>`; } 
    catch (error) { document.getElementById('loginMsg').innerHTML = '<div style="color: #ef4444;">Login failed. Please try again.</div>'; } 
    finally { btn.textContent = originalText; btn.disabled = false; }
});
window.togglePassword = togglePassword;
<?php endif; ?>
</script>
</body>
</html>
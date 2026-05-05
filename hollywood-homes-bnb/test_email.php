<?php
require_once 'config.php';
require_once 'email_config.php';

echo "<h2>Testing Email Configuration</h2>";

// Check if PHPMailer files exist
$phpmailer_files = [
    __DIR__ . '/PHPMailer/Exception.php',
    __DIR__ . '/PHPMailer/PHPMailer.php',
    __DIR__ . '/PHPMailer/SMTP.php'
];

echo "<h3>Checking PHPMailer Files:</h3>";
foreach ($phpmailer_files as $file) {
    if (file_exists($file)) {
        echo "<span style='color: green;'>✅ " . basename($file) . " found</span><br>";
    } else {
        echo "<span style='color: red;'>❌ " . basename($file) . " NOT found at: " . $file . "</span><br>";
    }
}

echo "<h3>Sending Test Email:</h3>";

$emailSender = new EmailSender();

// Test booking data
$test_booking = [
    'guest_name' => 'Test Customer',
    'guest_email' => 'tonnyodhiambo49@gmail.com', // Send to yourself
    'guest_phone' => '+254792069328',
    'check_in' => date('Y-m-d'),
    'check_out' => date('Y-m-d', strtotime('+3 days')),
    'booking_reference' => 'TEST-' . rand(1000, 9999),
    'subtotal' => 45000,
    'service_fee' => 4500,
    'tax' => 7200,
    'total_price' => 56700,
    'status' => 'pending',
    'special_requests' => 'Test special request'
];

$test_room = [
    'name' => 'Hollywood Suite (Test)',
    'price' => 15000
];

$result = $emailSender->sendBookingConfirmation($test_booking, $test_room);

if ($result) {
    echo "<p style='color: green; font-weight: bold;'>✅ EMAIL SENT SUCCESSFULLY!</p>";
    echo "<p>Check your inbox at <strong>tonnyodhiambo49@gmail.com</strong> (and spam folder).</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ FAILED TO SEND EMAIL</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>Your Gmail App Password is correct in config.php</li>";
    echo "<li>2-Step Verification is enabled on your Gmail account</li>";
    echo "<li>The PHPMailer files are correctly placed in /PHPMailer/ folder</li>";
    echo "</ul>";
}

echo "<h3>Configuration Summary:</h3>";
echo "<ul>";
echo "<li>SMTP Host: " . SMTP_HOST . "</li>";
echo "<li>SMTP Port: " . SMTP_PORT . "</li>";
echo "<li>SMTP User: " . SMTP_USER . "</li>";
echo "<li>From Email: " . SMTP_FROM_EMAIL . "</li>";
echo "<li>From Name: " . SMTP_FROM_NAME . "</li>";
echo "<li>Site URL: " . SITE_URL . "</li>";
echo "</ul>";
?>
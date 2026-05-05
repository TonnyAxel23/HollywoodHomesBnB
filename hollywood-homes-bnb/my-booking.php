<?php
require_once 'config.php';

$booking_ref = $_GET['ref'] ?? '';
$error = '';
$booking = null;
$room = null;

if (!empty($booking_ref)) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Fetch booking details
        $stmt = $db->prepare("
            SELECT b.*, r.name as room_name, r.price as room_price, r.image_url as room_image 
            FROM bookings b
            LEFT JOIN rooms r ON b.room_id = r.id
            WHERE b.booking_reference = ?
        ");
        $stmt->execute([$booking_ref]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            $error = "Booking not found. Please check your reference number.";
        }
    } catch (Exception $e) {
        $error = "An error occurred. Please try again later.";
        error_log("Booking lookup error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Booking - Hollywood Homes BnB</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --gold: #D4AF37; --gold-dark: #B8942E; --dark: #0a0a0a; --darker: #000000; --gray: #1a1a1a; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; }
        body { font-family: 'Montserrat', sans-serif; background: var(--dark); color: white; }
        h1, h2, h3, h4 { font-family: 'Playfair Display', serif; }
        
        .navbar { position: fixed; top: 0; width: 100%; padding: 1rem 0; background: rgba(0,0,0,0.95); backdrop-filter: blur(20px); z-index: 1000; border-bottom: 1px solid rgba(212,175,55,0.2); }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 2rem; }
        .nav-container { display: flex; justify-content: space-between; align-items: center; }
        .logo-img { height: 60px; width: auto; cursor: pointer; transition: all 0.3s ease; }
        .logo-img:hover { transform: scale(1.02); filter: drop-shadow(0 0 5px rgba(212, 175, 55, 0.5)); }
        .nav-links { display: flex; gap: 2rem; list-style: none; align-items: center; }
        .nav-links a { color: white; text-decoration: none; transition: all 0.3s; cursor: pointer; font-weight: 500; }
        .nav-links a:hover { color: var(--gold); }
        .book-now-btn { background: linear-gradient(135deg, var(--gold), var(--gold-dark)); color: #000 !important; padding: 0.5rem 1.5rem !important; border-radius: 50px; }
        .mobile-menu { display: none; font-size: 1.5rem; cursor: pointer; color: var(--gold); }
        
        .page-header { padding: 8rem 0 3rem; text-align: center; background: linear-gradient(135deg, rgba(0,0,0,0.9), rgba(0,0,0,0.7)), url('https://images.pexels.com/photos/2587054/pexels-photo-2587054.jpeg'); background-size: cover; }
        .page-header h1 { font-size: 2.5rem; } .page-header h1 span { color: var(--gold); }
        
        .booking-container { padding: 3rem 0 5rem; }
        .booking-card { background: linear-gradient(135deg, var(--gray), var(--darker)); border-radius: 20px; border: 1px solid rgba(212,175,55,0.2); overflow: hidden; margin-bottom: 2rem; }
        .booking-header { background: #000; padding: 20px 30px; border-bottom: 1px solid var(--gold); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .booking-ref { font-family: monospace; font-size: 1.1rem; color: var(--gold); }
        .status-badge { padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .status-pending { background: var(--warning); color: #000; }
        .status-confirmed { background: var(--success); color: #fff; }
        .status-cancelled { background: var(--danger); color: #fff; }
        .booking-body { padding: 30px; }
        .booking-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        @media (max-width: 768px) { .booking-grid { grid-template-columns: 1fr; } }
        .info-section { background: rgba(0,0,0,0.3); padding: 20px; border-radius: 15px; border: 1px solid rgba(212,175,55,0.1); }
        .info-section h3 { color: var(--gold); margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .info-row:last-child { border-bottom: none; }
        .info-row .label { color: #888; }
        .info-row .value { color: #fff; font-weight: 500; }
        .amount { font-size: 1.3rem; color: var(--gold); font-weight: bold; }
        .receipt-box { background: linear-gradient(135deg, #1a1a1a, #0a0a0a); border: 2px solid var(--gold); border-radius: 15px; padding: 20px; margin-top: 20px; }
        .receipt-header { background: var(--gold); color: #000; padding: 10px; text-align: center; border-radius: 10px; margin-bottom: 15px; }
        .btn { display: inline-block; padding: 12px 25px; border-radius: 50px; text-decoration: none; font-weight: bold; transition: all 0.3s; cursor: pointer; border: none; }
        .btn-primary { background: linear-gradient(135deg, var(--gold), var(--gold-dark)); color: #000; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(212,175,55,0.3); }
        .btn-outline { background: transparent; border: 1px solid var(--gold); color: var(--gold); }
        .btn-outline:hover { background: var(--gold); color: #000; }
        .search-form { background: linear-gradient(135deg, var(--gray), var(--darker)); border-radius: 20px; padding: 30px; text-align: center; border: 1px solid rgba(212,175,55,0.2); max-width: 500px; margin: 0 auto; }
        .search-form input { width: 100%; padding: 15px; background: var(--darker); border: 1px solid rgba(212,175,55,0.3); color: white; border-radius: 12px; margin-bottom: 15px; font-size: 1rem; text-align: center; font-family: monospace; }
        .search-form input:focus { outline: none; border-color: var(--gold); }
        .error-message { background: rgba(239,68,68,0.2); color: #ef4444; padding: 15px; border-radius: 10px; text-align: center; margin-bottom: 20px; }
        .success-message { background: rgba(16,185,129,0.2); color: #10b981; padding: 15px; border-radius: 10px; text-align: center; margin-bottom: 20px; }
        .whatsapp-float { position: fixed; bottom: 2rem; right: 2rem; background: #25D366; width: 55px; height: 55px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: white; z-index: 1000; animation: pulse 2s infinite; text-decoration: none; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(37,211,102,0.4); } 70% { box-shadow: 0 0 0 15px rgba(37,211,102,0); } 100% { box-shadow: 0 0 0 0 rgba(37,211,102,0); } }
        footer { background: var(--darker); padding: 3rem 0 2rem; text-align: center; border-top: 1px solid rgba(212,175,55,0.2); margin-top: 3rem; }
        @media (max-width: 768px) {
            .mobile-menu { display: block; }
            .nav-links { display: none; flex-direction: column; position: absolute; top: 100%; left: 0; width: 100%; background: rgba(0,0,0,0.95); padding: 1rem; text-align: center; gap: 1rem; }
            .nav-links.show { display: flex; }
            .page-header h1 { font-size: 1.8rem; }
            .booking-header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<div class="navbar" id="navbar">
    <div class="container nav-container">
        <div class="logo" onclick="window.location.href='index.html'">
            <img src="images/logo.png" alt="Hollywood Homes BnB" class="logo-img">
        </div>
        <div class="mobile-menu" id="mobileMenu"><i class="fas fa-bars"></i></div>
        <ul class="nav-links" id="navLinks">
            <li><a href="index.html">Home</a></li>
            <li><a href="index.html#about">About</a></li>
            <li><a href="index.html#rooms">Suites</a></li>
            <li><a href="index.html#contact">Contact</a></li>
            <li><a href="booking.html" class="book-now-btn">Book Now</a></li>
            <li><a href="my-booking.php" style="color: var(--gold);"><i class="fas fa-search"></i> My Booking</a></li>
        </ul>
    </div>
</div>

<section class="page-header">
    <div class="container">
        <h1>My <span>Booking</span></h1>
        <p>View your booking details, payment status, and receipt</p>
    </div>
</section>

<section class="booking-container">
    <div class="container">
        <?php if (!empty($booking_ref) && $booking): 
            $check_in = new DateTime($booking['check_in']);
            $check_out = new DateTime($booking['check_out']);
            $nights = $check_in->diff($check_out)->days;
            $receipt_no = 'RCP-' . strtoupper(substr($booking['booking_reference'], 3));
            $status_class = 'status-' . $booking['status'];
            $status_text = ucfirst($booking['status']);
        ?>
        <div class="booking-card">
            <div class="booking-header">
                <div>
                    <i class="fas fa-ticket-alt" style="color: var(--gold);"></i>
                    <span class="booking-ref"><?php echo htmlspecialchars($booking['booking_reference']); ?></span>
                </div>
                <div>
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo strtoupper($status_text); ?>
                    </span>
                </div>
            </div>
            <div class="booking-body">
                <div class="booking-grid">
                    <div class="info-section">
                        <h3><i class="fas fa-user"></i> Guest Information</h3>
                        <div class="info-row"><span class="label">Full Name:</span><span class="value"><?php echo htmlspecialchars($booking['guest_name']); ?></span></div>
                        <div class="info-row"><span class="label">Email Address:</span><span class="value"><?php echo htmlspecialchars($booking['guest_email']); ?></span></div>
                        <div class="info-row"><span class="label">Phone Number:</span><span class="value"><?php echo htmlspecialchars($booking['guest_phone']); ?></span></div>
                    </div>
                    
                    <div class="info-section">
                        <h3><i class="fas fa-hotel"></i> Stay Details</h3>
                        <div class="info-row"><span class="label">Room:</span><span class="value"><?php echo htmlspecialchars($booking['room_name']); ?></span></div>
                        <div class="info-row"><span class="label">Check-in:</span><span class="value"><?php echo $booking['check_in']; ?> (2:00 PM)</span></div>
                        <div class="info-row"><span class="label">Check-out:</span><span class="value"><?php echo $booking['check_out']; ?> (11:00 AM)</span></div>
                        <div class="info-row"><span class="label">Number of Nights:</span><span class="value"><?php echo $nights; ?></span></div>
                    </div>
                </div>
                
                <div class="info-section" style="margin-top: 20px;">
                    <h3><i class="fas fa-receipt"></i> Payment Breakdown</h3>
                    <div class="info-row"><span class="label">Room Charge (<?php echo $nights; ?> nights):</span><span class="value">KSh <?php echo number_format($booking['subtotal'], 2); ?></span></div>
                    <div class="info-row"><span class="label">Service Fee (10%):</span><span class="value">KSh <?php echo number_format($booking['service_fee'], 2); ?></span></div>
                    <div class="info-row"><span class="label">Tax (16% VAT):</span><span class="value">KSh <?php echo number_format($booking['tax'], 2); ?></span></div>
                    <div class="info-row"><span class="label"><strong>Total Amount:</strong></span><span class="value amount">KSh <?php echo number_format($booking['total_price'], 2); ?></span></div>
                </div>
                
                <?php if ($booking['status'] == 'confirmed'): ?>
                <div class="receipt-box">
                    <div class="receipt-header">
                        <h3 style="margin: 0;"><i class="fas fa-check-circle"></i> OFFICIAL RECEIPT</h3>
                    </div>
                    <div class="info-row"><span class="label">Receipt Number:</span><span class="value"><?php echo $receipt_no; ?></span></div>
                    <div class="info-row"><span class="label">Payment Date:</span><span class="value"><?php echo date('Y-m-d H:i:s'); ?></span></div>
                    <div class="info-row"><span class="label">Payment Method:</span><span class="value">M-Pesa (Pochi La Biashara)</span></div>
                    <div class="info-row"><span class="label">M-Pesa Number:</span><span class="value">0792069328</span></div>
                    <div class="info-row"><span class="label">Transaction Ref:</span><span class="value"><?php echo htmlspecialchars($booking['booking_reference']); ?></span></div>
                    <div style="margin-top: 15px; text-align: center; padding: 10px; background: rgba(212,175,55,0.1); border-radius: 8px;">
                        <i class="fas fa-check-circle" style="color: #10b981;"></i> Payment Confirmed
                    </div>
                </div>
                <?php elseif ($booking['status'] == 'pending'): ?>
                <div class="receipt-box" style="border-color: var(--warning);">
                    <div class="receipt-header" style="background: var(--warning);">
                        <h3 style="margin: 0;"><i class="fas fa-clock"></i> Payment Pending</h3>
                    </div>
                    <p style="text-align: center; margin-bottom: 15px;">Complete your payment to confirm your booking:</p>
                    <div class="info-row"><span class="label">M-Pesa Paybill:</span><span class="value">0792069328</span></div>
                    <div class="info-row"><span class="label">Account/Ref:</span><span class="value"><?php echo htmlspecialchars($booking['booking_reference']); ?></span></div>
                    <div class="info-row"><span class="label">Amount:</span><span class="value amount">KSh <?php echo number_format($booking['total_price'], 2); ?></span></div>
                    <div style="margin-top: 15px; text-align: center;">
                        <a href="https://www.safaricom.co.ke/personal/m-pesa" target="_blank" class="btn btn-primary" style="padding: 8px 20px; font-size: 0.9rem;"><i class="fas fa-mobile-alt"></i> Pay with M-Pesa</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($booking['special_requests'])): ?>
                <div class="info-section" style="margin-top: 20px;">
                    <h3><i class="fas fa-pen"></i> Special Requests</h3>
                    <p style="color: #ccc; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></p>
                </div>
                <?php endif; ?>
                
                <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px; flex-wrap: wrap;">
                    <a href="index.html" class="btn btn-outline"><i class="fas fa-home"></i> Back to Home</a>
                    <a href="booking.html" class="btn btn-primary"><i class="fas fa-calendar-plus"></i> Make Another Booking</a>
                    <a href="#" onclick="window.print();" class="btn btn-outline"><i class="fas fa-print"></i> Print Receipt</a>
                </div>
            </div>
        </div>
        
        <?php elseif (!empty($booking_ref) && !$booking): ?>
        <div class="search-form">
            <i class="fas fa-search" style="font-size: 3rem; color: var(--gold); margin-bottom: 1rem; display: block;"></i>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
            <p style="color: #888; margin-top: 15px;">Please check your reference number and try again.</p>
            <a href="my-booking.php" class="btn btn-primary" style="margin-top: 20px;"><i class="fas fa-arrow-left"></i> Try Again</a>
        </div>
        <?php else: ?>
        <div class="search-form">
            <i class="fas fa-ticket-alt" style="font-size: 3rem; color: var(--gold); margin-bottom: 1rem; display: block;"></i>
            <h3 style="margin-bottom: 1rem;">Find Your Booking</h3>
            <p style="color: #888; margin-bottom: 1.5rem;">Enter your booking reference number to view your booking details and receipt.</p>
            <form method="GET" action="">
                <input type="text" name="ref" placeholder="Enter Booking Reference (e.g., BKG243AEB78)" required autocomplete="off">
                <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-search"></i> View My Booking</button>
            </form>
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(212,175,55,0.2);">
                <p style="color: #666; font-size: 0.85rem;">Don't have a booking reference? <a href="booking.html" style="color: var(--gold);">Book a room now →</a></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<a href="https://wa.me/254792069328" class="whatsapp-float" target="_blank"><i class="fab fa-whatsapp"></i></a>

<footer>
    <div class="container">
        <p>📍 Bungoma Town, Kenya | 📞 +254 792 069 328 | ✉️ tonnyodhiambo49@gmail.com</p>
        <p style="margin-top: 1rem; font-size: 0.85rem;">© 2025 Hollywood Homes BnB - Where Comfort Meets Class</p>
    </div>
</footer>

<script>
// Mobile menu toggle
document.getElementById('mobileMenu')?.addEventListener('click', () => {
    document.getElementById('navLinks').classList.toggle('show');
});

// Close mobile menu when clicking a link
document.querySelectorAll('#navLinks a').forEach(link => {
    link.addEventListener('click', () => {
        document.getElementById('navLinks')?.classList.remove('show');
    });
});

// Navbar scroll effect
window.addEventListener('scroll', () => {
    const navbar = document.getElementById('navbar');
    if (window.scrollY > 50) {
        navbar.style.background = 'rgba(0,0,0,0.95)';
    } else {
        navbar.style.background = 'rgba(0,0,0,0.95)';
    }
});
</script>
</body>
</html>
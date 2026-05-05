<?php
/**
 * Email Configuration Class
 * Uses PHPMailer for reliable Gmail SMTP integration
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer classes
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

class EmailSender {
    private $mail;
    private $logo_url;
    private $admin_email;
    private $mpesa_number;
    private $company_details;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->logo_url = SITE_URL . 'images/logo.png';
        $this->admin_email = 'tonnyodhiambo49@gmail.com';
        $this->mpesa_number = '0792069328';
        $this->company_details = [
            'name' => 'Hollywood Homes BnB',
            'address' => 'Bungoma Town, Kenya',
            'phone' => '+254 792 069 328',
            'email' => 'tonnyodhiambo49@gmail.com',
            'pin' => 'Pending Registration'
        ];
        $this->setupSMTP();
    }
    
    private function setupSMTP() {
        try {
            $this->mail->isSMTP();
            $this->mail->Host = SMTP_HOST;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = SMTP_USER;
            $this->mail->Password = SMTP_PASS;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = SMTP_PORT;
            $this->mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mail->isHTML(true);
            $this->mail->CharSet = 'UTF-8';
        } catch (Exception $e) {
            error_log("SMTP Setup Error: " . $e->getMessage());
        }
    }
    
    public function sendBookingConfirmation($booking_data, $room_data) {
        try {
            $to = $booking_data['guest_email'];
            $subject = "Booking Confirmation & Payment Instructions - " . $booking_data['booking_reference'];
            
            $check_in = new DateTime($booking_data['check_in']);
            $check_out = new DateTime($booking_data['check_out']);
            $nights = $check_in->diff($check_out)->days;
            
            $subtotal = number_format($booking_data['subtotal'], 2);
            $service_fee = number_format($booking_data['service_fee'], 2);
            $tax = number_format($booking_data['tax'], 2);
            $total = number_format($booking_data['total_price'], 2);
            $price_per_night = number_format($room_data['price'], 2);
            
            $mpesa_html = $this->getMpesaInstructionsHTML($total, $booking_data['booking_reference']);
            $html_content = $this->getBookingConfirmationHTML($booking_data, $room_data, $nights, $subtotal, $service_fee, $tax, $total, $price_per_night, $mpesa_html);
            $text_content = $this->getBookingConfirmationText($booking_data, $room_data, $nights, $subtotal, $service_fee, $tax, $total, $price_per_night);
            
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $booking_data['guest_name']);
            $this->mail->Subject = $subject;
            $this->mail->Body = $html_content;
            $this->mail->AltBody = $text_content;
            
            $guest_email_sent = $this->mail->send();
            $admin_email_sent = $this->sendAdminNotification($booking_data, $room_data);
            
            return $guest_email_sent && $admin_email_sent;
        } catch (Exception $e) {
            error_log("Email Send Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    public function sendBookingStatusUpdate($booking_data, $room_name, $old_status, $new_status) {
        try {
            $to = $booking_data['guest_email'];
            
            if ($new_status == 'confirmed') {
                $subject = "✓ PAYMENT CONFIRMED & RECEIPT - Booking Confirmed! - " . $booking_data['booking_reference'];
                $html_content = $this->getPaymentConfirmedWithReceiptHTML($booking_data, $room_name);
                $text_content = $this->getPaymentConfirmedWithReceiptText($booking_data, $room_name);
            } else {
                $subject = "Booking Status Update - " . $booking_data['booking_reference'];
                $html_content = $this->getStatusUpdateHTML($booking_data, $room_name, $new_status);
                $text_content = $this->getStatusUpdateText($booking_data, $room_name, $new_status);
            }
            
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, $booking_data['guest_name']);
            $this->mail->Subject = $subject;
            $this->mail->Body = $html_content;
            $this->mail->AltBody = $text_content;
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Status Email Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    public function sendAdminNotification($booking_data, $room_data) {
        try {
            $to = $this->admin_email;
            $subject = "🔔 NEW BOOKING - Payment Pending - " . $booking_data['booking_reference'];
            
            $check_in = new DateTime($booking_data['check_in']);
            $check_out = new DateTime($booking_data['check_out']);
            $nights = $check_in->diff($check_out)->days;
            $total = number_format($booking_data['total_price'], 2);
            
            $html_content = "
            <!DOCTYPE html>
            <html>
            <head><meta charset='UTF-8'><title>New Booking</title></head>
            <body style='font-family: Arial, sans-serif; background: #0a0a0a; margin: 0; padding: 20px;'>
                <div style='max-width: 600px; margin: 0 auto; background: #1a1a1a; border-radius: 12px; border: 1px solid #D4AF37;'>
                    <div style='background: #000; padding: 20px; text-align: center; border-bottom: 1px solid #D4AF37;'>
                        <img src='{$this->logo_url}' style='height: 60px;'>
                    </div>
                    <div style='padding: 25px;'>
                        <div style='background: #f59e0b20; padding: 10px; border-radius: 8px; text-align: center; margin-bottom: 20px;'>
                            <span style='color: #f59e0b; font-weight: bold;'>💰 PENDING PAYMENT - AWAITING CONFIRMATION</span>
                        </div>
                        <h2 style='color: #D4AF37;'>📋 Booking Details</h2>
                        <div style='background: #0a0a0a; padding: 15px; border-radius: 10px; border: 1px solid #333;'>
                            <p><strong style='color: #D4AF37;'>Reference:</strong> #{$booking_data['booking_reference']}</p>
                            <p><strong style='color: #D4AF37;'>Guest:</strong> {$booking_data['guest_name']}</p>
                            <p><strong style='color: #D4AF37;'>Email:</strong> {$booking_data['guest_email']}</p>
                            <p><strong style='color: #D4AF37;'>Phone:</strong> {$booking_data['guest_phone']}</p>
                            <p><strong style='color: #D4AF37;'>Room:</strong> {$room_data['name']}</p>
                            <p><strong style='color: #D4AF37;'>Check-in:</strong> {$booking_data['check_in']}</p>
                            <p><strong style='color: #D4AF37;'>Check-out:</strong> {$booking_data['check_out']}</p>
                            <p><strong style='color: #D4AF37;'>Nights:</strong> {$nights}</p>
                            <p><strong style='color: #D4AF37;'>Total Amount:</strong> <span style='color: #D4AF37; font-size: 18px;'>KSh {$total}</span></p>
                        </div>
                        <div style='margin-top: 20px; text-align: center;'>
                            <a href='" . SITE_URL . "admin.php' style='background: #D4AF37; color: #000; padding: 10px 25px; text-decoration: none; border-radius: 50px;'>Confirm Payment →</a>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $this->mail->clearAddresses();
            $this->mail->addAddress($to, 'Admin');
            $this->mail->Subject = $subject;
            $this->mail->Body = $html_content;
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Admin Notification Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    private function getPaymentConfirmedWithReceiptHTML($booking, $room_name) {
        $check_in = new DateTime($booking['check_in']);
        $check_out = new DateTime($booking['check_out']);
        $nights = $check_in->diff($check_out)->days;
        $booking_date = new DateTime($booking['created_at']);
        $receipt_no = 'RCP-' . strtoupper(substr($booking['booking_reference'], 3));
        
        $subtotal = number_format($booking['subtotal'], 2);
        $service_fee = number_format($booking['service_fee'], 2);
        $tax = number_format($booking['tax'], 2);
        $total = number_format($booking['total_price'], 2);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Payment Confirmation & Receipt</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background: #0a0a0a; margin: 0; padding: 20px; }
                .container { max-width: 700px; margin: 0 auto; background: #1a1a1a; border-radius: 12px; overflow: hidden; border: 1px solid #D4AF37; }
                .header { background: #000000; padding: 25px; text-align: center; border-bottom: 1px solid #D4AF37; }
                .logo-img { height: 80px; width: auto; margin: 0 auto; display: block; }
                .success-badge { background: #10b981; color: #fff; padding: 8px 20px; border-radius: 30px; display: inline-block; margin: 15px 0; font-weight: bold; }
                .content { padding: 30px; }
                .receipt-header { background: linear-gradient(135deg, #D4AF37, #B8942E); padding: 20px; text-align: center; border-radius: 10px; margin-bottom: 25px; }
                .receipt-header h2 { color: #000; margin: 0; }
                .receipt-header p { color: #000; margin: 5px 0 0; }
                .section-title { color: #D4AF37; font-size: 18px; margin: 25px 0 15px; padding-bottom: 8px; border-bottom: 2px solid #D4AF37; display: inline-block; }
                .info-box { background: #0a0a0a; padding: 20px; border-radius: 10px; border: 1px solid #333; margin-bottom: 20px; }
                .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #333; color: #fff; }
                .info-row:last-child { border-bottom: none; }
                .info-row strong { color: #D4AF37; }
                .receipt-details { display: flex; justify-content: space-between; flex-wrap: wrap; margin-bottom: 20px; }
                .receipt-details p { color: #fff; margin: 8px 0; }
                .receipt-details strong { color: #D4AF37; }
                .payment-badge { display: inline-block; background: #10b981; color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
                .total-row { font-size: 18px; font-weight: bold; border-top: 2px solid #D4AF37; margin-top: 10px; padding-top: 15px; }
                .total-row span { color: #10b981; }
                .footer { background: #000000; text-align: center; padding: 20px; border-top: 1px solid #D4AF37; }
                .footer p { color: #888; margin: 5px 0; font-size: 12px; }
                .btn { display: inline-block; background: #D4AF37; color: #000; padding: 10px 25px; text-decoration: none; border-radius: 50px; margin-top: 15px; font-weight: bold; }
                .thankyou { text-align: center; margin: 20px 0; color: #D4AF37; font-size: 18px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <img src='{$this->logo_url}' alt='Hollywood Homes BnB' class='logo-img'>
                    <div class='success-badge'>✓ PAYMENT CONFIRMED ✓</div>
                </div>
                <div class='content'>
                    <div class='receipt-header'>
                        <h2>OFFICIAL PAYMENT RECEIPT</h2>
                        <p>Tax Invoice / Receipt</p>
                    </div>
                    
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <span class='payment-badge'>PAID</span>
                    </div>
                    
                    <div class='receipt-details'>
                        <div>
                            <p><strong>Receipt No:</strong> <span style='color: #fff;'>{$receipt_no}</span></p>
                            <p><strong>Date:</strong> <span style='color: #fff;'>" . date('Y-m-d H:i:s') . "</span></p>
                        </div>
                        <div>
                            <p><strong>Booking Ref:</strong> <span style='color: #fff;'>{$booking['booking_reference']}</span></p>
                            <p><strong>Payment Status:</strong> <span style='color: #10b981;'>Confirmed</span></p>
                        </div>
                    </div>
                    
                    <h3 class='section-title'>🏨 BILL TO</h3>
                    <div class='info-box'>
                        <div class='info-row'><strong>Guest Name:</strong> <span>{$booking['guest_name']}</span></div>
                        <div class='info-row'><strong>Email:</strong> <span>{$booking['guest_email']}</span></div>
                        <div class='info-row'><strong>Phone:</strong> <span>{$booking['guest_phone']}</span></div>
                    </div>
                    
                    <h3 class='section-title'>📋 BOOKING DETAILS</h3>
                    <div class='info-box'>
                        <div class='info-row'><strong>Room Type:</strong> <span>{$room_name}</span></div>
                        <div class='info-row'><strong>Check-in:</strong> <span>{$booking['check_in']} (2:00 PM)</span></div>
                        <div class='info-row'><strong>Check-out:</strong> <span>{$booking['check_out']} (11:00 AM)</span></div>
                        <div class='info-row'><strong>Number of Nights:</strong> <span>{$nights}</span></div>
                    </div>
                    
                    <h3 class='section-title'>💰 PAYMENT BREAKDOWN</h3>
                    <div class='info-box'>
                        <div class='info-row'><strong>Room Charge ({$nights} nights):</strong> <span>KSh {$subtotal}</span></div>
                        <div class='info-row'><strong>Service Fee (10%):</strong> <span>KSh {$service_fee}</span></div>
                        <div class='info-row'><strong>Tax (16% VAT):</strong> <span>KSh {$tax}</span></div>
                        <div class='info-row total-row'><strong>TOTAL PAID:</strong> <span style='color: #10b981; font-size: 20px;'>KSh {$total}</span></div>
                    </div>
                    
                    <h3 class='section-title'>📱 PAYMENT METHOD</h3>
                    <div class='info-box'>
                        <div class='info-row'><strong>Payment Method:</strong> <span>M-Pesa (Pochi La Biashara)</span></div>
                        <div class='info-row'><strong>Business Number:</strong> <span>{$this->mpesa_number}</span></div>
                        <div class='info-row'><strong>Account Reference:</strong> <span>{$booking['booking_reference']}</span></div>
                    </div>
                    
                    <div class='thankyou'>
                        🎉 Thank you for choosing Hollywood Homes BnB!<br>
                        We look forward to hosting you!
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='" . SITE_URL . "my-booking.php?ref=" . $booking['booking_reference'] . "' class='btn'>📋 View Your Booking</a>
                    </div>
                    
                    <div style='margin-top: 25px; padding-top: 15px; border-top: 1px solid #333; text-align: center; color: #888; font-size: 12px;'>
                        <p>📞 Need assistance? Contact us: +254 792 069 328</p>
                        <p>📍 {$this->company_details['address']}</p>
                        <p>✉️ {$this->company_details['email']}</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Hollywood Homes BnB - Where Comfort Meets Class</p>
                    <p>This is your official payment receipt. Please keep it for your records.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getPaymentConfirmedWithReceiptText($booking, $room_name) {
        $check_in = new DateTime($booking['check_in']);
        $check_out = new DateTime($booking['check_out']);
        $nights = $check_in->diff($check_out)->days;
        $receipt_no = 'RCP-' . strtoupper(substr($booking['booking_reference'], 3));
        $total = number_format($booking['total_price'], 2);
        
        return "
========================================
    HOLLYWOOD HOMES BnB
    OFFICIAL PAYMENT RECEIPT
========================================

Receipt No: {$receipt_no}
Date: " . date('Y-m-d H:i:s') . "
Booking Reference: {$booking['booking_reference']}
Payment Status: ✓ CONFIRMED

----------------------------------------
BILL TO
----------------------------------------
Guest Name: {$booking['guest_name']}
Email: {$booking['guest_email']}
Phone: {$booking['guest_phone']}

----------------------------------------
BOOKING DETAILS
----------------------------------------
Room: {$room_name}
Check-in: {$booking['check_in']} (2:00 PM)
Check-out: {$booking['check_out']} (11:00 AM)
Nights: {$nights}

----------------------------------------
PAYMENT BREAKDOWN
----------------------------------------
Room Charge ({$nights} nights): KSh " . number_format($booking['subtotal'], 2) . "
Service Fee (10%): KSh " . number_format($booking['service_fee'], 2) . "
Tax (16% VAT): KSh " . number_format($booking['tax'], 2) . "
TOTAL PAID: KSh {$total}

----------------------------------------
PAYMENT METHOD
----------------------------------------
Method: M-Pesa (Pochi La Biashara)
Business Number: {$this->mpesa_number}
Account Reference: {$booking['booking_reference']}

========================================
🎉 THANK YOU FOR CHOOSING US!
We look forward to hosting you at Hollywood Homes BnB!
========================================

View your booking online: " . SITE_URL . "my-booking.php?ref={$booking['booking_reference']}

Contact: +254 792 069 328
Email: {$this->company_details['email']}
Location: Bungoma Town, Kenya

This is your official payment receipt. Please keep it for your records.
";
    }
    
    private function getMpesaInstructionsHTML($total, $reference) {
        return "
        <div style='background: linear-gradient(135deg, #1a2a1a, #0a1a0a); border: 2px solid #D4AF37; border-radius: 12px; padding: 20px; margin: 20px 0;'>
            <h3 style='color: #D4AF37; margin-top: 0; text-align: center;'>💳 M-Pesa Payment Instructions</h3>
            <div style='text-align: center;'>
                <p style='font-size: 14px; color: #ccc; margin: 5px 0;'>Pochi La Biashara</p>
                <p style='font-size: 28px; color: #D4AF37; font-weight: bold; letter-spacing: 2px;'>{$this->mpesa_number}</p>
            </div>
            <div style='margin-top: 15px;'>
                <p style='color: #fff;'><strong>📱 Steps to Pay:</strong></p>
                <ol style='color: #ccc; line-height: 1.8; margin-left: 20px;'>
                    <li>Go to M-Pesa menu on your phone</li>
                    <li>Select <strong>'Lipa na M-Pesa'</strong></li>
                    <li>Select <strong>'Pochi La Biashara'</strong></li>
                    <li>Enter Business Number: <strong style='color: #D4AF37;'>{$this->mpesa_number}</strong></li>
                    <li>Enter Amount: <strong style='color: #D4AF37;'>KSh {$total}</strong></li>
                    <li>Enter Account/Reference: <strong style='color: #D4AF37;'>{$reference}</strong></li>
                    <li>Enter your M-Pesa PIN and confirm</li>
                </ol>
                <div style='background: rgba(0,0,0,0.5); padding: 10px; border-radius: 8px; margin-top: 15px; text-align: center;'>
                    <p style='color: #D4AF37; margin: 0;'><strong>📌 Important:</strong></p>
                    <p style='color: #fff; margin: 5px 0;'>Use your Booking Reference <strong>{$reference}</strong> as the account number</p>
                </div>
            </div>
            <div style='margin-top: 15px; padding: 10px; background: #f59e0b20; border-radius: 8px; text-align: center;'>
                <p style='color: #f59e0b; margin: 0; font-size: 13px;'>
                    ⏳ Please complete payment within 24 hours to secure your booking
                </p>
            </div>
        </div>
        ";
    }
    
    private function getBookingConfirmationHTML($booking, $room, $nights, $subtotal, $service_fee, $tax, $total, $price_per_night, $mpesa_html) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Booking Confirmation</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; background: #0a0a0a; }
                .container { max-width: 600px; margin: 20px auto; background: #1a1a1a; border-radius: 12px; overflow: hidden; border: 1px solid #D4AF37; }
                .header { background: #000000; padding: 25px; text-align: center; border-bottom: 1px solid #D4AF37; }
                .logo-img { height: 80px; width: auto; margin: 0 auto; display: block; }
                .content { padding: 25px; }
                .greeting { color: #fff; margin-bottom: 20px; }
                .greeting p { color: #ccc; margin-top: 8px; }
                h3 { color: #D4AF37; margin: 20px 0 15px; }
                .booking-details { background: #0a0a0a; padding: 15px; border-radius: 10px; margin: 15px 0; border: 1px solid #333; }
                .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #333; color: #fff; }
                .detail-row:last-child { border-bottom: none; }
                .detail-row strong { color: #D4AF37; }
                .pending-badge { background: #f59e0b; color: #000; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-block; }
                .footer { background: #000000; text-align: center; padding: 15px; border-top: 1px solid #D4AF37; }
                .footer p { color: #888; margin: 5px 0; font-size: 12px; }
                .btn { display: inline-block; background: #D4AF37; color: #000; padding: 10px 20px; text-decoration: none; border-radius: 50px; font-weight: bold; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <img src='{$this->logo_url}' alt='Hollywood Homes BnB' class='logo-img'>
                </div>
                <div class='content'>
                    <div class='greeting'>
                        <strong>Dear {$booking['guest_name']},</strong>
                        <p>Thank you for choosing Hollywood Homes BnB! Your booking has been received.</p>
                    </div>
                    
                    <div style='background: #f59e0b20; border-left: 4px solid #f59e0b; padding: 12px; margin-bottom: 20px;'>
                        <p style='color: #f59e0b; margin: 0;'><strong>⏳ Payment Required:</strong> Your booking is pending payment. Complete payment within 24 hours to secure your reservation.</p>
                    </div>
                    
                    <h3>📋 Booking Details</h3>
                    <div class='booking-details'>
                        <div class='detail-row'><strong>Reference:</strong> <span>{$booking['booking_reference']}</span></div>
                        <div class='detail-row'><strong>Status:</strong> <span class='pending-badge'>Pending Payment</span></div>
                        <div class='detail-row'><strong>Room:</strong> <span>{$room['name']}</span></div>
                        <div class='detail-row'><strong>Check-in:</strong> <span>{$booking['check_in']} (2:00 PM)</span></div>
                        <div class='detail-row'><strong>Check-out:</strong> <span>{$booking['check_out']} (11:00 AM)</span></div>
                        <div class='detail-row'><strong>Nights:</strong> <span>{$nights}</span></div>
                    </div>
                    
                    {$mpesa_html}
                    
                    <h3>💰 Summary</h3>
                    <div class='booking-details'>
                        <div class='detail-row'><strong>Total Amount:</strong> <span style='color: #D4AF37; font-size: 18px;'>KSh {$total}</span></div>
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='" . SITE_URL . "my-booking.php?ref=" . $booking['booking_reference'] . "' class='btn'>📋 View Your Booking</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Hollywood Homes BnB - Where Comfort Meets Class</p>
                    <p>📞 +254 792 069 328 | ✉️ tonnyodhiambo49@gmail.com</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getBookingConfirmationText($booking, $room, $nights, $subtotal, $service_fee, $tax, $total, $price_per_night) {
        return "HOLLYWOOD HOMES BnB\n\n"
            . "Dear {$booking['guest_name']},\n\n"
            . "Thank you for choosing Hollywood Homes BnB! Your booking has been received.\n\n"
            . "⏳ IMPORTANT: Your booking is pending payment. Complete payment within 24 hours.\n\n"
            . "M-PESA PAYMENT INSTRUCTIONS\n"
            . "===========================\n"
            . "Pochi La Biashara Number: {$this->mpesa_number}\n"
            . "Amount: KSh {$total}\n"
            . "Reference: {$booking['booking_reference']}\n\n"
            . "Steps: M-Pesa → Lipa na M-Pesa → Pochi La Biashara → Enter {$this->mpesa_number} → Amount KSh {$total} → Reference {$booking['booking_reference']}\n\n"
            . "BOOKING DETAILS\n"
            . "----------------\n"
            . "Reference: {$booking['booking_reference']}\n"
            . "Status: Pending Payment\n"
            . "Room: {$room['name']}\n"
            . "Check-in: {$booking['check_in']} (2:00 PM)\n"
            . "Check-out: {$booking['check_out']} (11:00 AM)\n"
            . "Nights: {$nights}\n"
            . "Total: KSh {$total}\n\n"
            . "View your booking: " . SITE_URL . "my-booking.php?ref={$booking['booking_reference']}\n\n"
            . "Contact: +254 792 069 328\n"
            . "Thank you,\nHollywood Homes BnB Team";
    }
    
    private function getStatusUpdateHTML($booking, $room_name, $new_status) {
        $status_colors = [
            'confirmed' => '#10b981',
            'pending' => '#f59e0b',
            'cancelled' => '#ef4444'
        ];
        $status_color = $status_colors[$new_status] ?? '#666';
        $status_text = strtoupper($new_status);
        
        return "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'><title>Booking Status Update</title></head>
        <body style='font-family: Arial, sans-serif; background: #0a0a0a; margin: 0; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: #1a1a1a; border-radius: 12px; border: 1px solid #D4AF37;'>
                <div style='background: #000; padding: 25px; text-align: center; border-bottom: 1px solid #D4AF37;'>
                    <img src='{$this->logo_url}' style='height: 60px; margin: 0 auto; display: block;'>
                </div>
                <div style='padding: 25px;'>
                    <h2 style='color: #fff;'>Dear {$booking['guest_name']},</h2>
                    <p style='color: #ccc;'>Your booking status has been updated.</p>
                    <div style='text-align: center; margin: 20px 0;'>
                        <span style='background: {$status_color}; color: #fff; padding: 6px 20px; border-radius: 25px;'>{$status_text}</span>
                    </div>
                    <div style='background: #0a0a0a; padding: 15px; border-radius: 10px; border: 1px solid #333;'>
                        <p><strong style='color: #D4AF37;'>Reference:</strong> <span style='color: #fff;'>{$booking['booking_reference']}</span></p>
                        <p><strong style='color: #D4AF37;'>Room:</strong> <span style='color: #fff;'>{$room_name}</span></p>
                        <p><strong style='color: #D4AF37;'>Dates:</strong> <span style='color: #fff;'>{$booking['check_in']} to {$booking['check_out']}</span></p>
                    </div>
                    <div style='text-align: center; margin-top: 20px;'>
                        <a href='" . SITE_URL . "my-booking.php?ref=" . $booking['booking_reference'] . "' style='color: #D4AF37; font-weight: bold;'>📋 View Your Booking</a>
                    </div>
                </div>
                <div style='background: #000; text-align: center; padding: 15px; border-top: 1px solid #D4AF37;'>
                    <p style='color: #888;'>Hollywood Homes BnB - Where Comfort Meets Class</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getStatusUpdateText($booking, $room_name, $new_status) {
        return "HOLLYWOOD HOMES BnB\n\n"
            . "Dear {$booking['guest_name']},\n\n"
            . "Your booking status has been updated to: " . strtoupper($new_status) . "\n\n"
            . "Reference: {$booking['booking_reference']}\n"
            . "Room: {$room_name}\n"
            . "Dates: {$booking['check_in']} to {$booking['check_out']}\n\n"
            . "View your booking: " . SITE_URL . "my-booking.php?ref={$booking['booking_reference']}\n\n"
            . "Contact: +254 792 069 328\n"
            . "Thank you,\nHollywood Homes BnB Team";
    }
}
?>
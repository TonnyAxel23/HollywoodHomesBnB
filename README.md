# Hollywood Homes BnB - Luxury Boutique Booking System

A complete hotel booking management system for Hollywood Homes BnB, a luxury boutique B&B in Bungoma Town, Kenya.

## 📋 Overview

This is a full-featured hotel booking system with:
- **Frontend Website** - Luxury hotel showcase with room listings and online booking
- **Admin Dashboard** - Complete management interface for rooms, bookings, messages, and audit logs
- **Database Integration** - MySQL database with full CRUD operations
- **Responsive Design** - Mobile-friendly layout that works on all devices

## 🚀 Features

### Frontend (Public)
- ✅ Luxury hotel homepage with hero section
- ✅ Dynamic room listings with images, prices, and amenities
- ✅ Online booking system with date selection
- ✅ Real-time price calculation (subtotal + 10% service fee + 16% VAT)
- ✅ Booking confirmation with unique reference number
- ✅ Contact form for inquiries
- ✅ Newsletter subscription
- ✅ WhatsApp integration for instant communication
- ✅ Google Fonts and Font Awesome icons
- ✅ AOS animations for smooth scrolling effects
- ✅ Fully responsive design

### Admin Dashboard
- ✅ **Secure Admin Login** - Password-protected access
- ✅ **Dashboard** - Key metrics overview (total bookings, revenue, available rooms, pending bookings)
- ✅ **Room Management** - Add, edit, delete rooms with images, prices, amenities, and status
- ✅ **Booking Management** - 
  - Card-based booking display with guest information
  - Search bookings by name, email, or reference
  - Filter by status (All, Pending, Confirmed, Cancelled)
  - Sort by newest, oldest, highest amount, lowest amount
  - Update booking status (Pending → Confirmed → Cancelled)
  - View detailed booking information with price breakdown
- ✅ **Message Management** - View and manage contact form submissions
- ✅ **Audit Logs** - Track all admin activities
  - Table view and timeline view toggle
  - Search and filter by action, level, or date
  - Color-coded log levels (Info, Warning, Danger, Success)
- ✅ **Settings** - Change admin password, update site settings
- ✅ **Password Reset** - Forgot password functionality with email reset link

## 🛠️ Technology Stack

| Technology | Purpose |
|------------|---------|
| PHP 7.4+ | Backend logic and API endpoints |
| MySQL | Database storage |
| HTML5 | Structure |
| CSS3 | Styling with custom properties |
| JavaScript (Vanilla) | Frontend interactivity |
| Font Awesome 6 | Icons |
| Google Fonts (Inter, Playfair Display, Cormorant Garamond, Montserrat) | Typography |
| Chart.js | Dashboard charts |
| AOS Library | Scroll animations |

## 📁 Project Structure

```
hollywood-bnb/
├── index.html              # Main homepage
├── booking.html            # Booking page
├── admin.php               # Admin dashboard
├── api.php                 # API endpoints
├── config.php              # Database configuration and functions
├── logout.php              # Logout handler
├── images/
│   └── logo.png            # Site logo
├── logs/
│   └── activity.log        # Admin activity logs
├── uploads/                # Room images upload directory
└── cache/                  # Cache directory
```

## 💾 Database Schema

The system automatically creates the following tables on first run:

| Table | Description |
|-------|-------------|
| `rooms` | Room information (name, price, description, image, amenities, status) |
| `bookings` | Booking records (guest info, dates, pricing, status, reference) |
| `contacts` | Contact form submissions |
| `newsletters` | Newsletter subscribers |
| `site_settings` | System settings (admin password, reset tokens, site config) |
| `reviews` | Guest reviews and ratings |

### Default Admin Credentials
- **Username:** `admin`
- **Password:** `Hollywood@2024!`

## 🔧 Installation Guide

### Prerequisites 
- XAMPP / WAMP / MAMP / LAMP stack
- PHP 7.4 or higher
- MySQL 5.7 or higher

### Step-by-Step Installation

1. **Download the project files** and place them in your web server directory:
   - XAMPP: `C:\xampp\htdocs\hollywood-bnb\`
   - WAMP: `C:\wamp64\www\hollywood-bnb\`
   - Linux: `/var/www/html/hollywood-bnb/`

2. **Create the database** (optional - the system creates it automatically):
   ```sql
   CREATE DATABASE hollywood_bnb;
   ```

3. **Configure database connection** in `config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'hollywood_bnb');
   ```

4. **Set proper permissions** for directories:
   ```bash
   chmod 755 logs/ uploads/ cache/
   ```

5. **Add your logo**:
   - Place your logo image at `images/logo.png`
   - Recommended size: 200x60 pixels (transparent PNG preferred)

6. **Start your web server** (Apache and MySQL)

7. **Access the website**:
   - Frontend: `http://localhost/hollywood-bnb/`
   - Admin Dashboard: `http://localhost/hollywood-bnb/admin.php`

## 🔐 Admin Login

| Field | Value |
|-------|-------|
| URL | `http://localhost/hollywood-bnb/admin.php` |
| Username | `admin` |
| Password | `Hollywood@2024!` |

### Password Reset
If you forget the admin password:
1. Click "Forgot Password?" on the login page
2. Enter `admin@hollywoodhomesbnb.com`
3. Check the error log for the reset link (email sending requires SMTP configuration)
4. Or reset directly in the database: update `site_settings` table where `setting_key = 'admin_password'`

## 📡 API Endpoints

### Public Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api.php?action=getRooms` | GET | Get available rooms |
| `api.php?action=getAllRooms` | GET | Get all rooms |
| `api.php?action=getRoom&id={id}` | GET | Get single room details |
| `api.php?action=getFeaturedRooms` | GET | Get featured rooms (limit 3) |
| `api.php?action=checkAvailability` | GET | Check room availability for dates |
| `api.php?action=bookRoom` | POST | Create a new booking |
| `api.php?action=getBooking&reference={ref}` | GET | Get booking by reference |
| `api.php?action=contact` | POST | Submit contact form |
| `api.php?action=subscribeNewsletter` | POST | Subscribe to newsletter |
| `api.php?action=getReviews` | GET | Get approved reviews |

### Admin Endpoints (require authentication)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api.php?action=adminLogin` | POST | Admin login |
| `api.php?action=adminGetStats` | GET | Get dashboard statistics |
| `api.php?action=adminGetRooms` | GET | Get all rooms for management |
| `api.php?action=adminAddRoom` | POST | Add new room |
| `api.php?action=adminUpdateRoom` | POST | Update room details |
| `api.php?action=adminDeleteRoom` | POST | Delete room |
| `api.php?action=adminGetBookings` | GET | Get all bookings |
| `api.php?action=adminGetBooking&id={id}` | GET | Get single booking |
| `api.php?action=adminUpdateBookingStatus` | POST | Update booking status |
| `api.php?action=adminGetMessages` | GET | Get contact messages |
| `api.php?action=adminMarkMessageRead&id={id}` | GET | Mark message as read |
| `api.php?action=adminGetAuditLogs` | GET | Get activity logs |
| `api.php?action=adminChangePassword` | POST | Change admin password |

## 💰 Pricing Configuration

The system includes automatic fee calculation:

| Fee Type | Percentage |
|----------|------------|
| Service Fee | 10% |
| Tax (VAT) | 16% |

**Formula:**
```
Subtotal = Price per night × Number of nights
Service Fee = Subtotal × 0.10
Tax = Subtotal × 0.16
Total = Subtotal + Service Fee + Tax
```

To modify these rates, update the constants in `config.php`:
```php
define('TAX_RATE', 0.16);     // 16% VAT
define('SERVICE_FEE', 0.10);  // 10% service fee
```

## 🎨 Customization

### Changing Colors
The color scheme is controlled by CSS variables in the `<style>` section:

```css
:root {
    --gold: #D4AF37;
    --gold-dark: #B8942E;
    --dark: #0a0a0a;
    --gray: #1a1a1a;
    --success: #10b981;
    --danger: #ef4444;
}
```

### Adding Your Logo
1. Place your logo in `images/logo.png`
2. The system automatically uses it across all pages
3. Supported formats: PNG (recommended), JPG, WebP

### Modifying Currency
The system uses Kenyan Shilling (KES) by default. To change:
1. Update `config.php`:
   ```php
   define('CURRENCY', 'KES');
   define('CURRENCY_SYMBOL', 'KSh');
   ```
2. Update the `formatKES()` function in JavaScript files

### Email Configuration
To enable email sending, update SMTP settings in `config.php`:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
```

## 📱 Responsive Breakpoints

| Device | Breakpoint |
|--------|------------|
| Mobile | < 768px |
| Tablet | 768px - 968px |
| Desktop | > 968px |

## 🔒 Security Features

- ✅ Password hashing with Bcrypt
- ✅ Session-based authentication
- ✅ SQL injection prevention (PDO prepared statements)
- ✅ XSS protection (htmlspecialchars escaping)
- ✅ CSRF protection on forms
- ✅ Login attempt limiting
- ✅ Session timeout (2 hours)
- ✅ Secure file uploads with .htaccess protection

## 🐛 Troubleshooting

### Database Connection Error
- Ensure MySQL is running in XAMPP/WAMP
- Check database credentials in `config.php`
- Verify database name `hollywood_bnb` exists

### Login Issues
- Default password: `Hollywood@2024!`
- Check if session storage is working
- Clear browser cookies and cache

### Images Not Loading
- Ensure `uploads/` directory exists and has write permissions
- Check image URLs in database
- Default placeholder images are used if none specified

### 404 Errors
- Ensure mod_rewrite is enabled (if using pretty URLs)
- Verify all files are in the correct directory

## 📞 Support

For issues or questions:
- Email: tonnyodhiambo49@gmail.com
- Phone: +254 792 069 328
- WhatsApp: +254 792 069 328

## 📄 License

This project is proprietary and confidential. Unauthorized copying, distribution, or use is strictly prohibited.

## 👨‍💻 Author

Developed for Hollywood Homes BnB - Where Comfort Meets Class

---

**© 2025 Hollywood Homes BnB. All Rights Reserved.**

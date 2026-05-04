// Frontend JavaScript for Hollywood Homes BnB
let currentPage = 'home';
let roomsCache = [];

const pages = {
    home: `
        <section class="hero">
            <div class="container hero-content">
                <h1>Hollywood Homes BnB</h1>
                <p class="tagline">"Where Comfort Meets Class."</p>
                <button id="bookNowHeroBtn" class="btn">✨ Book Your Stay Now</button>
            </div>
        </section>
        <section>
            <div class="container">
                <h2 class="section-title">Welcome to Luxury Living</h2>
                <p style="text-align: center; max-width: 800px; margin: 0 auto 3rem; font-size: 1.1rem;">
                    Experience the perfect blend of Hollywood glamour and Kenyan hospitality. 
                    Located in the heart of Bungoma Town, our suites offer unparalleled comfort, 
                    premium amenities, and 24/7 personalized service.
                </p>
                <div class="features-grid">
                    <div class="feature-card"><i class="fas fa-wifi"></i><h3>Free WiFi</h3><p>High-speed fiber optic</p></div>
                    <div class="feature-card"><i class="fab fa-netflix"></i><h3>Netflix Premium</h3><p>All your favorites</p></div>
                    <div class="feature-card"><i class="fas fa-shield-alt"></i><h3>24/7 Security</h3><p>CCTV & secure access</p></div>
                    <div class="feature-card"><i class="fas fa-concierge-bell"></i><h3>Concierge Service</h3><p>Always available</p></div>
                </div>
            </div>
        </section>
    `,
    
    rooms: `
        <section>
            <div class="container">
                <h2 class="section-title">Our Signature Suites</h2>
                <div id="roomsList" class="rooms-grid">
                    <div style="text-align: center;">Loading luxury suites...</div>
                </div>
            </div>
        </section>
    `,
    
    booking: `
        <section>
            <div class="container">
                <h2 class="section-title">Reserve Your Experience</h2>
                <div style="max-width: 600px; margin: 0 auto; background: #111; padding: 2rem; border-radius: 20px; border: 1px solid #D4AF37;">
                    <form id="bookingForm">
                        <label>Full Name</label>
                        <input type="text" id="fullname" required placeholder="John Doe">
                        <label>Phone Number</label>
                        <input type="tel" id="phone" required placeholder="+254 XXX XXX XXX">
                        <label>Email Address</label>
                        <input type="email" id="email" required placeholder="john@example.com">
                        <label>Check-in Date</label>
                        <input type="date" id="checkin" required>
                        <label>Check-out Date</label>
                        <input type="date" id="checkout" required>
                        <label>Select Suite</label>
                        <select id="roomSelect" required>
                            <option value="">Loading rooms...</option>
                        </select>
                        <button type="submit" class="btn" style="width: 100%;">Confirm Booking</button>
                        <div id="bookingMsg" style="margin-top: 1rem;"></div>
                    </form>
                </div>
            </div>
        </section>
    `,
    
    contact: `
        <section>
            <div class="container">
                <h2 class="section-title">Get in Touch</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <div style="background: #111; padding: 2rem; border-radius: 20px;">
                        <h3 style="color: #D4AF37;">Contact Information</h3>
                        <p style="margin: 1rem 0;"><i class="fas fa-phone-alt" style="color: #D4AF37;"></i> <a href="tel:+254712345678" style="color: white; text-decoration: none;">+254 712 345 678</a></p>
                        <p style="margin: 1rem 0;"><i class="fab fa-whatsapp" style="color: #25D366;"></i> <a href="https://wa.me/254712345679" style="color: white; text-decoration: none;">+254 712 345 679 (WhatsApp)</a></p>
                        <p style="margin: 1rem 0;"><i class="fas fa-envelope" style="color: #D4AF37;"></i> stay@hollywoodhomesbnb.com</p>
                        <p style="margin: 1rem 0;"><i class="fas fa-map-marker-alt" style="color: #D4AF37;"></i> Bungoma Town, Opposite Golf Hotel, Kenya</p>
                    </div>
                    <div style="background: #111; padding: 2rem; border-radius: 20px;">
                        <h3 style="color: #D4AF37;">Send us a Message</h3>
                        <form id="contactForm">
                            <input type="text" id="contactName" placeholder="Your Name" required>
                            <input type="email" id="contactEmail" placeholder="Email Address" required>
                            <textarea id="contactMessage" rows="4" placeholder="Your message..." required></textarea>
                            <button type="submit" class="btn" style="width: 100%;">Send Message</button>
                            <div id="contactMsg" style="margin-top: 1rem;"></div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    `
};

async function fetchRooms() {
    const response = await fetch('api.php?action=getRooms');
    const data = await response.json();
    if(data.success) return data.rooms;
    return [];
}

async function renderRooms() {
    const container = document.getElementById('roomsList');
    if(!container) return;
    const rooms = await fetchRooms();
    roomsCache = rooms;
    
    if(rooms.length === 0) {
        container.innerHTML = '<div style="text-align: center;">No rooms available at the moment.</div>';
        return;
    }
    
    container.innerHTML = rooms.map(room => `
        <div class="room-card" onclick="showRoomDetails(${room.id})">
            <img src="${room.image_url || 'https://images.pexels.com/photos/1648771/pexels-photo-1648771.jpeg'}" alt="${room.name}">
            <div class="room-info">
                <h3>${room.name}</h3>
                <p>${room.short_description || room.description.substring(0, 100)}...</p>
                <div class="price">$${room.price} <span style="font-size: 0.9rem;">/ night</span></div>
                <div style="display: flex; gap: 0.5rem; margin: 1rem 0;">
                    <i class="fas fa-wifi" style="color: #D4AF37;"></i>
                    <i class="fab fa-netflix" style="color: #D4AF37;"></i>
                    <i class="fas fa-hot-tub" style="color: #D4AF37;"></i>
                </div>
                <button class="btn" onclick="event.stopPropagation(); showRoomDetails(${room.id})">View Details →</button>
            </div>
        </div>
    `).join('');
}

async function showRoomDetails(roomId) {
    const response = await fetch(`api.php?action=getRoom&id=${roomId}`);
    const data = await response.json();
    if(data.success) {
        const room = data.room;
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'flex';
        modal.innerHTML = `
            <div class="modal-content">
                <h2 style="color: #D4AF37;">${room.name}</h2>
                <img src="${room.image_url}" style="width: 100%; border-radius: 15px; margin: 1rem 0;">
                <p>${room.description}</p>
                <div style="margin: 1rem 0;">
                    <h4 style="color: #D4AF37;">Amenities:</h4>
                    <ul style="list-style: none;">
                        ${room.amenities ? JSON.parse(room.amenities).map(a => `<li><i class="fas fa-check" style="color: #D4AF37;"></i> ${a}</li>`).join('') : '<li>Premium amenities included</li>'}
                    </ul>
                </div>
                <div class="price">$${room.price} per night</div>
                <div style="margin: 1rem 0;">
                    <span style="color: ${room.status === 'available' ? '#4CAF50' : '#f44346'}">${room.status === 'available' ? '✓ Available' : '✗ Currently Unavailable'}</span>
                </div>
                ${room.status === 'available' ? `<button class="btn" onclick="bookNowFromModal(${room.id})">Book Now</button>` : ''}
                <button class="btn-outline btn" onclick="this.closest('.modal').remove()">Close</button>
            </div>
        `;
        document.body.appendChild(modal);
    }
}

async function bookNowFromModal(roomId) {
    document.querySelector('.modal')?.remove();
    await navigateTo('booking');
    const roomSelect = document.getElementById('roomSelect');
    if(roomSelect) {
        await populateRoomSelect();
        roomSelect.value = roomId;
    }
}

async function populateRoomSelect() {
    const select = document.getElementById('roomSelect');
    if(!select) return;
    const rooms = await fetchRooms();
    select.innerHTML = '<option value="">Select a suite</option>';
    rooms.filter(r => r.status === 'available').forEach(room => {
        const option = document.createElement('option');
        option.value = room.id;
        option.textContent = `${room.name} - $${room.price}/night`;
        select.appendChild(option);
    });
}

async function submitBooking(e) {
    e.preventDefault();
    const bookingData = {
        name: document.getElementById('fullname').value,
        phone: document.getElementById('phone').value,
        email: document.getElementById('email').value,
        room_id: document.getElementById('roomSelect').value,
        check_in: document.getElementById('checkin').value,
        check_out: document.getElementById('checkout').value
    };
    
    if(!bookingData.room_id) {
        alert('Please select a room');
        return;
    }
    
    const response = await fetch('api.php?action=bookRoom', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(bookingData)
    });
    
    const result = await response.json();
    const msgDiv = document.getElementById('bookingMsg');
    if(result.success) {
        msgDiv.innerHTML = `<div style="background: #4CAF50; color: white; padding: 1rem; border-radius: 10px;">✅ ${result.message}</div>`;
        document.getElementById('bookingForm').reset();
        setTimeout(() => navigateTo('home'), 2000);
    } else {
        msgDiv.innerHTML = `<div style="background: #f44346; color: white; padding: 1rem; border-radius: 10px;">❌ ${result.message}</div>`;
    }
}

async function submitContact(e) {
    e.preventDefault();
    const contactData = {
        name: document.getElementById('contactName').value,
        email: document.getElementById('contactEmail').value,
        message: document.getElementById('contactMessage').value
    };
    
    const response = await fetch('api.php?action=contact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(contactData)
    });
    
    const result = await response.json();
    const msgDiv = document.getElementById('contactMsg');
    if(result.success) {
        msgDiv.innerHTML = `<div style="background: #4CAF50; color: white; padding: 1rem; border-radius: 10px;">✅ Message sent! We'll reply soon.</div>`;
        document.getElementById('contactForm').reset();
    } else {
        msgDiv.innerHTML = `<div style="background: #f44346; color: white; padding: 1rem; border-radius: 10px;">❌ Failed to send message.</div>`;
    }
}

async function navigateTo(page) {
    currentPage = page;
    const app = document.getElementById('app');
    app.innerHTML = pages[page];
    
    if(page === 'rooms') await renderRooms();
    if(page === 'booking') {
        await populateRoomSelect();
        document.getElementById('bookingForm')?.addEventListener('submit', submitBooking);
    }
    if(page === 'contact') {
        document.getElementById('contactForm')?.addEventListener('submit', submitContact);
    }
    if(page === 'home') {
        const heroBtn = document.getElementById('bookNowHeroBtn');
        if(heroBtn) heroBtn.addEventListener('click', () => navigateTo('booking'));
    }
    
    // Update active nav links
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.classList.remove('active');
        if(link.dataset.page === page) link.classList.add('active');
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    await navigateTo('home');
    
    // Mobile menu toggle
    const mobileMenu = document.getElementById('mobileMenu');
    const navLinks = document.getElementById('navLinks');
    mobileMenu?.addEventListener('click', () => navLinks.classList.toggle('show'));
    
    // Navigation
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = link.dataset.page;
            if(page) navigateTo(page);
            navLinks.classList.remove('show');
        });
    });
});

// Make functions global for onclick handlers
window.showRoomDetails = showRoomDetails;
window.bookNowFromModal = bookNowFromModal;
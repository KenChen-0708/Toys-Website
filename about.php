<?php
require_once 'helper/auth.php';
redirect_if_admin();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/about.css">
    <link rel="stylesheet" href="/assets/css/dark_mode_style.css">
</head>

<?php include 'partials/header.php'; ?>

<div class="about-container">

    <!-- Hero Section -->
    <section class="about-hero">
        <div class="hero-content">
            <h1>Welcome to TOYWORLD</h1>
            <p class="hero-subtitle">Where Imagination Comes to Play</p>
        </div>
    </section>

    <!-- Our Story Section -->
    <section class="about-section">
        <div class="section-content">
            <div class="content-grid">
                <div class="content-text">
                    <h2>Our Story</h2>
                    <p>Founded in 1995, TOYWORLD began as a small family-owned toy store with a simple mission: to bring joy and wonder to children of all ages. What started as a single location has grown into a beloved toy destination across multiple cities, but our commitment to quality, imagination, and exceptional customer service remains unchanged.</p>
                    <p>For nearly three decades, we've been helping families create magical memories through play. From classic toys that have stood the test of time to the latest innovations in children's entertainment, we carefully curate our selection to inspire creativity, learning, and endless fun.</p>
                </div>
                <div class="content-image">
                    <div class="image-placeholder">
                        <svg class="icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission & Values Section -->
    <section class="about-section values-section">
        <div class="section-content">
            <h2 class="section-title">Our Mission & Values</h2>
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>
                    <h3>Quality First</h3>
                    <p>We carefully select every toy in our store, ensuring safety, durability, and educational value for your children.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                        </svg>
                    </div>
                    <h3>Family-Focused</h3>
                    <p>We believe in strengthening family bonds through play and creating experiences that bring generations together.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7zm2.85 11.1l-.85.6V16h-4v-2.3l-.85-.6C7.8 12.16 7 10.63 7 9c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.63-.8 3.16-2.15 4.1z"/>
                        </svg>
                    </div>
                    <h3>Inspire Creativity</h3>
                    <p>Our toys are chosen to spark imagination, encourage learning, and help children develop essential skills.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17 8C8 10 5.9 16.17 3.82 21.34l1.89.66.67-2.08C6.38 20.73 7 21 7.58 21c.73 0 1.41-.41 1.74-1.06.72-1.42-.04-3.15-1.46-3.87-.73-.36-1.58-.43-2.35-.2l1.26-4.05C9.5 13.3 12.8 14 17 14V8zM5.5 9A2.5 2.5 0 0 0 8 6.5 2.5 2.5 0 0 0 5.5 4 2.5 2.5 0 0 0 3 6.5 2.5 2.5 0 0 0 5.5 9zm11.5 1c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm-7.5-.5c.46-.17.97-.23 1.47-.16l-1.29 4.14c.3-.08.63-.11.96-.07 1.4.23 2.37 1.55 2.14 2.95-.23 1.4-1.55 2.37-2.95 2.14-1.39-.23-2.37-1.55-2.14-2.95.07-.42.25-.79.5-1.11C8.16 16.7 9.8 14.33 9.5 9.5z"/>
                        </svg>
                    </div>
                    <h3>Sustainability</h3>
                    <p>We're committed to offering eco-friendly toys and reducing our environmental impact wherever possible.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Store Locations Section (NO API — ONLY IFRAME) -->
    <section class="about-section locations-section">
        <div class="section-content">
            <h2 class="section-title">Visit Our Stores</h2>
            <p class="section-subtitle">Find a TOYWORLD location near you</p>

            <!-- Google Maps Iframe -->
            <div class="map-container">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3983.5377912546605!2d101.72398217430151!3d3.2152605527440827!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31cc3843bfb6a031%3A0x2dc5e067aae3ab84!2sTunku%20Abdul%20Rahman%20University%20of%20Management%20and%20Technology%20(TAR%20UMT)!5e0!3m2!1sen!2smy!4v1763521085487!5m2!1sen!2smy"
                    width="100%"
                    height="100%"
                    style="border:0;"
                    allowfullscreen=""
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>

            <!-- Store Cards -->
            <div class="stores-grid">
                <div class="store-card">
                    <div class="store-header">
                        <h3>
                            <svg class="icon-sm" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            TOYWORLD Manhattan
                        </h3>
                        <span class="store-badge">Flagship Store</span>
                    </div>
                    <div class="store-details">
                        <p>
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                            </svg>
                            123 Broadway Avenue, New York, NY 10001
                        </p>
                        <p>
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                            </svg>
                            (212) 555-0100
                        </p>
                        <p>
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                            </svg>
                            Mon-Sat: 9:00 AM - 8:00 PM<br><span class="time-indent">Sun: 10:00 AM - 6:00 PM</span>
                        </p>
                        <p>
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                            </svg>
                            manhattan@toyworld.com
                        </p>
                    </div>
                </div>

                <div class="store-card">
                    <div class="store-header">
                        <h3>
                            <svg class="icon-sm" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            TOYWORLD Brooklyn
                        </h3>
                    </div>
                    <div class="store-details">
                        <p>
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                            </svg>
                            456 Atlantic Avenue, Brooklyn, NY 11217
                        </p>
                        <p>
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                            </svg>
                            (718) 555-0200
                        </p>
                        <p>
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                            </svg>
                            Mon-Sat: 9:00 AM - 8:00 PM<br><span class="time-indent">Sun: 10:00 AM - 6:00 PM</span>
                        </p>
                        <p>
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                            </svg>
                            brooklyn@toyworld.com
                        </p>
                    </div>
                </div>

                <div class="store-card">
                    <div class="store-header">
                        <h3>
                            <svg class="icon-sm" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            TOYWORLD Queens
                        </h3>
                    </div>
                    <div class="store-details">
                        <p>
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                            </svg>
                            789 Queens Boulevard, Queens, NY 11375
                        </p>
                        <p>
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                            </svg>
                            (718) 555-0300
                        </p>
                        <p>
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                            </svg>
                            Mon-Sat: 9:00 AM - 8:00 PM<br><span class="time-indent">Sun: 10:00 AM - 6:00 PM</span>
                        </p>
                        <p>
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                            </svg>
                            queens@toyworld.com
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="about-section why-choose-section">
        <div class="section-content">
            <h2 class="section-title">Why Choose TOYWORLD?</h2>
            <div class="features-grid">
                <div class="feature-item">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/>
                    </svg>
                    <h4>Extensive Selection</h4>
                    <p>Thousands of toys from trusted brands and unique finds you won't see anywhere else.</p>
                </div>

                <div class="feature-item">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/>
                    </svg>
                    <h4>Safety Guaranteed</h4>
                    <p>All products meet or exceed safety standards and regulations for your peace of mind.</p>
                </div>

                <div class="feature-item">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                    <h4>Expert Staff</h4>
                    <p>Our knowledgeable team is passionate about toys and ready to help you find the perfect gift.</p>
                </div>

                <div class="feature-item">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
                    </svg>
                    <h4>Fast Shipping</h4>
                    <p>Quick and reliable delivery service to get toys to your door as fast as possible.</p>
                </div>

                <div class="feature-item">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 6h-2.18c.11-.31.18-.65.18-1 0-1.66-1.34-3-3-3-1.05 0-1.96.54-2.5 1.35l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 11 8.76l1-1.36 1 1.36L15.38 12 17 10.83 14.92 8H20v6z"/>
                    </svg>
                    <h4>Gift Wrapping</h4>
                    <p>Complimentary gift wrapping service available to make every occasion special.</p>
                </div>

                <div class="feature-item">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/>
                    </svg>
                    <h4>Easy Returns</h4>
                    <p>Hassle-free return policy because your satisfaction is our top priority.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="about-section cta-section">
        <div class="cta-content">
            <h2>Ready to Explore?</h2>
            <p>Visit us in-store or browse our extensive collection online</p>
            <div class="cta-buttons">
                <a href="/pages/product/product_list.php" class="cta-btn primary-btn">
                    <svg class="icon-xs" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18 6h-2c0-2.21-1.79-4-4-4S8 3.79 8 6H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6-2c1.1 0 2 .9 2 2h-4c0-1.1.9-2 2-2zm6 16H6V8h2v2c0 .55.45 1 1 1s1-.45 1-1V8h4v2c0 .55.45 1 1 1s1-.45 1-1V8h2v12z"/>
                    </svg>
                    Shop Now
                </a>
            </div>
        </div>
    </section>

</div>

<?php include 'partials/footer.php'; ?>
</body>

</html>

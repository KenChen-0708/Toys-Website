<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/about.css">
    <link rel="stylesheet" href="/assets/css/dark_mode_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <i class="fas fa-store"></i>
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
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Quality First</h3>
                    <p>We carefully select every toy in our store, ensuring safety, durability, and educational value for your children.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Family-Focused</h3>
                    <p>We believe in strengthening family bonds through play and creating experiences that bring generations together.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <h3>Inspire Creativity</h3>
                    <p>Our toys are chosen to spark imagination, encourage learning, and help children develop essential skills.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-leaf"></i>
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
                        <h3><i class="fas fa-map-marker-alt"></i> TOYWORLD Manhattan</h3>
                        <span class="store-badge">Flagship Store</span>
                    </div>
                    <div class="store-details">
                        <p><i class="fas fa-location-dot"></i> 123 Broadway Avenue, New York, NY 10001</p>
                        <p><i class="fas fa-phone"></i> (212) 555-0100</p>
                        <p><i class="fas fa-clock"></i> Mon-Sat: 9:00 AM - 8:00 PM<br><span class="time-indent">Sun: 10:00 AM - 6:00 PM</span></p>
                        <p><i class="fas fa-envelope"></i> manhattan@toyworld.com</p>
                    </div>
                </div>

                <div class="store-card">
                    <div class="store-header">
                        <h3><i class="fas fa-map-marker-alt"></i> TOYWORLD Brooklyn</h3>
                    </div>
                    <div class="store-details">
                        <p><i class="fas fa-location-dot"></i> 456 Atlantic Avenue, Brooklyn, NY 11217</p>
                        <p><i class="fas fa-phone"></i> (718) 555-0200</p>
                        <p><i class="fas fa-clock"></i> Mon-Sat: 9:00 AM - 8:00 PM<br><span class="time-indent">Sun: 10:00 AM - 6:00 PM</span></p>
                        <p><i class="fas fa-envelope"></i> brooklyn@toyworld.com</p>
                    </div>
                </div>

                <div class="store-card">
                    <div class="store-header">
                        <h3><i class="fas fa-map-marker-alt"></i> TOYWORLD Queens</h3>
                    </div>
                    <div class="store-details">
                        <p><i class="fas fa-location-dot"></i> 789 Queens Boulevard, Queens, NY 11375</p>
                        <p><i class="fas fa-phone"></i> (718) 555-0300</p>
                        <p><i class="fas fa-clock"></i> Mon-Sat: 9:00 AM - 8:00 PM<br><span class="time-indent">Sun: 10:00 AM - 6:00 PM</span></p>
                        <p><i class="fas fa-envelope"></i> queens@toyworld.com</p>
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
                    <i class="fas fa-box-open"></i>
                    <h4>Extensive Selection</h4>
                    <p>Thousands of toys from trusted brands and unique finds you won't see anywhere else.</p>
                </div>

                <div class="feature-item">
                    <i class="fas fa-shield-halved"></i>
                    <h4>Safety Guaranteed</h4>
                    <p>All products meet or exceed safety standards and regulations for your peace of mind.</p>
                </div>

                <div class="feature-item">
                    <i class="fas fa-star"></i>
                    <h4>Expert Staff</h4>
                    <p>Our knowledgeable team is passionate about toys and ready to help you find the perfect gift.</p>
                </div>

                <div class="feature-item">
                    <i class="fas fa-truck-fast"></i>
                    <h4>Fast Shipping</h4>
                    <p>Quick and reliable delivery service to get toys to your door as fast as possible.</p>
                </div>

                <div class="feature-item">
                    <i class="fas fa-gift"></i>
                    <h4>Gift Wrapping</h4>
                    <p>Complimentary gift wrapping service available to make every occasion special.</p>
                </div>

                <div class="feature-item">
                    <i class="fas fa-rotate-left"></i>
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
                    <i class="fas fa-shopping-bag"></i> Shop Now
                </a>
            </div>
        </div>
    </section>

</div>

<?php include 'partials/footer.php'; ?>
</body>

</html>
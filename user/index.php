<?php
ob_start();
include_once '../components/connection.php';
include_once '../components/popups.php';
include_once '../components/session.php';

if (isset($_GET['logout'])) {
    unset($_SESSION['user_id']);
    session_destroy();
    header('location:user/index.php');
    exit;
}

?>
<style type="text/css">
    <?php include '../css/user-css/styles.css';?>
</style>

<!DOCTYPE html>
<html>
<head>
    <title>
        BeanRush - Home Page
    </title>
</head>

<body>

<?php include 'header.php'; ?><!--This is for header file-->

<main>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="section-content">

            <div class="hero-details">
                <h2 class="title">Best Coffee</h2>
                <h3 class="subtitle">Make Your day great with our special coffee</h3>
                <p class="description">Welcome to our coffee paradise, where every bean tells a story and every cup sparks joy.</p>

                <div class="buttons">
                    <a href="menu.php" class="button order-now">Browse Menu</a>
                    <a href="#contact-form" class="button contact-us">Contact Us</a>
                </div>
            </div>

            <div class="hero-image-wrapper">
                <img src="../images/imagescoffee-hero-section.png" alt="Hero" class="hero-image">
            </div>
        </div>
    </section>


    <!-- About Section -->
    <section id="about">
    <section class="about-section">
        <div class="section-content">
            <div class="about-image-wrapper">
                <img src="../images/about-image.jpg" alt="About" class="about-image">
            </div>
            <div class="about-details">
                <h2 class="section-title">About Us</h2>
                <p class="text">
                    At BeanRush, we’re all about crafting the perfect coffee moments. Whether you're looking for a peaceful corner to unwind or a vibrant space to share laughter with friends, our café blends rich flavors, warm vibes, and unforgettable experiences — one cup at a time.
                </p>
                <div class="social-link-list">
                    <a href="#" class="social-link"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#" class="social-link"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fa-brands fa-twitter"></i></a>
                </div>
            </div>
        </div>
    </section>
</section>

    <!-- Menu Section -->
     <section id="menu">
    <section class="menu-section">
        <h2 class="section-title">Our Menu</h2>
        <div class="section-content">
            <ul class="menu-list">

                <li class="menu-item">
                    <img src="../home-images/hot-beverages.png" alt="Hot Beverages" class="menu-image">
                    <div class=buttons>
                    <!-- <a href="view-products.php" class="button">Shop now</a> -->
                    </div>
                    <div class="menu-details">
                    <h3 class="name">Hot Beverages</h3>
                    <p class="text">Wide range of hot coffees to keep you fresh.</p>
                </div>
                </li>
                <li class="menu-item">
                    <img src="../home-images/cold-beverages.png" alt="Cold Beverages" class="menu-image">
                    <div class=buttons>
                    <!-- <a href="view-products.php" class="button">Shop now</a> -->
                    </div>
                    <div class="menu-details">
                    <h3 class="name">Cold Beverages</h3>
                    <p class="text">creamy and forthy cold coffee to make you cool.</p>
                </div>
                </li>
                <li class="menu-item">
                    <img src="../home-images/refreshment.png" alt="Refreshment" class="menu-image">
                    <div class=buttons>
                    <!-- <a href="view-products.php" class="button">Shop now</a> -->
                    </div>
                    <div class="menu-details">
                    <h3 class="name">Refreshment</h3>
                    <p class="text">Fruit and icy refreshing drive to make feel refresh.</p>
                </div>
                </li>
                <li class="menu-item">
                    <img src="../home-images/special-combo.png" alt="Special Combos" class="menu-image">
                    <div class=buttons>
                    <!-- <a href="view-products.php" class="button">Shop now</a> -->
                    </div>
                    <div class="menu-details">
                    <h3 class="name">Special Combos</h3>
                    <p class="text">Your favourite eating and drinking combations.</p>
                </div>
                </li>
                <li class="menu-item">
                    <img src="../home-images/desserts.png" alt="Dessert" class="menu-image">
                
                    <div class=buttons>
                    <!-- <a href="view-products.php" class="button">Shop now</a> -->
                    </div>
                    
                    <div class="menu-details">
                    <h3 class="name">Dessert</h3>
                    <p class="text">Satiate your palate and take you on a culinary treat.</p>
                </div>
                </li>
                <li class="menu-item">
                    <img src="../home-images/burger-frenchfries.png" alt="Burger & French fries" class="menu-image">
                    
                    <div class=buttons>
                    <!-- <a href="view-products.php" class="button">Shop now</a> -->
                    </div>
                    
                    <div class="menu-details">
                    <h3 class="name">Burger & French Fries</h3>
                    <p class="text">Quick bites to satisfy your small size hunger.</p>
                </div>
                </li>
            </ul>
        </div>
    </section>
    </section>

    <!--Testimonials section-->
    <section id="testimonials">
        <!--Testimonials section-->
        <section class="testimonials-section">
            <h2 class="section-title">Testimonials</h2>
            <div class="section-content">
                <div class="slider-container swiper">
                    <div class="slider-wrapper">
                        <ul class="testimonials-list swiper-wrapper">
                            <li class="testimonial swiper-slide">
                                <img src="../Testimonial-images/user-1.jpg" alt="User" class="user-image">
                                <h3 class="name">Sarah Johnson</h3>
                                <i class="feedback">Loved the french roast. Perfectly
                                    balanced and rich, Will order again!
                                </i>
                            </li>
                            <li class="testimonial swiper-slide">
                                <img src="../Testimonial-images/user-5.jpg" alt="User" class="user-image">
                                <h3 class="name">Julie Carlos</h3>
                                <i class="feedback">Great espresso blend! smooth and bold
                                    flavor. Fast shipping tool!
                                </i>
                            </li>
                            <li class="testimonial swiper-slide">
                                <img src="../Testimonial-images/user-3.jpg" alt="User" class="user-image">
                                <h3 class="name">Michael Brown</h3>
                                <i class="feedback">Fantastic mocha flavor, fresh and
                                    aromatic. Quick shipping!
                                </i>
                            </li>
                            <li class="testimonial swiper-slide">
                                <img src="../Testimonial-images/user-4.jpg" alt="User" class="user-image">
                                <h3 class="name">Emily Harris</h3>
                                <i class="feedback">Excellent quality! fresh beans and
                                    quick delivery. Highly recommend.
                                </i>
                            </li>
                            <li class="testimonial swiper-slide">
                                <img src="../Testimonial-images/user-2.jpg" alt="User" class="user-image">
                                <h3 class="name">Anthony Thompson</h3>
                                <i class="feedback">Best decaf I've tried! smooth and
                                    flavorful. Arrived promptly.
                                </i>
                            </li>
                        </ul>
                        <div class="swiper-pagination"></div>
                        <div class="swiper-slide-button swiper-button-prev"></div>
                        <div class="swiper-slide-button swiper-button-next"></div>
                    </div>
                </div>
            </div>
        </section>

        </section>
        
   <!--Gallary section-->
   <section id="gallery">
        <section class="gallery-section">
            <h2 class="section-title">Gallary</h2>
            <div class="section-content">
                <ul class="gallery-list">
                    <li class="gallery-item">
                        <img src="../Gallery-images/image-1.png" alt="Gallery" class="gallery-image">
                    </li>
                    <li class="gallery-item">
                        <img src="../Gallery-images/image-2.png" alt="Gallery" class="gallery-image">
                    </li>
                    <li class="gallery-item">
                        <img src="../Gallery-images/image-3.png" alt="Gallery" class="gallery-image">
                    </li>
                    <li class="gallery-item">
                        <img src="../Gallery-images/image-4.png" alt="Gallery" class="gallery-image">
                    </li>
                    <li class="gallery-item">
                        <img src="../Gallery-images/image-5.png" alt="Gallery" class="gallery-image">
                    </li>
                    <li class="gallery-item">
                        <img src="../Gallery-images/image-6.png" alt="Gallery" class="gallery-image">
                    </li>
                </ul>
            </div>
        </section>
        </section>

        <!-- Contact us Section -->
        <section id="contact" class="contact-section">
        <div class="section-content">
            <h2 class="section-title">Contact Us</h2>
            <div class="contact-container">
                <div class="contact-info">
                    <h2>Get In Touch</h2>
                    
                    <div class="contact-details">
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="contact-text">
                                <h4>Our Location</h4>
                                <p>Indira College of Commerce and Science, Wakad, Pune Maharastra 411033</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div class="contact-text">
                                <h4>Phone Number</h4>
                                <p><a href="tel:+91 88250 69465">+91 88250 69465</a></p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-text">
                                <h4>Email Address</h4>
                                <p><a href="mailto:rahidmalik011@gmail.com">rahidmalik011@gmail.com</a></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="business-hours">
                        <h3>Business Hours</h3>
                        <ul class="hours-list">
                            <li><span>Monday - Friday:</span> <span>8:00 AM - 10:00 PM</span></li>
                            <li><span>Saturday:</span> <span>9:00 AM - 11:00 PM</span></li>
                            <li><span>Sunday:</span> <span>9:00 AM - 9:00 PM</span></li>
                        </ul>
                    </div>
                </div>
                
                <div id="contact-form">
                    <form class="contact-Form" method="post">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Your Message</label>
                            <textarea id="message" name="message" class="form-control" placeholder="Your message here..." required></textarea>
                        </div>
                        
                        <button type="submit" class="submit-btn">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
    <?php include_once 'footer.php';?>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="../js/script.js"></script>
     <script>
    // Simple form validation and submission
    document.getElementById('contactForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Form validation logic
        const name = document.getElementById('name').value;
        const email = document.getElementById('email').value;
        const message = document.getElementById('message').value;
        
        if(name && email && message) {
            // Form is valid - show success message
            alert('Thank you for your message! We will get back to you soon.');
            this.reset();
        } else {
            alert('Please fill in all required fields.');
        }
    });
    
    // Update the Contact Us button in the hero section to scroll to contact form
    document.querySelector('.button.contact-us').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('contact').scrollIntoView({ behavior: 'smooth' });
    });

</body>
</html>


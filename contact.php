<?php
include 'includes/db.php';
include 'includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add your form processing logic here
}
?>
    <header class="header">
        <h1>Contact Us</h1>
        <p>We'd love to hear from you!</p>
    </header>

    <div class="contact-container">
        <div class="contact-info">
            <h2>Get in Touch</h2>
            <div class="contact-details">
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt icon"></i>
                    <div>
                        <h3>Visit Us</h3>
                        <p>123 Creative Lane<br>Digital City, DC 45678</p>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-phone icon"></i>
                    <div>
                        <h3>Call Us</h3>
                        <p>+1 (555) 123-4567<br>Mon-Fri 9am-5pm EST</p>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope icon"></i>
                    <div>
                        <h3>Email Us</h3>
                        <p>support@artifyx.com<br>contact@artifyx.com</p>
                    </div>
                </div>
            </div>
        </div>

        <form class="contact-form" action="/submit-contact" method="POST">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" required>
            </div>
            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" rows="5" required></textarea>
            </div>
            <button type="submit">Send Message</button>
        </form>
    </div>

    <div class="map-container">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d12345.67890!2d-74.005941!3d40.712776!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNDDCsDQyJzQ2LjAiTiA3NMKwMDAnMjEuMyJX!5e0!3m2!1sen!2sus!4v1620000000000!5m2!1sen!2sus" 
                width="100%" 
                height="300" 
                style="border:0;" 
                allowfullscreen="" 
                loading="lazy">
        </iframe>
    </div>


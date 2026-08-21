<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$isLoggedIn = Auth::isLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymBros - Fitness & Bodybuilding</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link
    href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Oswald:wght@500;600;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
</head>

<body>
  <!-- Loading Animation -->
  <div class="page-loader">
    <div class="loader">
      <div class="dumbbell">
        <div class="bar"></div>
        <div class="weight left"></div>
        <div class="weight right"></div>
      </div>
      <p>Loading GymBros...</p>
    </div>
  </div>
  <!--<div class="heading">
    <h1>Gym System</h1>
  </div>-->
  <header>
    <div class="logo">
      <h1>Gym<span>Bros</span></h1>
    </div>

    <!-- Mobile Menu Button -->
    <div class="mobile-menu-btn">
      <span></span>
      <span></span>
      <span></span>
    </div>

    <div class="navBar">
      <ul>
        <li><a href="index.php" class="active"><i class="fas fa-home"></i> Home</a></li>
        <?php if ($isLoggedIn): ?>
          <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="change-password.php"><i class="fas fa-key"></i> Change Password</a></li>
          <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        <?php else: ?>
          <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
          <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="hero-content">
        <h2>Transform Your <span>Body</span>, Transform Your <span>Life</span></h2>
        <p>Join thousands of GymBros members achieving their fitness goals with world-class equipment, expert
          trainers, and a supportive community.</p>
        <?php if (!$isLoggedIn): ?>
          <a href="register.php" class="cta-button">Start Your Journey Today</a>
        <?php else: ?>
          <a href="dashboard.php" class="cta-button">Go to Dashboard</a>
        <?php endif; ?>
      </div>

      <!-- Hero Stats -->
      <div class="hero-stats">
        <div class="stat">
          <span class="stat-number" data-count="5000">0</span>
          <span class="stat-label">Active Members</span>
        </div>
        <div class="stat">
          <span class="stat-number" data-count="25">0</span>
          <span class="stat-label">Expert Trainers</span>
        </div>
        <div class="stat">
          <span class="stat-number" data-count="15">0</span>
          <span class="stat-label">Years Experience</span>
        </div>
      </div>
    </section>

    <section class="features">
      <div class="section-header">
        <h2>Why Choose GymBros?</h2>
        <p>We provide everything you need to achieve your fitness goals</p>
      </div>

      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-dumbbell"></i>
          </div>
          <h3>Modern Equipment</h3>
          <p>Top-of-the-line fitness equipment from leading brands to help you reach your goals efficiently and
            safely.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-user-friends"></i>
          </div>
          <h3>Expert Trainers</h3>
          <p>Our certified trainers are dedicated to helping you perfect your form, create personalized plans, and
            maximize results.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-heartbeat"></i>
          </div>
          <h3>Health First</h3>
          <p>We prioritize your health and safety with clean facilities, proper spacing, and wellness-focused
            programs.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-users"></i>
          </div>
          <h3>Supportive Community</h3>
          <p>Join a community of like-minded individuals who motivate and support each other's fitness journeys.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-calendar-alt"></i>
          </div>
          <h3>Flexible Schedule</h3>
          <p>Open 24/7 to fit your busy lifestyle with classes available at various times throughout the day.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <i class="fas fa-trophy"></i>
          </div>
          <h3>Proven Results</h3>
          <p>Our methods have helped thousands achieve their transformation goals with sustainable, long-term results.
          </p>
        </div>
      </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials">
      <div class="section-header">
        <h2>Success Stories</h2>
        <p>Hear from our members who transformed their lives</p>
      </div>

      <div class="testimonials-container">
        <div class="testimonial-card">
          <div class="testimonial-content">
            <p>"GymBros helped me lose 40 pounds and completely change my lifestyle. The trainers are amazing!"</p>
          </div>
          <div class="testimonial-author">
            <div class="author-avatar">
              <i class="fas fa-user"></i>
            </div>
            <div class="author-info">
              <h4>Michael Johnson</h4>
              <span>Lost 40 lbs</span>
            </div>
          </div>
        </div>

        <div class="testimonial-card">
          <div class="testimonial-content">
            <p>"The community at GymBros kept me motivated when I wanted to give up. Now I'm in the best shape of my
              life!"</p>
          </div>
          <div class="testimonial-author">
            <div class="author-avatar">
              <i class="fas fa-user"></i>
            </div>
            <div class="author-info">
              <h4>Sarah Williams</h4>
              <span>Gained 15 lbs muscle</span>
            </div>
          </div>
        </div>

        <div class="testimonial-card">
          <div class="testimonial-content">
            <p>"As a busy professional, the 24/7 access was a game-changer. I can work out whenever my schedule
              allows."</p>
          </div>
          <div class="testimonial-author">
            <div class="author-avatar">
              <i class="fas fa-user"></i>
            </div>
            <div class="author-info">
              <h4>David Chen</h4>
              <span>Member for 3 years</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Membership Plans Section -->
    <section class="membership">
      <div class="section-header">
        <h2>Membership Plans</h2>
        <p>Choose the plan that fits your goals and budget</p>
      </div>

      <div class="plans-container">
        <div class="plan-card">
          <div class="plan-header">
            <h3>Basic</h3>
            <div class="plan-price">
              <span class="currency">$</span>
              <span class="amount">29</span>
              <span class="period">/month</span>
            </div>
          </div>
          <ul class="plan-features">
            <li><i class="fas fa-check"></i> Gym Access</li>
            <li><i class="fas fa-check"></i> Locker Room</li>
            <li><i class="fas fa-times"></i> Personal Training</li>
            <li><i class="fas fa-times"></i> Group Classes</li>
            <li><i class="fas fa-times"></i> Nutrition Planning</li>
          </ul>
          <a href="register.php" class="plan-button">Get Started</a>
        </div>

        <div class="plan-card featured">
          <div class="plan-badge">Most Popular</div>
          <div class="plan-header">
            <h3>Pro</h3>
            <div class="plan-price">
              <span class="currency">$</span>
              <span class="amount">49</span>
              <span class="period">/month</span>
            </div>
          </div>
          <ul class="plan-features">
            <li><i class="fas fa-check"></i> Gym Access</li>
            <li><i class="fas fa-check"></i> Locker Room</li>
            <li><i class="fas fa-check"></i> Personal Training (2x/mo)</li>
            <li><i class="fas fa-check"></i> Group Classes</li>
            <li><i class="fas fa-times"></i> Nutrition Planning</li>
          </ul>
          <a href="register.php" class="plan-button">Get Started</a>
        </div>

        <div class="plan-card">
          <div class="plan-header">
            <h3>Elite</h3>
            <div class="plan-price">
              <span class="currency">$</span>
              <span class="amount">79</span>
              <span class="period">/month</span>
            </div>
          </div>
          <ul class="plan-features">
            <li><i class="fas fa-check"></i> Gym Access</li>
            <li><i class="fas fa-check"></i> Locker Room</li>
            <li><i class="fas fa-check"></i> Personal Training (4x/mo)</li>
            <li><i class="fas fa-check"></i> Group Classes</li>
            <li><i class="fas fa-check"></i> Nutrition Planning</li>
          </ul>
          <a href="register.php" class="plan-button">Get Started</a>
        </div>
      </div>
    </section>
  </main>

  <footer>
    <div class="footer-content">
      <div class="footer-section">
        <div class="logo">
          <h1>Gym<span>Bros</span></h1>
        </div>
        <p>Transforming lives through fitness since 2008. Join our community and start your journey today.</p>
        <div class="social-icons">
          <a href="#"><i class="fab fa-facebook"></i></a>
          <a href="#"><i class="fab fa-instagram"></i></a>
          <a href="#"><i class="fab fa-twitter"></i></a>
          <a href="#"><i class="fab fa-youtube"></i></a>
        </div>
      </div>

      <div class="footer-section">
        <h3>Quick Links</h3>
        <ul>
          <li><a href="index.php">Home</a></li>
          <?php if ($isLoggedIn): ?>
            <li><a href="dashboard.php">Dashboard</a></li>
          <?php else: ?>
            <li><a href="login.php">Login</a></li>
            <li><a href="register.php">Register</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="footer-section">
        <h3>Contact Info</h3>
        <ul class="contact-info">
          <li><i class="fas fa-map-marker-alt"></i> 123 Fitness Street, Gym City</li>
          <li><i class="fas fa-phone"></i> (555) 123-4567</li>
          <li><i class="fas fa-envelope"></i> info@gymbros.com</li>
          <li><i class="fas fa-clock"></i> Open 24/7</li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <p class="copyright"> 2023 GymBros. All rights reserved.</p>
    </div>
  </footer>

  <script src="../js/loader.js">
  </script>
</body>

</html>
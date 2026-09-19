function hidePageLoader() {
  const pageLoader = document.querySelector('.page-loader');
  if (pageLoader) {
    pageLoader.classList.add('hide');
    pageLoader.style.opacity = '0';
    pageLoader.style.pointerEvents = 'none';
    setTimeout(() => {
      pageLoader.style.display = 'none';
    }, 300);
  }
}

// Hide on DOM ready or immediately if already loaded
if (document.readyState === 'complete' || document.readyState === 'interactive') {
  setTimeout(hidePageLoader, 150);
} else {
  document.addEventListener('DOMContentLoaded', () => setTimeout(hidePageLoader, 150));
  window.addEventListener('load', hidePageLoader);
}

// Safety timeout: Guarantee loader hides after 800ms max under all conditions
setTimeout(hidePageLoader, 800);

    // Animate stats counter
    function animateStats() {
      const stats = document.querySelectorAll('.stat-number');
      stats.forEach(stat => {
        const target = parseInt(stat.getAttribute('data-count'));
        const duration = 2000; // 2 seconds
        const step = target / (duration / 16); // 60fps
        let current = 0;

        const timer = setInterval(() => {
          current += step;
          if (current >= target) {
            current = target;
            clearInterval(timer);
          }
          stat.textContent = Math.floor(current);
        }, 16);
      });
    }

    // Intersection Observer for stats animation
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateStats();
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    // Observe hero stats section
    const heroStats = document.querySelector('.hero-stats');
    if (heroStats) {
      observer.observe(heroStats);
    }

    // Mobile menu toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navBar = document.querySelector('.navBar');

    if (mobileMenuBtn) {
      mobileMenuBtn.addEventListener('click', () => {
        mobileMenuBtn.classList.toggle('active');
        navBar.classList.toggle('active');
      });
    }
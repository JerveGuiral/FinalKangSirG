// Authentication-related JavaScript functionality
class AuthHelper {
    static togglePassword() {
        const passwordInput = document.getElementById('password');
        const passwordIcon = document.getElementById('password-icon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            passwordIcon.className = 'fas fa-eye-slash';
        } else {
            passwordInput.type = 'password';
            passwordIcon.className = 'fas fa-eye';
        }
    }

    static validateUsername(username) {
        const usernameRegex = /^[a-zA-Z0-9]{3,20}$/;
        return usernameRegex.test(username);
    }

    static validatePassword(password) {
        return password.length >= 8 && password.length <= 128;
    }

    static checkUsername(username) {
        if (username.length < 3) return;
        
        const feedbackDiv = document.getElementById('username-feedback');
        if (!feedbackDiv) return;
        
        // Simulate AJAX check
        fetch('check_username.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `username=${encodeURIComponent(username)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                feedbackDiv.innerHTML = '<span class="feedback error"><i class="fas fa-times"></i> Username already exists</span>';
            } else {
                feedbackDiv.innerHTML = '<span class="feedback success"><i class="fas fa-check"></i> Username available</span>';
            }
        })
        .catch(() => {
            // Fallback client-side validation
            const isValid = /^[a-zA-Z0-9_]{3,20}$/.test(username);
            if (isValid) {
                feedbackDiv.innerHTML = '<span class="feedback success"><i class="fas fa-check"></i> Username format valid</span>';
            } else {
                feedbackDiv.innerHTML = '<span class="feedback error"><i class="fas fa-times"></i> Use 3-20 letters, numbers, or underscore</span>';
            }
        });
    }

    static validatePasswordMatch() {
        const password = document.querySelector('input[name="password"]');
        const confirmPassword = document.querySelector('input[name="confirm_password"]');
        
        if (password && confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                if (this.value !== password.value) {
                    this.setCustomValidity('Passwords do not match');
                    this.style.borderColor = '#dc3545';
                } else {
                    this.setCustomValidity('');
                    this.style.borderColor = '#28a745';
                }
            });
        }
    }

    static preventBackButton() {
        // Prevent back button after login (for dashboard)
        if (window.history && window.history.pushState) {
            window.history.pushState(null, null, window.location.href);
            window.onpopstate = function() {
                window.history.go(1);
            };
        }
    }

    static preventLoginBackButton() {
        // AGGRESSIVE back button prevention for LOGIN PAGE
        console.log('Aggressive login page back button prevention activated');
        
        if (window.history && window.history.pushState) {
            // Method 1: Push multiple states to create a barrier
            for (let i = 0; i < 5; i++) {
                window.history.pushState(null, null, window.location.href);
            }
            
            // Method 2: Continuous history management
            let historyInterval = setInterval(() => {
                window.history.pushState(null, null, window.location.href);
            }, 100);
            
            // Method 3: Aggressive popstate handler
            window.addEventListener('popstate', function(event) {
                // Push multiple states to create a barrier
                for (let i = 0; i < 3; i++) {
                    window.history.pushState(null, null, window.location.href);
                }
                
                // Force forward navigation
                window.history.forward();
                
                // Show persistent warning
                AuthHelper.showAggressiveBackButtonWarning();
                
                // Log the attempt
                console.log('Back button blocked on login page');
            });

            // Store the interval so we can clear it if needed
            window.loginBackButtonInterval = historyInterval;
            
            // Additional protection - replace current entry
            window.history.replaceState(null, null, window.location.href);
        }
    }

    static showAggressiveBackButtonWarning() {
        // Remove existing warning if any
        const existingWarning = document.getElementById('back-button-warning');
        if (existingWarning) {
            existingWarning.remove();
        }

        // Create more persistent warning notification
        const warning = document.createElement('div');
        warning.id = 'back-button-warning';
        warning.innerHTML = `
            <div class="back-button-warning-content">
                <i class="fas fa-ban"></i>
                <div>
                    <strong>Back Navigation Blocked</strong>
                    <div>Back button is disabled on login page. Use the navigation menu above.</div>
                </div>
                <button onclick="this.closest('#back-button-warning').remove()" class="close-warning">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        // Add styles if not already added
        if (!document.getElementById('back-button-warning-styles')) {
            const styles = document.createElement('style');
            styles.id = 'back-button-warning-styles';
            styles.textContent = `
                #back-button-warning {
                    position: fixed;
                    top: 80px;
                    right: 20px;
                    z-index: 10000;
                    animation: slideInWarning 0.3s ease;
                }
                
                .back-button-warning-content {
                    background: #dc3545;
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    font-family: 'Montserrat', sans-serif;
                    font-size: 14px;
                    max-width: 350px;
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                    border-left: 4px solid #ff5e00;
                }
                
                .back-button-warning-content i.fa-ban {
                    font-size: 18px;
                    margin-top: 2px;
                    color: #ffc107;
                }
                
                .back-button-warning-content strong {
                    display: block;
                    margin-bottom: 4px;
                    font-size: 15px;
                }
                
                .close-warning {
                    background: none;
                    border: none;
                    color: white;
                    cursor: pointer;
                    padding: 4px;
                    margin-left: auto;
                    align-self: flex-start;
                }
                
                .close-warning:hover {
                    color: #ffc107;
                }
                
                @keyframes slideInWarning {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes slideOutWarning {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(styles);
        }
        
        document.body.appendChild(warning);
        
        // Auto-remove after 6 seconds (longer for aggressive warning)
        setTimeout(() => {
            if (warning.parentNode) {
                warning.style.animation = 'slideOutWarning 0.3s ease';
                setTimeout(() => {
                    if (warning.parentNode) {
                        warning.parentNode.removeChild(warning);
                    }
                }, 300);
            }
        }, 6000);
    }

    // Method to clear the prevention (if needed for logout)
    static clearBackButtonPrevention() {
        if (window.loginBackButtonInterval) {
            clearInterval(window.loginBackButtonInterval);
            window.loginBackButtonInterval = null;
        }
    }

    static initSecurityQuestions() {
        const questions = [
            "Who is your best friend in Elementary?",
            "What is the name of your favorite pet?",
            "Who is your favorite teacher in high school?"
        ];
        
        // Randomly assign questions to avoid pattern
        const questionSelects = document.querySelectorAll('select[name^="security_question"]');
        questionSelects.forEach((select, index) => {
            // Shuffle questions for each select
            const shuffled = [...questions].sort(() => Math.random() - 0.5);
            shuffled.forEach(question => {
                const option = document.createElement('option');
                option.value = question;
                option.textContent = question;
                select.appendChild(option);
            });
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Password toggle functionality (login page only)
    const isLoginPage = window.location.pathname.includes('login.php') || 
        document.querySelector('form[id="login-form"]');
    if (isLoginPage) {
        const passwordToggles = document.querySelectorAll('.password-toggle');
        passwordToggles.forEach(toggle => {
            toggle.addEventListener('click', AuthHelper.togglePassword);
        });
    }

    // Username availability check
    const usernameInputs = document.querySelectorAll('input[name="username"]');
    usernameInputs.forEach(input => {
        input.addEventListener('blur', function() {
            AuthHelper.checkUsername(this.value);
        });
    });

    // Password match validation
    AuthHelper.validatePasswordMatch();

    // Security questions initialization
    AuthHelper.initSecurityQuestions();

    // AGGRESSIVE BACK BUTTON PREVENTION - FOR LOGIN PAGE
    if (window.location.pathname.includes('login.php') || 
        document.querySelector('form[id="login-form"]')) {
        AuthHelper.preventLoginBackButton();
        
        // REMOVED the beforeunload event that was causing the alert on form submission
    }

    // Back button prevention for dashboard (existing)
    if (window.location.pathname.includes('dashboard.php')) {
        AuthHelper.preventBackButton();
    }

    // Form submission enhancements with validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Client-side validation for login form
            if (this.id === 'login-form') {
                const username = this.querySelector('input[name="username"]');
                const password = this.querySelector('input[name="password"]');
                
                if (username && !AuthHelper.validateUsername(username.value)) {
                    e.preventDefault();
                    alert('Username must be 3-20 characters (letters and numbers only)');
                    username.focus();
                    return;
                }
                
                if (password && !AuthHelper.validatePassword(password.value)) {
                    e.preventDefault();
                    alert('Password must be 8-128 characters long');
                    password.focus();
                    return;
                }
            }
            
            // Additional client-side validation before submission
            if (!this.checkValidity()) {
                e.preventDefault();
                // Highlight invalid fields
                const invalidFields = this.querySelectorAll(':invalid');
                invalidFields.forEach(field => {
                    field.style.borderColor = '#dc3545';
                    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
                
                // Show first error message
                if (invalidFields.length > 0) {
                    alert('Please correct the errors before submitting.');
                }
            }
        });
    });
});
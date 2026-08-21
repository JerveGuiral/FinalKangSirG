// Real-time validation functions
class Validation {
    static formatID(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length > 4) {
            value = value.substring(0, 4) + '-' + value.substring(4, 8);
        }
        input.value = value;
        return value;
    }

    static validateName(input, fieldName) {
        let value = input.value.trim();
        
        // Remove double spaces
        value = value.replace(/\s+/g, ' ');
        input.value = value;
        
        const errors = [];
        
        if (value === '') return { isValid: false, errors: [`${fieldName} is required`] };

        // Check for numbers followed by letters or letters followed by numbers
        if (/\d[a-zA-Z]|[a-zA-Z]\d/.test(value)) {
            errors.push('Numbers and letters cannot be mixed together');
        }
        
        // Check for double spaces (should not exist after cleaning)
        if (value.includes('  ')) {
            errors.push('Double spaces are not allowed');
        }
        
        // Check three consecutive identical letters
        if (/([a-zA-Z])\1\1/i.test(value)) {
            errors.push('Three consecutive identical letters are not allowed');
        }
        
        // Check all capital letters
        if (/^[A-Z\s]+$/.test(value)) {
            errors.push('All capital letters are not allowed');
        }
        
        // Check first letter is capital
        if (!/^[A-Z]/.test(value)) {
            errors.push('First letter must be capital');
        }
        
        // Check rest should be lowercase (simple validation)
        if (!/^[A-Z][a-z]*(\s[A-Z][a-z]*)*$/.test(value)) {
            errors.push('Name format should be: First Letter Capital, rest lowercase');
        }
        
        // No special characters or numbers
        if (/[^a-zA-Z\s]/.test(value)) {
            errors.push('Special characters and numbers are not allowed');
        }
        
        return {
            isValid: errors.length === 0,
            errors: errors
        };
    }

    static validateExtension(input) {
        const value = input.value.trim();
        if (value === '') return { isValid: true, errors: [] }; // Optional field
        
        const errors = [];
        const validExtensions = ['jr', 'sr', 'i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x'];
        const normalizedValue = value.toLowerCase().replace(/\./g, '');
        
        if (!validExtensions.includes(normalizedValue)) {
            errors.push('Extension must be like Jr., Sr., I, II, III, IV, V, etc.');
        }
        
        return {
            isValid: errors.length === 0,
            errors: errors
        };
    }

    static validatePassword(password) {
        const strength = {
            score: 0,
            feedback: []
        };
        
        if (password.length >= 8) strength.score++;
        else strength.feedback.push("At least 8 characters");
        
        if (/[A-Z]/.test(password)) strength.score++;
        else strength.feedback.push("One uppercase letter");
        
        if (/[a-z]/.test(password)) strength.score++;
        else strength.feedback.push("One lowercase letter");
        
        if (/[0-9]/.test(password)) strength.score++;
        else strength.feedback.push("One number");
        
        if (/[^a-zA-Z0-9]/.test(password)) strength.score++;
        else strength.feedback.push("One special character");
        
        let strengthLevel = 'weak';
        if (strength.score >= 4) strengthLevel = 'strong';
        else if (strength.score >= 3) strengthLevel = 'medium';
        
        return {
            level: strengthLevel,
            score: strength.score,
            feedback: strength.feedback
        };
    }

    static calculateAge(birthdate) {
        const today = new Date();
        const birthDate = new Date(birthdate);
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        return age;
    }

    static validateEmail(email) {
        const value = String(email || '').trim();
        if (value.length === 0) {
            return { isValid: false, errors: ['Email is required'] };
        }
        if (/\s/.test(value)) {
            return { isValid: false, errors: ['Email cannot contain spaces'] };
        }
        if (value.indexOf('@') === -1) {
            return { isValid: false, errors: ["Email must contain '@'"] };
        }
        const parts = value.split('@');
        if (parts.length !== 2 || parts[1].indexOf('.') === -1) {
            return { isValid: false, errors: ["Email domain must contain '.'"] };
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        if (!emailRegex.test(value)) {
            return { isValid: false, errors: ['Please enter a valid email address'] };
        }
        return { isValid: true, errors: [] };
    }

    static validateZipCode(zipCode) {
        return /^\d{4,10}$/.test(zipCode);
    }

    static toTitleCase(str) {
        const s = String(str || '').toLowerCase().replace(/\s+/g, ' ').trim();
        return s.replace(/(^|[\s\-\/,\.'])\w/g, m => m.toUpperCase());
    }

    static validateAddress(input, fieldName) {
        // Normalize spacing
        let value = (input.value || '').trim().replace(/\s+/g, ' ');
        input.value = value;

        const errors = [];

        if (value.length === 0) {
            return { isValid: false, errors: [`${fieldName} is required`] };
        }

        // Length constraints
        if (value.length < 2) {
            errors.push(`${fieldName} is too short`);
        }
        if (value.length > 100) {
            errors.push(`${fieldName} is too long (max 100 characters)`);
        }

        // Character rules
        const isStreet = /purok\/?street/i.test(fieldName);
        // Allowable characters per field
        // Street: letters, numbers, spaces, period, comma, dash, slash, hash, apostrophe
        // Others: letters, spaces, period, comma, dash, apostrophe (no digits)
        const streetRegex = /^[A-Za-z0-9\s.,\-/#']+$/;
        const genericRegex = /^[A-Za-z\s.,\-']+$/;

        if (isStreet) {
            if (!streetRegex.test(value)) {
                errors.push('Only letters, numbers, spaces, . , - / # and apostrophe are allowed');
            }
        } else {
            if (!genericRegex.test(value)) {
                errors.push('Only letters, spaces, . , - and apostrophe are allowed');
            }
            if (/[0-9]/.test(value)) {
                errors.push('Numbers are not allowed');
            }
        }

        return {
            isValid: errors.length === 0,
            errors
        };
    }

    static validateUsername(username) {
        const errors = [];
        
        if (username.length < 3) {
            return { isValid: false, errors: ['Username must be at least 3 characters'] };
        }
        
        if (!/^[a-zA-Z0-9_]+$/.test(username)) {
            return { isValid: false, errors: ['Username can only contain letters, numbers, and underscores'] };
        }
        
        return { isValid: true, errors: [] };
    }
}

// Global functions
window.formatID = function(input) {
    Validation.formatID(input);
}

window.validateName = function(input, fieldName) {
    const result = Validation.validateName(input, fieldName);
    showFieldValidation(input, result);
}

window.validateExtension = function(input) {
    const result = Validation.validateExtension(input);
    showFieldValidation(input, result);
}

window.validateEmail = function(input) {
    const value = input.value.trim();
    const result = Validation.validateEmail(value);
    showFieldValidation(input, result);
}

window.validateAddress = function(input, fieldName) {
    const result = Validation.validateAddress(input, fieldName);
    showFieldValidation(input, result);
}

window.validateZipCode = function(input) {
    const value = input.value.trim();
    const isValid = Validation.validateZipCode(value);
    const result = {
        isValid: isValid,
        errors: isValid ? [] : ['Zip code must contain numbers only (4-10 digits)']
    };
    showFieldValidation(input, result);
}

window.validatePasswordStrength = function() {
    const passwordInput = document.getElementById('password');
    const strengthDiv = document.getElementById('password-strength');
    
    if (!passwordInput || !strengthDiv) return;
    
    const password = passwordInput.value;
    const result = Validation.validatePassword(password);
    
    if (password.length === 0) {
        strengthDiv.style.display = 'none';
        return;
    }
    
    strengthDiv.textContent = `Password strength: ${result.level}`;
    strengthDiv.className = `password-strength strength-${result.level}`;
    strengthDiv.style.display = 'block';
    
    // Visual feedback
    if (result.level === 'weak') {
        strengthDiv.style.color = '#dc3545';
    } else if (result.level === 'medium') {
        strengthDiv.style.color = '#ffc107';
    } else {
        strengthDiv.style.color = '#28a745';
    }
}

window.validatePasswordMatch = function() {
    const password = document.querySelector('input[name="password"]').value;
    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
    const confirmInput = document.querySelector('input[name="confirm_password"]');
    const matchDiv = document.getElementById('password-match');
    
    if (confirmPassword === '') return;
    
    const isValid = password === confirmPassword;
    const result = {
        isValid: isValid,
        errors: isValid ? [] : ['Passwords do not match']
    };
    showFieldValidation(confirmInput, result);

    if (matchDiv) {
        if (isValid) {
            matchDiv.textContent = 'Passwords match';
            matchDiv.style.color = '#28a745';
            matchDiv.style.display = 'block';
        } else {
            matchDiv.textContent = '';
            matchDiv.style.display = 'none';
        }
    }
}

// Change Password page: strength for #new_password
window.validateNewPasswordStrength = function() {
    const passwordInput = document.getElementById('new_password');
    const strengthDiv = document.getElementById('password-strength');
    if (!passwordInput || !strengthDiv) return;

    const password = passwordInput.value;
    const result = Validation.validatePassword(password);

    if (password.length === 0) {
        strengthDiv.style.display = 'none';
        return;
    }

    strengthDiv.textContent = `Password strength: ${result.level}`;
    strengthDiv.className = `password-strength strength-${result.level}`;
    strengthDiv.style.display = 'block';

    if (result.level === 'weak') {
        strengthDiv.style.color = '#dc3545';
    } else if (result.level === 'medium') {
        strengthDiv.style.color = '#ffc107';
    } else {
        strengthDiv.style.color = '#28a745';
    }
}

// Change Password page: match validation for #new_password and input[name="confirm_password"]
window.validateNewPasswordMatch = function() {
    const password = document.getElementById('new_password');
    const confirmInput = document.querySelector('input[name="confirm_password"]');
    if (!password || !confirmInput) return;

    const isValid = (confirmInput.value === password.value);
    const result = { isValid, errors: isValid ? [] : ['Passwords do not match'] };
    showFieldValidation(confirmInput, result);

    // Optional message area for change/reset pages
    const matchDiv = document.getElementById('password-match');
    if (matchDiv) {
        if (isValid && confirmInput.value.length > 0) {
            matchDiv.textContent = 'Passwords match';
            matchDiv.style.color = '#28a745';
            matchDiv.style.display = 'block';
        } else {
            matchDiv.textContent = '';
            matchDiv.style.display = 'none';
        }
    }
}

window.validateSecurityAnswerMatch = function(questionNumber) {
    const answer = document.querySelector(`input[name="security_answer${questionNumber}"]`).value;
    const answerRepeat = document.querySelector(`input[name="security_answer${questionNumber}_repeat"]`);
    const repeatValue = answerRepeat.value;
    
    if (repeatValue === '') return;
    
    const isValid = answer === repeatValue;
    const result = {
        isValid: isValid,
        errors: isValid ? [] : ['Security answers do not match']
    };
    showFieldValidation(answerRepeat, result);
}

window.checkUsername = function(username) {
    const feedback = document.getElementById('username-feedback');
    if (!feedback) return;
    
    // First validate format
    const formatValidation = Validation.validateUsername(username);
    if (!formatValidation.isValid) {
        feedback.innerHTML = `<i class="fas fa-times-circle"></i> ${formatValidation.errors[0]}`;
        feedback.className = 'feedback error';
        return;
    }
    
    if (username.length < 3) {
        feedback.innerHTML = '';
        return;
    }
    
    // Simulate username check - in real implementation, make AJAX call
    feedback.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking username...';
    feedback.className = 'feedback warning';
    
    setTimeout(() => {
        // This would be replaced with actual AJAX call to check username availability
        const isAvailable = Math.random() > 0.5; // Simulate random result
        
        if (isAvailable) {
            feedback.innerHTML = '<i class="fas fa-check-circle"></i> Username available';
            feedback.className = 'feedback success';
        } else {
            feedback.innerHTML = '<i class="fas fa-times-circle"></i> Username already exists';
            feedback.className = 'feedback error';
        }
    }, 1000);
}

function showFieldValidation(input, result) {
    const group = input.closest('.form-group') || input.parentNode;
    if (!group) return;

    // Remove any existing error nodes inside this form-group to avoid stacking
    const existing = group.querySelectorAll('.field-error');
    existing.forEach((el, idx) => {
        if (idx > 0) el.remove();
    });

    let errorDiv = group.querySelector('.field-error');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        // Prefer to append near the input-with-icon wrapper if present
        const wrapper = input.parentNode && input.parentNode.classList && input.parentNode.classList.contains('input-with-icon')
            ? input.parentNode
            : group;
        wrapper.appendChild(errorDiv);
    }

    // Show only the first error message
    if (!result.isValid && result.errors && result.errors.length > 0) {
        input.classList.add('error-field');
        input.classList.remove('valid-field');
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${result.errors[0]}`;
        errorDiv.style.display = 'block';
    } else {
        input.classList.remove('error-field');
        input.classList.add('valid-field');
        errorDiv.style.display = 'none';
    }

    // If there's a dedicated feedback element (e.g., username-feedback), hide it when showing errors
    if (!result.isValid) {
        const feedback = group.querySelector('#username-feedback');
        if (feedback) {
            feedback.textContent = '';
            feedback.className = 'feedback';
        }
    }
}

// Real-time event listeners for auto-cleaning
document.addEventListener('DOMContentLoaded', function() {
    // Auto-remove double spaces from name fields
    const nameInputs = document.querySelectorAll('input[name="first_name"], input[name="middle_name"], input[name="last_name"]');
    nameInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/\s+/g, ' ');
            // Real-time validate to show errors like three consecutive letters
            window.validateName(this, this.getAttribute('name').replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()));
            // Ensure no birthdate field-error is shown initially
    if (birthdateInit) {
        const existingErr = birthdateInit.parentNode && birthdateInit.parentNode.querySelector('.field-error');
        if (existingErr) existingErr.style.display = 'none';
    }
});
    });

    // Auto-remove non-numeric characters from zip code
    const zipInput = document.querySelector('input[name="zip_code"]');
    if (zipInput) {
        zipInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    }

    // Real-time email validation
    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            window.validateEmail(emailInput);
        });
    }

    const addressNames = ['purok_street','barangay','city_municipality','province','country'];
    addressNames.forEach(n => {
        const el = document.querySelector(`input[name="${n}"]`);
        if (el) {
            el.addEventListener('blur', function() {
                this.value = Validation.toTitleCase(this.value);
                window.validateAddress(this, this.getAttribute('name').replace('_', ' '));
            });
        }
    });

    // Real-time password match validation
    const pwd = document.querySelector('input[name="password"]');
    const cpwd = document.querySelector('input[name="confirm_password"]');
    if (pwd) {
        pwd.addEventListener('input', function() {
            window.validatePasswordStrength();
            if (cpwd && cpwd.value.length > 0) {
                window.validatePasswordMatch();
            }
        });
    }
    if (cpwd) {
        cpwd.addEventListener('input', function() {
            window.validatePasswordMatch();
        });
    }

    // Change Password page listeners
    const newPwd = document.getElementById('new_password');
    const confirmNewPwd = document.querySelector('input[name="confirm_password"]');
    if (newPwd) {
        newPwd.addEventListener('input', function() {
            window.validateNewPasswordStrength();
            if (confirmNewPwd && confirmNewPwd.value.length > 0) {
                window.validateNewPasswordMatch();
            }
        });
    }
    if (confirmNewPwd && newPwd) {
        confirmNewPwd.addEventListener('input', function() {
            window.validateNewPasswordMatch();
        });
    }

    // Initialize birthdate age validation if pre-filled
    const birthdateInit = document.getElementById('birthdate');
    if (birthdateInit && birthdateInit.value) {
        window.calculateAge();
    }
});

window.calculateAge = function() {
    const birthdate = document.getElementById('birthdate');
    const ageDisplay = document.getElementById('age-display');
    const ageError = document.getElementById('age-error');
    
    if (!birthdate) return;

    if (ageDisplay) {
        if (birthdate.value) {
            const age = Validation.calculateAge(birthdate.value);
            ageDisplay.textContent = `${age} years old`;

            const isValid = age >= 18;
            // Visual cue
            ageDisplay.style.color = isValid ? '#28a745' : '#dc3545';
            // Native validity
            birthdate.setCustomValidity(isValid ? '' : 'Must be at least 18 years old');
            // Show error only in Age section
            if (ageError) {
                if (!isValid) {
                    ageError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Must be at least 18 years old';
                    ageError.style.display = 'block';
                } else {
                    ageError.style.display = 'none';
                    ageError.textContent = '';
                }
            }
            // Hide any birthdate field-error element if present
            const birthErr = birthdate.parentNode && birthdate.parentNode.querySelector('.field-error');
            if (birthErr) birthErr.style.display = 'none';
        } else {
            ageDisplay.textContent = 'Enter birthdate';
            ageDisplay.style.color = '';
            birthdate.setCustomValidity('');
            if (ageError) {
                ageError.style.display = 'none';
                ageError.textContent = '';
            }
            const birthErr = birthdate.parentNode && birthdate.parentNode.querySelector('.field-error');
            if (birthErr) birthErr.style.display = 'none';
        }
    }
}

window.togglePassword = function(fieldId) {
    const passwordInput = document.getElementById(fieldId);
    const passwordIcon = document.getElementById(fieldId + '-icon');
    
    if (!passwordInput || !passwordIcon) return;
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        passwordIcon.className = 'fas fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        passwordIcon.className = 'fas fa-eye';
    }
}
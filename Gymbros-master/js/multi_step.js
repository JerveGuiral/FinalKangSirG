// Multi-step form functionality with integrated validation
class MultiStepForm {
  constructor() {
    this.currentStep = 1;
    this.totalSteps = 3;
    this.lastUsernameQuery = '';
    this.init();
  }

  init() {
    this.setupEventListeners();
    this.updateProgressBar();
    this.setupRealTimeValidation();
    // Initial age validation if birthdate is pre-filled
    this.calculateAge();
  }

  setupEventListeners() {
    // Next buttons
    document.querySelectorAll(".btn-next").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        const nextStep = parseInt(e.target.dataset.next);
        if (this.validateCurrentStep()) {
          this.goToStep(nextStep);
        }
      });
    });

    // Previous buttons
    document.querySelectorAll(".btn-prev").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        const prevStep = parseInt(e.target.dataset.prev);
        this.goToStep(prevStep);
      });
    });

    // Form submission
    const form = document.getElementById("register-form");
    if (form) {
      form.addEventListener("submit", (e) => {
        if (!this.validateAllSteps()) {
          e.preventDefault();
          this.showStepError(
            "Please correct all errors before submitting the form."
          );
        }
      });
    }
  }

  setupRealTimeValidation() {
    // Real-time ID number existence check
    const idInput = document.querySelector('input[name="id_number"]');
    if (idInput) {
      let idTimeout;
      idInput.addEventListener("input", (e) => {
        clearTimeout(idTimeout);

        // Format ID in real-time
        this.formatID(idInput);

        idTimeout = setTimeout(() => {
          if (idInput.value.length === 9) {
            // xxxx-xxxx format
            this.checkIDRealTime(idInput.value);
          } else {
            // Clear feedback if not complete format
            const feedback = idInput.parentNode.querySelector("#id-feedback");
            if (feedback) {
              feedback.innerHTML = "";
              feedback.style.display = "none";
            }
          }
        }, 800); // Wait 800ms after user stops typing
      });
    }

    // Username checking
    const usernameInput = document.querySelector('input[name="username"]');
    if (usernameInput) {
      let usernameTimeout;
      usernameInput.addEventListener("input", (e) => {
        clearTimeout(usernameTimeout);
        const val = usernameInput.value.trim();

        // If empty or too short, hide availability feedback immediately
        const feedback = document.getElementById("username-feedback");
        if (feedback && val.length < 3) {
          feedback.innerHTML = "";
          feedback.style.display = "none";
        }

        // Also hide any existing field-error in the username form-group to avoid double rows
        if (val.length < 3) {
          const group = usernameInput.closest('.form-group') || usernameInput.parentNode;
          if (group) {
            const errs = group.querySelectorAll('.field-error');
            errs.forEach(el => { el.style.display = 'none'; });
          }
        }

        usernameTimeout = setTimeout(() => {
          if (val.length >= 3) {
            this.checkUsernameRealTime(val);
          }
        }, 500);
      });
    }

    // Other real-time validations
    document
      .querySelectorAll(".form-step input, .form-step select")
      .forEach((input) => {
        if (
          input.type === "submit" ||
          input.type === "button" ||
          input.type === "hidden"
        ) {
          return;
        }

        input.addEventListener("input", (e) => {
          this.validateSingleField(input);

          if (input.name === "birthdate") {
            this.calculateAge();
          }
          if (input.name === "password") {
            this.validatePasswordStrength();
          }
          if (input.name === "confirm_password") {
            this.validatePasswordMatch();
          }
          if (
            input.name.includes("security_answer") &&
            input.name.includes("_repeat")
          ) {
            const questionNum = input.name.match(/\d/)[0];
            this.validateSecurityAnswerMatch(questionNum);
          }
        });

        // Name fields: remove any pre-attached listeners (e.g., from validation.js) and attach minimal handler
        if (
          input.tagName === "INPUT" &&
          input.type === "text" &&
          input.name && ["first_name","middle_name","last_name"].includes(input.name)
        ) {
          const cloned = input.cloneNode(true);
          input.parentNode.replaceChild(cloned, input);
          cloned.addEventListener("input", () => {
            // Minimal cleaning: only trim leading spaces; keep internal spaces for double-space validation
            this.cleanNameField(cloned);
            // Validate immediately to show specific error sequence
            this.validateSingleField(cloned);
          });
        }

        // Auto-clean zip code (numbers only)
        if (input.name === "zip_code") {
          input.addEventListener("input", (e) => {
            input.value = input.value.replace(/\D/g, "");
          });
        }
      });
  }

  checkIDRealTime(idNumber) {
    const input = document.querySelector('input[name="id_number"]');
    if (!input) return;

    console.log("Checking ID:", idNumber);

    // Create or find feedback element
    let feedback = input.parentNode.querySelector("#id-feedback");
    if (!feedback) {
      feedback = document.createElement("div");
      feedback.id = "id-feedback";
      feedback.className = "feedback";
      input.parentNode.appendChild(feedback);
    }

    // Show loading state
    feedback.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Checking ID...';
    feedback.className = "feedback warning";
    feedback.style.display = "block";

    // Make the AJAX call
    fetch("check_id.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "id_number=" + encodeURIComponent(idNumber),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Network response was not ok: " + response.status);
        }
        return response.json();
      })
      .then((data) => {
        console.log("ID check response:", data);

        if (data.available) {
          feedback.innerHTML =
            '<i class="fas fa-check-circle"></i> ID available';
          feedback.className = "feedback success";
          this.showFieldSuccess(input);
        } else {
          feedback.innerHTML =
            '<i class="fas fa-times-circle"></i> ID already exists in database';
          feedback.className = "feedback error";
          this.showFieldError(input, "ID number already exists in database");
        }
      })
      .catch((error) => {
        console.error("Error checking ID:", error);
        // On error, just hide the feedback - don't show error message
        feedback.innerHTML = "";
        feedback.style.display = "none";
      });
  }

  checkUsernameRealTime(username) {
    const feedback = document.getElementById("username-feedback");
    const usernameInputEl = document.querySelector('input[name="username"]');
    if (!feedback) return;

    if (username.length < 3) {
      feedback.innerHTML = "";
      feedback.style.display = 'none';
      return;
    }

    // Track the last requested username to avoid stale responses updating UI
    this.lastUsernameQuery = username;

    feedback.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Checking username...';
    feedback.className = "feedback warning";

    fetch("check_username.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `username=${encodeURIComponent(username)}`,
    })
      .then((response) => response.json())
      .then((data) => {
        // Ignore if response is stale (input changed since request)
        const currentVal = (usernameInputEl && usernameInputEl.value || '').trim();
        if (currentVal !== this.lastUsernameQuery) {
          return;
        }
        const available = !!(data && ((data.available === true) || (data.exists === false)));
        if (available) {
          // Success: border-only, hide any feedback element
          feedback.innerHTML = '';
          feedback.style.display = 'none';
          if (usernameInputEl) {
            // Clear any field-error in the username group before showing success
            const group = usernameInputEl.closest('.form-group') || usernameInputEl.parentNode;
            if (group) {
              const errs = group.querySelectorAll('.field-error');
              errs.forEach(el => { el.style.display = 'none'; });
            }
            this.showFieldSuccess(usernameInputEl);
          }
        } else {
          // Error: use field-error only, hide feedback to avoid double rows
          if (usernameInputEl) {
            feedback.innerHTML = '';
            feedback.style.display = 'none';
            this.showFieldError(usernameInputEl, 'Username already exists');
          }
        }
      })
      .catch((error) => {
        feedback.innerHTML =
          '<i class="fas fa-exclamation-triangle"></i> Error checking username';
        feedback.className = "feedback error";
      });
  }

  cleanNameField(input) {
    // Do not block characters or auto-capitalize here.
    // Only trim leading spaces; keep internal multiple spaces for validation.
    let value = input.value;
    value = value.replace(/^\s+/, '');
    input.value = value;
  }

  formatID(input) {
    let value = input.value.replace(/\D/g, "");
    if (value.length > 4) {
      value = value.substring(0, 4) + "-" + value.substring(4, 8);
    }
    input.value = value;
  }

  calculateAge() {
    const birthdate = document.getElementById("birthdate");
    const ageDisplay = document.getElementById("age-display");

    if (!birthdate) return;

    if (birthdate.value && ageDisplay) {
      const age = this.calculateAgeFromDate(birthdate.value);
      ageDisplay.textContent = `${age} years old`;

      const isValid = age >= 18;
      ageDisplay.style.color = isValid ? "#28a745" : "#dc3545";
      birthdate.setCustomValidity(isValid ? "" : "Must be at least 18 years old");

      // Show error under the Age section
      const ageError = document.getElementById('age-error');
      if (ageError) {
        if (!isValid) {
          ageError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Must be at least 18 years old';
          ageError.style.display = 'block';
        } else {
          ageError.style.display = 'none';
          ageError.textContent = '';
        }
      }
      // Ensure no birthdate field-error is visible
      const birthErr = birthdate.parentNode && birthdate.parentNode.querySelector('.field-error');
      if (birthErr) birthErr.style.display = 'none';
    } else if (ageDisplay) {
      ageDisplay.textContent = "Enter birthdate";
      ageDisplay.style.color = "";
      birthdate.setCustomValidity("");
      const ageError = document.getElementById('age-error');
      if (ageError) {
        ageError.style.display = 'none';
        ageError.textContent = '';
      }
      const birthErr = birthdate.parentNode && birthdate.parentNode.querySelector('.field-error');
      if (birthErr) birthErr.style.display = 'none';
    }
  }

  calculateAgeFromDate(birthdate) {
    const today = new Date();
    const birthDate = new Date(birthdate);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (
      monthDiff < 0 ||
      (monthDiff === 0 && today.getDate() < birthDate.getDate())
    ) {
      age--;
    }
    return age;
  }

  validateCurrentStep() {
    const currentStepElement = document.querySelector(
      `.form-step[data-step="${this.currentStep}"]`
    );
    const inputs = currentStepElement.querySelectorAll("input, select");

    let isValid = true;
    let errorCount = 0;
    let firstInvalidField = null;

    this.clearStepError();

    // Hide server errors during client-side validation
    const serverErrors = document.getElementById("server-errors");
    if (serverErrors) {
      serverErrors.style.display = "none";
    }

    inputs.forEach((input) => {
      if (
        input.type === "submit" ||
        input.type === "button" ||
        input.type === "hidden"
      ) {
        return;
      }

      // Special handling for birthdate: show error in Age section only
      if (input.name === 'birthdate') {
        const age = this.calculateAgeFromDate(input.value);
        const underAge = isNaN(age) || age < 18;
        // keep native validity
        input.setCustomValidity(underAge ? 'Must be at least 18 years old' : '');
        // show only in Age error container
        const ageError = document.getElementById('age-error');
        if (ageError) {
          if (underAge) {
            ageError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Must be at least 18 years old';
            ageError.style.display = 'block';
          } else {
            ageError.style.display = 'none';
            ageError.textContent = '';
          }
        }
        if (underAge) {
          isValid = false;
          errorCount++;
          if (!firstInvalidField) firstInvalidField = input;
        } else {
          // do not show success/error styles on birthdate input itself
          this.clearFieldError(input);
        }
        return; // skip default field handling for birthdate
      }

      const fieldValidation = this.validateField(input);
      if (!fieldValidation.isValid) {
        isValid = false;
        errorCount++;
        if (!firstInvalidField) {
          firstInvalidField = input;
        }
        this.showFieldError(input, fieldValidation.errors);
      } else {
        this.clearFieldError(input);
        this.showFieldSuccess(input);
      }
    });

    if (!isValid) {
      this.showStepError(
        `Please correct the ${errorCount} error${
          errorCount > 1 ? "s" : ""
        } before proceeding to the next step.`
      );

      if (firstInvalidField) {
        firstInvalidField.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
        firstInvalidField.focus();
      }
    } else {
      this.clearStepError();
    }

    return isValid;
  }

  validateSingleField(input) {
    // Birthdate is handled via Age section only
    if (input && input.name === 'birthdate') {
      this.calculateAge();
      // Do not attach a field-error to the birthdate input itself
      return input.checkValidity();
    }

    const fieldValidation = this.validateField(input);
    if (!fieldValidation.isValid) {
      this.showFieldError(input, fieldValidation.errors);
    } else {
      this.clearFieldError(input);
      this.showFieldSuccess(input);
    }
    return fieldValidation.isValid;
  }

  validateField(input) {
    const raw = input.value;
    const value = raw.trim();
    const fieldName = this.getFieldDisplayName(input.name);
    const errors = [];

    if (!input.hasAttribute("required") && value === "") {
      return { isValid: true, errors: [] };
    }

    if (input.hasAttribute("required") && !value) {
      errors.push(`${fieldName} is required`);
      return { isValid: false, errors: errors };
    }

    // Birthdate handled separately in validateCurrentStep/calculateAge

    const tripleRepeat = /([a-z])\1\1/i;
    if (["first_name", "middle_name", "last_name"].includes(input.name)) {
      // Ordered, specific validations for names
      const hasDigit = /\d/.test(value);
      const hasLetter = /[A-Za-z]/.test(value);
      const hasSpecial = /[^A-Za-z0-9\s]/.test(value);

      // 1) Special characters only
      if (hasSpecial) {
        errors.push('Special characters are not allowed');
      }
      // 2) Mixed numbers and letters
      if (hasDigit && hasLetter) {
        errors.push('Numbers and letters cannot be mixed together');
      }
      // 3) Numbers present (and not mixed already covered)
      if (hasDigit && !hasLetter) {
        errors.push('Numbers are not allowed');
      }
      // 4) Double spaces (use raw value to detect spacing inside)
      if (/\s{2,}/.test(raw)) {
        errors.push('Double spaces are not allowed');
      }
      // 5) Three consecutive identical letters
      if (tripleRepeat.test(value)) {
        errors.push('Three consecutive identical letters are not allowed');
      }
      // 6) All caps not allowed (but allow a single initial uppercase letter)
      if (/^[A-Z\s]+$/.test(value) && /[A-Z].*[A-Z]/.test(value)) {
        errors.push('All capital letters are not allowed');
      }
      // 7) Capitalization rule: require first letter uppercase
      if (!/^[A-Z]/.test(value)) {
        errors.push('First letter must be capital');
      }
    }
    if (input.name === 'username') {
      if (tripleRepeat.test(value)) {
        errors.push(`${fieldName}: no three consecutive identical letters`);
      }
    }

    // Email-specific live validation
    if (input.name === 'email') {
      if (/\s/.test(value)) {
        errors.push('Email cannot contain spaces');
      } else if (value.indexOf('@') === -1) {
        errors.push("Email must contain '@'");
      } else {
        const parts = value.split('@');
        if (parts.length !== 2 || parts[1].indexOf('.') === -1) {
          errors.push("Email domain must contain '.'");
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
          errors.push('Please enter a valid email address');
        }
      }
    }

    // Return all collected errors so multiple issues can be shown at once
    return {
      isValid: errors.length === 0,
      errors: errors,
    };
  }

  validatePasswordStrength() {
    const passwordInput = document.getElementById("password");
    const strengthDiv = document.getElementById("password-strength");

    if (!passwordInput || !strengthDiv) return;

    const password = passwordInput.value;
    const result = this.analyzePasswordStrength(password);

    if (password.length === 0) {
      strengthDiv.style.display = "none";
      return;
    }

    strengthDiv.textContent = `Password strength: ${result.level}`;
    strengthDiv.className = `password-strength strength-${result.level}`;
    strengthDiv.style.display = "block";

    if (result.level === "weak") {
      strengthDiv.style.color = "#dc3545";
    } else if (result.level === "medium") {
      strengthDiv.style.color = "#ffc107";
    } else {
      strengthDiv.style.color = "#28a745";
    }
  }

  validatePasswordMatch() {
    const password = document.querySelector('input[name="password"]').value;
    const confirmPassword = document.querySelector(
      'input[name="confirm_password"]'
    ).value;
    const confirmInput = document.querySelector(
      'input[name="confirm_password"]'
    );
    const matchDiv = document.getElementById('password-match');

    if (confirmPassword === "") {
      if (matchDiv) {
        matchDiv.style.display = 'none';
        matchDiv.textContent = '';
      }
      return;
    }

    const isValid = password === confirmPassword;
    const result = {
      isValid: isValid,
      errors: isValid ? [] : ["Passwords do not match"],
    };

    if (!result.isValid) {
      // Show only one error source: use field-error, hide matchDiv
      this.showFieldError(confirmInput, result.errors[0]);
      if (matchDiv) {
        matchDiv.textContent = '';
        matchDiv.style.display = 'none';
      }
    } else {
      this.clearFieldError(confirmInput);
      this.showFieldSuccess(confirmInput);
      if (matchDiv) {
        matchDiv.textContent = 'Passwords match';
        matchDiv.style.color = '#28a745';
        matchDiv.style.display = 'block';
      }
    }
  }

  validateSecurityAnswerMatch(questionNumber) {
    const answer = document.querySelector(
      `input[name="security_answer${questionNumber}"]`
    ).value;
    const answerRepeat = document.querySelector(
      `input[name="security_answer${questionNumber}_repeat"]`
    );
    const repeatValue = answerRepeat.value;

    if (repeatValue === "") return;

    const isValid = answer === repeatValue;
    const result = {
      isValid: isValid,
      errors: isValid ? [] : ["Security answers do not match"],
    };

    if (!result.isValid) {
      this.showFieldError(answerRepeat, result.errors[0]);
    } else {
      this.clearFieldError(answerRepeat);
      this.showFieldSuccess(answerRepeat);
    }
  }

  analyzePasswordStrength(password) {
    let strength = 0;
    let feedback = [];

    if (password.length >= 8) strength++;
    else feedback.push("At least 8 characters");

    if (/[A-Z]/.test(password)) strength++;
    else feedback.push("One uppercase letter");

    if (/[a-z]/.test(password)) strength++;
    else feedback.push("One lowercase letter");

    if (/[0-9]/.test(password)) strength++;
    else feedback.push("One number");

    if (/[^A-Za-z0-9]/.test(password)) strength++;
    else feedback.push("One special character");

    let level = "weak";
    if (strength >= 4) level = "strong";
    else if (strength >= 3) level = "medium";

    return { level, feedback };
  }

  getFieldDisplayName(fieldName) {
    const names = {
      first_name: "First Name",
      last_name: "Last Name",
      middle_name: "Middle Name",
      extension_name: "Extension Name",
      id_number: "ID Number",
      birthdate: "Birthdate",
      email: "Email Address",
      sex: "Sex",
      purok_street: "Purok/Street",
      barangay: "Barangay",
      city_municipality: "City/Municipality",
      province: "Province",
      country: "Country",
      zip_code: "Zip Code",
      username: "Username",
      password: "Password",
      confirm_password: "Confirm Password",
    };
    return names[fieldName] || fieldName.replace(/_/g, " ");
  }

  showFieldError(input, messages) {
    // Do not show field-error under the Birthdate input; Age section handles it
    if (input && input.name === 'birthdate') {
      return;
    }
    input.classList.remove("valid-field");
    input.classList.add("error-field");

    // Scope to closest .form-group to avoid stacking multiple messages
    const group = input.closest('.form-group') || input.parentNode;
    if (group && group.classList) {
      group.classList.add('has-error');
    }
    // Ensure we have a single error container per group
    let errorDiv = group.querySelector('.field-error');
    if (!errorDiv) {
      errorDiv = document.createElement('div');
      errorDiv.className = 'field-error';
      const wrapper = input.parentNode && input.parentNode.classList && input.parentNode.classList.contains('input-with-icon')
        ? input.parentNode
        : group;
      wrapper.appendChild(errorDiv);
    }

    // Hide any feedback within this group to avoid double overlays (e.g., username availability)
    const feedbackEls = group.querySelectorAll('.feedback, #username-feedback');
    feedbackEls.forEach(el => { el.innerHTML = ''; el.style.display = 'none'; });

    // Normalize messages to an array, de-duplicate, and sort by fixed priority
    const list = Array.isArray(messages) ? messages : [messages];
    const unique = Array.from(new Set(list.filter(Boolean)));
    const priority = [
      'Special characters are not allowed',
      'Numbers and letters cannot be mixed together',
      'Numbers are not allowed',
      'Double spaces are not allowed',
      'Three consecutive identical letters are not allowed',
      'All capital letters are not allowed',
      'First letter must be capital'
    ];
    const idx = (m) => {
      const i = priority.indexOf(m);
      return i === -1 ? Number.MAX_SAFE_INTEGER : i;
    };
    unique.sort((a,b) => idx(a) - idx(b));
    if (unique.length <= 1) {
      if (errorDiv.classList) errorDiv.classList.remove('multiple');
      // Ensure the error bubble is positioned under the input wrapper for single errors
      const wrapper = input.parentNode && input.parentNode.classList && input.parentNode.classList.contains('input-with-icon')
        ? input.parentNode
        : group;
      if (errorDiv.parentNode !== wrapper) {
        errorDiv.parentNode && errorDiv.parentNode.removeChild(errorDiv);
        wrapper.appendChild(errorDiv);
      }
      const msg = unique[0] || 'Invalid input';
      errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${msg}`;
    } else {
      // Ensure error container lives under the .form-group (not inside input-with-icon)
      if (errorDiv.parentNode && errorDiv.parentNode !== group) {
        errorDiv.parentNode.removeChild(errorDiv);
        group.appendChild(errorDiv);
      }
      if (errorDiv.classList) errorDiv.classList.add('multiple');
      const items = unique.map(m => `<li>${m}</li>`).join('');
      errorDiv.innerHTML = `<ul class="field-error-list">${items}</ul>`;
    }
    errorDiv.style.display = "block";

    input.style.borderColor = "#dc3545";
    input.style.boxShadow = "0 0 0 2px rgba(220, 53, 69, 0.1)";
  }

  showFieldSuccess(input) {
    input.classList.remove("error-field");
    input.classList.add("valid-field");

    // Group-scoped cleanup: hide/remove error nodes and feedback in this field's group
    const group = input.closest('.form-group') || input.parentNode;
    if (group) {
      const errs = group.querySelectorAll('.field-error');
      errs.forEach((el, idx) => { if (idx > 0) el.remove(); else el.style.display = 'none'; });
      const fbs = group.querySelectorAll('.feedback, #username-feedback');
      fbs.forEach(el => { el.innerHTML = ''; el.style.display = 'none'; });
      if (group.classList) {
        group.classList.remove('has-error');
      }
    }

    input.style.borderColor = "#28a745";
    input.style.boxShadow = "0 0 0 2px rgba(40, 167, 69, 0.1)";
  }

  clearFieldError(input) {
    input.classList.remove("error-field", "valid-field");
    input.style.borderColor = "";
    input.style.boxShadow = "";

    // Group-scoped cleanup: hide/remove error nodes and feedback in this field's group
    const group = input.closest('.form-group') || input.parentNode;
    if (group) {
      const errs = group.querySelectorAll('.field-error');
      errs.forEach((el, idx) => { if (idx > 0) el.remove(); else el.style.display = 'none'; });
      const fbs = group.querySelectorAll('.feedback, #username-feedback');
      fbs.forEach(el => { el.innerHTML = ''; el.style.display = 'none'; });
      if (group.classList) {
        group.classList.remove('has-error');
      }
    }
  }

  showStepError(message) {
    this.clearStepError();

    const currentStepElement = document.querySelector(
      `.form-step[data-step="${this.currentStep}"]`
    );
    const errorDiv = document.createElement("div");
    errorDiv.className = "step-error-message";
    errorDiv.innerHTML = `
      <div class="error-alert">
        <i class="fas fa-exclamation-triangle"></i>
        <span>${message}</span>
      </div>
    `;

    const progressSteps = document.querySelector(".progress-steps");
    if (progressSteps && currentStepElement.contains(progressSteps)) {
      progressSteps.parentNode.insertBefore(
        errorDiv,
        progressSteps.nextSibling
      );
    } else {
      currentStepElement.insertBefore(errorDiv, currentStepElement.firstChild);
    }
  }

  clearStepError() {
    const existingError = document.querySelector(".step-error-message");
    if (existingError) {
      existingError.remove();
    }
  }

  validateAllSteps() {
    let allValid = true;

    for (let step = 1; step <= this.totalSteps; step++) {
      this.currentStep = step;
      if (!this.validateCurrentStep()) {
        allValid = false;
        break;
      }
    }

    return allValid;
  }

  goToStep(step) {
    const currentStepElement = document.querySelector(
      `.form-step[data-step="${this.currentStep}"]`
    );
    const currentStepIndicator = document.querySelector(
      `.step[data-step="${this.currentStep}"]`
    );

    if (currentStepElement) currentStepElement.classList.remove("active");
    if (currentStepIndicator) currentStepIndicator.classList.remove("active");

    const newStepElement = document.querySelector(
      `.form-step[data-step="${step}"]`
    );
    const newStepIndicator = document.querySelector(
      `.step[data-step="${step}"]`
    );

    if (newStepElement) newStepElement.classList.add("active");
    if (newStepIndicator) newStepIndicator.classList.add("active");

    for (let i = 1; i < step; i++) {
      const stepElement = document.querySelector(`.step[data-step="${i}"]`);
      if (stepElement) {
        stepElement.classList.add("completed");
      }
    }

    for (let i = step + 1; i <= this.totalSteps; i++) {
      const stepElement = document.querySelector(`.step[data-step="${i}"]`);
      if (stepElement) {
        stepElement.classList.remove("completed");
      }
    }

    this.currentStep = step;
    this.updateProgressBar();
    this.clearStepError();
  }

  updateProgressBar() {
    const progress = ((this.currentStep - 1) / (this.totalSteps - 1)) * 100;
    const progressFill = document.querySelector(".progress-fill");
    if (progressFill) {
      progressFill.style.width = `${progress}%`;
    }
  }
}

// Global functions for HTML inline event handlers
window.formatID = function (input) {
  const form = new MultiStepForm();
  form.formatID(input);
};

window.validateName = function (input, fieldName) {
  const form = new MultiStepForm();
  form.validateSingleField(input);
};

window.validateExtension = function (input) {
  const form = new MultiStepForm();
  form.validateSingleField(input);
};

window.validateEmail = function (input) {
  const form = new MultiStepForm();
  form.validateSingleField(input);
};

window.validateAddress = function (input, fieldName) {
  const form = new MultiStepForm();
  form.validateSingleField(input);
};

window.validateZipCode = function (input) {
  const form = new MultiStepForm();
  form.validateSingleField(input);
};

window.validatePasswordStrength = function () {
  const form = new MultiStepForm();
  form.validatePasswordStrength();
};

window.validatePasswordMatch = function () {
  const form = new MultiStepForm();
  form.validatePasswordMatch();
};

window.validateSecurityAnswerMatch = function (questionNumber) {
  const form = new MultiStepForm();
  form.validateSecurityAnswerMatch(questionNumber);
};

window.checkUsername = function (username) {
  const form = new MultiStepForm();
  form.checkUsernameRealTime(username);
};

window.calculateAge = function () {
  const form = new MultiStepForm();
  form.calculateAge();
};

window.toggleExtensionOther = function (selectEl) {
  const wrapper = document.getElementById("extension-other-wrapper");
  const input = document.querySelector('input[name="extension_other"]');
  if (!wrapper || !input) return;

  const show = selectEl && selectEl.value.toLowerCase() === "other";
  wrapper.style.display = show ? "block" : "none";
  if (show) {
    input.setAttribute("required", "required");
    input.addEventListener("input", () => {
      const form = new MultiStepForm();
      form.validateSingleField(input);
    }, { once: true });
  } else {
    input.removeAttribute("required");
    input.value = input.value; // keep value if typed; server ignores when not Other
  }
};

window.togglePassword = function (fieldId) {
  const passwordInput = document.getElementById(fieldId);
  const passwordIcon = document.getElementById(fieldId + "-icon");

  if (!passwordInput || !passwordIcon) return;

  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    passwordIcon.className = "fas fa-eye-slash";
  } else {
    passwordInput.type = "password";
    passwordIcon.className = "fas fa-eye";
  }
  return false;
};

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  new MultiStepForm();
});

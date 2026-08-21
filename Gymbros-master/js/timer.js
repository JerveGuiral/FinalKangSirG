// Login attempt lockout timer functionality with full persistence
class LoginTimer {
  constructor() {
    this.lockoutTime =
      parseInt(document.getElementById("lockout_time")?.value) || 0;
    this.loginAttempts =
      parseInt(document.getElementById("login_attempts")?.value) || 0;
    this.username =
      document.querySelector('input[name="username"]')?.value || "";
    this.timerInterval = null;

    console.log("Timer initialized:", {
      lockoutTime: this.lockoutTime,
      attempts: this.loginAttempts,
      username: this.username,
    });

    this.init();
  }

  init() {
    // First, restore form inputs from localStorage
    this.restoreFormInputs();

    // NEW: Only start timer if we're at a threshold point AND have lockout time
    if (this.lockoutTime > 0 && this.shouldShowLockout()) {
      console.log(
        "Starting timer from PHP data (threshold reached):",
        this.lockoutTime
      );
      this.saveLockoutState();
      this.startTimer();
      this.disableForm();
    } else {
      console.log(
        "No threshold lockout active. Consecutive attempts:",
        this.loginAttempts,
        "Lockout time:",
        this.lockoutTime
      );
      this.checkPersistentLockout();
    }
  }

  // NEW METHOD: Check if we should show lockout based on attempt count
  shouldShowLockout() {
    return (
      this.loginAttempts === 3 ||
      this.loginAttempts === 6 ||
      this.loginAttempts === 9
    );
  }

  saveLockoutState() {
    if (this.lockoutTime > 0 && this.username && this.shouldShowLockout()) {
      const lockoutData = {
        username: this.username,
        lockout_until: Math.floor(Date.now() / 1000) + this.lockoutTime,
        attempts: this.loginAttempts,
        timestamp: new Date().toISOString(),
      };
      localStorage.setItem("user_lockout", JSON.stringify(lockoutData));
      console.log("Lockout state saved to localStorage:", lockoutData);
    }
  }

  restoreFormInputs() {
    // Restore username from localStorage
    const savedUsername = localStorage.getItem("login_username");
    const usernameInput = document.querySelector('input[name="username"]');

    if (usernameInput && savedUsername) {
      console.log("Restored username from localStorage:", savedUsername);
      usernameInput.value = savedUsername;
      this.username = savedUsername;
    }
  }

  checkPersistentLockout() {
    const storedLockout = localStorage.getItem("user_lockout");
    if (storedLockout) {
      try {
        const lockoutData = JSON.parse(storedLockout);
        const now = Math.floor(Date.now() / 1000);

        console.log("Found stored lockout:", lockoutData);

        // NEW: Only restore if we were at a threshold and lockout is still active
        if (lockoutData.lockout_until > now && this.shouldShowLockout()) {
          const remainingTime = lockoutData.lockout_until - now;
          console.log(
            "Threshold lockout still active, remaining time:",
            remainingTime
          );

          this.lockoutTime = remainingTime;
          this.loginAttempts = lockoutData.attempts || 0;
          this.username = lockoutData.username || this.username;

          // Update the hidden fields to reflect the actual state
          const lockoutTimeInput = document.getElementById("lockout_time");
          const loginAttemptsInput = document.getElementById("login_attempts");
          if (lockoutTimeInput) lockoutTimeInput.value = this.lockoutTime;
          if (loginAttemptsInput) loginAttemptsInput.value = this.loginAttempts;

          this.startTimer();
          this.disableForm();
        } else {
          console.log("Lockout expired or not at threshold, clearing storage");
          localStorage.removeItem("user_lockout");
          this.clearFormInputs();
        }
      } catch (e) {
        console.error("Error parsing stored lockout:", e);
        localStorage.removeItem("user_lockout");
      }
    } else {
      console.log("No stored lockout found in localStorage");
    }
  }

  startTimer() {
    let timeLeft = this.lockoutTime;
    const countdownElement = document.getElementById("timer");
    const loginText = document.getElementById("login-text");
    const countdownContainer = document.getElementById("countdown");
    const loginBtn = document.getElementById("login-btn");
    const registerLink = document.getElementById("register-link");
    const forgotPasswordLink = document.getElementById("forgot-password-link");

    if (countdownElement && loginText && countdownContainer) {
      console.log("Starting timer with", timeLeft, "seconds");

      loginText.style.display = "none";
      countdownContainer.style.display = "inline";

      this.updateTimerDisplay(timeLeft, countdownElement);

      // Store lockout in localStorage for persistence
      const lockoutData = {
        username: this.username,
        lockout_until: Math.floor(Date.now() / 1000) + timeLeft,
        attempts: this.loginAttempts,
        timestamp: new Date().toISOString(),
      };
      localStorage.setItem("user_lockout", JSON.stringify(lockoutData));
      console.log("Saved lockout to localStorage:", lockoutData);

      this.timerInterval = setInterval(() => {
        timeLeft--;
        this.updateTimerDisplay(timeLeft, countdownElement);

        // Update localStorage every second to keep it current
        if (timeLeft > 0) {
          const updatedLockoutData = {
            username: this.username,
            lockout_until: Math.floor(Date.now() / 1000) + timeLeft,
            attempts: this.loginAttempts,
            timestamp: new Date().toISOString(),
          };
          localStorage.setItem(
            "user_lockout",
            JSON.stringify(updatedLockoutData)
          );
        }

        if (timeLeft <= 0) {
          console.log("Timer finished");
          this.stopTimer();
          this.enableForm();
          loginText.style.display = "inline";
          countdownContainer.style.display = "none";

          // Clear localStorage
          localStorage.removeItem("user_lockout");
          console.log("Cleared lockout from localStorage");

          if (loginBtn) loginBtn.disabled = false;
          if (registerLink) registerLink.style.pointerEvents = "auto";
          if (forgotPasswordLink)
            forgotPasswordLink.style.pointerEvents = "auto";
        }
      }, 1000);
    } else {
      console.error("Timer elements not found!");
    }
  }

  stopTimer() {
    if (this.timerInterval) {
      clearInterval(this.timerInterval);
      this.timerInterval = null;
    }
  }

  updateTimerDisplay(time, element) {
    if (element) {
      element.textContent = time;
    }
  }

  disableForm() {
    console.log("Disabling form");
    const loginBtn = document.getElementById("login-btn");
    const registerLink = document.getElementById("register-link");
    const forgotPasswordLink = document.getElementById("forgot-password-link");
    const formInputs = document.querySelectorAll(
      '#login-form input[type="text"], #login-form input[type="password"]'
    );

    if (loginBtn) loginBtn.disabled = true;
    if (registerLink) registerLink.style.pointerEvents = "none";
    if (forgotPasswordLink) forgotPasswordLink.style.pointerEvents = "none";

    formInputs.forEach((input) => {
      input.readOnly = true;
    });
  }

  enableForm() {
    console.log("Enabling form");
    const loginBtn = document.getElementById("login-btn");
    const registerLink = document.getElementById("register-link");
    const forgotPasswordLink = document.getElementById("forgot-password-link");
    const formInputs = document.querySelectorAll(
      '#login-form input[type="text"], #login-form input[type="password"]'
    );

    if (loginBtn) loginBtn.disabled = false;
    if (registerLink) registerLink.style.pointerEvents = "auto";
    if (forgotPasswordLink) forgotPasswordLink.style.pointerEvents = "auto";

    formInputs.forEach((input) => {
      input.readOnly = false;
    });
  }

  clearFormInputs() {
    localStorage.removeItem("login_username");
    console.log("Cleared form inputs from localStorage");
  }
}

// Initialize when page loads
document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM loaded, initializing login timer...");
  const loginTimer = new LoginTimer();

  // Save username as user types
  const usernameInput = document.querySelector('input[name="username"]');
  if (usernameInput) {
    usernameInput.addEventListener("input", function () {
      localStorage.setItem("login_username", this.value);
      console.log("Saved username to localStorage:", this.value);
    });
  }

  // Clear everything on successful form submission
  const loginForm = document.getElementById("login-form");
  if (loginForm) {
    loginForm.addEventListener("submit", function (e) {
      console.log("Form submitted, clearing localStorage");
      localStorage.removeItem("login_username");
      localStorage.removeItem("user_lockout");
    });
  }
});

// Also save username when page is about to unload (as backup)
window.addEventListener("beforeunload", function () {
  const usernameInput = document.querySelector('input[name="username"]');
  if (usernameInput && usernameInput.value) {
    localStorage.setItem("login_username", usernameInput.value);
  }
});

/**
 * GymBros User Dashboard Client-Side Logic
 */

document.addEventListener('DOMContentLoaded', () => {
  initTabs();
  initModalCloseHandlers();
  initBMILiveCalculator();
});

// Toast notification helper
function showToast(message, isSuccess = true) {
  let toast = document.getElementById('dashToast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'dashToast';
    toast.className = 'dash-toast';
    document.body.appendChild(toast);
  }

  const iconClass = isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle';
  const color = isSuccess ? '#10b981' : '#ef4444';
  
  toast.innerHTML = `<i class="fas ${iconClass}" style="color: ${color}; font-size: 18px;"></i> <span>${message}</span>`;
  toast.style.borderColor = color;
  toast.classList.add('show');

  setTimeout(() => {
    toast.classList.remove('show');
  }, 4000);
}

// CSRF Token Helper
function getCSRFToken() {
  const tokenEl = document.getElementById('csrf_token_val');
  return tokenEl ? tokenEl.value : '';
}

// Tab Switching System
function initTabs() {
  const tabBtns = document.querySelectorAll('.dashboard-tab-btn');
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetTab = btn.getAttribute('data-tab');
      switchDashboardTab(targetTab);
    });
  });
}

function switchDashboardTab(tabId) {
  // Update Buttons
  document.querySelectorAll('.dashboard-tab-btn').forEach(btn => {
    if (btn.getAttribute('data-tab') === tabId) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });

  // Update Panes
  document.querySelectorAll('.tab-pane').forEach(pane => {
    if (pane.id === tabId) {
      pane.classList.add('active');
    } else {
      pane.classList.remove('active');
    }
  });
}

// Modal Control
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function initModalCloseHandlers() {
  document.querySelectorAll('.modal-close-btn, .btn-modal-cancel').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const modal = e.target.closest('.modal-overlay');
      if (modal) closeModal(modal.id);
    });
  });

  document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        closeModal(modal.id);
      }
    });
  });
}

// ----------------------------------------------------
// 1. WORKOUT LOGGER
// ----------------------------------------------------
async function submitLogWorkoutForm(e) {
  e.preventDefault();
  const form = e.target;
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalBtnText = submitBtn.innerHTML;

  const data = {
    action: 'log_workout',
    csrf_token: getCSRFToken(),
    workout_name: document.getElementById('log-workout-name').value.trim(),
    muscle_group: document.getElementById('log-muscle-group').value,
    duration_minutes: document.getElementById('log-duration').value,
    calories_burned: document.getElementById('log-calories').value,
    sets_count: document.getElementById('log-sets').value,
    reps_count: document.getElementById('log-reps').value,
    weight_lifted: document.getElementById('log-weight').value,
    workout_date: document.getElementById('log-date').value,
    notes: document.getElementById('log-notes').value.trim()
  };

  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  try {
    const res = await fetch('user_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await res.json();

    if (result.success) {
      showToast(result.message, true);
      closeModal('modal-log-workout');
      form.reset();
      
      // Update stats on dashboard
      if (result.stats) {
        updateStatsUI(result.stats);
      }

      // Prepend to workout table or reload list
      reloadWorkoutTable();
    } else {
      showToast(result.message || 'Error recording workout', false);
    }
  } catch (err) {
    showToast('Network error while saving workout.', false);
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalBtnText;
  }
}

async function deleteWorkout(workoutId) {
  if (!confirm('Are you sure you want to remove this workout record?')) return;

  try {
    const res = await fetch('user_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'delete_workout',
        csrf_token: getCSRFToken(),
        workout_id: workoutId
      })
    });
    const result = await res.json();
    if (result.success) {
      showToast(result.message, true);
      const row = document.getElementById(`workout-row-${workoutId}`);
      if (row) row.remove();
      if (result.stats) {
        updateStatsUI(result.stats);
      }
    } else {
      showToast(result.message, false);
    }
  } catch (err) {
    showToast('Failed to delete workout entry.', false);
  }
}

async function reloadWorkoutTable() {
  try {
    const res = await fetch('user_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'get_workouts',
        csrf_token: getCSRFToken()
      })
    });
    const result = await res.json();
    if (result.success && result.workouts) {
      renderWorkoutTable(result.workouts);
    }
  } catch (err) {
    console.error(err);
  }
}

function renderWorkoutTable(workouts) {
  const tbody = document.getElementById('workoutTableBody');
  if (!tbody) return;

  if (workouts.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:#94a3b8; padding:30px;"><i class="fas fa-dumbbell" style="font-size:24px; margin-bottom:10px; display:block; color:#64748b;"></i>No workouts logged yet. Click "Log Workout" to start!</td></tr>`;
    return;
  }

  tbody.innerHTML = workouts.map(w => `
    <tr id="workout-row-${w.id}">
      <td><strong>${escapeHtml(w.workout_date)}</strong></td>
      <td><strong>${escapeHtml(w.workout_name)}</strong></td>
      <td><span class="muscle-badge">${escapeHtml(w.muscle_group)}</span></td>
      <td>${escapeHtml(w.sets_count)} sets × ${escapeHtml(w.reps_count)} reps</td>
      <td>${escapeHtml(w.duration_minutes)} mins</td>
      <td><span style="color:#f87171; font-weight:600;">🔥 ${escapeHtml(w.calories_burned)} kcal</span></td>
      <td>
        <button class="btn-del-workout" onclick="deleteWorkout(${w.id})" title="Delete entry">
          <i class="fas fa-trash"></i>
        </button>
      </td>
    </tr>
  `).join('');
}

// ----------------------------------------------------
// 2. GYM CLASS BOOKINGS
// ----------------------------------------------------
function openBookClassModal(classId, className, instructor, scheduleDay, startTime, endTime) {
  document.getElementById('book-class-id').value = classId;
  document.getElementById('book-class-name-display').innerText = className;
  document.getElementById('book-class-instructor-display').innerText = instructor;
  document.getElementById('book-class-schedule-display').innerText = `${scheduleDay} • ${startTime} - ${endTime}`;
  
  // Set default booking date to today
  const today = new Date().toISOString().split('T')[0];
  const dateInput = document.getElementById('book-class-date');
  dateInput.value = today;
  dateInput.min = today;

  openModal('modal-book-class');
}

async function submitBookClassForm(e) {
  e.preventDefault();
  const form = e.target;
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalBtnText = submitBtn.innerHTML;

  const classId = document.getElementById('book-class-id').value;
  const bookingDate = document.getElementById('book-class-date').value;

  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Confirming...';

  try {
    const res = await fetch('user_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'book_class',
        csrf_token: getCSRFToken(),
        class_id: classId,
        booking_date: bookingDate
      })
    });
    const result = await res.json();

    if (result.success) {
      showToast(result.message, true);
      closeModal('modal-book-class');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(result.message || 'Could not complete booking', false);
    }
  } catch (err) {
    showToast('Network error while booking class.', false);
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalBtnText;
  }
}

async function cancelBooking(bookingId) {
  if (!confirm('Are you sure you want to cancel this class booking?')) return;

  try {
    const res = await fetch('user_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'cancel_booking',
        csrf_token: getCSRFToken(),
        booking_id: bookingId
      })
    });
    const result = await res.json();
    if (result.success) {
      showToast(result.message, true);
      const card = document.getElementById(`booking-card-${bookingId}`);
      if (card) card.remove();
      setTimeout(() => location.reload(), 800);
    } else {
      showToast(result.message, false);
    }
  } catch (err) {
    showToast('Failed to cancel booking.', false);
  }
}

// ----------------------------------------------------
// 3. BMI & BODY METRICS
// ----------------------------------------------------
function initBMILiveCalculator() {
  const weightInput = document.getElementById('metric-weight');
  const heightInput = document.getElementById('metric-height');
  const livePreview = document.getElementById('bmi-live-preview');

  function calculateLive() {
    const w = parseFloat(weightInput?.value);
    const h = parseFloat(heightInput?.value);
    if (w > 0 && h > 0 && livePreview) {
      const hm = h / 100;
      const bmi = (w / (hm * hm)).toFixed(1);
      let cat = 'Normal';
      let color = '#10b981';
      if (bmi < 18.5) { cat = 'Underweight'; color = '#3b82f6'; }
      else if (bmi < 25) { cat = 'Normal Weight'; color = '#10b981'; }
      else if (bmi < 30) { cat = 'Overweight'; color = '#f59e0b'; }
      else { cat = 'Obese'; color = '#ef4444'; }

      livePreview.innerHTML = `Computed BMI: <strong style="color:${color}">${bmi} (${cat})</strong>`;
    }
  }

  if (weightInput) weightInput.addEventListener('input', calculateLive);
  if (heightInput) heightInput.addEventListener('input', calculateLive);
}

async function submitBodyMetricsForm(e) {
  e.preventDefault();
  const form = e.target;
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalBtnText = submitBtn.innerHTML;

  const data = {
    action: 'log_body_metric',
    csrf_token: getCSRFToken(),
    weight_kg: document.getElementById('metric-weight').value,
    height_cm: document.getElementById('metric-height').value,
    target_weight_kg: document.getElementById('metric-target-weight').value,
    fitness_goal: document.getElementById('metric-fitness-goal').value,
    body_fat_percentage: document.getElementById('metric-body-fat').value,
    notes: document.getElementById('metric-notes').value.trim()
  };

  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculating & Saving...';

  try {
    const res = await fetch('user_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await res.json();

    if (result.success) {
      showToast(result.message, true);
      closeModal('modal-log-metric');

      // Update BMI display
      const bmiVal = document.getElementById('bmi-display-num');
      if (bmiVal) bmiVal.innerText = result.bmi;
      const bmiStatPill = document.getElementById('stat-bmi-num');
      if (bmiStatPill) bmiStatPill.innerText = result.bmi;
      const bmiCatPill = document.getElementById('bmi-category-badge');
      if (bmiCatPill) {
        bmiCatPill.innerText = result.category;
      }
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(result.message || 'Error recording body metrics', false);
    }
  } catch (err) {
    showToast('Network error while updating metrics.', false);
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalBtnText;
  }
}

// ----------------------------------------------------
// 4. PROFILE & SETTINGS
// ----------------------------------------------------
async function submitProfileForm(e) {
  e.preventDefault();
  const form = e.target;
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalBtnText = submitBtn.innerHTML;

  const data = {
    action: 'update_profile',
    csrf_token: getCSRFToken(),
    phone_number: document.getElementById('prof-phone').value.trim(),
    fitness_goal: document.getElementById('prof-fitness-goal').value,
    bio: document.getElementById('prof-bio').value.trim(),
    purok_street: document.getElementById('prof-purok').value.trim(),
    barangay: document.getElementById('prof-barangay').value.trim(),
    city_municipality: document.getElementById('prof-city').value.trim(),
    province: document.getElementById('prof-province').value.trim(),
    zip_code: document.getElementById('prof-zip').value.trim()
  };

  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  try {
    const res = await fetch('user_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await res.json();

    if (result.success) {
      showToast(result.message, true);
      closeModal('modal-edit-profile');
      setTimeout(() => location.reload(), 800);
    } else {
      showToast(result.message || 'Failed to update profile', false);
    }
  } catch (err) {
    showToast('Network error while saving profile.', false);
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalBtnText;
  }
}

// Helper to update dynamic counters on UI
function updateStatsUI(stats) {
  const workoutCountEl = document.getElementById('stat-workout-count');
  if (workoutCountEl) workoutCountEl.innerText = stats.total_workouts;

  const calEl = document.getElementById('stat-calories-total');
  if (calEl) calEl.innerText = Number(stats.total_calories).toLocaleString() + ' kcal';

  const streakEl = document.getElementById('stat-streak-days');
  if (streakEl) streakEl.innerText = stats.streak_days + ' days';
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

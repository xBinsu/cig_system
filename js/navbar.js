// Notification panel toggle
function toggleNotificationPanel() {
  let panel = document.getElementById("notificationPanel");
  if (panel) {
    panel.classList.toggle("show");
  }
}

// Close notification panel when clicking outside
document.addEventListener('click', function(e) {
  let panel = document.getElementById("notificationPanel");
  let bell = document.querySelector(".notification-bell");
  if (panel && !panel.contains(e.target) && !bell.contains(e.target)) {
    panel.classList.remove("show");
  }
});

// Show logout confirmation modal
function showLogoutModal() {
  const modal = document.getElementById('logoutModal');
  if (modal) {
    modal.style.display = 'flex';
  }
}

// Cancel logout
function cancelLogout() {
  const modal = document.getElementById('logoutModal');
  if (modal) {
    modal.style.display = 'none';
  }
}

// Confirm logout
function confirmLogout() {
  // Clear session/localStorage if needed
  localStorage.clear();
  sessionStorage.clear();
  // Redirect to logout page
  window.location.href = 'logout.php';
}

// Close modal when clicking outside of it
document.addEventListener('click', function(e) {
  const modal = document.getElementById('logoutModal');
  if (modal && e.target === modal) {
    modal.style.display = 'none';
  }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const modal = document.getElementById('logoutModal');
    if (modal && modal.style.display !== 'none') {
      modal.style.display = 'none';
    }
  }
});



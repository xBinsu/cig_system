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

// Logout function
function logout() {
  if (confirm('Are you sure you want to logout?')) {
    // Clear session/localStorage if needed
    localStorage.clear();
    sessionStorage.clear();
    // Redirect to login page
    window.location.href = 'login.html';
  }
}



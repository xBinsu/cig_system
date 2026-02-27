// Announcement Board Functions
function editAnnouncement() {
  let currentText = document.getElementById('announcementContent').innerText;
  document.getElementById('announcementText').value = currentText;
  document.getElementById('announcementModal').style.display = 'flex';
}

function closeAnnouncementModal() {
  document.getElementById('announcementModal').style.display = 'none';
}

// Handle announcement save
document.getElementById('announcementForm').addEventListener('submit', function(e) {
  e.preventDefault();
  let newText = document.getElementById('announcementText').value;
  document.getElementById('announcementContent').innerText = newText;
  closeAnnouncementModal();
  alert('Announcement updated successfully!');
});

// Close modal when clicking outside
window.addEventListener('click', function(e) {
  let modal = document.getElementById('announcementModal');
  if (e.target === modal) {
    closeAnnouncementModal();
  }
});

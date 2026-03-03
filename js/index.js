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
  
  const announcementId = document.getElementById('announcementId').value;
  const newText = document.getElementById('announcementText').value;
  const userId = document.getElementById('currentUserId')?.value || 1;
  
  // Create URLSearchParams for PUT request
  const params = new URLSearchParams();
  params.append('announcement_id', announcementId);
  params.append('title', 'Admin Announcement');
  params.append('content', newText);
  params.append('updated_by', userId);
  
  fetch('../api/announcements.php?action=update', {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: params.toString()
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      document.getElementById('announcementContent').innerHTML = '<p>' + escapeHtml(newText) + '</p>';
      closeAnnouncementModal();
      alert('Announcement updated successfully!');
    } else {
      alert('Failed to update announcement: ' + (data.message || 'Unknown error'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error updating announcement');
  });
});

// Helper function to escape HTML
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
  let modal = document.getElementById('announcementModal');
  if (e.target === modal) {
    closeAnnouncementModal();
  }
});

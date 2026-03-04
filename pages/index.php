<?php
session_start();
require_once '../db/config.php';

// Redirect if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$db = new Database();

// Get organization count (users with org_code)
$org_count = $db->fetchRow("SELECT COUNT(*) as count FROM users WHERE org_code IS NOT NULL AND status = 'active'");

// Get submission count
$submission_count = $db->fetchRow("SELECT COUNT(*) as count FROM submissions");

// Get approval statistics
$stats = $db->fetchRow("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM submissions
");

$approval_rate = $stats['total'] > 0 
    ? round(($stats['approved'] / $stats['total']) * 100) 
    : 0;

// Fetch latest announcement from database
$announcement = $db->fetchRow("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1") 
    ?? ['content' => 'Welcome to the Admin Dashboard!'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php $current_page = 'home'; ?>
<?php include 'navbar.php'; ?>
  <div id="page-content" class="page-background">
    <!-- WELCOME SECTION -->
    <div class="welcome-section">
      <div class="welcome-content">
        <h1>Welcome to CIG</h1>
        <p class="welcome-subtitle">Council of Internal Governance</p>
        <p class="welcome-description">Manage submissions, reviews, and organizational governance with ease. Stay updated with the latest announcements and maintain transparency across all departments.</p>
      </div>
      <div class="welcome-stats">
        <div class="stat-item">
          <span class="stat-number"><?php echo $org_count['count']; ?></span>
          <span class="stat-label">Organizations</span>
        </div>
        <div class="stat-item">
          <span class="stat-number"><?php echo number_format($submission_count['count']); ?></span>
          <span class="stat-label">Submissions</span>
        </div>
        <div class="stat-item">
          <span class="stat-number"><?php echo $approval_rate; ?>%</span>
          <span class="stat-label">Approval Rate</span>
        </div>
      </div>
    </div>

    <!-- ANNOUNCEMENT BOARD -->
    <div class="announcement-board">
      <!-- Header -->
      <div class="announcement-header">
        <div class="announcement-header-left">
          <i class="fas fa-megaphone announcement-icon"></i>
          <h3>Important Announcements</h3>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
          <div class="ann-search-wrap">
            <i class="fas fa-search ann-search-icon"></i>
            <input type="text" id="annSearch" class="ann-search" placeholder="Search announcements..." oninput="filterAnnouncements()">
          </div>
          <button class="edit-btn" onclick="openNewAnnouncement()"><i class="fas fa-plus"></i> New</button>
        </div>
      </div>

      <!-- Filter Tabs -->
      <div class="ann-tabs">
        <?php
          $categories = ['All','General','HR','IT','Finance','Events'];
          foreach($categories as $cat): ?>
          <button class="ann-tab <?php echo $cat==='All'?'active':''; ?>" onclick="setAnnTab(this,'<?php echo $cat; ?>')"><?php echo $cat; ?></button>
        <?php endforeach; ?>
      </div>

      <!-- Announcement Cards -->
      <div class="ann-cards" id="annCards">
        <!-- Cards loaded via JavaScript from database -->
      </div>

      <div class="announcement-footer">
        <span class="announcement-updated"><i class="fas fa-clock" style="margin-right:5px;"></i>Last updated: <?php echo date('M d, Y H:i'); ?></span>
      </div>
    </div>

    <!-- ORGANIZATION VALUES SECTION -->
    <div class="values-section">
      

      <div class="values-container">
        <!-- MISSION CARD -->
        <div class="value-card-new mission">
          <div class="card-image-header" style="background: linear-gradient(135deg, #1e90ff 0%, #00bfff 100%); position: relative; overflow: hidden;">

            <div class="hexagon-icon">
              <i class="fas fa-rocket" style="font-size: 48px; color: #1e90ff;"></i>
            </div>
          </div>
          <div class="card-title-section">
            <h3>MISSION</h3>
          </div>
          <div class="card-description">
            <p>To strengthen the capability of organization through collaboration and active participation in school governance.</p>
          </div>
        </div>

        <!-- VISION CARD -->
        <div class="value-card-new vision">
          <div class="card-image-header" style="background: linear-gradient(135deg, #ff6b6b 0%, #ff1744 100%); position: relative; overflow: hidden;">
            <div class="hexagon-icon">
              <i class="fas fa-eye" style="font-size: 48px; color: #ff6b6b;"></i>
            </div>
          </div>
          <div class="card-title-section">
            <h3>VISION</h3>
          </div>
          <div class="card-description">
            <p>A highly trusted organization committed to capacitating progressive communities.</p>
          </div>
        </div>

        <!-- VALUES CARD -->
        <div class="value-card-new values">
          <div class="card-image-header" style="background: linear-gradient(135deg, #ff9500 0%, #ff6f00 100%); position: relative; overflow: hidden;">
            <div class="hexagon-icon">
              <i class="fas fa-heart" style="font-size: 48px; color: #ff9500;"></i>
            </div>
          </div>
          <div class="card-title-section">
            <h3>VALUES</h3>
          </div>
          <div class="card-description">
            <ul style="list-style: none; padding: 0; text-align: left;">
              <li style="padding: 6px 0;"><strong>SERVICE</strong> - Dedicated to serving our communities</li>
              <li style="padding: 6px 0;"><strong>VOLUNTEERISM</strong> - Active participation and commitment</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <style>
      .values-section {
        margin: 60px 0;
      }

      .values-header {
        text-align: center;
        margin-bottom: 50px;
      }

      .values-header h2 {
        font-size: 2.2em;
        color: #1a202c;
        font-weight: 900;
        margin-bottom: 10px;
      }

      .values-header p {
        font-size: 1.1em;
        color: #718096;
        margin: 0;
      }

      .values-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 40px;
        margin-bottom: 40px;
      }

      .value-card-new {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
      }

      .value-card-new:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
      }

      .card-image-header {
        height: 220px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
      }

      .hexagon-icon {
        width: 140px;
        height: 140px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        position: relative;
        z-index: 2;
      }

      .card-title-section {
        padding: 25px;
        text-align: center;
        border-bottom: 2px solid #f0f0f0;
      }

      .card-title-section h3 {
        font-size: 1.8em;
        font-weight: 800;
        margin: 0;
        color: #1a202c;
        letter-spacing: 1px;
      }

      .card-description {
        padding: 25px;
        flex-grow: 1;
        display: flex;
        align-items: center;
      }

      .card-description p {
        margin: 0;
        font-size: 0.95em;
        line-height: 1.7;
        color: #4a5568;
        text-align: center;
      }

      .card-description ul {
        font-size: 0.95em;
        line-height: 1.7;
        color: #4a5568;
      }

      .card-description li {
        color: #4a5568;
      }

      @media (max-width: 768px) {
        .values-header h2 {
          font-size: 1.8em;
        }

        .values-container {
          grid-template-columns: 1fr;
          gap: 30px;
        }

        .card-image-header {
          height: 180px;
        }

        .hexagon-icon {
          width: 110px;
          height: 110px;
        }
      }
    </style>
  </div>

  <!-- ANNOUNCEMENT MODAL -->
  <div id="announcementModal" class="modal">
    <div class="modal-content ann-modal-content">
      <h3 id="annModalTitle">📢 New Announcement</h3>
      <form id="announcementForm">
        <input type="hidden" id="announcementId" name="announcement_id" value="">
        <input type="hidden" id="currentUserId" name="user_id" value="<?php echo $_SESSION['user_id'] ?? 1; ?>">
        <input type="hidden" id="editingMode" value="create">

        <label class="ann-label">Title <span style="color: red;">*</span></label>
        <input type="text" id="announcementTitle" name="title" class="ann-input" placeholder="Announcement title..." required>

        <div style="display:flex;gap:12px;">
          <div style="flex:1;">
            <label class="ann-label">Category</label>
            <select id="announcementCategory" name="category" class="ann-input">
              <option value="General">General</option>
              <option value="HR">HR</option>
              <option value="IT">IT</option>
              <option value="Finance">Finance</option>
              <option value="Events">Events</option>
            </select>
          </div>
          <div style="flex:1;">
            <label class="ann-label">Priority</label>
            <select id="announcementPriority" name="priority" class="ann-input">
              <option value="normal" selected>Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
              <option value="low">Low</option>
            </select>
          </div>
        </div>

        <label class="ann-label">Message <span style="color: red;">*</span></label>
        <textarea id="announcementText" name="content" placeholder="Write your announcement..." required style="min-height: 150px;"></textarea>
        <div id="annCharCount" style="text-align:right;font-size:12px;color:#94a3b8;margin-top:-12px;margin-bottom:16px;">0 characters</div>

        <div class="ann-form-messages">
          <div id="annSuccess" style="display:none;color:green;font-weight:600;margin-bottom:10px;"></div>
          <div id="annError" style="display:none;color:red;font-weight:600;margin-bottom:10px;"></div>
        </div>

        <div class="modal-buttons">
          <button type="button" class="cancel-btn" onclick="closeAnnouncementModal()">Cancel</button>
          <button type="submit" class="save-btn" id="annSaveBtn">Post Announcement</button>
        </div>
      </form>
    </div>
  </div>
  <?php include 'footer.php'; ?>

</div>

<script>
// ── Load Announcements on Page Load ──────
window.addEventListener('load', function() {
  loadAnnouncements();
});

function loadAnnouncements() {
  fetch('../api/announcements.php?action=all')
    .then(response => response.json())
    .then(data => {
      if (data.success && data.data) {
        renderAnnouncements(data.data);
      }
    })
    .catch(error => console.error('Error loading announcements:', error));
}

function renderAnnouncements(announcements) {
  const container = document.getElementById('annCards');
  container.innerHTML = '';
  
  const priorityLabels = {urgent:'Urgent',high:'High',normal:'Normal',low:'Low'};
  const catColors = {General:'ann-cat-general',HR:'ann-cat-hr',IT:'ann-cat-it',Finance:'ann-cat-finance',Events:'ann-cat-events'};
  
  announcements.forEach(ann => {
    const card = document.createElement('div');
    card.className = `ann-card ann-priority-${ann.priority || 'normal'}`;
    card.dataset.category = ann.category || 'General';
    card.dataset.id = ann.announcement_id || ann.id || '';
    
    const createdDate = new Date(ann.created_at).toLocaleString('en-US', {
      month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
    
    card.innerHTML = `
      <div class="ann-priority-bar"></div>
      <div class="ann-card-inner">
        <div class="ann-card-top">
          <div class="ann-card-meta">
            <div class="ann-avatar" style="background:#2d6a4f;">AD</div>
            <div>
              <div class="ann-card-title">
                <span class="ann-pin-icon" style="display:none;">📌</span>
                <strong>${htmlEscape(ann.title)}</strong>
                <span class="ann-tag ${catColors[ann.category] || 'ann-cat-general'}">${htmlEscape(ann.category || 'General')}</span>
                <span class="ann-tag ann-priority-tag ${ann.priority || 'normal'}">● ${priorityLabels[ann.priority] || 'Normal'}</span>
              </div>
              <div class="ann-card-byline">Admin · ${createdDate} · <span class="ann-views"><i class="fas fa-eye"></i> ${ann.view_count || 0} views</span></div>
            </div>
          </div>
          <div class="ann-actions">
            <button class="ann-action-btn" title="Pin" onclick="togglePin(this)">📍</button>
            <button class="ann-action-btn" title="Edit" onclick="editAnnouncement(this)">✏️</button>
            <button class="ann-action-btn ann-danger" title="Delete" onclick="deleteAnnCard(this)">🗑️</button>
          </div>
        </div>
        <div class="ann-body"><p>${htmlEscape(ann.content)}</p></div>
        <div class="ann-reactions">
          <button class="ann-react-btn" onclick="toggleReact(this,'👍')">👍 <span>0</span></button>
          <button class="ann-react-btn" onclick="toggleReact(this,'✅')">✅ <span>0</span></button>
          <button class="ann-react-btn" onclick="toggleReact(this,'🎉')">🎉 <span>0</span></button>
        </div>
      </div>
    `;
    
    container.appendChild(card);
  });
  
  // Re-apply filter after loading
  filterAnnouncements();
}

function htmlEscape(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}

function setAnnTab(el, cat) {
  document.querySelectorAll('.ann-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  currentAnnTab = cat;
  filterAnnouncements();
}

function filterAnnouncements() {
  const q = document.getElementById('annSearch').value.toLowerCase();
  document.querySelectorAll('.ann-card').forEach(card => {
    const cat = card.dataset.category || 'General';
    const text = card.innerText.toLowerCase();
    const matchCat = currentAnnTab === 'All' || cat === currentAnnTab;
    const matchQ = text.includes(q);
    card.style.display = (matchCat && matchQ) ? '' : 'none';
  });
}

function toggleReact(btn, emoji) {
  const span = btn.querySelector('span');
  const active = btn.classList.toggle('reacted');
  span.textContent = parseInt(span.textContent) + (active ? 1 : -1);
}

function togglePin(btn) {
  const card = btn.closest('.ann-card');
  const pinIcon = card.querySelector('.ann-pin-icon');
  const pinned = card.classList.toggle('is-pinned');
  btn.textContent = pinned ? '📌' : '📍';
  pinIcon.style.display = pinned ? 'inline' : 'none';
  // Move pinned cards to top
  const container = document.getElementById('annCards');
  if (pinned) container.prepend(card);
}

function deleteAnnCard(btn) {
  if (confirm('Delete this announcement?')) {
    const card = btn.closest('.ann-card');
    const announcementId = card.dataset.id;
    const userId = document.getElementById('currentUserId').value;
    
    if (!announcementId) {
      alert('Error: Cannot find announcement ID');
      return;
    }
    
    const formData = new FormData();
    formData.append('announcement_id', announcementId);
    formData.append('updated_by', userId);
    
    fetch('../api/announcements.php?action=delete', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        card.remove();
        showAnnSuccess('Announcement deleted successfully!');
      } else {
        alert('Failed to delete: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(error => {
      console.error('Delete error:', error);
      alert('Error deleting announcement: ' + error.message);
    });
  }
}

function openNewAnnouncement() {
  document.getElementById('editingMode').value = 'create';
  document.getElementById('announcementId').value = ''; // Always clear ID for new announcements
  document.getElementById('annModalTitle').textContent = '📢 New Announcement';
  document.getElementById('announcementTitle').value = '';
  document.getElementById('announcementText').value = '';
  document.getElementById('announcementId').value = '';
  document.getElementById('announcementCategory').value = 'General';
  document.getElementById('announcementPriority').value = 'normal';
  document.getElementById('annSaveBtn').textContent = 'Post Announcement';
  document.getElementById('annCharCount').textContent = '0 characters';
  document.getElementById('annSuccess').style.display = 'none';
  document.getElementById('annError').style.display = 'none';
  document.getElementById('announcementModal').style.display = 'flex';
}

function editAnnouncement(btn) {
  const card = btn.closest('.ann-card');
  const body = card.querySelector('.ann-body p').textContent;
  const titleEl = card.querySelector('.ann-card-title strong');
  const title = titleEl ? titleEl.textContent : '';
  
  // Extract category from data-category attribute
  const category = card.dataset.category || 'General';
  
  // Extract priority from class name (ann-priority-{priority})
  const priorityClass = Array.from(card.classList).find(cls => cls.startsWith('ann-priority-'));
  const priority = priorityClass ? priorityClass.replace('ann-priority-', '') : 'normal';
  
  const annId = card.dataset.id;

  if (!annId || annId === '' || annId === '0') {
    alert('Error: This announcement has no ID. It may not have been saved to the database properly.');
    return;
  }
  
  document.getElementById('editingMode').value = 'edit';
  document.getElementById('annModalTitle').textContent = '✏️ Edit Announcement';
  document.getElementById('announcementTitle').value = title;
  document.getElementById('announcementText').value = body;
  document.getElementById('announcementId').value = annId || '';
  document.getElementById('announcementCategory').value = category;
  document.getElementById('announcementPriority').value = priority;
  document.getElementById('annSaveBtn').textContent = 'Save Changes';
  document.getElementById('annCharCount').textContent = body.length + ' characters';
  document.getElementById('annSuccess').style.display = 'none';
  document.getElementById('annError').style.display = 'none';
  document.getElementById('announcementModal').style.display = 'flex';
}

function closeAnnouncementModal() {
  document.getElementById('announcementModal').style.display = 'none';
}

// Character counter
document.getElementById('announcementText').addEventListener('input', function() {
  document.getElementById('annCharCount').textContent = this.value.length + ' characters';
});

// Handle form submit - POST to API
document.getElementById('announcementForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const title = document.getElementById('announcementTitle').value.trim();
  const content = document.getElementById('announcementText').value.trim();
  const category = document.getElementById('announcementCategory').value;
  const priority = document.getElementById('announcementPriority').value;
  const userId = document.getElementById('currentUserId').value;
  const announcementId = document.getElementById('announcementId').value.trim();
  // Use announcementId as source of truth — NOT editingMode (which can get stale)
  const isEditMode = announcementId !== '' && announcementId !== '0';

  // Reset messages
  document.getElementById('annSuccess').style.display = 'none';
  document.getElementById('annError').style.display = 'none';

  if (!title || !content) {
    showAnnError('Please fill in all required fields');
    return;
  }

  const formData = new FormData();
  formData.append('title', title);
  formData.append('content', content);
  formData.append('category', category);
  formData.append('priority', priority);

  const btn = document.getElementById('annSaveBtn');
  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Saving...';

  if (isEditMode) {

    // Update existing announcement
    formData.append('announcement_id', announcementId);
    formData.append('updated_by', userId);
    
    fetch('../api/announcements.php?action=update', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      console.log('API Response:', data);
      if (data.success) {
        showAnnSuccess('Announcement updated successfully!');
        setTimeout(() => {
          closeAnnouncementModal();
          loadAnnouncements();
        }, 1000);
      } else {
        showAnnError(data.message || 'Failed to update announcement');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showAnnError('Network error: ' + error.message);
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = originalText;
    });
  } else {
    // Create new announcement — no announcementId means this is new
    formData.append('created_by', userId);
    
    fetch('../api/announcements.php?action=create', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showAnnSuccess('Announcement posted successfully!');
        setTimeout(() => {
          closeAnnouncementModal();
          loadAnnouncements(); // Reload list instead of full page reload
        }, 1000);
      } else {
        showAnnError(data.message || 'Failed to post announcement');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showAnnError('Network error: ' + error.message);
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = originalText;
    });
  }
});

function showAnnError(message) {
  const errorEl = document.getElementById('annError');
  errorEl.textContent = message;
  errorEl.style.display = 'block';
}

function showAnnSuccess(message) {
  const successEl = document.getElementById('annSuccess');
  successEl.textContent = message;
  successEl.style.display = 'block';
}

// Close on backdrop click
window.onclick = function(event) {
  const modal = document.getElementById('announcementModal');
  if (event.target === modal) modal.style.display = 'none';
}

function toggleNotificationPanel() {
  const panel = document.getElementById('notificationPanel');
  panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}
</script>

<script src="../js/navbar.js"></script>
<script src="../js/index.js"></script>


</body>
</html>
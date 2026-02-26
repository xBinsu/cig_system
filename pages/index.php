<?php
session_start();
require_once '../db/config.php';

// Redirect if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "cig_system");

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get organization count
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM organizations WHERE status = 'active'");
$org_count = mysqli_fetch_assoc($result);

// Get submission count
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM submissions");
$submission_count = mysqli_fetch_assoc($result);

// Get approval statistics
$result = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM submissions
");
$stats = mysqli_fetch_assoc($result);

$approval_rate = $stats['total'] > 0 
    ? round(($stats['approved'] / $stats['total']) * 100) 
    : 0;

// Initialize announcement variable
$announcement = ['setting_value' => 'Welcome to the Admin Dashboard!'];

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
      <div class="announcement-board-inner">
        <div class="announcement-header">
          <div class="announcement-header-left">
            <div class="announcement-icon">
              <i class="fas fa-bell"></i>
            </div>
            <div class="announcement-header-text">
              <h3>Latest Announcements</h3>
              <span class="announcement-subtitle">Important updates and notices</span>
            </div>
          </div>
          <button class="edit-btn" onclick="editAnnouncement()">
            <i class="fas fa-edit"></i>
            <span>Edit</span>
          </button>
        </div>
        <div class="announcement-content" id="announcementContent">
          <p><?php echo htmlspecialchars($announcement['setting_value'] ?? 'Welcome to the Admin Dashboard!'); ?></p>
        </div>
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
    <div class="modal-content">
      <h3>Edit Announcement</h3>
      <form id="announcementForm" method="POST" action="">
        <textarea id="announcementText" name="announcement_text" placeholder="Enter announcement text..." required><?php echo htmlspecialchars($announcement['setting_value'] ?? ''); ?></textarea>
        <div class="modal-buttons">
          <button type="submit" class="save-btn">Save</button>
          <button type="button" class="cancel-btn" onclick="closeAnnouncementModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>
  <?php include 'footer.php'; ?>

</div>

<script>
function toggleNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function editAnnouncement() {
    document.getElementById('announcementModal').style.display = 'block';
}

function closeAnnouncementModal() {
    document.getElementById('announcementModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('announcementModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<script src="../js/navbar.js"></script>
<script src="../js/index.js"></script>


</body>
</html>

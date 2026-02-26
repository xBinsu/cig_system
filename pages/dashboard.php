<?php
/**
 * CIG Admin Dashboard - Main Dashboard Page
 * Displays statistics and recent submissions from database
 */

session_start();
require_once '../db/config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php'); 
    exit();
}

$db = new Database();

// Get submission statistics
try {
    $stats = $db->fetchRow("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM submissions
    ");

    // Get recent submissions
    $recent_submissions = $db->fetchAll("
        SELECT s.*, u.full_name, o.org_name 
        FROM submissions s
        LEFT JOIN users u ON s.user_id = u.user_id
        LEFT JOIN organizations o ON s.org_id = o.org_id
        ORDER BY s.submitted_at DESC
        LIMIT 10
    ");

    // Get recent notifications
    $notifications = $db->fetchAll("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = false
        ORDER BY created_at DESC
        LIMIT 5
    ", [1]); // Admin user_id = 1

    // Count unread notifications
    $unread_count = count($notifications);
} catch (Exception $e) {
    error_log('Dashboard Error: ' . $e->getMessage());
    $stats = null;
    $recent_submissions = [];
    $notifications = [];
    $unread_count = 0;
}

$user = ['full_name' => $_SESSION['admin_email'] ?? 'Admin'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Admin</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="../css/components.css">
<link rel="stylesheet" href="../css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php 
$current_page = 'dashboard';
$unread_count = $unread_count ?? 0;
$user_name = $user['full_name'] ?? '';
?>
<?php include 'navbar.php'; ?>

  <!-- DASHBOARD -->
  <div class="page active">
    <div class="page-header">
      <h2><i class="fas fa-chart-line"></i> Dashboard</h2>
    </div>
    
    <div class="stats-cards">
      <div class="stat-card stat-card-primary">
        <h3><i class="fas fa-file-alt"></i> Total Submissions</h3>
        <p><?php echo $stats ? $stats['total'] : '0'; ?></p>
      </div>
      <div class="stat-card stat-card-warnings">
        <h3><i class="fas fa-hourglass-half"></i> Pending</h3>
        <p><?php echo $stats ? $stats['pending'] : '0'; ?></p>
      </div>
      <div class="stat-card stat-card-success">
        <h3><i class="fas fa-check-circle"></i> Approved</h3>
        <p><?php echo $stats ? $stats['approved'] : '0'; ?></p>
      </div>
      <div class="stat-card stat-card-warning">
        <h3><i class="fas fa-times-circle"></i> Rejected</h3>
        <p><?php echo $stats ? $stats['rejected'] : '0'; ?></p>
      </div>
    </div>

    <div class="table-container">
      <h3 style="font-size: 18px; font-weight: 700; color: #047857; margin: 0 0 20px 0; display: flex; align-items: center; gap: 10px;"><i class="fas fa-history"></i> Recent Submissions</h3>
      <table>
        <thead>
          <tr>
            <th><i class="fas fa-hashtag"></i> Ref No</th>
            <th><i class="fas fa-building"></i> Organization</th>
            <th><i class="fas fa-file-alt"></i> Title</th>
            <th><i class="fas fa-tag"></i> Status</th>
            <th><i class="fas fa-calendar"></i> Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($recent_submissions)): ?>
            <?php foreach ($recent_submissions as $index => $submission): ?>
              <tr>
                <td class="ref-number">#<?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo htmlspecialchars($submission['org_name'] ?? 'N/A'); ?></td>
                <td class="title-cell"><strong><?php echo htmlspecialchars($submission['title']); ?></strong></td>
                <td><span class="status-badge <?php echo strtolower($submission['status']); ?>"><i class="fas fa-circle"></i> <?php echo ucfirst(str_replace('_', ' ', $submission['status'])); ?></span></td>
                <td><?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <!-- Sample Data -->
            <tr>
              <td class="ref-number">#001</td>
              <td>Tech Innovations Inc.</td>
              <td class="title-cell"><strong>Digital Transformation Initiative 2026</strong></td>
              <td><span class="status-badge approved"><i class="fas fa-circle"></i> Approved</span></td>
              <td>Feb 25, 2026</td>
            </tr>
            <tr>
              <td class="ref-number">#002</td>
              <td>Global Solutions Ltd.</td>
              <td class="title-cell"><strong>Cloud Infrastructure Upgrade</strong></td>
              <td><span class="status-badge pending"><i class="fas fa-circle"></i> Pending</span></td>
              <td>Feb 26, 2026</td>
            </tr>
            <tr>
              <td class="ref-number">#003</td>
              <td>Future Systems Corp.</td>
              <td class="title-cell"><strong>AI Integration Project Phase 1</strong></td>
              <td><span class="status-badge approved"><i class="fas fa-circle"></i> Approved</span></td>
              <td>Feb 24, 2026</td>
            </tr>
            <tr>
              <td class="ref-number">#004</td>
              <td>Enterprise Solutions Group</td>
              <td class="title-cell"><strong>Security Enhancement Program</strong></td>
              <td><span class="status-badge rejected"><i class="fas fa-circle"></i> Rejected</span></td>
              <td>Feb 23, 2026</td>
            </tr>
            <tr>
              <td class="ref-number">#005</td>
              <td>Digital Dynamics Ltd.</td>
              <td class="title-cell"><strong>Mobile App Development Framework</strong></td>
              <td><span class="status-badge pending"><i class="fas fa-circle"></i> Pending</span></td>
              <td>Feb 22, 2026</td>
            </tr>
          <?php endif; ?>
        </tbody>
        </tbody>
      </table>
    </div>
    
  </div>
  <?php include 'footer.php'; ?>
</div>

<script src="../js/navbar.js"></script>
<script src="../js/dashboard.js"></script>
<script>
function toggleNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>

<?php
/**
 * Helper function to format time ago
 */
function timeAgo($date) {
    $time = strtotime($date);
    $current_time = time();
    $diff = $current_time - $time;

    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = round($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = round($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date('M d, Y', $time);
    }
}
?>

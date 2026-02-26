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
    
    <div class="cards">
      <div class="card card-total">
        <h3>Total Submissions</h3>
        <p><?php echo $stats ? $stats['total'] : '0'; ?></p>
      </div>
      <div class="card card-pending">
        <h3>Pending</h3>
        <p><?php echo $stats ? $stats['pending'] : '0'; ?></p>
      </div>
      <div class="card card-approved">
        <h3>Approved</h3>
        <p><?php echo $stats ? $stats['approved'] : '0'; ?></p>
      </div>
      <div class="card card-rejected">
        <h3>Rejected</h3>
        <p><?php echo $stats ? $stats['rejected'] : '0'; ?></p>
      </div>
    </div>

    <div class="table-container">
      <h2>Recent Submissions</h2>
      <table>
        <thead>
          <tr>
            <th>Ref No</th>
            <th>Organization</th>
            <th>Title</th>
            <th>Status</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($recent_submissions)): ?>
            <?php foreach ($recent_submissions as $index => $submission): ?>
              <tr>
                <td><?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo htmlspecialchars($submission['org_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($submission['title']); ?></td>
                <td>
                  <span class="status <?php echo strtolower($submission['status']); ?>">
                    <?php echo ucfirst($submission['status']); ?>
                  </span>
                </td>
                <td><?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?></td>
                <td>
                  <a href="review.php?id=<?php echo $submission['submission_id']; ?>" class="action-btn">Review</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align: center; color: #999;">No submissions found</td>
            </tr>
          <?php endif; ?>
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

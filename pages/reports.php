<?php
/**
 * CIG Admin Dashboard - Reports Page
 * Displays system statistics and analytics
 */

session_start();
require_once '../db/config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$user = ['full_name' => $_SESSION['admin_email'] ?? 'Admin'];

// Get statistics
try {
    // Submission statistics
    $stats = $db->fetchRow("
        SELECT 
            COUNT(*) as total_submissions,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_review
        FROM submissions
    ");

    // Organization statistics
    $org_stats = $db->fetchRow("
        SELECT 
            COUNT(*) as total_orgs,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_orgs
        FROM organizations
    ");

    // User statistics
    $user_stats = $db->fetchRow("
        SELECT COUNT(*) as total_users FROM users
    ");

    // Monthly submissions
    $monthly_stats = $db->fetchAll("
        SELECT DATE_FORMAT(submitted_at, '%Y-%m') as month, COUNT(*) as count
        FROM submissions
        GROUP BY DATE_FORMAT(submitted_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");

    // Recent activity
    $recent_activity = $db->fetchAll("
        SELECT al.*, u.full_name, u.username
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        ORDER BY al.created_at DESC
        LIMIT 20
    ");
} catch (Exception $e) {
    error_log('Reports Error: ' . $e->getMessage());
    $stats = null;
    $org_stats = null;
    $user_stats = null;
    $monthly_stats = [];
    $recent_activity = [];
}

// Calculate approval rate
$approval_rate = ($stats && $stats['total_submissions'] > 0) 
    ? round(($stats['approved'] / $stats['total_submissions']) * 100) 
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports - Admin</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="../css/reports.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php 
$current_page = 'reports';
$user_name = $user['full_name'] ?? '';
?>
<?php include 'navbar.php'; ?>

  <!-- REPORTS -->
  <div class="page active">
    <div class="page-header">
      <h2><i class="fas fa-chart-bar"></i> System Reports & Analytics</h2>
    </div>

    <!-- SUMMARY CARDS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
      <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px;">
        <h3 style="margin: 0 0 10px 0;">Total Submissions</h3>
        <p style="margin: 0; font-size: 2em; font-weight: bold;"><?php echo $stats['total_submissions'] ?? 0; ?></p>
      </div>
      <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 8px;">
        <h3 style="margin: 0 0 10px 0;">Approval Rate</h3>
        <p style="margin: 0; font-size: 2em; font-weight: bold;"><?php echo $approval_rate; ?>%</p>
      </div>
      <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 8px;">
        <h3 style="margin: 0 0 10px 0;">Organizations</h3>
        <p style="margin: 0; font-size: 2em; font-weight: bold;"><?php echo $org_stats['active_orgs'] ?? 0; ?></p>
      </div>
      <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 20px; border-radius: 8px;">
        <h3 style="margin: 0 0 10px 0;">Total Users</h3>
        <p style="margin: 0; font-size: 2em; font-weight: bold;"><?php echo $user_stats['total_users'] ?? 0; ?></p>
      </div>
    </div>

    <!-- SUBMISSION STATUS BREAKDOWN -->
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
      <h3>Submission Status Breakdown</h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
        <div style="background-color: white; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
          <p style="margin: 0; color: #666; font-size: 0.9em;">Pending</p>
          <p style="margin: 5px 0 0 0; font-size: 1.5em; font-weight: bold; color: #ffc107;"><?php echo $stats['pending'] ?? 0; ?></p>
        </div>
        <div style="background-color: white; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
          <p style="margin: 0; color: #666; font-size: 0.9em;">Approved</p>
          <p style="margin: 5px 0 0 0; font-size: 1.5em; font-weight: bold; color: #28a745;"><?php echo $stats['approved'] ?? 0; ?></p>
        </div>
        <div style="background-color: white; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545;">
          <p style="margin: 0; color: #666; font-size: 0.9em;">Rejected</p>
          <p style="margin: 5px 0 0 0; font-size: 1.5em; font-weight: bold; color: #dc3545;"><?php echo $stats['rejected'] ?? 0; ?></p>
        </div>
        <div style="background-color: white; padding: 15px; border-radius: 8px; border-left: 4px solid #17a2b8;">
          <p style="margin: 0; color: #666; font-size: 0.9em;">In Review</p>
          <p style="margin: 5px 0 0 0; font-size: 1.5em; font-weight: bold; color: #17a2b8;"><?php echo $stats['in_review'] ?? 0; ?></p>
        </div>
      </div>
    </div>

    <!-- RECENT ACTIVITY -->
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px;">
      <h3>Recent System Activity</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Action</th>
              <th>Details</th>
              <th>IP Address</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($recent_activity)): ?>
              <?php foreach ($recent_activity as $activity): ?>
                <tr>
                  <td><?php echo htmlspecialchars($activity['full_name'] ?? 'Unknown'); ?></td>
                  <td><?php echo htmlspecialchars(ucfirst($activity['action'])); ?></td>
                  <td><?php echo htmlspecialchars($activity['details']); ?></td>
                  <td><?php echo htmlspecialchars($activity['ip_address'] ?? 'N/A'); ?></td>
                  <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" style="text-align: center; color: #999;">No recent activity</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php include 'footer.php'; ?>
</div>

<script src="../js/navbar.js"></script>
<script src="../js/reports.js"></script>
<script>
function toggleNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>

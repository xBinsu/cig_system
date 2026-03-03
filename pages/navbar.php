<?php
/**
 * CIG Navigation Bar Component
 * Include this file in all admin pages
 * 
 * Optional Parameters:
 * - $current_page: Current page identifier (dashboard, submissions, review, archive, organizations, reports)
 * - $unread_count: Number of unread notifications (default: 0)
 * - $user_name: User's full name for display (default: empty)
 * - $notifications: Array of notification objects (optional)
 */

// Set defaults if not provided
$current_page = $current_page ?? '';
$unread_count = $unread_count ?? 0;
$user_name = $user_name ?? '';
$notifications = $notifications ?? [];
?>

<!-- NAVBAR (Sidebar + Topbar) -->
<div class="sidebar">
  <a href="index.php" class="logo-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>" data-page="home"><img src="../assets/cigorig.png" alt="Logo" class="logo"></a>
  <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" data-page="dashboard"><i></i> <span>Dashboard</span></a>
  <a href="submissions.php" class="nav-link <?php echo $current_page === 'submissions' ? 'active' : ''; ?>" data-page="submissions"><i></i> <span>Submissions</span></a>
  <a href="review.php" class="nav-link <?php echo $current_page === 'review' ? 'active' : ''; ?>" data-page="review"><i></i> <span>Organizations</span></a>
  <a href="archive.php" class="nav-link <?php echo $current_page === 'archive' ? 'active' : ''; ?>" data-page="archive"><i></i> <span>Document Archive</span></a>
  <a href="reports.php" class="nav-link <?php echo $current_page === 'reports' ? 'active' : ''; ?>" data-page="reports"><i></i> <span>Reports</span></a>
  <div class="sidebar-footer">
    <button onclick="showLogoutModal()" class="logout-btn"><span>Logout</span></button>
  </div>
</div>

<!-- LOGOUT CONFIRMATION MODAL -->
<div id="logoutModal" class="logout-modal-overlay" style="display: none;">
  <div class="logout-modal">
    <div class="logout-modal-header">
      <h3>Confirm Logout</h3>
    </div>
    <div class="logout-modal-body">
      <p>Are you sure you want to logout?</p>
    </div>
    <div class="logout-modal-footer">
      <button onclick="cancelLogout()" class="modal-btn modal-btn-cancel">Cancel</button>
      <button onclick="confirmLogout()" class="modal-btn modal-btn-confirm">Logout</button>
    </div>
  </div>
</div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <div id="cig">COUNCIL OF INTERNAL GOVERNANCE<p class="cig-subtitle">Pamantasan ng Lungsod ng San Pablo</p></div>
    </div>
    <div class="topbar-right">
      <div class="notification-bell" onclick="toggleNotificationPanel()">
        <span class="bell-icon">🔔</span>
        <span class="notification-badge" id="notificationBadge"><?php echo $unread_count; ?></span>
      </div>
      <?php if (!empty($user_name)): ?>
        <div><?php echo htmlspecialchars($user_name); ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- NOTIFICATION PANEL -->
  <div id="notificationPanel" class="notification-panel">
    <div class="notification-header">
      <h4>Notifications</h4>
      <button class="close-notification" onclick="toggleNotificationPanel()">✕</button>
    </div>
    <div class="notification-list">
      <?php if (!empty($notifications)): ?>
        <?php foreach ($notifications as $notif): ?>
          <div class="notification-item">
            <div class="notification-icon"><?php echo htmlspecialchars($notif['type'] === 'submission' ? '📋' : ($notif['type'] === 'approval' ? '✅' : '⚠️')); ?></div>
            <div class="notification-content">
              <p class="notification-title"><?php echo htmlspecialchars($notif['title'] ?? ''); ?></p>
              <p class="notification-text"><?php echo htmlspecialchars($notif['message'] ?? ''); ?></p>
              <span class="notification-time"><?php echo isset($notif['created_at']) ? timeAgo($notif['created_at']) : ''; ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="notification-item">
          <p style="text-align: center; color: #999;">No new notifications</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

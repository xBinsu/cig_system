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
  <div class="sidebar-footer">
    <button onclick="showLogoutModal()" class="logout-btn"><span>Logout</span></button>
  </div>
</div>

<!-- LOGOUT CONFIRMATION MODAL -->
<div id="logoutModal" class="logout-modal-overlay" style="display: none;">
  <div class="logout-modal">
    <div class="logout-modal-icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
    </div>
    <p class="logout-modal-label">CIG ADMIN</p>
    <h3 class="logout-modal-title">Sign out?</h3>
    <p class="logout-modal-desc">You're about to sign out of your admin account. Any unsaved changes will be lost.</p>
    <div class="logout-modal-footer">
      <button onclick="cancelLogout()" class="modal-btn modal-btn-cancel">Stay</button>
      <button onclick="confirmLogout()" class="modal-btn modal-btn-confirm">Yes, sign out</button>
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
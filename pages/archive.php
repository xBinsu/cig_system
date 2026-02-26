<?php
/**
 * CIG Admin Dashboard - Document Archive Page
 * Displays archived documents and file management
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

// Get filter parameters
$search_query = $_GET['search'] ?? '';
$org_filter = $_GET['org'] ?? '';

// Get rejected submissions
try {
    $query = "
        SELECT s.*, u.full_name as submitted_by_name, o.org_name
        FROM submissions s
        LEFT JOIN users u ON s.user_id = u.user_id
        LEFT JOIN organizations o ON s.org_id = o.org_id
        WHERE s.status = 'rejected'
    ";
    $params = [];
    
    if ($org_filter) {
        $query .= " AND o.org_id = ?";
        $params[] = $org_filter;
    }
    
    if ($search_query) {
        $query .= " AND (s.title LIKE ? OR u.full_name LIKE ? OR o.org_name LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    
    $query .= " ORDER BY s.updated_at DESC";
    $rejected_submissions = $db->fetchAll($query, $params);
    
    // Get organizations for filter dropdown
    $organizations = $db->fetchAll("SELECT org_id, org_name FROM organizations ORDER BY org_name ASC");
} catch (Exception $e) {
    error_log('Archive Error: ' . $e->getMessage());
    $rejected_submissions = [];
    $organizations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Document Archive - Admin</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="../css/components.css">
<link rel="stylesheet" href="../css/archive.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php 
$current_page = 'archive';
$user_name = $user['full_name'] ?? '';
?>
<?php include 'navbar.php'; ?>

  <!-- DOCUMENT ARCHIVE -->
  <div class="page active">
    <div class="page-header">
      <h2><i class="fas fa-archive"></i> Archive - Rejected Submissions</h2>
    </div>


    <!-- SEARCH & FILTER -->
    <div class="search-filter-container">
      <form method="GET" class="search-filter-form">
        <div class="search-input-wrapper">
          <i class="fas fa-search search-icon"></i>
          <input type="text" name="search" placeholder="Search by title, submitter, or organization..." value="<?php echo htmlspecialchars($search_query); ?>" class="search-input">
        </div>
        <select name="org" class="filter-select">
          <option value="">All Organizations</option>
          <?php foreach ($organizations as $org): ?>
            <option value="<?php echo htmlspecialchars($org['org_id']); ?>" <?php echo $org_filter == $org['org_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($org['org_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($search_query || $org_filter): ?>
          <a href="archive.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- REJECTED SUBMISSIONS -->
    <div>
      <h3 style="font-size: 18px; font-weight: 700; color: #047857; margin: 0 0 20px 0; display: flex; align-items: center; gap: 10px;"><i class="fas fa-trash"></i> Rejected Submissions (<?php echo count($rejected_submissions); ?>)</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th><i class="fas fa-hashtag"></i> Ref No</th>
              <th><i class="fas fa-file-alt"></i> Title</th>
              <th><i class="fas fa-building"></i> Organization</th>
              <th><i class="fas fa-user"></i> Submitted By</th>
              <th><i class="fas fa-calendar"></i> Submission Date</th>
              <th><i class="fas fa-ban"></i> Rejected Date</th>
              <th><i class="fas fa-cog"></i> Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($rejected_submissions)): ?>
              <?php foreach ($rejected_submissions as $index => $submission): ?>
                <tr>
                  <td class="ref-number">#<?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?></td>
                  <td class="title-cell"><strong><?php echo htmlspecialchars($submission['title']); ?></strong></td>
                  <td><?php echo htmlspecialchars($submission['org_name'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($submission['submitted_by_name'] ?? 'N/A'); ?></td>
                  <td><?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?></td>
                  <td><?php echo date('M d, Y', strtotime($submission['updated_at'])); ?></td>
                  <td>
                    <div class="action-buttons">
                      <a href="archive.php?view=<?php echo $submission['submission_id']; ?>" class="btn-action btn-view" title="View Details"><i class="fas fa-eye"></i> View</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <!-- Sample Rejected Submissions -->
              <tr>
                <td class="ref-number">#001</td>
                <td class="title-cell"><strong>Incomplete Project Documentation</strong></td>
                <td>Research Department</td>
                <td>John Smith</td>
                <td>Feb 20, 2026</td>
                <td>Feb 21, 2026</td>
                <td><div class="action-buttons"><a href="#" class="btn-action btn-view" title="View Details"><i class="fas fa-eye"></i> View</a></div></td>
              </tr>
              <tr>
                <td class="ref-number">#002</td>
                <td class="title-cell"><strong>Budget Proposal - Missing Approvals</strong></td>
                <td>Finance Committee</td>
                <td>Sarah Johnson</td>
                <td>Feb 18, 2026</td>
                <td>Feb 19, 2026</td>
                <td><div class="action-buttons"><a href="#" class="btn-action btn-view" title="View Details"><i class="fas fa-eye"></i> View</a></div></td>
              </tr>
              <tr>
                <td class="ref-number">#003</td>
                <td class="title-cell"><strong>Facilities Maintenance Request</strong></td>
                <td>Operations Team</td>
                <td>Michael Chen</td>
                <td>Feb 15, 2026</td>
                <td>Feb 16, 2026</td>
                <td><div class="action-buttons"><a href="#" class="btn-action btn-view" title="View Details"><i class="fas fa-eye"></i> View</a></div></td>
              </tr>
              <tr>
                <td class="ref-number">#004</td>
                <td class="title-cell"><strong>Event Proposal - Insufficient Details</strong></td>
                <td>Student Activities</td>
                <td>Emma Wilson</td>
                <td>Feb 12, 2026</td>
                <td>Feb 13, 2026</td>
                <td><div class="action-buttons"><a href="#" class="btn-action btn-view" title="View Details"><i class="fas fa-eye"></i> View</a></div></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php include 'footer.php'; ?>
</div>

<style>
/* Archive page specific styles are loaded from components.css */
</style>

<script src="../js/navbar.js"></script>
<script src="../js/archive.js"></script>
<script>
function toggleNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>

<?php
// No additional functions needed for the new archive structure.
?>

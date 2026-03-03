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
      
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Ref No</th>
              <th>Title</th>
              <th>Organization</th>
              <th>Submitted By</th>
              <th>Submission Date</th>
              <th>Rejected Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($rejected_submissions)): ?>
              <?php foreach ($rejected_submissions as $index => $submission): ?>
                <tr>
                  <td><?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?></td>
                  <td><?php echo htmlspecialchars($submission['title']); ?></td>
                  <td><?php echo htmlspecialchars($submission['org_name'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($submission['submitted_by_name'] ?? 'N/A'); ?></td>
                  <td><?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?></td>
                  <td><?php echo date('M d, Y', strtotime($submission['updated_at'])); ?></td>
                  <td>
                    <a href="archive.php?view=<?php echo $submission['submission_id']; ?>" class="action-link"><i class="fas fa-eye"></i> View Details</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <!-- Sample Archived/Rejected Submissions -->
              <tr>
                <td>001</td>
                <td>Incomplete Project Documentation</td>
                <td>Research Department</td>
                <td>John Smith</td>
                <td>Feb 20, 2026</td>
                <td>Feb 21, 2026</td>
                <td>
                  <a href="#" class="action-link"><i class="fas fa-eye"></i> View Details</a>
                </td>
              </tr>
              <tr>
                <td>002</td>
                <td>Budget Proposal - Missing Approvals</td>
                <td>Finance Committee</td>
                <td>Sarah Johnson</td>
                <td>Feb 18, 2026</td>
                <td>Feb 19, 2026</td>
                <td>
                  <a href="#" class="action-link"><i class="fas fa-eye"></i> View Details</a>
                </td>
              </tr>
              <tr>
                <td>003</td>
                <td>Facilities Maintenance Request</td>
                <td>Operations Team</td>
                <td>Michael Chen</td>
                <td>Feb 15, 2026</td>
                <td>Feb 16, 2026</td>
                <td>
                  <a href="#" class="action-link"><i class="fas fa-eye"></i> View Details</a>
                </td>
              </tr>
              <tr>
                <td>004</td>
                <td>Event Proposal - Insufficient Details</td>
                <td>Student Activities</td>
                <td>Emma Wilson</td>
                <td>Feb 12, 2026</td>
                <td>Feb 13, 2026</td>
                <td>
                  <a href="#" class="action-link"><i class="fas fa-eye"></i> View Details</a>
                </td>
              </tr>
              <tr>
                <td>005</td>
                <td>Policy Review Update - Rejected</td>
                <td>Academic Affairs</td>
                <td>Dr. Patricia Lee</td>
                <td>Feb 10, 2026</td>
                <td>Feb 11, 2026</td>
                <td>
                  <a href="#" class="action-link"><i class="fas fa-eye"></i> View Details</a>
                </td>
              </tr>
              <tr>
                <td>006</td>
                <td>Compliance Audit Report</td>
                <td>Audit Committee</td>
                <td>James Richardson</td>
                <td>Feb 08, 2026</td>
                <td>Feb 09, 2026</td>
                <td>
                  <a href="#" class="action-link"><i class="fas fa-eye"></i> View Details</a>
                </td>
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
.search-filter-container {
  margin-bottom: 30px;
  padding: 20px 24px;
  background: linear-gradient(135deg, #f5f7ff 0%, #f0f4ff 100%);
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0, 123, 255, 0.1);
  border: 1px solid rgba(0, 123, 255, 0.15);
}

.search-filter-form {
  display: flex;
  gap: 12px;
  align-items: center;
  max-width: 100%;
  flex-wrap: wrap;
}

.search-input-wrapper {
  flex: 1;
  min-width: 280px;
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 16px;
  color: #007bff;
  font-size: 18px;
  pointer-events: none;
  transition: all 0.3s ease;
}

.search-input {
  flex: 1;
  padding: 12px 14px 12px 45px;
  border: 2px solid #e8eef9;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 500;
  transition: all 0.3s ease;
  background: white;
}

.search-input:focus {
  outline: none;
  border-color: #007bff;
  background: white;
  box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.12), 0 4px 12px rgba(0, 123, 255, 0.15);
}

.search-input::placeholder {
  color: #a8b5c8;
  font-weight: 400;
}

.filter-select {
  padding: 12px 14px;
  border: 2px solid #e8eef9;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 500;
  background: white;
  cursor: pointer;
  transition: all 0.3s ease;
  color: #333;
}

.filter-select:focus {
  outline: none;
  border-color: #007bff;
  background: white;
  box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.12), 0 4px 12px rgba(0, 123, 255, 0.15);
}

.btn-search {
  padding: 12px 20px;
  background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
  color: white;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-size: 15px;
  font-weight: 600;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
  white-space: nowrap;
}

.btn-search:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
  background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
}

.btn-search:active {
  transform: translateY(-1px);
}

.btn-clear {
  padding: 12px 20px;
  background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
  color: white;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-size: 15px;
  font-weight: 600;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
  white-space: nowrap;
}

.btn-clear:hover {
  background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
}

.btn-clear:active {
  transform: translateY(-1px);
}
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

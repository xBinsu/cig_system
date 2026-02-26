<?php
/**
 * CIG Admin Dashboard - Submissions Page
 * Displays all submissions with database integration
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
$status_filter = $_GET['status'] ?? null;
$search_query = $_GET['search'] ?? '';

// Build query - Only show pending and in_review submissions
$query = "
    SELECT s.*, u.full_name as submitted_by_name, o.org_name 
    FROM submissions s
    LEFT JOIN users u ON s.user_id = u.user_id
    LEFT JOIN organizations o ON s.org_id = o.org_id
    WHERE s.status IN ('pending', 'in_review')
";
$params = [];

if ($search_query) {
    $query .= " AND (s.title LIKE ? OR o.org_name LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$query .= " ORDER BY s.submitted_at DESC";

try {
    $submissions = $db->fetchAll($query, $params);
} catch (Exception $e) {
    error_log('Submissions Error: ' . $e->getMessage());
    $submissions = [];
}

// Sample submissions for demonstration (pending and in_review only)
$sample_submissions = [
    [
        'submission_id' => 1,
        'title' => 'New Library Computer Lab Setup',
        'org_name' => 'Technology Department',
        'status' => 'pending',
        'submitted_by_name' => 'David Martinez',
        'submitted_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
    ],
    [
        'submission_id' => 2,
        'title' => 'Campus Safety Improvement Proposal',
        'org_name' => 'Security Committee',
        'status' => 'in_review',
        'submitted_by_name' => 'Maria Cruz',
        'submitted_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
    ],
    [
        'submission_id' => 3,
        'title' => 'Budget Allocation for School Events',
        'org_name' => 'Finance Board',
        'status' => 'pending',
        'submitted_by_name' => 'Robert Tanaka',
        'submitted_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
    ],
    [
        'submission_id' => 4,
        'title' => 'Renovation Plan - Student Center',
        'org_name' => 'Facilities Management',
        'status' => 'in_review',
        'submitted_by_name' => 'Lisa Anderson',
        'submitted_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
    ],
    [
        'submission_id' => 5,
        'title' => 'Parking Expansion Initiative',
        'org_name' => 'Transportation Services',
        'status' => 'pending',
        'submitted_by_name' => 'James Park',
        'submitted_at' => date('Y-m-d H:i:s', strtotime('-4 days'))
    ]
];

// Use sample submissions if no real submissions exist
if (empty($submissions)) {
    $submissions = $sample_submissions;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Submissions - Admin</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="../css/components.css">
<link rel="stylesheet" href="../css/submissions.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php 
$current_page = 'submissions';
$user_name = $user['full_name'] ?? '';
?>
<?php include 'navbar.php'; ?>

  <!-- SUBMISSIONS -->
  <div class="page active">
    <div class="page-header">
      <h2><i class="fas fa-file-alt"></i> Submissions</h2>
    </div>
    
    <div class="search-filter-container">
      <form method="GET" class="search-filter-form">
        <div class="search-input-wrapper">
          <i class="fas fa-search search-icon"></i>
          <input type="text" name="search" placeholder="Search submissions..." value="<?php echo htmlspecialchars($search_query); ?>" class="search-input">
        </div>
      </form>
    </div>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th><i class="fas fa-hashtag"></i> Ref No</th>
            <th><i class="fas fa-building"></i> Organization</th>
            <th><i class="fas fa-file-alt"></i> Title</th>
            <th><i class="fas fa-tag"></i> Status</th>
            <th><i class="fas fa-user"></i> Submitted By</th>
            <th><i class="fas fa-calendar"></i> Date</th>
            <th><i class="fas fa-cog"></i> Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($submissions)): ?>
            <?php foreach ($submissions as $index => $submission): ?>
              <tr>
                <td class="ref-number">#<?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo htmlspecialchars($submission['org_name'] ?? 'N/A'); ?></td>
                <td class="title-cell"><strong><?php echo htmlspecialchars($submission['title']); ?></strong></td>
                <td>
                  <span class="status-badge <?php echo strtolower($submission['status']); ?>">
                    <i class="fas fa-circle"></i> <?php echo ucfirst(str_replace('_', ' ', $submission['status'])); ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars($submission['submitted_by_name'] ?? 'N/A'); ?></td>
                <td><?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?></td>
                <td>
                  <div class="action-buttons">
                    <a href="#" onclick="previewSubmission(<?php echo $submission['submission_id']; ?>); return false;" class="btn-action btn-view" title="Preview"><i class="fas fa-eye"></i> Preview</a>
                    <button class="btn-action btn-approve" onclick="approveSubmission(<?php echo $submission['submission_id']; ?>)" title="Approve" <?php echo in_array($submission['status'], ['approved']) ? 'disabled' : ''; ?>>
                      <i class="fas fa-check"></i> Approve
                    </button>
                    <button class="btn-action btn-reject" onclick="rejectSubmission(<?php echo $submission['submission_id']; ?>)" title="Reject" <?php echo in_array($submission['status'], ['rejected']) ? 'disabled' : ''; ?>>
                      <i class="fas fa-times"></i> Reject
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="empty-row">No submissions found</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php include 'footer.php'; ?>
</div>

<script src="../js/navbar.js"></script>
<script src="../js/submissions.js"></script>

<script>
function toggleNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function previewSubmission(id) {
    console.log('Preview submission: ' + id);
}

function approveSubmission(id) {
    console.log('Approve submission: ' + id);
}

function rejectSubmission(id) {
    console.log('Reject submission: ' + id);
}
</script>
</body>
</html>

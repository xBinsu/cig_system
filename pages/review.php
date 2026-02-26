<?php
/**
 * CIG Admin Dashboard - Review & Approval Page
 * Folder-based submission review and approval workflow
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

// Get selected organization from URL
$selected_org_id = $_GET['org'] ?? null;
$selected_submission_id = $_GET['id'] ?? null;
$search_query = $_GET['search'] ?? '';

// Get all organizations (49 for now - can be changed later)
$organizations = [];
try {
    $organizations = $db->fetchAll("
        SELECT org_id, org_name, org_code,
               (SELECT COUNT(*) FROM submissions WHERE org_id = organizations.org_id AND status = 'approved') as submission_count
        FROM organizations
        ORDER BY org_name ASC
        LIMIT 49
    ");
    
    // If we have less than 49, we can keep what we have or the user can add more
} catch (Exception $e) {
    error_log('Organizations Error: ' . $e->getMessage());
    $organizations = [];
}

// Sample organizations for demonstration
$sample_organizations = [
    [
        'org_id' => 1,
        'org_name' => 'Student Government Association',
        'org_code' => 'SGA',
        'submission_count' => 2
    ],
    [
        'org_id' => 2,
        'org_name' => 'Cultural and Arts Club',
        'org_code' => 'CAC',
        'submission_count' => 1
    ],
    [
        'org_id' => 3,
        'org_name' => 'Academic Excellence Board',
        'org_code' => 'AEB',
        'submission_count' => 2
    ]
];

// Use sample organizations if no real organizations exist
if (empty($organizations)) {
    $organizations = $sample_organizations;
}

// Get submissions for selected organization (approved only)
$submissions = [];
$current_org = null;
if ($selected_org_id) {
    try {
        // Get organization details
        $current_org = $db->fetchRow("
            SELECT * FROM organizations WHERE org_id = ?
        ", [$selected_org_id]);
        
        // Use sample organization if not found
        if (!$current_org) {
            $current_org = [
                'org_id' => 1,
                'org_name' => 'Student Government Association',
                'org_code' => 'SGA',
                'description' => 'Main student government body'
            ];
        }
        
        // Get approved submissions for this organization
        $query = "
            SELECT s.*, u.full_name as submitted_by_name, 
                   (SELECT COUNT(*) FROM reviews WHERE submission_id = s.submission_id) as review_count
            FROM submissions s
            LEFT JOIN users u ON s.user_id = u.user_id
            WHERE s.org_id = ? AND s.status = 'approved'
        ";
        $params = [$selected_org_id];
        
        if ($search_query) {
            $query .= " AND (s.title LIKE ? OR u.full_name LIKE ?)";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
        }
        
        $query .= " ORDER BY s.submitted_at DESC";
        $submissions = $db->fetchAll($query, $params);
    } catch (Exception $e) {
        error_log('Submissions Query Error: ' . $e->getMessage());
        $submissions = [];
    }
}

// Get individual submission details
$submission = null;
if ($selected_submission_id && $selected_org_id) {
    try {
        $submission = $db->fetchRow("
            SELECT s.*, u.full_name as submitted_by_name, o.org_name
            FROM submissions s
            LEFT JOIN users u ON s.user_id = u.user_id
            LEFT JOIN organizations o ON s.org_id = o.org_id
            WHERE s.submission_id = ? AND s.org_id = ?
        ", [$selected_submission_id, $selected_org_id]);
        
        // Get reviews for this submission
        if ($submission) {
            $reviews = $db->fetchAll("
                SELECT r.*, u.full_name, u.username
                FROM reviews r
                LEFT JOIN users u ON r.reviewer_id = u.user_id
                WHERE r.submission_id = ?
                ORDER BY r.reviewed_at DESC
            ", [$selected_submission_id]);
        } else {
            // Sample submission for demonstration
            $submission = [
                'submission_id' => 101,
                'title' => 'New Student Governance Initiative',
                'description' => 'This initiative aims to improve student government representation and involvement across all academic departments.',
                'org_name' => 'Student Government Association',
                'status' => 'approved',
                'submitted_by_name' => 'John Santos',
                'submitted_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
            ];
            $reviews = [];
        }
    } catch (Exception $e) {
        error_log('Review Page Error: ' . $e->getMessage());
    }
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $submission_id = $_POST['submission_id'];

    try {
        if ($action === 'approve') {
            $db->update('submissions', ['status' => 'approved'], 'submission_id = ?', [$submission_id]);
            $db->insert('reviews', [
                'submission_id' => $submission_id,
                'reviewer_id' => 1,
                'feedback' => $_POST['feedback'] ?? '',
                'status' => 'approved'
            ]);
        } elseif ($action === 'reject') {
            $db->update('submissions', ['status' => 'rejected'], 'submission_id = ?', [$submission_id]);
            $db->insert('reviews', [
                'submission_id' => $submission_id,
                'reviewer_id' => 1,
                'feedback' => $_POST['feedback'] ?? '',
                'status' => 'rejected'
            ]);
        }
        header('Location: review.php?org=' . $submission['org_id'] . '&id=' . $submission_id . '&success=1');
        exit();
    } catch (Exception $e) {
        error_log('Review Update Error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review & Approval - Admin</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="../css/components.css">
<link rel="stylesheet" href="../css/review.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>
<body>

<?php 
$current_page = 'review';
$user_name = $user['full_name'] ?? '';
?>
<?php include 'navbar.php'; ?>

  <!-- REVIEW & APPROVAL -->
  <div class="page active">
    <div class="page-header">
      <h2><i class="fas fa-check-circle"></i> Review & Approval</h2>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="success-alert">
      <i class="fas fa-check-circle"></i>
      <span>Review submitted successfully!</span>
    </div>
    <?php endif; ?>

    <?php if ($selected_submission_id && $submission && $submission['status'] === 'approved'): ?>
      <!-- SUBMISSION DETAIL VIEW -->
      <div class="breadcrumb-nav">
        <a href="review.php"><i class="fas fa-folder-open"></i> Organizations</a>
        <i class="fas fa-chevron-right"></i>
        <a href="review.php?org=<?php echo $selected_org_id; ?>"><i class="fas fa-building"></i> <?php echo htmlspecialchars($current_org['org_name'] ?? ''); ?></a>
        <i class="fas fa-chevron-right"></i>
        <span><i class="fas fa-file"></i> <?php echo htmlspecialchars($submission['title']); ?></span>
      </div>

      <div class="submission-detail-card">
        <div class="submission-header">
          <h3><?php echo htmlspecialchars($submission['title']); ?></h3>
          <span class="status-badge <?php echo strtolower($submission['status']); ?>">
            <i class="fas fa-check-circle"></i> <?php echo ucfirst($submission['status']); ?>
          </span>
        </div>

        <div class="submission-meta">
          <div class="meta-item">
            <span class="meta-label"><i class="fas fa-building"></i> Organization</span>
            <span class="meta-value"><?php echo htmlspecialchars($submission['org_name']); ?></span>
          </div>
          <div class="meta-item">
            <span class="meta-label"><i class="fas fa-user"></i> Submitted By</span>
            <span class="meta-value"><?php echo htmlspecialchars($submission['submitted_by_name']); ?></span>
          </div>
          <div class="meta-item">
            <span class="meta-label"><i class="fas fa-calendar"></i> Submitted At</span>
            <span class="meta-value"><?php echo date('M d, Y H:i', strtotime($submission['submitted_at'])); ?></span>
          </div>
        </div>

        <div class="submission-description">
          <h4>Description</h4>
          <p><?php echo htmlspecialchars($submission['description']); ?></p>
        </div>
      </div>

      <!-- REVIEWS SECTION -->
      <div class="reviews-section">
        <h3><i class="fas fa-comments"></i> Reviews (<?php echo isset($reviews) ? count($reviews) : 0; ?>)</h3>
        
        <?php if (!empty($reviews)): ?>
          <div class="reviews-list">
            <?php foreach ($reviews as $review): ?>
              <div class="review-item">
                <div class="review-header">
                  <div class="reviewer-info">
                    <span class="reviewer-name"><?php echo htmlspecialchars($review['full_name'] ?? 'Unknown'); ?></span>
                    <span class="review-date"><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($review['reviewed_at'])); ?></span>
                  </div>
                  <span class="review-status-badge <?php echo strtolower($review['status']); ?>">
                    <?php echo ucfirst($review['status']); ?>
                  </span>
                </div>
                <div class="review-feedback">
                  <p><?php echo htmlspecialchars($review['feedback']); ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="no-reviews">
            <i class="fas fa-inbox"></i>
            <span>No reviews yet</span>
          </div>
        <?php endif; ?>
      </div>

      <!-- SUBMISSION ACTIONS -->
      <div class="submission-actions-card">
        <div class="actions-header">
          <h3><i class="fas fa-check-double"></i> Submission Complete</h3>
          <p>This submission has been approved and is ready for organizational review.</p>
        </div>
        <div class="actions-buttons">
          <a href="review.php?org=<?php echo $selected_org_id; ?>" class="btn-action btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Organization
          </a>
        </div>
      </div>

    <?php elseif ($selected_org_id && $current_org): ?>
      <!-- ORGANIZATION SUBMISSIONS TABLE VIEW -->
      <div class="breadcrumb-nav">
        <a href="review.php"><i class="fas fa-folder-open"></i> Organizations</a>
        <i class="fas fa-chevron-right"></i>
        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($current_org['org_name']); ?></span>
      </div>

      <div class="org-header">
        <h3><?php echo htmlspecialchars($current_org['org_name']); ?> - Submissions</h3>
        <p class="org-code">Code: <?php echo htmlspecialchars($current_org['org_code']); ?></p>
      </div>
      
      <div class="search-filter-container">
        <form method="GET" class="search-filter-form">
          <input type="hidden" name="org" value="<?php echo htmlspecialchars($selected_org_id); ?>">
          <div class="search-input-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" name="search" placeholder="Search by title or submitter..." value="<?php echo htmlspecialchars($search_query); ?>" class="search-input">
          </div>
          <?php if ($search_query): ?>
            <a href="review.php?org=<?php echo htmlspecialchars($selected_org_id); ?>" class="btn-clear">
              <i class="fas fa-times"></i> Clear
            </a>
          <?php endif; ?>
        </form>
      </div>
      
      <div class="table-container">
        <table class="submissions-table">
          <thead>
            <tr>
              <th><i class="fas fa-hashtag"></i> Ref #</th>
              <th><i class="fas fa-file-alt"></i> Title</th>
              <th><i class="fas fa-tag"></i> Status</th>
              <th><i class="fas fa-user"></i> Submitted By</th>
              <th><i class="fas fa-calendar"></i> Date</th>
              <th><i class="fas fa-comments"></i> Reviews</th>
              <th><i class="fas fa-cog"></i> Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($submissions)): ?>
              <?php foreach ($submissions as $index => $sub): ?>
                <tr>
                  <td class="ref-number">#<?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?></td>
                  <td class="title-cell"><strong><?php echo htmlspecialchars($sub['title']); ?></strong></td>
                  <td><span class="status-badge <?php echo strtolower($sub['status']); ?>"><i class="fas fa-check-circle"></i> <?php echo ucfirst($sub['status']); ?></span></td>
                  <td><?php echo htmlspecialchars($sub['submitted_by_name'] ?? 'N/A'); ?></td>
                  <td><?php echo date('M d, Y', strtotime($sub['submitted_at'])); ?></td>
                  <td class="review-count"><span class="badge-count"><?php echo $sub['review_count'] ?? 0; ?></span></td>
                  <td>
                    <div class="action-buttons">
                      <a href="review.php?org=<?php echo $selected_org_id; ?>&id=<?php echo $sub['submission_id']; ?>" class="btn-action btn-view" title="Preview">
                        <i class="fas fa-eye"></i> Preview
                      </a>
                      <button class="btn-action btn-download" onclick="downloadSubmission(<?php echo $sub['submission_id']; ?>)" title="Download">
                        <i class="fas fa-download"></i> Download
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr class="empty-row">
                <td colspan="7">
                  <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No submissions found</p>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php else: ?>
      <!-- ORGANIZATION FOLDERS VIEW -->
      <div class="folders-header">
        <h3><i class="fas fa-inbox"></i> Organization Folders</h3>
        <p>Select an organization to view and review submissions</p>
      </div>
      
      <div class="folders-grid">
        <?php 
        // Display 49 organization folders
        for ($i = 1; $i <= 49; $i++): 
          $org = $organizations[$i - 1] ?? null;
        ?>
          <?php if ($org): ?>
            <a href="review.php?org=<?php echo htmlspecialchars($org['org_id']); ?>" class="folder-card">
              <div class="folder-icon">
                <i class="fas fa-folder"></i>
              </div>
              <div class="folder-content">
                <p class="folder-name"><?php echo htmlspecialchars($org['org_name']); ?></p>
                <p class="folder-code"><?php echo htmlspecialchars($org['org_code']); ?></p>
              </div>
              <div class="folder-stats">
                <span class="stat-badge">
                  <i class="fas fa-file-alt"></i>
                  <?php echo $org['submission_count'] ?? 0; ?> submissions
                </span>
              </div>
              <div class="folder-hover-arrow">
                <i class="fas fa-chevron-right"></i>
              </div>
            </a>
          <?php else: ?>
            <div class="folder-card folder-empty">
              <div class="folder-icon">
                <i class="fas fa-folder"></i>
              </div>
              <div class="folder-content">
                <p class="folder-name">Empty Slot</p>
                <p class="folder-code">--</p>
              </div>
            </div>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php include 'footer.php'; ?>
</div>

<script src="../js/navbar.js"></script>
<script src="../js/review.js"></script>
<script>
function downloadSubmission(id) {
    console.log('Downloading submission: ' + id);
    // Implementation would go here
}
</script>

</body>
</html>

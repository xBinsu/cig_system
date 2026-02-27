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
        
        // Sample submissions for demonstration
        if (empty($submissions)) {
            $sample_submissions = [
                [
                    'submission_id' => 101,
                    'title' => 'New Student Governance Initiative',
                    'status' => 'approved',
                    'submitted_by_name' => 'John Santos',
                    'submitted_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                    'review_count' => 0
                ],
                [
                    'submission_id' => 102,
                    'title' => 'Campus Sustainability Program',
                    'status' => 'approved',
                    'submitted_by_name' => 'Maria Gonzalez',
                    'submitted_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'review_count' => 0
                ]
            ];
            $submissions = $sample_submissions;
        }
    } catch (Exception $e) {
        error_log('Submissions Error: ' . $e->getMessage());
    }
}

// Get submission details if viewing a specific submission
$submission = null;
$reviews = [];
if ($selected_submission_id) {
    try {
        $submission = $db->fetchRow("
            SELECT s.*, u.full_name as submitted_by_name, o.org_name 
            FROM submissions s
            LEFT JOIN users u ON s.user_id = u.user_id
            LEFT JOIN organizations o ON s.org_id = o.org_id
            WHERE s.submission_id = ?
        ", [$selected_submission_id]);

        if ($submission) {
            $reviews = $db->fetchAll("
                SELECT r.*, u.full_name as reviewer_name
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
    <div style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
      ✓ Review submitted successfully!
    </div>
    <?php endif; ?>

    <?php if ($selected_submission_id && $submission && $submission['status'] === 'approved'): ?>
      <!-- SUBMISSION DETAIL VIEW -->
      <div class="breadcrumb">
        <a href="review.php">Organizations</a> / 
        <a href="review.php?org=<?php echo $selected_org_id; ?>"><?php echo htmlspecialchars($current_org['org_name'] ?? ''); ?></a> / 
        <span><?php echo htmlspecialchars($submission['title']); ?></span>
      </div>

      <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h3><?php echo htmlspecialchars($submission['title']); ?></h3>
        <p><strong>Organization:</strong> <?php echo htmlspecialchars($submission['org_name'] ?? 'N/A'); ?></p>
        <p><strong>Status:</strong> <span class="status <?php echo strtolower($submission['status']); ?>"><?php echo ucfirst($submission['status']); ?></span></p>
        <p><strong>Submitted By:</strong> <?php echo htmlspecialchars($submission['submitted_by_name']); ?></p>
        <p><strong>Submitted Date:</strong> <?php echo date('M d, Y H:i', strtotime($submission['submitted_at'])); ?></p>
        <p><strong>Description:</strong></p>
        <p><?php echo htmlspecialchars($submission['description']); ?></p>
      </div>

      <!-- REVIEWS HISTORY -->
      <div style="margin-bottom: 20px;">
        <h3>Review History</h3>
        <?php if (!empty($reviews)): ?>
          <?php foreach ($reviews as $review): ?>
            <div style="background-color: #f8f9fa; padding: 15px; margin-bottom: 10px; border-radius: 4px; border-left: 4px solid #007bff;">
              <p><strong><?php echo htmlspecialchars($review['reviewer_name']); ?></strong> - <?php echo date('M d, Y H:i', strtotime($review['reviewed_at'])); ?></p>
              <p style="color: #666; margin: 5px 0;">Status: <strong><?php echo ucfirst($review['status']); ?></strong></p>
              <p style="color: #666; margin: 5px 0;">Feedback: <?php echo htmlspecialchars($review['feedback']); ?></p>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="color: #999;">No reviews yet</p>
        <?php endif; ?>
      </div>

      <!-- SUBMISSION ACTIONS -->
      <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px;">
        <h3>Submission Complete</h3>
        <p style="color: #666; margin-bottom: 15px;">This submission has been approved and is ready for organizational review.</p>
        <div style="display: flex; gap: 10px;">
          <a href="review.php?org=<?php echo $selected_org_id; ?>" style="padding: 10px 20px; background-color: #6c757d; color: white; border: none; border-radius: 4px; text-decoration: none; display: inline-block;">← Back to Organization</a>
        </div>
      </div>

    <?php elseif ($selected_org_id && $current_org): ?>
      <!-- ORGANIZATION SUBMISSIONS TABLE VIEW -->
      <div class="breadcrumb">
        <a href="review.php">Organizations</a> / 
        <span><?php echo htmlspecialchars($current_org['org_name']); ?></span>
      </div>

      <h3><?php echo htmlspecialchars($current_org['org_name']); ?> - Submissions</h3>
      
      <div class="search-filter-container">
        <form method="GET" class="search-filter-form">
          <input type="hidden" name="org" value="<?php echo htmlspecialchars($selected_org_id); ?>">
          <div class="search-input-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" name="search" placeholder="Search by title or submitter..." value="<?php echo htmlspecialchars($search_query); ?>" class="search-input">
          </div>
          <?php if ($search_query): ?>
            <a href="review.php?org=<?php echo htmlspecialchars($selected_org_id); ?>" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
          <?php endif; ?>
        </form>
      </div>
      
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Ref #</th>
              <th>Title</th>
              <th>Status</th>
              <th>Submitted By</th>
              <th>Date</th>
              <th>Reviews</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($submissions)): ?>
              <?php foreach ($submissions as $index => $sub): ?>
                <tr>
                  <td><?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?></td>
                  <td><?php echo htmlspecialchars($sub['title']); ?></td>
                  <td><span class="status <?php echo strtolower($sub['status']); ?>"><?php echo ucfirst($sub['status']); ?></span></td>
                  <td><?php echo htmlspecialchars($sub['submitted_by_name'] ?? 'N/A'); ?></td>
                  <td><?php echo date('M d, Y', strtotime($sub['submitted_at'])); ?></td>
                  <td><?php echo $sub['review_count'] ?? 0; ?></td>
                  <td>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                      <a href="review.php?org=<?php echo $selected_org_id; ?>&id=<?php echo $sub['submission_id']; ?>" class="action-btn open-btn" title="Preview"><i class="fas fa-eye"></i> Preview</a>
                      <button class="action-btn download-btn" onclick="downloadSubmission(<?php echo $sub['submission_id']; ?>)" title="Download"><i class="fas fa-download"></i> Download</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" style="text-align: center; color: #999;">No submissions found</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php else: ?>
      <!-- ORGANIZATION FOLDERS VIEW -->
      <p style="color: #666; margin-bottom: 20px;">Select an organization folder to view and review submissions</p>
      
      <div class="folders-container">
        <?php 
        // Display 49 organization folders
        for ($i = 1; $i <= 49; $i++): 
          $org = isset($organizations[$i - 1]) ? $organizations[$i - 1] : null;
          if ($org):
            $count = $org['submission_count'] ?? 0;
            $org_id = $org['org_id'];
            $org_name = htmlspecialchars($org['org_name']);
        ?>
          <a href="review.php?org=<?php echo $org_id; ?>" class="folder-item">
            <div class="folder-icon">📁</div>
            <div class="folder-name"><?php echo $org_name; ?></div>
            <div class="folder-count"><?php echo $count; ?> submission<?php echo $count !== 1 ? 's' : ''; ?></div>
          </a>
        <?php 
          else:
            // Empty placeholder for organizations that don't exist yet
        ?>
          <div class="folder-item" style="opacity: 0.5; cursor: default; background-color: #f0f0f0;">
            <div class="folder-icon">📁</div>
            <div class="folder-name">Org <?php echo $i; ?></div>
            <div class="folder-count">0 submissions</div>
          </div>
        <?php 
          endif;
        endfor; 
        ?>
      </div>
    <?php endif; ?>
  </div>
  <?php include 'footer.php'; ?>
</div>

<script src="../js/navbar.js"></script>
<script src="../js/review.js"></script>

<script>
function toggleNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function downloadSubmission(submissionId) {
  fetch('../api/submissions.php?action=download&submission_id=' + submissionId)
    .then(response => {
      if (response.ok) {
        return response.blob();
      }
      throw new Error('Download failed');
    })
    .then(blob => {
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'submission_' + submissionId + '.json';
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Failed to download submission');
    });
}
</script>
</body>
</html>

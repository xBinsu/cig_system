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

  <!-- REVIEW & APPROVAL & ORGANIZATIONS -->
  <div class="page active">
    <div class="page-header">
      <h2><i class="fas fa-cogs"></i> Organization Management & Reviews</h2>
      <?php if (!$selected_org_id && !$selected_submission_id): ?>
      <div class="page-header-search">
        <div class="search-input-wrapper-header">
          <i class="fas fa-search search-icon"></i>
          <input type="text" id="orgSearchInput" placeholder="Search organizations..." class="search-input-header">
        </div>
      </div>
      <?php endif; ?>
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

<<<<<<< Updated upstream
      <h3><?php echo htmlspecialchars($current_org['org_name']); ?> </h3>
=======
      <div class="org-header-section">
        <div class="org-header-icon">
          <i class="fas fa-building"></i>
        </div>
        <div class="org-header-content">
          <div class="org-header-title">
            <h3><?php echo htmlspecialchars($current_org['org_name']); ?></h3>
            <span class="org-status-badge"><?php echo strtoupper($current_org['status'] ?? 'active'); ?></span>
          </div>
          <div class="org-header-meta">
            <span class="meta-item"><i class="fas fa-code"></i> <?php echo htmlspecialchars($current_org['org_code'] ?? 'N/A'); ?></span>
          </div>
        </div>
        <button onclick="showOrgInfoModal()" class="btn-info">
          <i class="fas fa-info-circle"></i> <span>Organization Info</span>
        </button>
      </div>
>>>>>>> Stashed changes
      
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
<<<<<<< Updated upstream
      <!-- ORGANIZATION FOLDERS VIEW -->
      <p style="color: #666; margin-bottom: 20px;">Select an organization folder to view and review submissions</p>
=======
>>>>>>> Stashed changes
      
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

  <!-- ORGANIZATION INFO MODAL -->
  <div id="orgInfoModal" class="modal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2><i class="fas fa-building"></i> Organization Information</h2>
        <button type="button" onclick="closeOrgInfoModal()" class="modal-close">✕</button>
      </div>
      <div class="modal-body org-info-content">
        <div class="info-section">
          <div class="info-grid">
            <div class="info-item">
              <label>Organization Name</label>
              <p><?php echo htmlspecialchars($current_org['org_name'] ?? 'N/A'); ?></p>
            </div>
            <div class="info-item">
              <label>Organization Code</label>
              <p><?php echo htmlspecialchars($current_org['org_code'] ?? 'N/A'); ?></p>
            </div>
            <div class="info-item">
              <label>Status</label>
              <p><span class="status-badge <?php echo strtolower($current_org['status'] ?? 'active'); ?>"><i class="fas fa-circle"></i> <?php echo ucfirst($current_org['status'] ?? 'active'); ?></span></p>
            </div>
            <div class="info-item">
              <label>Email</label>
              <p><?php echo htmlspecialchars($current_org['email'] ?? 'N/A'); ?></p>
            </div>
            <div class="info-item">
              <label>Phone</label>
              <p><?php echo htmlspecialchars($current_org['phone'] ?? 'N/A'); ?></p>
            </div>
            <div class="info-item">
              <label>Contact Person</label>
              <p><?php echo htmlspecialchars($current_org['contact_person'] ?? 'N/A'); ?></p>
            </div>
          </div>
          
          <?php if (!empty($current_org['description'])): ?>
          <div class="description-section">
            <label>Description</label>
            <p><?php echo htmlspecialchars($current_org['description']); ?></p>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeOrgInfoModal()" class="btn-secondary">Close</button>
      </div>
    </div>
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

function showOrgInfoModal() {
    document.getElementById('orgInfoModal').style.display = 'flex';
}

function closeOrgInfoModal() {
    document.getElementById('orgInfoModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('orgInfoModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<style>
/* PAGE HEADER WITH SEARCH */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  gap: 20px;
  flex-wrap: wrap;
}

.page-header h2 {
  flex: 1;
  min-width: 300px;
}

.page-header-search {
  min-width: 300px;
}

.search-input-wrapper-header {
  position: relative;
  display: flex;
  align-items: center;
}

.search-input-header {
  width: 100%;
  padding: 10px 16px 10px 40px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  font-size: 14px;
  transition: all 0.3s ease;
  background: white;
}

.search-input-header:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
  background: #f9fafb;
}

.search-input-wrapper-header .search-icon {
  position: absolute;
  left: 12px;
  color: #9ca3af;
  font-size: 14px;
  pointer-events: none;
}

.search-input-header::placeholder {
  color: #d1d5db;
}

/* ORGANIZATION HEADER SECTION */
.org-header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 40px;
  padding: 28px 30px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 1) 100%);
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(16, 185, 129, 0.12),
              inset 0 1px 0 rgba(255, 255, 255, 0.8);
  border: 1.5px solid rgba(16, 185, 129, 0.15);
  position: relative;
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.org-header-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #10b981 0%, #059669 50%, #047857 100%);
}

.org-header-section:hover {
  box-shadow: 0 12px 40px rgba(16, 185, 129, 0.16),
              inset 0 1px 0 rgba(255, 255, 255, 0.8);
  transform: translateY(-4px);
}

.org-header-icon {
  width: 70px;
  height: 70px;
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  color: #10b981;
  box-shadow: 0 8px 24px rgba(16, 185, 129, 0.2);
  flex-shrink: 0;
  animation: iconFloat 3s ease-in-out infinite;
}

@keyframes iconFloat {
  0%, 100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-8px);
  }
}

.org-header-content {
  flex: 1;
  margin-left: 24px;
  min-width: 0;
}

.org-header-title {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.org-header-title h3 {
  margin: 0;
  font-size: 1.6em;
  color: #1a202c;
  font-weight: 800;
  letter-spacing: -0.5px;
}

.org-status-badge {
  display: inline-block;
  padding: 6px 12px;
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  color: #065f46;
  font-size: 0.75em;
  font-weight: 700;
  border-radius: 8px;
  letter-spacing: 0.5px;
}

.org-header-meta {
  display: flex;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
}

.org-header-meta .meta-item {
  font-size: 0.9em;
  color: #718096;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 6px;
}

.org-header-meta .meta-item i {
  color: #10b981;
  font-size: 1em;
}

.btn-info {
  padding: 12px 28px;
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  color: white;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 700;
  font-size: 0.95em;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
  display: flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
}

.btn-info::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: left 0.6s ease;
}

.btn-info:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
}

.btn-info:hover::before {
  left: 100%;
}

.btn-info:active {
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
}

.btn-info span {
  font-size: 0.95em;
  letter-spacing: 0.3px;
}

/* ORGANIZATION INFO MODAL */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  justify-content: center;
  align-items: center;
  animation: fadeIn 0.3s ease-in-out;
  backdrop-filter: blur(4px);
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal-content {
  background: white;
  padding: 0;
  border-radius: 12px;
  width: 90%;
  max-width: 600px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  background: linear-gradient(135deg, #047857 0%, #10b981 100%);
  color: white;
  padding: 25px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: none;
}

.modal-header h2 {
  margin: 0;
  font-size: 1.4em;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-close {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  font-size: 24px;
  cursor: pointer;
  width: 35px;
  height: 35px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.modal-close:hover {
  background: rgba(255, 255, 255, 0.3);
}

.modal-body {
  padding: 30px;
  max-height: 60vh;
  overflow-y: auto;
}

.org-info-content .info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.info-item {
  display: flex;
  flex-direction: column;
}

.info-item label {
  font-weight: 700;
  color: #047857;
  font-size: 0.85em;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
}

.info-item p {
  margin: 0;
  color: #2d3748;
  font-size: 1em;
  padding: 10px 12px;
  background: #f7fafc;
  border-radius: 6px;
  border-left: 3px solid #10b981;
}

.description-section {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 2px solid #f0f0f0;
}

.description-section label {
  font-weight: 700;
  color: #047857;
  font-size: 0.85em;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: block;
  margin-bottom: 10px;
}

.description-section p {
  margin: 0;
  color: #2d3748;
  line-height: 1.6;
  padding: 12px;
  background: #f7fafc;
  border-radius: 6px;
  border-left: 3px solid #10b981;
}

.modal-footer {
  padding: 20px 30px;
  border-top: 1px solid #f0f0f0;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn-secondary {
  padding: 10px 20px;
  background: #f0f0f0;
  color: #4a5568;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.9em;
  transition: all 0.3s ease;
}

.btn-secondary:hover {
  background: #e8ecf1;
  border-color: #cbd5e0;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .page-header h2 {
    min-width: auto;
  }

  .page-header-search {
    min-width: auto;
    width: 100%;
  }

  .org-header-section {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
  }

  .org-header-icon {
    width: 60px;
    height: 60px;
    font-size: 28px;
  }

  .org-header-content {
    margin-left: 0;
    width: 100%;
  }

  .org-header-title h3 {
    font-size: 1.3em;
  }

  .org-header-meta {
    gap: 12px;
  }

  .btn-info {
    width: 100%;
    justify-content: center;
    padding: 12px 20px;
  }

  .modal-content {
    width: 95%;
  }

  .org-info-content .info-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .org-header-section {
    padding: 16px;
  }

  .org-header-icon {
    width: 50px;
    height: 50px;
    font-size: 24px;
  }

  .org-header-title h3 {
    font-size: 1.1em;
  }

  .org-header-meta {
    flex-direction: column;
    gap: 8px;
    width: 100%;
  }

  .btn-info {
    padding: 10px 16px;
    font-size: 0.85em;
  }

  .btn-info span {
    display: none;
  }
}
</style>

<script>
// Search functionality for organizations grid
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('orgSearchInput');
  if (!searchInput) return;

  searchInput.addEventListener('keyup', function() {
    const searchQuery = this.value.toLowerCase();
    const folderCards = document.querySelectorAll('.folder-card');
    
    folderCards.forEach(card => {
      const folderName = card.querySelector('.folder-name')?.textContent.toLowerCase() || '';
      const folderCode = card.querySelector('.folder-code')?.textContent.toLowerCase() || '';
      
      if (folderName.includes(searchQuery) || folderCode.includes(searchQuery)) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });
  });
});

// Organization Info Modal
function showOrgInfoModal() {
  document.getElementById('orgInfoModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeOrgInfoModal() {
  document.getElementById('orgInfoModal').style.display = 'none';
  document.body.style.overflow = 'auto';
}

window.onclick = function(event) {
  const modal = document.getElementById('orgInfoModal');
  if (event.target === modal) {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  }
}
</script>
</body>
</html>

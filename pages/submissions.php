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
            <th>Ref No</th>
            <th>Organization</th>
            <th>Title</th>
            <th>Status</th>
            <th>Submitted By</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($submissions)): ?>
            <?php foreach ($submissions as $index => $submission): ?>
              <tr>
                <td><?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo htmlspecialchars($submission['org_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($submission['title']); ?></td>
                <td>
                  <span class="status <?php echo strtolower($submission['status']); ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $submission['status'])); ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars($submission['submitted_by_name'] ?? 'N/A'); ?></td>
                <td><?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?></td>
                <td>
                  <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <a href="#" onclick="previewSubmission(<?php echo $submission['submission_id']; ?>); return false;" class="action-btn open-btn" title="Preview"><i class="fas fa-eye"></i> Preview</a>
                    <button class="action-btn approve-btn" onclick="approveSubmission(<?php echo $submission['submission_id']; ?>)" title="Approve" <?php echo in_array($submission['status'], ['approved']) ? 'disabled' : ''; ?>>
                      <i class="fas fa-check"></i> Approve
                    </button>
                    <button class="action-btn reject-btn" onclick="rejectSubmission(<?php echo $submission['submission_id']; ?>)" title="Reject" <?php echo in_array($submission['status'], ['rejected']) ? 'disabled' : ''; ?>>
                      <i class="fas fa-times"></i> Reject
                    </button>
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
  </div>
  <?php include 'footer.php'; ?>
</div>

<script src="../js/navbar.js"></script>
<script src="../js/submissions.js"></script>

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

.action-btn {
  padding: 6px 12px;
  border: none;
  border-radius: 4px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  text-decoration: none;
  white-space: nowrap;
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.open-btn {
  background-color: #3498db;
  color: white;
}

.open-btn:hover {
  background-color: #2980b9;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
}

.approve-btn {
  background-color: #27ae60;
  color: white;
}

.approve-btn:hover:not(:disabled) {
  background-color: #229954;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(39, 174, 96, 0.3);
}

.reject-btn {
  background-color: #e74c3c;
  color: white;
}

.reject-btn:hover:not(:disabled) {
  background-color: #c0392b;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(231, 76, 60, 0.3);
}

.download-btn {
  background-color: #9b59b6;
  color: white;
}

.download-btn:hover {
  background-color: #8e44ad;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(155, 89, 182, 0.3);
}
</style>

<script>
function toggleNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function approveSubmission(submissionId) {
  if (confirm('Are you sure you want to approve this submission? It will move to Review & Approval.')) {
    fetch('../api/submissions.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: 'action=approve&submission_id=' + submissionId
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('Submission approved! It has been moved to Review & Approval.');
        location.reload();
      } else {
        alert('Error: ' + (data.message || 'Failed to approve submission'));
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred while approving the submission');
    });
  }
}

function rejectSubmission(submissionId) {
  const reason = prompt('Enter rejection reason (this will be archived):');
  if (reason !== null) {
    fetch('../api/submissions.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: 'action=reject&submission_id=' + submissionId + '&reason=' + encodeURIComponent(reason)
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('Submission rejected! It has been moved to Archive.');
        location.reload();
      } else {
        alert('Error: ' + (data.message || 'Failed to reject submission'));
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred while rejecting the submission');
    });
  }
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
      link.download = 'submission_' + submissionId + '.zip';
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Failed to download submission files');
    });
}

function previewSubmission(submissionId) {
  // Find the submission row and get its data
  const row = event.target.closest('tr');
  const title = row.cells[2].textContent;
  const org = row.cells[1].textContent;
  const submitter = row.cells[4].textContent;
  const date = row.cells[5].textContent;
  
  // Create a modal preview
  const preview = `
    <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;">
      <div style="background-color: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2 style="margin: 0;">${title}</h2>
          <button onclick="this.closest('div').parentElement.parentElement.remove();" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
          <p><strong>Organization:</strong> ${org}</p>
          <p><strong>Submitted By:</strong> ${submitter}</p>
          <p><strong>Date:</strong> ${date}</p>
        </div>
        <div style="text-align: center; color: #999; padding: 40px;">
          <p>Preview content would be displayed here</p>
        </div>
      </div>
    </div>
  `;
  
  const previewDiv = document.createElement('div');
  previewDiv.innerHTML = preview;
  document.body.appendChild(previewDiv);
}
</script>
</body>
</html>

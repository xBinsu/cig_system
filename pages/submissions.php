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
    SELECT s.*, u.full_name as submitted_by_name, COALESCE(org.org_name, org.full_name) as org_name 
    FROM submissions s
    LEFT JOIN users u ON s.user_id = u.user_id
    LEFT JOIN users org ON s.org_id = org.user_id
    WHERE s.status IN ('pending', 'in_review')
";
$params = [];

if ($search_query) {
    $query .= " AND (s.title LIKE ? OR org.org_name LIKE ? OR org.full_name LIKE ?)";
    $params[] = "%$search_query%";
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

// Compute summary counts
$total       = count($submissions);
$pending_ct  = count(array_filter($submissions, fn($s) => $s['status'] === 'pending'));
$review_ct   = count(array_filter($submissions, fn($s) => $s['status'] === 'in_review'));
$oldest_days = 0;
foreach ($submissions as $s) {
    $days = (int) floor((time() - strtotime($s['submitted_at'])) / 86400);
    if ($days > $oldest_days) $oldest_days = $days;
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
      <p class="page-subtitle">Review and action pending submissions from organizations.</p>
    </div>

    <!-- STAT CARDS -->
    <div class="stat-cards">
      <div class="stat-card stat-total">
        <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
        <div class="stat-info">
          <span class="stat-value"><?php echo $total; ?></span>
          <span class="stat-label">Total Awaiting</span>
        </div>
      </div>
      <div class="stat-card stat-pending">
        <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="stat-info">
          <span class="stat-value"><?php echo $pending_ct; ?></span>
          <span class="stat-label">Pending</span>
        </div>
      </div>
      <div class="stat-card stat-review">
        <div class="stat-icon"><i class="fas fa-search"></i></div>
        <div class="stat-info">
          <span class="stat-value"><?php echo $review_ct; ?></span>
          <span class="stat-label">In Review</span>
        </div>
      </div>
      <div class="stat-card stat-oldest">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
          <span class="stat-value"><?php echo $oldest_days; ?>d</span>
          <span class="stat-label">Oldest Submission</span>
        </div>
      </div>
    </div>

    <!-- SEARCH & FILTER -->
    <div class="search-filter-container">
      <div class="search-bar-row">
        <div class="search-input-wrapper">
          <i class="fas fa-search search-icon" id="searchIcon"></i>
          <input
            type="text"
            id="liveSearchInput"
            placeholder="Search by title, organization, or submitter..."
            value="<?php echo htmlspecialchars($search_query); ?>"
            class="search-input"
            autocomplete="off"
          >
          <button class="search-clear-btn" id="searchClearBtn" onclick="clearSearch()" title="Clear search" style="display:none;">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="filter-pills">
          <button class="pill-btn <?php echo !$status_filter ? 'pill-active' : ''; ?>" onclick="setPill(this, '')">All</button>
          <button class="pill-btn <?php echo $status_filter === 'pending' ? 'pill-active' : ''; ?>" onclick="setPill(this, 'pending')">
            <span class="pill-dot pending-dot"></span> Pending
          </button>
          <button class="pill-btn <?php echo $status_filter === 'in_review' ? 'pill-active' : ''; ?>" onclick="setPill(this, 'in_review')">
            <span class="pill-dot review-dot"></span> In Review
          </button>
        </div>
      </div>
      <div class="search-meta-row" id="searchMetaRow" style="display:none;">
        <span id="searchMetaText"></span>
        <button class="search-meta-clear" onclick="clearSearch()"><i class="fas fa-times"></i> Clear</button>
      </div>
    </div>

    <!-- TABLE -->
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
              <?php
                $days_ago = (int) floor((time() - strtotime($submission['submitted_at'])) / 86400);
                $is_urgent = $days_ago >= 5;
              ?>
              <tr class="<?php echo $is_urgent ? 'row-urgent' : ''; ?>" data-id="<?php echo $submission['submission_id']; ?>"
                  data-title="<?php echo htmlspecialchars($submission['title']); ?>"
                  data-org="<?php echo htmlspecialchars($submission['org_name'] ?? 'N/A'); ?>"
                  data-submitter="<?php echo htmlspecialchars($submission['submitted_by_name'] ?? 'N/A'); ?>"
                  data-date="<?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?>"
                  data-status="<?php echo htmlspecialchars($submission['status']); ?>"
                  data-file="<?php echo htmlspecialchars($submission['file_name'] ?? ''); ?>">
                <td class="ref-cell">
                  #<?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?>
                  <?php if ($is_urgent): ?><span class="urgent-dot" title="Waiting <?php echo $days_ago; ?> days"></span><?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($submission['org_name'] ?? 'N/A'); ?></td>
                <td class="title-cell"><strong><?php echo htmlspecialchars($submission['title']); ?></strong></td>
                <td>
                  <span class="status <?php echo strtolower($submission['status']); ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $submission['status'])); ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars($submission['submitted_by_name'] ?? 'N/A'); ?></td>
                <td>
                  <span><?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?></span>
                  <span class="days-ago"><?php echo $days_ago === 0 ? 'Today' : $days_ago . 'd ago'; ?></span>
                </td>
                <td>
                  <div class="action-group">
                    <button class="action-link action-preview" onclick="previewSubmission(this)" title="Preview">
                      <i class="fas fa-eye"></i> Preview
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7">
                <div class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>No submissions found</p>
                  <span>All caught up! There are no pending submissions at the moment.</span>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- PREVIEW MODAL -->
  <div id="previewModal" class="modal-overlay" style="display:none;">
    <div class="modal-box modal-box-lg">
      <div class="modal-header">
        <div class="modal-header-icon preview-icon"><i class="fas fa-eye"></i></div>
        <div class="modal-header-text">
          <h3 id="previewTitle">Submission Preview</h3>
          <p id="previewOrg" class="modal-subtitle"></p>
        </div>
        <button class="modal-close-btn" onclick="closePreviewModal()"><i class="fas fa-times"></i></button>
      </div>

      <!-- META ROW -->
      <div class="preview-meta-bar">
        <div class="meta-item">
          <span class="meta-label"><i class="fas fa-user"></i> Submitted By</span>
          <span id="previewSubmitter" class="meta-value"></span>
        </div>
        <div class="meta-item">
          <span class="meta-label"><i class="fas fa-calendar"></i> Date Submitted</span>
          <span id="previewDate" class="meta-value"></span>
        </div>
        <div class="meta-item">
          <span class="meta-label"><i class="fas fa-tag"></i> Status</span>
          <span id="previewStatus" class="meta-value"></span>
        </div>
        <div class="meta-item">
          <span class="meta-label"><i class="fas fa-file"></i> File</span>
          <span id="previewFileName" class="meta-value file-name-truncate"></span>
        </div>
      </div>

      <!-- FILE VIEWER -->
      <div class="preview-viewer-wrap">
        <!-- Loading state -->
        <div id="previewLoading" class="preview-loading">
          <div class="preview-spinner"></div>
          <span>Loading document...</span>
        </div>
        <!-- Error state -->
        <div id="previewError" class="preview-error" style="display:none;">
          <i class="fas fa-exclamation-triangle"></i>
          <p>Could not load document preview.</p>
          <span id="previewErrorMsg"></span>
        </div>
        <!-- PDF / DOCX → PDF via LibreOffice -->
        <iframe
          id="previewIframe"
          class="preview-iframe"
          style="display:none;"
          title="Document Preview"
        ></iframe>
        <!-- Image viewer -->
        <img
          id="previewImage"
          class="preview-image"
          style="display:none;"
          alt="File preview"
        >
        <!-- Unsupported type fallback -->
        <div id="previewUnsupported" class="preview-unsupported" style="display:none;">
          <i class="fas fa-file-alt"></i>
          <p id="previewUnsupportedMsg">This file type cannot be previewed inline.</p>
          <span>Use the Download button to open the file.</span>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-modal-secondary" onclick="closePreviewModal()">Close</button>
        <a id="previewDownloadLink" href="#" class="btn-modal-download" target="_blank">
          <i class="fas fa-download"></i> Download
        </a>
      </div>
    </div>
  </div>
  <?php include 'footer.php'; ?>
</div>

<script src="../js/navbar.js"></script>
<script src="../js/submissions.js"></script>

<style>
/* ── PAGE SUBTITLE ─────────────────────────────────────────── */
.page-subtitle {
  margin: 6px 0 0 0;
  color: #4a5568;
  font-size: 0.92em;
  font-weight: 400;
}

/* ── STAT CARDS ─────────────────────────────────────────────── */
.stat-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 28px;
}

.stat-card {
  background: white;
  border-radius: 14px;
  padding: 20px 22px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.07);
  border: 1px solid #f0f0f0;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3em;
  flex-shrink: 0;
}

.stat-total   .stat-icon { background: linear-gradient(135deg,#e0f2fe,#bae6fd); color: #0369a1; }
.stat-pending .stat-icon { background: linear-gradient(135deg,#fef9c3,#fde68a); color: #92400e; }
.stat-review  .stat-icon { background: linear-gradient(135deg,#dbeafe,#bfdbfe); color: #1d4ed8; }
.stat-oldest  .stat-icon { background: linear-gradient(135deg,#fce7f3,#fbcfe8); color: #9d174d; }

.stat-info { display: flex; flex-direction: column; }
.stat-value { font-size: 1.8em; font-weight: 800; color: #1a202c; line-height: 1; }
.stat-label { font-size: 0.8em; color: #718096; font-weight: 500; margin-top: 4px; }

/* ── SEARCH / FILTER ───────────────────────────────────────── */
.search-filter-container {
  margin-bottom: 24px;
  padding: 18px 22px;
  background: white;
  border-radius: 14px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.07);
  border: 1px solid #e8eef9;
  transition: box-shadow 0.2s ease;
}

.search-filter-container:focus-within {
  box-shadow: 0 4px 20px rgba(16,185,129,0.12);
  border-color: rgba(16,185,129,0.3);
}

.search-bar-row {
  display: flex;
  gap: 14px;
  align-items: center;
  flex-wrap: wrap;
}

.search-input-wrapper {
  flex: 1;
  min-width: 260px;
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 16px;
  color: #a0aec0;
  font-size: 15px;
  pointer-events: none;
  transition: color 0.2s ease;
  z-index: 1;
}

.search-input-wrapper:focus-within .search-icon {
  color: #10b981;
}

.search-input {
  width: 100%;
  padding: 12px 44px 12px 44px;
  border: 2px solid #e8eef9;
  border-radius: 11px;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.25s ease;
  background: #f8fafc;
  color: #1a202c;
  font-family: inherit;
}

.search-input:focus {
  outline: none;
  border-color: #10b981;
  background: white;
  box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
}

.search-input::placeholder { color: #a8b5c8; font-weight: 400; }

.search-clear-btn {
  position: absolute;
  right: 12px;
  background: #e2e8f0;
  border: none;
  width: 24px; height: 24px;
  border-radius: 50%;
  cursor: pointer;
  color: #64748b;
  font-size: 10px;
  display: flex; align-items:center; justify-content:center;
  transition: all 0.2s;
  box-shadow: none;
  padding: 0;
  flex-shrink: 0;
}
.search-clear-btn:hover { background: #cbd5e0; color: #1a202c; transform: none; box-shadow: none; }

/* ── STATUS PILLS ───────────────────────────────────────────── */
.filter-pills {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-shrink: 0;
  flex-wrap: wrap;
}

.pill-btn {
  padding: 8px 16px;
  border: 2px solid #e2e8f0;
  border-radius: 50px;
  background: white;
  color: #64748b;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 6px;
  box-shadow: none;
  font-family: inherit;
}

.pill-btn:hover {
  border-color: #10b981;
  color: #10b981;
  background: #f0fdf4;
  transform: none;
  box-shadow: none;
}

.pill-btn.pill-active {
  background: linear-gradient(135deg,#10b981,#059669);
  border-color: #059669;
  color: white;
  box-shadow: 0 3px 10px rgba(16,185,129,0.3);
}

.pill-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.pending-dot { background: #f59e0b; }
.review-dot  { background: #3b82f6; }

.pill-btn.pill-active .pending-dot { background: rgba(255,255,255,0.8); }
.pill-btn.pill-active .review-dot  { background: rgba(255,255,255,0.8); }

/* ── SEARCH META ROW ────────────────────────────────────────── */
.search-meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #f0f4f8;
  font-size: 0.85em;
  color: #4a5568;
}

.search-meta-clear {
  background: none;
  border: none;
  color: #ef4444;
  font-size: 0.85em;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  display: flex; align-items:center; gap:5px;
  transition: color 0.2s;
  box-shadow: none;
  font-family: inherit;
}
.search-meta-clear:hover { color: #dc2626; transform: none; box-shadow: none; }

/* ── HIGHLIGHT MATCH ────────────────────────────────────────── */
.search-highlight {
  background: #fef9c3;
  border-radius: 3px;
  padding: 0 2px;
  font-weight: 700;
  color: #92400e;
}

.row-hidden { display: none !important; }

/* ── TABLE EXTRAS ──────────────────────────────────────────── */
.ref-cell { white-space: nowrap; position: relative; }
.urgent-dot {
  display: inline-block;
  width: 8px; height: 8px;
  background: #ef4444;
  border-radius: 50%;
  margin-left: 6px;
  vertical-align: middle;
  animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse {
  0%,100% { opacity:1; transform:scale(1); }
  50%      { opacity:.6; transform:scale(1.3); }
}

.row-urgent { border-left: 3px solid #ef4444 !important; }
.title-cell strong { color: #1a202c; }
.days-ago { display: block; font-size: 0.78em; color: #a0aec0; margin-top: 2px; }

.action-group { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }

.action-link {
  padding: 6px 11px;
  border: none;
  border-radius: 7px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.25s ease;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  text-decoration: none;
  white-space: nowrap;
  box-shadow: none;
}

.action-preview  { background: #e0f2fe; color: #0369a1; }
.action-preview:hover  { background: #bae6fd; transform: translateY(-1px); }
.action-approve  { background: #d1fae5; color: #065f46; }
.action-approve:hover  { background: #a7f3d0; transform: translateY(-1px); }
.action-reject   { background: #fee2e2; color: #991b1b; }
.action-reject:hover   { background: #fecaca; transform: translateY(-1px); }

/* ── STATUS BADGES ─────────────────────────────────────────── */
.status {
  display: inline-block;
  padding: 5px 11px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
.status.pending   { background:#fff3cd; color:#856404; border:1px solid #fde68a; }
.status.in_review { background:#dbeafe; color:#1d4ed8; border:1px solid #bfdbfe; }
.status.approved  { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
.status.rejected  { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }

/* ── EMPTY STATE ───────────────────────────────────────────── */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #a0aec0;
  gap: 10px;
}
.empty-state i   { font-size: 3em; color: #cbd5e0; }
.empty-state p   { font-size: 1.1em; font-weight: 600; color: #718096; margin: 0; }
.empty-state span{ font-size: 0.88em; color: #a0aec0; }

/* ── MODALS ─────────────────────────────────────────────────── */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.45);
  backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999;
  animation: fadeInOverlay 0.2s ease;
}
@keyframes fadeInOverlay { from{opacity:0} to{opacity:1} }

.modal-box {
  background: white;
  border-radius: 18px;
  max-width: 580px;
  width: 94%;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  overflow: hidden;
  animation: modalPop 0.28s cubic-bezier(0.34,1.56,0.64,1);
}
.modal-box-sm { max-width: 460px; }

.modal-box-lg {
  max-width: 1100px;
  width: 92vw;
  height: 90vh;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* ── PREVIEW META BAR ───────────────────────────────────────── */
.preview-meta-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  border-bottom: 1px solid #f0f0f0;
  background: #f8fafc;
  flex-shrink: 0;
}

.preview-meta-bar .meta-item {
  padding: 13px 18px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  border-right: 1px solid #f0f0f0;
}
.preview-meta-bar .meta-item:last-child { border-right: none; }
.file-name-truncate { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; }

/* ── FILE VIEWER WRAP ───────────────────────────────────────── */
.preview-viewer-wrap {
  flex: 1;
  position: relative;
  background: #e8ecef;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  min-height: 0; /* critical for flex children to shrink properly */
}

.preview-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  color: #718096;
  font-size: 0.92em;
  font-weight: 500;
}

.preview-spinner {
  width: 40px; height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 0.75s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.preview-error {
  flex-direction: column;
  align-items: center;
  gap: 10px;
  color: #991b1b;
  text-align: center;
  padding: 30px;
}
.preview-error i { font-size: 2.5em; color: #fca5a5; }
.preview-error p { margin: 0; font-weight: 700; }
.preview-error span { font-size: 0.85em; color: #718096; }

.preview-unsupported {
  flex-direction: column;
  align-items: center;
  gap: 10px;
  color: #718096;
  text-align: center;
  padding: 30px;
}
.preview-unsupported i { font-size: 3em; color: #cbd5e0; }
.preview-unsupported p { margin: 0; font-weight: 600; color: #4a5568; }
.preview-unsupported span { font-size: 0.85em; }

.preview-iframe {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  border: none;
  background: white;
  display: block;
}

.preview-image {
  max-width: 100%;
  max-height: 520px;
  object-fit: contain;
  border-radius: 4px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.12);
}

.modal-header-text { flex: 1; min-width: 0; overflow: hidden; }
.modal-header-text h3 { margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.btn-modal-download {
  padding: 10px 20px;
  background: linear-gradient(135deg,#8b5cf6,#7c3aed);
  color: white;
  border: none;
  border-radius: 9px;
  font-weight: 600;
  font-size: 0.9em;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  box-shadow: 0 3px 10px rgba(139,92,246,0.3);
  text-decoration: none;
  font-family: inherit;
}
.btn-modal-download:hover { background: linear-gradient(135deg,#7c3aed,#6d28d9); transform: translateY(-1px); color: white; }

@media(max-width:700px) {
  .preview-meta-bar { grid-template-columns: repeat(2,1fr); }
  .modal-box-lg { height: 98vh; max-height: 98vh; width: 100vw; border-radius: 12px; }
}

@keyframes modalPop {
  from { transform: scale(0.92) translateY(20px); opacity:0; }
  to   { transform: scale(1) translateY(0);        opacity:1; }
}

.modal-header {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 22px 24px 18px;
  border-bottom: 1px solid #f0f0f0;
}
.modal-header h3 { margin:0; font-size:1.15em; font-weight:700; color:#1a202c; }
.modal-subtitle  { margin:3px 0 0; font-size:0.85em; color:#718096; }
.modal-header-icon {
  width: 44px; height: 44px;
  border-radius: 12px;
  display: flex; align-items:center; justify-content:center;
  font-size: 1.1em;
  flex-shrink: 0;
}
.preview-icon { background: #e0f2fe; color: #0369a1; }
.approve-icon { background: #d1fae5; color: #065f46; }
.reject-icon  { background: #fee2e2; color: #991b1b; }

.modal-close-btn {
  margin-left: auto;
  background: #f1f5f9;
  border: none;
  width: 32px; height: 32px;
  border-radius: 8px;
  cursor: pointer;
  color: #64748b;
  font-size: 0.9em;
  display: flex; align-items:center; justify-content:center;
  transition: all 0.2s;
  flex-shrink: 0;
  box-shadow: none;
  padding: 0;
}
.modal-close-btn:hover { background:#e2e8f0; color:#1a202c; transform:none; }

.modal-body { padding: 22px 24px; }

.meta-item { display:flex; flex-direction:column; gap:4px; }
.meta-label { font-size:0.78em; color:#718096; font-weight:600; display:flex; align-items:center; gap:5px; }
.meta-value { font-size:0.9em; color:#1a202c; font-weight:700; }

.modal-footer {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 14px 24px 20px;
  border-top: 1px solid #f0f0f0;
  flex-shrink: 0;
}

.btn-modal-secondary {
  padding: 10px 20px;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  font-weight: 600;
  font-size: 0.9em;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: none;
}
.btn-modal-secondary:hover { background:#e2e8f0; transform:none; }

/* ── TOAST ──────────────────────────────────────────────────── */
.toast-notification {
  position: fixed;
  bottom: 28px; right: 28px;
  padding: 14px 20px;
  border-radius: 12px;
  font-size: 0.92em;
  font-weight: 600;
  color: white;
  display: flex;
  align-items: center;
  gap: 10px;
  z-index: 99999;
  box-shadow: 0 8px 24px rgba(0,0,0,0.2);
  opacity: 0;
  transform: translateY(16px);
  transition: opacity 0.35s ease, transform 0.35s ease;
  max-width: 360px;
}
.toast-show { opacity:1; transform:translateY(0); }
.toast-success { background: linear-gradient(135deg,#10b981,#059669); }
.toast-error   { background: linear-gradient(135deg,#ef4444,#dc2626); }

/* ── RESPONSIVE ─────────────────────────────────────────────── */
@media(max-width:900px) {
  .stat-cards { grid-template-columns: repeat(2,1fr); }
}
@media(max-width:600px) {
  .stat-cards { grid-template-columns: 1fr 1fr; }
  .search-filter-form { flex-direction:column; align-items:stretch; }
  .filter-select, .btn-clear { width:100%; }
  .action-group { flex-direction:column; }
}
</style>

<script>
// ── LIVE SEARCH & PILL FILTER ────────────────────────────────
let _currentPill = '<?php echo htmlspecialchars($status_filter ?? ''); ?>';
let _currentSearch = '';
let _debounceTimer = null;

const searchInput  = document.getElementById('liveSearchInput');
const clearBtn     = document.getElementById('searchClearBtn');
const metaRow      = document.getElementById('searchMetaRow');
const metaText     = document.getElementById('searchMetaText');
const tableBody    = document.querySelector('table tbody');

// Init from URL value
if (searchInput && searchInput.value.trim()) {
  _currentSearch = searchInput.value.trim();
  clearBtn.style.display = 'flex';
  applyFilter();
}

searchInput?.addEventListener('input', function () {
  clearTimeout(_debounceTimer);
  _currentSearch = this.value.trim();
  clearBtn.style.display = _currentSearch ? 'flex' : 'none';
  _debounceTimer = setTimeout(applyFilter, 180);
});

// prevent form submission on Enter — we filter live
searchInput?.addEventListener('keydown', function(e) {
  if (e.key === 'Enter') e.preventDefault();
});

function setPill(btn, value) {
  _currentPill = value;
  document.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('pill-active'));
  btn.classList.add('pill-active');
  applyFilter();
}

function clearSearch() {
  searchInput.value = '';
  _currentSearch = '';
  clearBtn.style.display = 'none';
  searchInput.focus();
  applyFilter();
}

function applyFilter() {
  const rows     = tableBody ? tableBody.querySelectorAll('tr[data-id]') : [];
  const query    = _currentSearch.toLowerCase();
  let   visible  = 0;

  rows.forEach(row => {
    const title     = (row.dataset.title     || '').toLowerCase();
    const org       = (row.dataset.org       || '').toLowerCase();
    const submitter = (row.dataset.submitter || '').toLowerCase();
    const status    = (row.dataset.status    || '').toLowerCase();

    const matchSearch = !query || title.includes(query) || org.includes(query) || submitter.includes(query);
    const matchPill   = !_currentPill || status === _currentPill;

    if (matchSearch && matchPill) {
      row.classList.remove('row-hidden');
      visible++;
      // highlight matching text
      if (query) highlightRow(row, query);
      else       clearHighlight(row);
    } else {
      row.classList.add('row-hidden');
      clearHighlight(row);
    }
  });

  // show/hide empty state
  const emptyRow = tableBody?.querySelector('tr:not([data-id])');
  if (emptyRow) emptyRow.style.display = visible === 0 ? '' : 'none';

  // meta row
  if (query || _currentPill) {
    metaRow.style.display = 'flex';
    const parts = [];
    if (query)       parts.push(`"<strong>${escapeHtml(query)}</strong>"`);
    if (_currentPill) parts.push(`status: <strong>${_currentPill.replace('_',' ')}</strong>`);
    metaText.innerHTML = `<i class="fas fa-filter" style="color:#10b981;margin-right:5px;"></i> ${visible} result${visible !== 1 ? 's' : ''} for ${parts.join(' + ')}`;
  } else {
    metaRow.style.display = 'none';
  }
}

// ── HIGHLIGHT ────────────────────────────────────────────────
function highlightRow(row, query) {
  const cells = [row.cells[1], row.cells[2], row.cells[4]]; // org, title, submitter
  cells.forEach(cell => {
    if (!cell) return;
    // only highlight text nodes, not already-highlighted spans
    const orig = cell.getAttribute('data-orig') || cell.innerHTML;
    cell.setAttribute('data-orig', orig);
    const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
    cell.innerHTML = orig.replace(regex, '<mark class="search-highlight">$1</mark>');
  });
}

function clearHighlight(row) {
  const cells = [row.cells[1], row.cells[2], row.cells[4]];
  cells.forEach(cell => {
    if (!cell) return;
    const orig = cell.getAttribute('data-orig');
    if (orig) { cell.innerHTML = orig; cell.removeAttribute('data-orig'); }
  });
}

function escapeRegex(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
function escapeHtml(s)  { return s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

// ── MODAL HELPERS ────────────────────────────────────────────
function closeModal(id) {
  document.getElementById(id).style.display = 'none';
}

function closePreviewModal() {
  // Stop loading the iframe to kill any ongoing network request
  const iframe = document.getElementById('previewIframe');
  iframe.src = 'about:blank';
  document.getElementById('previewModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
  const el = document.getElementById('previewModal');
  if (el && e.target === el) closePreviewModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closePreviewModal();
});

// ── PREVIEW ──────────────────────────────────────────────────
let _activeSubmissionId = null;

function previewSubmission(btn) {
  const row       = btn.closest('tr');
  const id        = row.dataset.id;
  const title     = row.dataset.title;
  const org       = row.dataset.org;
  const submitter = row.dataset.submitter;
  const date      = row.dataset.date;
  const status    = row.dataset.status;
  const fileName  = row.dataset.file || '';

  _activeSubmissionId = id;

  // Fill meta
  document.getElementById('previewTitle').textContent     = title;
  document.getElementById('previewOrg').textContent       = org;
  document.getElementById('previewSubmitter').textContent = submitter;
  document.getElementById('previewDate').textContent      = date;
  document.getElementById('previewFileName').textContent  = fileName || '—';
  document.getElementById('previewStatus').innerHTML =
    `<span class="status ${status}">${status.replace('_',' ')}</span>`;

  // Download link
  document.getElementById('previewDownloadLink').href =
    `file_preview.php?submission_id=${id}&download=1`;

  // Show modal
  document.getElementById('previewModal').style.display = 'flex';

  // Reset viewer panels
  _showViewer('loading');

  // Decide render strategy by extension
  const ext = fileName.split('.').pop().toLowerCase();
  const previewableImage = ['jpg','jpeg','png','gif','webp'].includes(ext);
  const previewablePdf   = ext === 'pdf';
  const previewableDocx  = ['doc','docx'].includes(ext);
  const previewableTxt   = ext === 'txt';

  if (previewableImage) {
    const img = document.getElementById('previewImage');
    img.onload  = () => _showViewer('image');
    img.onerror = () => _showViewer('error', 'Image failed to load.');
    img.src = `file_preview.php?submission_id=${id}`;

  } else if (previewablePdf) {
    const iframe = document.getElementById('previewIframe');
    iframe.onload = () => _showViewer('iframe');
    iframe.onerror = () => _showViewer('error', 'Failed to load PDF.');
    iframe.src = `file_preview.php?submission_id=${id}`;
    // Fallback: PDF viewers sometimes don't fire onload — show after 2s
    setTimeout(() => {
      if (document.getElementById('previewLoading').style.display !== 'none') _showViewer('iframe');
    }, 2000);

  } else if (previewableDocx) {
    const iframe = document.getElementById('previewIframe');
    iframe.onload = () => _showViewer('iframe');
    iframe.onerror = () => _showViewer('error', 'Failed to convert document.');
    iframe.src = `docx_to_pdf.php?submission_id=${id}`;
    // LibreOffice conversion may take a moment
    setTimeout(() => {
      if (document.getElementById('previewLoading').style.display !== 'none') _showViewer('iframe');
    }, 5000);

  } else if (previewableTxt) {
    const iframe = document.getElementById('previewIframe');
    iframe.onload = () => _showViewer('iframe');
    iframe.src = `file_preview.php?submission_id=${id}`;

  } else if (!fileName) {
    _showViewer('unsupported', 'No file attached to this submission.');
  } else {
    _showViewer('unsupported', `".${ext}" files cannot be previewed inline.`);
  }
}

function _showViewer(panel, msg) {
  document.getElementById('previewLoading').style.display     = panel === 'loading'     ? 'flex' : 'none';
  document.getElementById('previewError').style.display       = panel === 'error'       ? 'flex' : 'none';
  document.getElementById('previewIframe').style.display      = panel === 'iframe'      ? 'block': 'none';
  document.getElementById('previewImage').style.display       = panel === 'image'       ? 'block': 'none';
  document.getElementById('previewUnsupported').style.display = panel === 'unsupported' ? 'flex' : 'none';
  if (panel === 'error'       && msg) document.getElementById('previewErrorMsg').textContent = msg;
  if (panel === 'unsupported' && msg) document.getElementById('previewUnsupportedMsg').textContent = msg;
}

// ── DOWNLOAD ─────────────────────────────────────────────────
function downloadSubmission(submissionId) {
  window.open(`file_preview.php?submission_id=${submissionId}&download=1`, '_blank');
}

function downloadFromPreview() {
  if (_activeSubmissionId) downloadSubmission(_activeSubmissionId);
}

// ── TOAST ─────────────────────────────────────────────────────
function showToast(msg, type) {
  const existing = document.querySelector('.toast-notification');
  if (existing) existing.remove();
  const toast = document.createElement('div');
  toast.className = 'toast-notification toast-' + type;
  toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${msg}`;
  document.body.appendChild(toast);
  setTimeout(() => toast.classList.add('toast-show'), 10);
  setTimeout(() => { toast.classList.remove('toast-show'); setTimeout(() => toast.remove(), 400); }, 3500);
}

function toggleNotificationPanel() {
  const panel = document.getElementById('notificationPanel');
  if (panel) panel.classList.toggle('show');
}
</script>
</body>
</html>
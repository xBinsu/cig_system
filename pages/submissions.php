<?php
/**
 * CIG Admin Dashboard - Submissions Page
 * Displays all submissions with database integration
 */

session_start();
require_once '../db/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$user = ['full_name' => $_SESSION['admin_email'] ?? 'Admin'];

// ── Handle Mark as Done (AJAX POST) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_done') {
    header('Content-Type: application/json');
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    if ($submission_id) {
        try {
            $admin_id = $_SESSION['admin_id'] ?? 1;
            $db->update('submissions',
                ['status' => 'approved', 'updated_at' => date('Y-m-d H:i:s')],
                'submission_id = ?', [$submission_id]
            );
            $existing = $db->fetchRow(
                "SELECT review_id FROM reviews WHERE submission_id = ? AND reviewer_id = ?",
                [$submission_id, $admin_id]
            );
            if (!$existing) {
                $db->insert('reviews', [
                    'submission_id' => $submission_id,
                    'reviewer_id'   => $admin_id,
                    'feedback'      => 'Marked as done by admin.',
                    'status'        => 'completed',
                    'reviewed_at'   => date('Y-m-d H:i:s'),
                ]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid submission ID']);
    }
    exit();
}

// ── Handle Mark as Done (AJAX POST) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_done') {
    header('Content-Type: application/json');
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    if ($submission_id) {
        try {
            $admin_id = $_SESSION['admin_id'] ?? 1;
            $db->update('submissions',
                ['status' => 'approved', 'updated_at' => date('Y-m-d H:i:s')],
                'submission_id = ?', [$submission_id]
            );
            $existing = $db->fetchRow(
                "SELECT review_id FROM reviews WHERE submission_id = ? AND reviewer_id = ?",
                [$submission_id, $admin_id]
            );
            if (!$existing) {
                $db->insert('reviews', [
                    'submission_id' => $submission_id,
                    'reviewer_id'   => $admin_id,
                    'feedback'      => 'Marked as done by admin.',
                    'status'        => 'completed',
                    'reviewed_at'   => date('Y-m-d H:i:s'),
                ]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid submission ID']);
    }
    exit();
}

$status_filter = $_GET['status'] ?? null;
$search_query  = $_GET['search'] ?? '';

$query = "
    SELECT s.*, u.full_name as submitted_by_name, COALESCE(org.org_name, org.full_name) as org_name 
    FROM submissions s
    LEFT JOIN users u ON s.user_id = u.user_id
    LEFT JOIN users org ON s.org_id = org.user_id
    WHERE s.status IN ('in_review', 'approved')
";
$params = [];

if ($search_query) {
    $query   .= " AND (s.title LIKE ? OR org.org_name LIKE ? OR org.full_name LIKE ?)";
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

// Sample submissions for demonstration (in_review only — forwarded by superadmin)
$sample_submissions = [];

// Use sample submissions if no real submissions exist
if (empty($submissions)) {
    $submissions = $sample_submissions;
}

$total       = count($submissions);
$pending_ct  = count(array_filter($submissions, fn($s) => $s['status'] === 'in_review'));
$done_ct     = count(array_filter($submissions, fn($s) => $s['status'] === 'approved'));
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
$user_name    = $user['full_name'] ?? '';
?>
<?php include 'navbar.php'; ?>

  <!-- SUBMISSIONS -->
  <div class="page active">
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2><i class="fas fa-file-alt"></i> Submissions</h2>
        <p class="page-subtitle">Submissions forwarded by superadmin for final review.</p>
      </div>
      <div style="display:inline-flex;align-items:center;gap:0.5rem;background:#fff;border:1.5px solid #e0f2e0;border-radius:10px;padding:0.4rem 1rem;font-size:0.84rem;font-weight:600;color:#2e7d32;"><i class="fas fa-calendar-alt" style="font-size:0.8rem;color:#81a888;"></i><span><?= date('l, F j, Y') ?></span><span style="color:#c8e6c9;">|</span><i class="fas fa-clock" style="font-size:0.8rem;color:#81a888;"></i><span class="live-clock-span"><?= date('h:i:s A') ?></span></div>
    </div>

    <!-- STAT CARDS -->
    <div class="stat-cards">
      <div class="stat-card stat-total">
        <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
        <div class="stat-info">
          <span class="stat-value"><?php echo $total; ?></span>
          <span class="stat-label">Total Forwarded</span>
        </div>
      </div>
      <div class="stat-card stat-review">
        <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
        <div class="stat-info">
          <span class="stat-value"><?php echo $pending_ct; ?></span>
          <span class="stat-label">Awaiting Review</span>
        </div>
      </div>
      <div class="stat-card stat-oldest">
        <div class="stat-icon"><i class="fas fa-check-double"></i></div>
        <div class="stat-info">
          <span class="stat-value"><?php echo $done_ct; ?></span>
          <span class="stat-label">Marked as Done</span>
        </div>
      </div>
    </div>

    <!-- BULK ACTION BAR (hidden until rows selected) -->
    <div id="bulkBar" style="display:none;align-items:center;gap:12px;flex-wrap:wrap;
         padding:12px 18px;background:linear-gradient(135deg,#065f46,#047857);
         border-radius:12px;margin-bottom:18px;box-shadow:0 4px 16px rgba(4,120,87,.25);">
      <span id="bulkCount" style="color:#fff;font-weight:700;font-size:0.95em;flex:1;">0 selected</span>
      <button onclick="bulkAction('mark_done')"
        style="padding:9px 20px;background:#10b981;color:#fff;border:none;border-radius:8px;
               font-weight:700;font-size:0.88em;cursor:pointer;display:flex;align-items:center;
               gap:7px;font-family:inherit;transition:opacity .2s;"
        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        <i class="fas fa-check-double"></i> Mark All as Done
      </button>
      <button onclick="bulkAction('reject')"
        style="padding:9px 20px;background:#ef4444;color:#fff;border:none;border-radius:8px;
               font-weight:700;font-size:0.88em;cursor:pointer;display:flex;align-items:center;
               gap:7px;font-family:inherit;transition:opacity .2s;"
        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        <i class="fas fa-times"></i> Reject All
      </button>
      <button onclick="clearSelection()"
        style="padding:9px 16px;background:rgba(255,255,255,.15);color:#fff;border:none;
               border-radius:8px;font-weight:600;font-size:0.88em;cursor:pointer;font-family:inherit;">
        <i class="fas fa-times-circle"></i> Clear
      </button>
    </div>

    <!-- SEARCH & FILTER -->
    <div class="search-filter-container">
      <div class="search-bar-row">
        <div class="search-input-wrapper">
          <i class="fas fa-search search-icon" id="searchIcon"></i>
          <input type="text" id="liveSearchInput"
            placeholder="Search by title, organization, or submitter..."
            value="<?php echo htmlspecialchars($search_query); ?>"
            class="search-input" autocomplete="off">
          <button class="search-clear-btn" id="searchClearBtn" onclick="clearSearch()" title="Clear search" style="display:none;">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="filter-pills">
          <button class="pill-btn <?php echo !$status_filter ? 'pill-active' : ''; ?>" onclick="setPill(this, '')">All</button>
          <button class="pill-btn <?php echo $status_filter === 'in_review' ? 'pill-active' : ''; ?>" onclick="setPill(this, 'in_review')">
            <span class="pill-dot review-dot"></span> Forwarded
          </button>
          <button class="pill-btn <?php echo $status_filter === 'approved' ? 'pill-active' : ''; ?>" onclick="setPill(this, 'approved')">
            <span class="pill-dot" style="background:#10b981;"></span> Done
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
            <th style="width:42px;text-align:center;">
              <input type="checkbox" id="selectAll" title="Select all in_review rows"
                style="width:16px;height:16px;cursor:pointer;accent-color:#10b981;">
            </th>
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
                $days_ago  = (int) floor((time() - strtotime($submission['submitted_at'])) / 86400);
                $is_urgent = $days_ago >= 5;
                $is_done   = $submission['status'] === 'approved';
              ?>
              <tr id="row-<?php echo $submission['submission_id']; ?>"
                  class="<?php echo $is_urgent ? 'row-urgent' : ''; ?>"
                  data-id="<?php echo $submission['submission_id']; ?>"
                  data-title="<?php echo htmlspecialchars($submission['title']); ?>"
                  data-org="<?php echo htmlspecialchars($submission['org_name'] ?? 'N/A'); ?>"
                  data-submitter="<?php echo htmlspecialchars($submission['submitted_by_name'] ?? 'N/A'); ?>"
                  data-date="<?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?>"
                  data-status="<?php echo htmlspecialchars($submission['status']); ?>"
                  data-file="<?php echo htmlspecialchars($submission['file_name'] ?? ''); ?>">
                <td style="text-align:center;">
                  <?php if (!$is_done): ?>
                    <input type="checkbox" class="row-checkbox"
                      data-id="<?php echo $submission['submission_id']; ?>"
                      style="width:16px;height:16px;cursor:pointer;accent-color:#10b981;"
                      onchange="updateBulkBar()">
                  <?php else: ?>
                    <i class="fas fa-check-circle" style="color:#a7f3d0;font-size:0.95em;" title="Already reviewed"></i>
                  <?php endif; ?>
                </td>
                <td class="ref-cell">
                  #<?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?>
                  <?php if ($is_urgent): ?><span class="urgent-dot" title="Waiting <?php echo $days_ago; ?> days"></span><?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($submission['org_name'] ?? 'N/A'); ?></td>
                <td class="title-cell"><strong><?php echo htmlspecialchars($submission['title']); ?></strong></td>
                <td>
                  <span class="status <?php echo strtolower($submission['status']); ?>">
                    <?php echo $submission['status'] === 'in_review' ? 'Forwarded' : 'Done'; ?>
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
              <td colspan="8">
                <div class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>No submissions found</p>
                  <span>No submissions forwarded by superadmin yet.</span>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- PREVIEW MODAL (unchanged) -->
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
      <div class="preview-meta-bar">
        <div class="meta-item"><span class="meta-label"><i class="fas fa-user"></i> Submitted By</span><span id="previewSubmitter" class="meta-value"></span></div>
        <div class="meta-item"><span class="meta-label"><i class="fas fa-calendar"></i> Date Submitted</span><span id="previewDate" class="meta-value"></span></div>
        <div class="meta-item"><span class="meta-label"><i class="fas fa-tag"></i> Status</span><span id="previewStatus" class="meta-value"></span></div>
        <div class="meta-item"><span class="meta-label"><i class="fas fa-file"></i> File</span><span id="previewFileName" class="meta-value file-name-truncate"></span></div>
      </div>
      <div class="preview-viewer-wrap">
        <div id="previewLoading" class="preview-loading"><div class="preview-spinner"></div><span>Loading document...</span></div>
        <div id="previewError" class="preview-error" style="display:none;"><i class="fas fa-exclamation-triangle"></i><p>Could not load document preview.</p><span id="previewErrorMsg"></span></div>
        <iframe id="previewIframe" class="preview-iframe" style="display:none;" title="Document Preview"></iframe>
        <img id="previewImage" class="preview-image" style="display:none;" alt="File preview">
        <div id="previewUnsupported" class="preview-unsupported" style="display:none;"><i class="fas fa-file-alt"></i><p id="previewUnsupportedMsg">This file type cannot be previewed inline.</p><span>Use the Download button to open the file.</span></div>
      </div>
      <div class="modal-footer">
        <button class="btn-modal-secondary" onclick="closePreviewModal()">Close</button>
        <a id="previewDownloadLink" href="#" class="btn-modal-download" target="_blank"><i class="fas fa-download"></i> Download</a>
        <button id="markDoneBtn" class="btn-modal-done" onclick="markAsDone()"><i class="fas fa-check-double"></i> Mark as Done</button>
      </div>
    </div>
  </div>

  <!-- BULK CONFIRM MODAL -->
  <div id="bulkConfirmModal" style="display:none;position:fixed;inset:0;z-index:10000;
       background:rgba(0,0,0,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:90%;max-width:420px;overflow:hidden;
                box-shadow:0 20px 60px rgba(0,0,0,.25);animation:bulkModalPop .25s ease;">
      <div id="bulkConfirmHeader" style="padding:20px 24px 16px;display:flex;align-items:center;gap:12px;">
        <div id="bulkConfirmIcon" style="width:42px;height:42px;border-radius:10px;display:flex;
             align-items:center;justify-content:center;font-size:1.1em;flex-shrink:0;"></div>
        <div>
          <div id="bulkConfirmTitle" style="font-size:1.05em;font-weight:700;color:#1a202c;"></div>
          <div id="bulkConfirmSubtitle" style="font-size:0.83em;color:#718096;margin-top:2px;"></div>
        </div>
      </div>
      <div style="padding:0 24px 16px;">
        <div id="bulkConfirmList"
          style="background:#f8fafc;border-radius:8px;padding:10px 14px;max-height:180px;
                 overflow-y:auto;font-size:0.85em;color:#2d3748;border:1px solid #e2e8f0;"></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 24px 20px;border-top:1px solid #f0f0f0;">
        <button onclick="closeBulkConfirm()"
          style="padding:9px 18px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;
                 border-radius:8px;font-weight:600;font-size:0.88em;cursor:pointer;font-family:inherit;">
          Cancel
        </button>
        <button id="bulkConfirmBtn"
          style="padding:9px 20px;color:#fff;border:none;border-radius:8px;font-weight:700;
                 font-size:0.88em;cursor:pointer;font-family:inherit;
                 display:flex;align-items:center;gap:7px;">
        </button>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>
</div>

<script src="../js/navbar.js"></script>
<script src="../js/submissions.js"></script>

<style>
@keyframes bulkModalPop {
  from { transform:scale(.93) translateY(16px); opacity:0; }
  to   { transform:scale(1) translateY(0);       opacity:1; }
}

/* existing styles unchanged below */
.page-subtitle { margin:6px 0 0; color:#4a5568; font-size:0.92em; font-weight:400; }
.stat-cards { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px; }
.stat-card { background:white; border-radius:14px; padding:20px 22px; display:flex; align-items:center; gap:16px; box-shadow:0 2px 12px rgba(0,0,0,0.07); border:1px solid #f0f0f0; transition:transform 0.2s ease,box-shadow 0.2s ease; }
.stat-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,0.1); }
.stat-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.3em; flex-shrink:0; }
.stat-total   .stat-icon { background:linear-gradient(135deg,#e0f2fe,#bae6fd); color:#0369a1; }
.stat-pending .stat-icon { background:linear-gradient(135deg,#fef9c3,#fde68a); color:#92400e; }
.stat-review  .stat-icon { background:linear-gradient(135deg,#dbeafe,#bfdbfe); color:#1d4ed8; }
.stat-oldest  .stat-icon { background:linear-gradient(135deg,#fce7f3,#fbcfe8); color:#9d174d; }
.stat-info { display:flex; flex-direction:column; }
.stat-value { font-size:1.8em; font-weight:800; color:#1a202c; line-height:1; }
.stat-label { font-size:0.8em; color:#718096; font-weight:500; margin-top:4px; }
.search-filter-container { margin-bottom:24px; padding:18px 22px; background:white; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,0.07); border:1px solid #e8eef9; transition:box-shadow 0.2s ease; }
.search-filter-container:focus-within { box-shadow:0 4px 20px rgba(16,185,129,0.12); border-color:rgba(16,185,129,0.3); }
.search-bar-row { display:flex; gap:14px; align-items:center; flex-wrap:wrap; }
.search-input-wrapper { flex:1; min-width:260px; position:relative; display:flex; align-items:center; }
.search-icon { position:absolute; left:16px; color:#a0aec0; font-size:15px; pointer-events:none; transition:color 0.2s ease; z-index:1; }
.search-input-wrapper:focus-within .search-icon { color:#10b981; }
.search-input { width:100%; padding:12px 44px 12px 44px; border:2px solid #e8eef9; border-radius:11px; font-size:14px; font-weight:500; transition:all 0.25s ease; background:#f8fafc; color:#1a202c; font-family:inherit; }
.search-input:focus { outline:none; border-color:#10b981; background:white; box-shadow:0 0 0 3px rgba(16,185,129,0.1); }
.search-input::placeholder { color:#a8b5c8; font-weight:400; }
.search-clear-btn { position:absolute; right:12px; background:#e2e8f0; border:none; width:24px; height:24px; border-radius:50%; cursor:pointer; color:#64748b; font-size:10px; display:flex; align-items:center; justify-content:center; transition:all 0.2s; box-shadow:none; padding:0; flex-shrink:0; }
.search-clear-btn:hover { background:#cbd5e0; color:#1a202c; transform:none; box-shadow:none; }
.search-meta-row { display:flex; align-items:center; justify-content:space-between; margin-top:12px; padding-top:12px; border-top:1px solid #f0f4f8; font-size:0.85em; color:#4a5568; }
.search-meta-clear { background:none; border:none; color:#ef4444; font-size:0.85em; font-weight:600; cursor:pointer; padding:0; display:flex; align-items:center; gap:5px; transition:color 0.2s; box-shadow:none; font-family:inherit; }
.search-meta-clear:hover { color:#dc2626; transform:none; box-shadow:none; }
.filter-pills { display:flex; gap:8px; align-items:center; flex-shrink:0; flex-wrap:wrap; }
.pill-btn { padding:8px 16px; border:2px solid #e2e8f0; border-radius:50px; background:white; color:#64748b; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s ease; display:flex; align-items:center; gap:6px; box-shadow:none; font-family:inherit; }
.pill-btn:hover { border-color:#10b981; color:#10b981; background:#f0fdf4; transform:none; box-shadow:none; }
.pill-btn.pill-active { background:linear-gradient(135deg,#10b981,#059669); border-color:#059669; color:white; box-shadow:0 3px 10px rgba(16,185,129,0.3); }
.pill-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.pending-dot { background:#f59e0b; }
.review-dot  { background:#3b82f6; }
.pill-btn.pill-active .pending-dot { background:rgba(255,255,255,0.8); }
.pill-btn.pill-active .review-dot  { background:rgba(255,255,255,0.8); }
.search-highlight { background:#fef9c3; border-radius:3px; padding:0 2px; font-weight:700; color:#92400e; }
.row-hidden { display:none !important; }
.ref-cell { white-space:nowrap; position:relative; }
.urgent-dot { display:inline-block; width:8px; height:8px; background:#ef4444; border-radius:50%; margin-left:6px; vertical-align:middle; animation:pulse 1.5s ease-in-out infinite; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:.6;transform:scale(1.3);} }
.row-urgent { border-left:3px solid #ef4444 !important; }
.title-cell strong { color:#1a202c; }
.days-ago { display:block; font-size:0.78em; color:#a0aec0; margin-top:2px; }
.action-group { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.action-link { padding:6px 11px; border:none; border-radius:7px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.25s ease; display:inline-flex; align-items:center; gap:5px; text-decoration:none; white-space:nowrap; box-shadow:none; }
.action-preview { background:#e0f2fe; color:#0369a1; }
.action-preview:hover { background:#bae6fd; transform:translateY(-1px); }
.status { display:inline-block; padding:5px 11px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; }
.status.pending   { background:#fff3cd; color:#856404; border:1px solid #fde68a; }
.status.in_review { background:#dbeafe; color:#1d4ed8; border:1px solid #bfdbfe; }
.status.approved  { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
.status.rejected  { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
.empty-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:60px 20px; color:#a0aec0; gap:10px; }
.empty-state i { font-size:3em; color:#cbd5e0; }
.empty-state p { font-size:1.1em; font-weight:600; color:#718096; margin:0; }
.empty-state span { font-size:0.88em; color:#a0aec0; }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.45); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; z-index:9999; animation:fadeInOverlay 0.2s ease; }
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
  max-width: 860px;
  width: 96%;
  max-height: 92vh;
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
  min-height: 460px;
  position: relative;
  background: #f0f4f8;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
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
  width: 100%;
  height: 100%;
  min-height: 460px;
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
  .modal-box-lg { max-height: 98vh; width: 99%; }
  .preview-viewer-wrap { min-height: 300px; }
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
.modal-subtitle { margin:3px 0 0; font-size:0.85em; color:#718096; }
.modal-header-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.1em; flex-shrink:0; }
.preview-icon { background:#e0f2fe; color:#0369a1; }
.modal-close-btn { margin-left:auto; background:#f1f5f9; border:none; width:32px; height:32px; border-radius:8px; cursor:pointer; color:#64748b; font-size:0.9em; display:flex; align-items:center; justify-content:center; transition:all 0.2s; flex-shrink:0; box-shadow:none; padding:0; }
.modal-close-btn:hover { background:#e2e8f0; color:#1a202c; transform:none; }
.meta-item { display:flex; flex-direction:column; gap:4px; }
.meta-label { font-size:0.78em; color:#718096; font-weight:600; display:flex; align-items:center; gap:5px; }
.meta-value { font-size:0.9em; color:#1a202c; font-weight:700; }
.modal-footer { display:flex; gap:10px; justify-content:flex-end; padding:14px 24px 20px; border-top:1px solid #f0f0f0; flex-shrink:0; }
.btn-modal-secondary { padding:10px 20px; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; border-radius:9px; font-weight:600; font-size:0.9em; cursor:pointer; transition:all 0.2s; box-shadow:none; }
.btn-modal-secondary:hover { background:#e2e8f0; transform:none; }
.toast-notification { position:fixed; bottom:28px; right:28px; padding:14px 20px; border-radius:12px; font-size:0.92em; font-weight:600; color:white; display:flex; align-items:center; gap:10px; z-index:99999; box-shadow:0 8px 24px rgba(0,0,0,0.2); opacity:0; transform:translateY(16px); transition:opacity 0.35s ease,transform 0.35s ease; max-width:360px; }
.toast-show { opacity:1; transform:translateY(0); }
.toast-success { background:linear-gradient(135deg,#10b981,#059669); }
.toast-error   { background:linear-gradient(135deg,#ef4444,#dc2626); }
@media(max-width:900px) { .stat-cards{grid-template-columns:repeat(2,1fr);} }
@media(max-width:600px) { .stat-cards{grid-template-columns:1fr 1fr;} .action-group{flex-direction:column;} }
</style>

<script>
// ── SELECT ALL ────────────────────────────────────────────────────────────
document.getElementById('selectAll').addEventListener('change', function() {
  document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
  updateBulkBar();
});

function updateBulkBar() {
  const checked = document.querySelectorAll('.row-checkbox:checked');
  const all     = document.querySelectorAll('.row-checkbox');
  const bar     = document.getElementById('bulkBar');
  // Sync indeterminate state
  document.getElementById('selectAll').indeterminate = checked.length > 0 && checked.length < all.length;
  document.getElementById('selectAll').checked = all.length > 0 && checked.length === all.length;
  bar.style.display = checked.length > 0 ? 'flex' : 'none';
  document.getElementById('bulkCount').textContent = checked.length + ' selected';
}

function clearSelection() {
  document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
  document.getElementById('selectAll').checked      = false;
  document.getElementById('selectAll').indeterminate = false;
  updateBulkBar();
}

// ── BULK ACTION ───────────────────────────────────────────────────────────
let _bulkMode = null;
let _bulkIds  = [];

function bulkAction(mode) {
  const checked = document.querySelectorAll('.row-checkbox:checked');
  if (!checked.length) return;
  _bulkMode = mode;
  _bulkIds  = Array.from(checked).map(cb => cb.dataset.id);

  const isDone   = mode === 'mark_done';
  const color    = isDone ? '#10b981' : '#ef4444';
  const darkColor= isDone ? '#059669' : '#dc2626';
  const iconBg   = isDone ? '#d1fae5' : '#fee2e2';
  const iconClr  = isDone ? '#065f46' : '#b91c1c';
  const icon     = isDone ? 'fa-check-double' : 'fa-times';
  const title    = isDone
    ? `Mark ${_bulkIds.length} submission${_bulkIds.length > 1 ? 's' : ''} as Done`
    : `Reject ${_bulkIds.length} submission${_bulkIds.length > 1 ? 's' : ''}`;
  const subtitle = isDone
    ? 'These will be marked as reviewed and appear in the Review page.'
    : 'These submissions will be rejected and orgs notified.';

  const listHtml = _bulkIds.map(id => {
    const row   = document.getElementById('row-' + id);
    const t     = row?.dataset.title    || 'Submission #' + id;
    const o     = row?.dataset.org      || '';
    return `<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f0f0f0;">
              <span style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:220px;">${t}</span>
              <span style="color:#718096;font-size:0.88em;flex-shrink:0;margin-left:8px;">${o}</span>
            </div>`;
  }).join('');

  document.getElementById('bulkConfirmHeader').style.background = iconBg + '99';
  document.getElementById('bulkConfirmIcon').style.cssText =
    `width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1em;flex-shrink:0;background:${iconBg};color:${iconClr};`;
  document.getElementById('bulkConfirmIcon').innerHTML    = `<i class="fas ${icon}"></i>`;
  document.getElementById('bulkConfirmTitle').textContent    = title;
  document.getElementById('bulkConfirmSubtitle').textContent = subtitle;
  document.getElementById('bulkConfirmList').innerHTML       = listHtml;

  const btn = document.getElementById('bulkConfirmBtn');
  btn.style.background = `linear-gradient(135deg,${color},${darkColor})`;
  btn.innerHTML = `<i class="fas ${icon}"></i> ${isDone ? 'Mark All Done' : 'Reject All'}`;
  btn.onclick   = executeBulkAction;

  document.getElementById('bulkConfirmModal').style.display = 'flex';
}

function closeBulkConfirm() {
  document.getElementById('bulkConfirmModal').style.display = 'none';
}

function executeBulkAction() {
  const btn = document.getElementById('bulkConfirmBtn');
  btn.disabled  = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';

  const fd = new FormData();
  fd.append('action', _bulkMode === 'mark_done' ? 'bulk_mark_done' : 'bulk_reject');
  _bulkIds.forEach(id => fd.append('submission_ids[]', id));

  fetch('../api/submissions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeBulkConfirm();
      if (data.success) {
        const isDone = _bulkMode === 'mark_done';
        showToast(
          `${data.count} submission${data.count !== 1 ? 's' : ''} ${isDone ? 'marked as done.' : 'rejected.'}`,
          isDone ? 'success' : 'error'
        );
        // Animate rows out
        _bulkIds.forEach(id => {
          const row = document.getElementById('row-' + id);
          if (row) {
            row.style.transition = 'opacity .35s,transform .35s';
            row.style.opacity    = '0';
            row.style.transform  = 'translateX(30px)';
            setTimeout(() => row.remove(), 350);
          }
        });
        clearSelection();
        // Show empty state if no rows left
        setTimeout(() => {
          const tbody = document.querySelector('table tbody');
          if (tbody && !tbody.querySelector('tr[data-id]')) {
            tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state">
              <i class="fas fa-inbox"></i><p>No submissions found</p>
              <span>No submissions forwarded by superadmin yet.</span>
            </div></td></tr>`;
          }
        }, 400);
      } else {
        showToast(data.message || 'Bulk action failed.', 'error');
        btn.disabled  = false;
        btn.innerHTML = _bulkMode === 'mark_done'
          ? '<i class="fas fa-check-double"></i> Mark All Done'
          : '<i class="fas fa-times"></i> Reject All';
      }
    })
    .catch(() => {
      closeBulkConfirm();
      showToast('Network error. Please try again.', 'error');
    });
}

// Close bulk modal on backdrop
document.getElementById('bulkConfirmModal').addEventListener('click', function(e) {
  if (e.target === this) closeBulkConfirm();
});

// ── LIVE SEARCH (unchanged) ───────────────────────────────────────────────
let _currentPill   = '<?php echo htmlspecialchars($status_filter ?? ''); ?>';
let _currentSearch = '';
let _debounceTimer = null;

const searchInput = document.getElementById('liveSearchInput');
const clearBtn    = document.getElementById('searchClearBtn');
const metaRow     = document.getElementById('searchMetaRow');
const metaText    = document.getElementById('searchMetaText');
const tableBody   = document.querySelector('table tbody');

if (searchInput && searchInput.value.trim()) {
  _currentSearch = searchInput.value.trim();
  clearBtn.style.display = 'flex';
  applyFilter();
}

searchInput?.addEventListener('input', function() {
  clearTimeout(_debounceTimer);
  _currentSearch = this.value.trim();
  clearBtn.style.display = _currentSearch ? 'flex' : 'none';
  _debounceTimer = setTimeout(applyFilter, 180);
});

searchInput?.addEventListener('keydown', function(e) { if (e.key === 'Enter') e.preventDefault(); });

function setPill(btn, value) {
  _currentPill = value;
  document.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('pill-active'));
  btn.classList.add('pill-active');
  applyFilter();
}

function clearSearch() {
  searchInput.value = ''; _currentSearch = '';
  clearBtn.style.display = 'none';
  searchInput.focus(); applyFilter();
}

function applyFilter() {
  const rows  = tableBody ? tableBody.querySelectorAll('tr[data-id]') : [];
  const query = _currentSearch.toLowerCase();
  let visible = 0;
  rows.forEach(row => {
    const matchSearch = !query || (row.dataset.title||'').toLowerCase().includes(query)
                               || (row.dataset.org||'').toLowerCase().includes(query)
                               || (row.dataset.submitter||'').toLowerCase().includes(query);
    const matchPill   = !_currentPill || (row.dataset.status||'') === _currentPill;
    if (matchSearch && matchPill) { row.classList.remove('row-hidden'); visible++;
      if (query) highlightRow(row, query); else clearHighlight(row);
    } else { row.classList.add('row-hidden'); clearHighlight(row); }
  });
  const emptyRow = tableBody?.querySelector('tr:not([data-id])');
  if (emptyRow) emptyRow.style.display = visible === 0 ? '' : 'none';
  if (query || _currentPill) {
    metaRow.style.display = 'flex';
    const parts = [];
    if (query) parts.push(`"<strong>${escapeHtml(query)}</strong>"`);
    if (_currentPill) parts.push(`status: <strong>${_currentPill.replace('_',' ')}</strong>`);
    metaText.innerHTML = `<i class="fas fa-filter" style="color:#10b981;margin-right:5px;"></i> ${visible} result${visible!==1?'s':''} for ${parts.join(' + ')}`;
  } else { metaRow.style.display = 'none'; }
}

function highlightRow(row, query) {
  [row.cells[2], row.cells[3], row.cells[5]].forEach(cell => {
    if (!cell) return;
    const orig = cell.getAttribute('data-orig') || cell.innerHTML;
    cell.setAttribute('data-orig', orig);
    cell.innerHTML = orig.replace(new RegExp(`(${escapeRegex(query)})`, 'gi'), '<mark class="search-highlight">$1</mark>');
  });
}

function clearHighlight(row) {
  [row.cells[2], row.cells[3], row.cells[5]].forEach(cell => {
    if (!cell) return;
    const orig = cell.getAttribute('data-orig');
    if (orig) { cell.innerHTML = orig; cell.removeAttribute('data-orig'); }
  });
}

function escapeRegex(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
function escapeHtml(s)  { return s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

// ── MODAL & PREVIEW (unchanged) ───────────────────────────────────────────
function closePreviewModal() {
  const iframe = document.getElementById('previewIframe');
  iframe.src = 'about:blank';
  document.getElementById('previewModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
  if (e.target === document.getElementById('previewModal')) closePreviewModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') { closePreviewModal(); closeBulkConfirm(); }
});

let _activeSubmissionId = null;

function previewSubmission(btn) {
  const row      = btn.closest('tr');
  const id       = row.dataset.id;
  const status   = row.dataset.status;
  const fileName = row.dataset.file || '';
  _activeSubmissionId = id;
  document.getElementById('previewTitle').textContent     = row.dataset.title;
  document.getElementById('previewOrg').textContent       = row.dataset.org;
  document.getElementById('previewSubmitter').textContent = row.dataset.submitter;
  document.getElementById('previewDate').textContent      = row.dataset.date;
  document.getElementById('previewFileName').textContent  = fileName || '—';
  document.getElementById('previewStatus').innerHTML      = `<span class="status ${status}">${status.replace('_',' ')}</span>`;
  document.getElementById('previewDownloadLink').href     = `file_preview.php?submission_id=${id}&download=1`;
  document.getElementById('previewModal').style.display  = 'flex';
  const doneBtn = document.getElementById('markDoneBtn');
  if (status === 'approved') {
    doneBtn.disabled = true; doneBtn.innerHTML = '<i class="fas fa-check-circle"></i> Reviewed & Done';
    doneBtn.style.background = '#d1fae5'; doneBtn.style.color = '#065f46'; doneBtn.style.boxShadow = 'none';
  } else {
    doneBtn.disabled = false; doneBtn.innerHTML = '<i class="fas fa-check-double"></i> Mark as Done';
    doneBtn.style.background = ''; doneBtn.style.color = ''; doneBtn.style.boxShadow = '';
  }
  _showViewer('loading');
  const ext = fileName.split('.').pop().toLowerCase();
  if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
    const img = document.getElementById('previewImage');
    img.onload = () => _showViewer('image'); img.onerror = () => _showViewer('error','Image failed to load.');
    img.src = `file_preview.php?submission_id=${id}`;
  } else if (ext === 'pdf') {
    const iframe = document.getElementById('previewIframe');
    iframe.onload = () => _showViewer('iframe'); iframe.src = `file_preview.php?submission_id=${id}`;
    setTimeout(() => { if (document.getElementById('previewLoading').style.display !== 'none') _showViewer('iframe'); }, 2000);
  } else if (['doc','docx'].includes(ext)) {
    const iframe = document.getElementById('previewIframe');
    iframe.onload = () => _showViewer('iframe'); iframe.src = `docx_to_pdf.php?submission_id=${id}`;
    setTimeout(() => { if (document.getElementById('previewLoading').style.display !== 'none') _showViewer('iframe'); }, 5000);
  } else if (!fileName) {
    _showViewer('unsupported','No file attached to this submission.');
  } else {
    _showViewer('unsupported', `".${ext}" files cannot be previewed inline.`);
  }
}

function _showViewer(panel, msg) {
  document.getElementById('previewLoading').style.display     = panel==='loading'     ? 'flex' : 'none';
  document.getElementById('previewError').style.display       = panel==='error'       ? 'flex' : 'none';
  document.getElementById('previewIframe').style.display      = panel==='iframe'      ? 'block': 'none';
  document.getElementById('previewImage').style.display       = panel==='image'       ? 'block': 'none';
  document.getElementById('previewUnsupported').style.display = panel==='unsupported' ? 'flex' : 'none';
  if (panel==='error'       && msg) document.getElementById('previewErrorMsg').textContent       = msg;
  if (panel==='unsupported' && msg) document.getElementById('previewUnsupportedMsg').textContent = msg;
}

function markAsDone() {
  if (!_activeSubmissionId) return;
  const btn = document.getElementById('markDoneBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  const fd = new FormData();
  fd.append('action', 'mark_done'); fd.append('submission_id', _activeSubmissionId);
  fetch('submissions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const row = document.querySelector(`tr[data-id="${_activeSubmissionId}"]`);
        if (row) {
          row.dataset.status = 'approved';
          const badge = row.querySelector('.status');
          if (badge) { badge.className = 'status approved'; badge.textContent = 'Done'; }
          // Remove checkbox, replace with done icon
          const cbCell = row.cells[0];
          if (cbCell) cbCell.innerHTML = '<i class="fas fa-check-circle" style="color:#a7f3d0;font-size:0.95em;" title="Already reviewed"></i>';
        }
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Reviewed & Done';
        btn.style.background = '#d1fae5'; btn.style.color = '#065f46'; btn.style.boxShadow = 'none';
        showToast('Marked as done — now visible in Review page.', 'success');
        setTimeout(() => closePreviewModal(), 1800);
      } else {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-double"></i> Mark as Done';
        showToast(data.message || 'Something went wrong.', 'error');
      }
    })
    .catch(() => {
      btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-double"></i> Mark as Done';
      showToast('Network error. Please try again.', 'error');
    });
}

function showToast(msg, type) {
  const existing = document.querySelector('.toast-notification');
  if (existing) existing.remove();
  const toast = document.createElement('div');
  toast.className = 'toast-notification toast-' + type;
  toast.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
  document.body.appendChild(toast);
  setTimeout(() => toast.classList.add('toast-show'), 10);
  setTimeout(() => { toast.classList.remove('toast-show'); setTimeout(() => toast.remove(), 400); }, 3500);
}

function toggleNotificationPanel() {
  const panel = document.getElementById('notificationPanel');
  if (panel) panel.classList.toggle('show');
}
</script>

<script>
(function() {
    function pad(n) { return n < 10 ? '0' + n : n; }
    function tick() {
        var now = new Date();
        var h = now.getHours(), m = pad(now.getMinutes()), s = pad(now.getSeconds());
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        document.querySelectorAll('.live-clock-span').forEach(function(el) {
            el.textContent = h + ':' + m + ':' + s + ' ' + ampm;
        });
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
<script>
(function(){
  function pad(n){return n<10?'0'+n:n;}
  function tick(){
    var now=new Date(),h=now.getHours(),m=pad(now.getMinutes()),s=pad(now.getSeconds());
    var ap=h>=12?'PM':'AM'; h=h%12||12;
    document.querySelectorAll('.live-clock-span').forEach(function(el){
      el.textContent=h+':'+m+':'+s+' '+ap;
    });
  }
  tick(); setInterval(tick,1000);
})();
</script>
</body>
</html>
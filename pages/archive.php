<?php
/**
 * CIG Admin Dashboard - Document Archive Page
 * Displays archived (rejected) submissions
 */

session_start();
require_once '../db/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$db   = new Database();
$user = ['full_name' => $_SESSION['admin_email'] ?? 'Admin'];

$search_query = $_GET['search'] ?? '';
$org_filter   = $_GET['org']    ?? '';

try {
    $query = "
        SELECT s.*, u.full_name as submitted_by_name, COALESCE(org.org_name, org.full_name) as org_name
        FROM submissions s
        LEFT JOIN users u   ON s.user_id = u.user_id
        LEFT JOIN users org ON s.org_id  = org.user_id
        WHERE s.status = 'rejected'
    ";
    $params = [];

    if ($org_filter) {
        $query   .= " AND org.user_id = ?";
        $params[] = $org_filter;
    }
    if ($search_query) {
        $query   .= " AND (s.title LIKE ? OR u.full_name LIKE ? OR org.org_name LIKE ? OR org.full_name LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }

    $query .= " ORDER BY s.updated_at DESC";
    $rejected_submissions = $db->fetchAll($query, $params);

    // Stats (unfiltered)
    $all_rejected = $db->fetchAll("
        SELECT s.*, COALESCE(org.org_name, org.full_name) as org_name
        FROM submissions s
        LEFT JOIN users org ON s.org_id = org.user_id
        WHERE s.status = 'rejected'
        ORDER BY s.updated_at DESC
    ");
    $total_rejected   = count($all_rejected);
    $orgs_affected    = count(array_unique(array_filter(array_column($all_rejected, 'org_name'))));
    $this_month       = date('Y-m');
    $this_month_count = count(array_filter($all_rejected, fn($r) => str_starts_with($r['updated_at'] ?? '', $this_month)));
    $latest_date      = !empty($all_rejected) ? date('M d, Y', strtotime($all_rejected[0]['updated_at'])) : '—';

    $organizations = $db->fetchAll("SELECT user_id as org_id, COALESCE(org_name, full_name) as org_name FROM users WHERE org_code IS NOT NULL ORDER BY COALESCE(org_name, full_name) ASC");
} catch (Exception $e) {
    error_log('Archive Error: ' . $e->getMessage());
    $rejected_submissions = [];
    $organizations        = [];
    $total_rejected = $orgs_affected = $this_month_count = 0;
    $latest_date = '—';
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
$user_name    = $user['full_name'] ?? '';
?>
<?php include 'navbar.php'; ?>

<div class="page active">

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div>
      <h2><i class="fas fa-archive"></i> Document Archive</h2>
      <p class="page-subtitle">Rejected submissions history &amp; records</p>
    </div>
  </div>

  <!-- STAT CARDS -->
  <div class="stat-cards">
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#fee2e2,#fecaca);">
        <i class="fas fa-times-circle" style="color:#ef4444;"></i>
      </div>
      <div class="stat-info">
        <span class="stat-value"><?php echo $total_rejected; ?></span>
        <span class="stat-label">Total Rejected</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#fef3c7,#fde68a);">
        <i class="fas fa-building" style="color:#f59e0b;"></i>
      </div>
      <div class="stat-info">
        <span class="stat-value"><?php echo $orgs_affected; ?></span>
        <span class="stat-label">Orgs Affected</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#e0e7ff,#c7d2fe);">
        <i class="fas fa-calendar-alt" style="color:#6366f1;"></i>
      </div>
      <div class="stat-info">
        <span class="stat-value"><?php echo $this_month_count; ?></span>
        <span class="stat-label">This Month</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);">
        <i class="fas fa-clock" style="color:#10b981;"></i>
      </div>
      <div class="stat-info">
        <span class="stat-value" style="font-size:0.95em;"><?php echo $latest_date; ?></span>
        <span class="stat-label">Latest Rejection</span>
      </div>
    </div>
  </div>

  <!-- SEARCH & FILTER BAR -->
  <div class="filter-bar">
    <div class="search-wrap">
      <i class="fas fa-search search-icon-inner"></i>
      <input type="text" id="liveSearch" placeholder="Search by title, submitter, or organization…"
             value="<?php echo htmlspecialchars($search_query); ?>">
      <button class="clear-search-btn" id="clearSearch" style="display:none;" title="Clear">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <?php if (!empty($organizations)): ?>
    <div class="org-pills" id="orgPills">
      <button class="pill active" data-org="">All Orgs</button>
      <?php foreach ($organizations as $org): ?>
        <button class="pill" data-org="<?php echo htmlspecialchars($org['org_id']); ?>">
          <?php echo htmlspecialchars($org['org_name']); ?>
        </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="search-meta" id="searchMeta" style="display:none;">
      <span id="searchResultCount"></span>
      <a href="#" id="clearAllFilters" class="clear-link"><i class="fas fa-times"></i> Clear all</a>
    </div>
  </div>

  <!-- TABLE -->
  <div class="table-container">
    <table id="archiveTable">
      <thead>
        <tr>
          <th style="width:70px;">Ref No</th>
          <th>Title</th>
          <th>Organization</th>
          <th>Submitted By</th>
          <th>Submitted</th>
          <th>Rejected</th>
          <th style="width:90px;">File</th>
          <th style="width:110px;">Action</th>
        </tr>
      </thead>
      <tbody id="archiveBody">
        <?php if (!empty($rejected_submissions)): ?>
          <?php foreach ($rejected_submissions as $index => $sub): ?>
            <?php
              $sub_date  = date('M d, Y', strtotime($sub['submitted_at']));
              $rej_date  = date('M d, Y', strtotime($sub['updated_at']));
              $days_since = (int)floor((time() - strtotime($sub['updated_at'])) / 86400);
              $ext       = strtolower(pathinfo($sub['file_name'] ?? '', PATHINFO_EXTENSION));
              $file_icon  = match($ext) {
                'pdf'                   => 'fa-file-pdf',
                'doc','docx'            => 'fa-file-word',
                'xls','xlsx'            => 'fa-file-excel',
                'jpg','jpeg','png','gif','webp' => 'fa-file-image',
                default                 => 'fa-file-alt'
              };
              $file_color = match($ext) {
                'pdf'        => '#ef4444',
                'doc','docx' => '#2563eb',
                'xls','xlsx' => '#16a34a',
                'jpg','jpeg','png','gif','webp' => '#f59e0b',
                default      => '#6b7280'
              };
            ?>
            <tr
              data-title="<?php echo htmlspecialchars(strtolower($sub['title'])); ?>"
              data-org="<?php echo htmlspecialchars(strtolower($sub['org_name'] ?? '')); ?>"
              data-submitter="<?php echo htmlspecialchars(strtolower($sub['submitted_by_name'] ?? '')); ?>"
              data-org-id="<?php echo htmlspecialchars($sub['org_id'] ?? ''); ?>"
              data-id="<?php echo $sub['submission_id']; ?>"
              data-file="<?php echo htmlspecialchars($sub['file_name'] ?? ''); ?>"
              data-sub-title="<?php echo htmlspecialchars($sub['title']); ?>"
              data-sub-org="<?php echo htmlspecialchars($sub['org_name'] ?? 'N/A'); ?>"
              data-sub-submitter="<?php echo htmlspecialchars($sub['submitted_by_name'] ?? 'N/A'); ?>"
              data-sub-date="<?php echo $sub_date; ?>"
              data-rej-date="<?php echo $rej_date; ?>">
              <td class="ref-cell">#<?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?></td>
              <td class="title-cell"><strong><?php echo htmlspecialchars($sub['title']); ?></strong></td>
              <td><?php echo htmlspecialchars($sub['org_name'] ?? 'N/A'); ?></td>
              <td><?php echo htmlspecialchars($sub['submitted_by_name'] ?? 'N/A'); ?></td>
              <td><?php echo $sub_date; ?></td>
              <td>
                <?php echo $rej_date; ?>
                <span class="days-ago"><?php echo $days_since === 0 ? 'Today' : $days_since . 'd ago'; ?></span>
              </td>
              <td>
                <?php if ($sub['file_name']): ?>
                  <span class="file-badge">
                    <i class="fas <?php echo $file_icon; ?>" style="color:<?php echo $file_color; ?>;"></i>
                    <?php echo strtoupper($ext) ?: '—'; ?>
                  </span>
                <?php else: ?>
                  <span class="file-badge no-file"><i class="fas fa-ban"></i> None</span>
                <?php endif; ?>
              </td>
              <td>
                <button class="btn-preview" onclick="openArchivePreview(this)">
                  <i class="fas fa-eye"></i> Preview
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr id="emptyRow">
            <td colspan="8">
              <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>No rejected submissions</p>
                <span>The archive is currently empty.</span>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div id="noResults" style="display:none;">
      <div class="empty-state">
        <i class="fas fa-search"></i>
        <p>No results found</p>
        <span id="noResultsMsg"></span>
      </div>
    </div>
  </div>

</div><!-- /.page -->

<!-- ═══ PREVIEW MODAL ═══════════════════════════════════════════ -->
<div id="archivePreviewModal" class="modal-overlay" style="display:none;">
  <div class="modal-box modal-box-lg">

    <div class="modal-header">
      <div class="modal-header-icon" style="background:linear-gradient(135deg,#fee2e2,#fecaca);">
        <i class="fas fa-archive" style="color:#ef4444;"></i>
      </div>
      <div class="modal-header-text">
        <h3 id="apTitle">Archived Submission</h3>
        <p id="apOrg" class="modal-subtitle"></p>
      </div>
      <span class="rejected-badge"><i class="fas fa-times-circle"></i> Rejected</span>
      <button class="modal-close-btn" onclick="closeArchivePreview()"><i class="fas fa-times"></i></button>
    </div>

    <div class="preview-meta-bar">
      <div class="meta-item">
        <span class="meta-label"><i class="fas fa-user"></i> Submitted By</span>
        <span id="apSubmitter" class="meta-value"></span>
      </div>
      <div class="meta-item">
        <span class="meta-label"><i class="fas fa-calendar-plus"></i> Submitted</span>
        <span id="apSubDate" class="meta-value"></span>
      </div>
      <div class="meta-item">
        <span class="meta-label"><i class="fas fa-calendar-times"></i> Rejected</span>
        <span id="apRejDate" class="meta-value"></span>
      </div>
      <div class="meta-item">
        <span class="meta-label"><i class="fas fa-file"></i> File</span>
        <span id="apFile" class="meta-value file-name-truncate"></span>
      </div>
    </div>

    <div class="preview-viewer-wrap">
      <div id="apLoading" class="preview-loading">
        <div class="preview-spinner"></div>
        <span>Loading document…</span>
      </div>
      <div id="apError" class="preview-error" style="display:none;">
        <i class="fas fa-exclamation-triangle"></i>
        <p>Could not load preview.</p>
        <span id="apErrorMsg"></span>
      </div>
      <iframe id="apIframe" class="preview-iframe" style="display:none;" title="Document Preview"></iframe>
      <img    id="apImage"  class="preview-image"  style="display:none;" alt="File preview">
      <div id="apUnsupported" class="preview-unsupported" style="display:none;">
        <i class="fas fa-file-alt"></i>
        <p id="apUnsupportedMsg">This file type cannot be previewed inline.</p>
        <span>Use the Download button to open the file.</span>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn-modal-secondary" onclick="closeArchivePreview()">Close</button>
      <a id="apDownloadLink" href="#" class="btn-modal-download" target="_blank">
        <i class="fas fa-download"></i> Download
      </a>
    </div>

  </div>
</div>

<?php include 'footer.php'; ?>

<!-- ═══ INLINE STYLES ════════════════════════════════════════════ -->
<style>
.page-subtitle { margin:4px 0 0; color:#4a5568; font-size:0.9em; font-weight:400; }

/* STAT CARDS */
.stat-cards {
  display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:22px;
}
.stat-card {
  background:white; border-radius:14px; padding:18px 22px;
  display:flex; align-items:center; gap:16px;
  box-shadow:0 2px 12px rgba(0,0,0,0.07); border:1px solid #f0f0f0;
  transition:transform .2s,box-shadow .2s;
}
.stat-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,0.1); }
.stat-icon {
  width:48px; height:48px; border-radius:12px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center; font-size:1.3em;
}
.stat-info { display:flex; flex-direction:column; gap:2px; }
.stat-value { font-size:1.6em; font-weight:800; color:#1a202c; line-height:1; }
.stat-label { font-size:0.78em; color:#718096; font-weight:600; margin-top:3px; }

/* FILTER BAR */
.filter-bar {
  background:white; border-radius:14px; padding:16px 20px;
  box-shadow:0 2px 12px rgba(0,0,0,0.06); border:1px solid #f0f0f0;
  margin-bottom:22px; display:flex; flex-direction:column; gap:12px;
}
.search-wrap { position:relative; display:flex; align-items:center; }
.search-wrap input {
  width:100%; padding:11px 42px 11px 40px;
  border:2px solid #e2e8f0; border-radius:10px;
  font-size:0.95em; transition:.25s; background:#f8fafc; color:#1a202c;
}
.search-wrap input:focus { outline:none; border-color:#10b981; background:white; box-shadow:0 0 0 3px rgba(16,185,129,.1); }
.search-icon-inner { position:absolute; left:14px; color:#94a3b8; pointer-events:none; font-size:0.95em; }
.clear-search-btn {
  position:absolute; right:12px; background:none; border:none; color:#94a3b8;
  cursor:pointer; padding:4px; line-height:1; box-shadow:none; transition:color .2s;
}
.clear-search-btn:hover { color:#ef4444; transform:none; box-shadow:none; }

.org-pills { display:flex; flex-wrap:wrap; gap:8px; }
.pill {
  padding:6px 14px; border-radius:20px; border:1.5px solid #e2e8f0;
  background:white; font-size:0.82em; font-weight:600; color:#4a5568;
  cursor:pointer; transition:.2s; line-height:1.5; box-shadow:none;
}
.pill:hover { border-color:#10b981; color:#10b981; transform:none; box-shadow:none; }
.pill.active {
  background:linear-gradient(135deg,#10b981,#059669); color:white;
  border-color:transparent; box-shadow:0 3px 8px rgba(16,185,129,.3);
}

.search-meta { display:flex; align-items:center; gap:10px; font-size:0.85em; color:#718096; }
.clear-link { color:#ef4444; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
.clear-link:hover { text-decoration:underline; }

/* TABLE */
.table-container {
  background:white; border-radius:16px;
  box-shadow:0 4px 20px rgba(0,0,0,0.08); border:1px solid #f0f0f0; overflow:hidden;
}
table { width:100%; border-collapse:collapse; }
th, td { padding:14px 18px; text-align:left; font-size:0.93em; }
th {
  background:linear-gradient(90deg,#10b981,#059669);
  color:white; font-weight:700; font-size:0.8em;
  text-transform:uppercase; letter-spacing:.5px;
}
tbody tr { border-bottom:1px solid #f4f4f5; transition:background .2s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:nth-child(odd)  { background:#fafbfc; }
tbody tr:nth-child(even) { background:white; }
tbody tr:hover { background:linear-gradient(90deg,#fef2f2,#fff1f1); }

.ref-cell { color:#94a3b8; font-weight:700; font-size:0.85em; }
.title-cell strong { color:#1a202c; }
.days-ago { display:block; font-size:0.78em; color:#94a3b8; margin-top:2px; }

.file-badge {
  display:inline-flex; align-items:center; gap:5px; padding:4px 10px;
  border-radius:6px; font-size:0.8em; font-weight:700; background:#f1f5f9; color:#475569;
}
.file-badge.no-file { color:#cbd5e0; }

.btn-preview {
  display:inline-flex; align-items:center; gap:6px;
  padding:7px 13px; border-radius:8px; font-size:0.85em; font-weight:600;
  background:linear-gradient(135deg,#dbeafe,#bfdbfe); color:#1e40af;
  border:1px solid rgba(30,64,175,.15); cursor:pointer; transition:.2s; box-shadow:none;
}
.btn-preview:hover { background:linear-gradient(135deg,#bfdbfe,#93c5fd); transform:translateY(-1px); box-shadow:0 4px 10px rgba(30,64,175,.15); }

.empty-state { text-align:center; padding:50px 20px; color:#94a3b8; }
.empty-state i { font-size:3em; margin-bottom:12px; display:block; color:#d1d5db; }
.empty-state p { margin:0 0 6px; font-weight:600; color:#6b7280; font-size:1.1em; }

/* MODAL */
.modal-overlay {
  position:fixed; inset:0; background:rgba(0,0,0,.45); backdrop-filter:blur(4px);
  display:flex; align-items:center; justify-content:center; z-index:9999;
  animation:fadeInOverlay .2s ease;
}
@keyframes fadeInOverlay { from{opacity:0} to{opacity:1} }
.modal-box {
  background:white; border-radius:18px; max-width:580px; width:94%;
  box-shadow:0 20px 60px rgba(0,0,0,.2); overflow:hidden;
  animation:modalPop .28s cubic-bezier(.34,1.56,.64,1);
}
@keyframes modalPop { from{transform:scale(.92) translateY(20px);opacity:0} to{transform:scale(1) translateY(0);opacity:1} }
.modal-box-lg {
  max-width:1100px; width:92vw; height:90vh; max-height:90vh;
  display:flex; flex-direction:column; overflow:hidden;
}
.modal-header {
  display:flex; align-items:center; gap:14px; padding:18px 22px;
  border-bottom:1px solid #f0f0f0; flex-shrink:0;
}
.modal-header-icon {
  width:44px; height:44px; border-radius:12px;
  display:flex; align-items:center; justify-content:center; font-size:1.2em; flex-shrink:0;
}
.modal-header-text { flex:1; min-width:0; overflow:hidden; }
.modal-header-text h3 { margin:0; font-size:1.05em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.modal-subtitle { margin:2px 0 0; font-size:0.82em; color:#718096; }
.rejected-badge {
  display:inline-flex; align-items:center; gap:5px; padding:5px 11px; flex-shrink:0;
  background:linear-gradient(135deg,#fee2e2,#fecaca); color:#ef4444;
  border-radius:20px; font-size:0.78em; font-weight:700;
}
.modal-close-btn {
  background:none; border:none; color:#94a3b8; font-size:1.1em;
  cursor:pointer; padding:6px; border-radius:8px; transition:.2s; flex-shrink:0; box-shadow:none;
}
.modal-close-btn:hover { background:#f1f5f9; color:#1a202c; transform:none; }

.preview-meta-bar {
  display:grid; grid-template-columns:repeat(4,1fr);
  border-bottom:1px solid #f0f0f0; background:#f8fafc; flex-shrink:0;
}
.preview-meta-bar .meta-item {
  padding:12px 18px; display:flex; flex-direction:column; gap:3px;
  border-right:1px solid #f0f0f0;
}
.preview-meta-bar .meta-item:last-child { border-right:none; }
.meta-label { font-size:0.74em; color:#718096; font-weight:600; display:flex; align-items:center; gap:5px; }
.meta-value { font-size:0.88em; color:#1a202c; font-weight:700; }
.file-name-truncate { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px; }

.preview-viewer-wrap {
  flex:1; position:relative; background:#e8ecef; min-height:0;
  display:flex; align-items:center; justify-content:center; overflow:hidden;
}
.preview-loading { display:flex; flex-direction:column; align-items:center; gap:14px; color:#718096; font-size:.92em; font-weight:500; }
.preview-spinner { width:40px; height:40px; border:3px solid #e2e8f0; border-top-color:#10b981; border-radius:50%; animation:spin .75s linear infinite; }
@keyframes spin { to{transform:rotate(360deg)} }
.preview-error { flex-direction:column; align-items:center; gap:10px; color:#991b1b; text-align:center; padding:30px; }
.preview-error i { font-size:2.5em; color:#fca5a5; }
.preview-error p { margin:0; font-weight:700; }
.preview-unsupported { flex-direction:column; align-items:center; gap:10px; color:#718096; text-align:center; padding:30px; }
.preview-unsupported i { font-size:3em; color:#cbd5e0; }
.preview-unsupported p { margin:0; font-weight:600; color:#4a5568; }
.preview-iframe { position:absolute; inset:0; width:100%; height:100%; border:none; background:white; display:block; }
.preview-image { max-width:100%; max-height:520px; object-fit:contain; border-radius:4px; box-shadow:0 4px 20px rgba(0,0,0,.12); }

.modal-footer {
  display:flex; gap:10px; justify-content:flex-end; padding:14px 22px 18px;
  border-top:1px solid #f0f0f0; flex-shrink:0;
}
.btn-modal-secondary {
  padding:9px 20px; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;
  border-radius:9px; font-weight:600; font-size:.9em; cursor:pointer; transition:.2s; box-shadow:none;
}
.btn-modal-secondary:hover { background:#e2e8f0; transform:none; }
.btn-modal-download {
  padding:9px 20px; background:linear-gradient(135deg,#8b5cf6,#7c3aed); color:white;
  border:none; border-radius:9px; font-weight:600; font-size:.9em; cursor:pointer;
  display:inline-flex; align-items:center; gap:7px;
  box-shadow:0 3px 10px rgba(139,92,246,.3); text-decoration:none; font-family:inherit; transition:.2s;
}
.btn-modal-download:hover { background:linear-gradient(135deg,#7c3aed,#6d28d9); transform:translateY(-1px); color:white; }

@media(max-width:900px) { .stat-cards { grid-template-columns:repeat(2,1fr); } }
@media(max-width:700px) {
  .preview-meta-bar { grid-template-columns:repeat(2,1fr); }
  .modal-box-lg { height:98vh; max-height:98vh; width:100vw; border-radius:12px; }
}
</style>

<script src="../js/navbar.js"></script>
<script>
// ── LIVE SEARCH + ORG PILL FILTER ───────────────────────────────
(function () {
  const searchInput  = document.getElementById('liveSearch');
  const clearBtn     = document.getElementById('clearSearch');
  const pills        = document.querySelectorAll('#orgPills .pill');
  const tbody        = document.getElementById('archiveBody');
  const rows         = tbody ? Array.from(tbody.querySelectorAll('tr[data-id]')) : [];
  const noResults    = document.getElementById('noResults');
  const noResultsMsg = document.getElementById('noResultsMsg');
  const searchMeta   = document.getElementById('searchMeta');
  const resultCount  = document.getElementById('searchResultCount');
  const clearAll     = document.getElementById('clearAllFilters');

  let activeOrg = '';
  let searchVal = '';

  function filter() {
    let visible = 0;
    rows.forEach(row => {
      const matchSearch = !searchVal ||
        (row.dataset.title     || '').includes(searchVal) ||
        (row.dataset.org       || '').includes(searchVal) ||
        (row.dataset.submitter || '').includes(searchVal);
      const matchOrg = !activeOrg || (row.dataset.orgId || '') === activeOrg;

      row.style.display = matchSearch && matchOrg ? '' : 'none';
      if (matchSearch && matchOrg) visible++;
    });

    if (noResults)    noResults.style.display    = (visible === 0 && rows.length > 0) ? 'block' : 'none';
    if (noResultsMsg) noResultsMsg.textContent   = `No results for "${searchVal || 'current filter'}"`;
    if (searchMeta)   searchMeta.style.display   = (searchVal || activeOrg) ? 'flex' : 'none';
    if (resultCount)  resultCount.textContent    = `${visible} result${visible !== 1 ? 's' : ''} found`;
    if (clearBtn)     clearBtn.style.display     = searchVal ? 'flex' : 'none';
  }

  let debounce;
  searchInput?.addEventListener('input', function () {
    searchVal = this.value.toLowerCase().trim();
    clearTimeout(debounce);
    debounce = setTimeout(filter, 160);
  });

  clearBtn?.addEventListener('click', function () {
    searchInput.value = ''; searchVal = ''; filter(); searchInput.focus();
  });

  pills.forEach(pill => {
    pill.addEventListener('click', function () {
      pills.forEach(p => p.classList.remove('active'));
      this.classList.add('active');
      activeOrg = this.dataset.org;
      filter();
    });
  });

  clearAll?.addEventListener('click', function (e) {
    e.preventDefault();
    searchInput.value = ''; searchVal = ''; activeOrg = '';
    pills.forEach(p => p.classList.remove('active'));
    document.querySelector('.pill[data-org=""]')?.classList.add('active');
    filter();
  });

  filter();
})();

// ── PREVIEW MODAL ───────────────────────────────────────────────
function openArchivePreview(btn) {
  const row      = btn.closest('tr');
  const id       = row.dataset.id;
  const fileName = row.dataset.file || '';

  document.getElementById('apTitle').textContent     = row.dataset.subTitle;
  document.getElementById('apOrg').textContent       = row.dataset.subOrg;
  document.getElementById('apSubmitter').textContent = row.dataset.subSubmitter;
  document.getElementById('apSubDate').textContent   = row.dataset.subDate;
  document.getElementById('apRejDate').textContent   = row.dataset.rejDate;
  document.getElementById('apFile').textContent      = fileName || '—';
  document.getElementById('apDownloadLink').href     = `file_preview.php?submission_id=${id}&download=1`;

  document.getElementById('archivePreviewModal').style.display = 'flex';
  _apShow('loading');

  const ext = fileName.split('.').pop().toLowerCase();

  if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
    const img = document.getElementById('apImage');
    img.onload  = () => _apShow('image');
    img.onerror = () => _apShow('error', 'Image failed to load.');
    img.src = `file_preview.php?submission_id=${id}`;
  } else if (ext === 'pdf') {
    const iframe = document.getElementById('apIframe');
    iframe.onload = () => _apShow('iframe');
    iframe.src = `file_preview.php?submission_id=${id}`;
    setTimeout(() => { if (document.getElementById('apLoading').style.display !== 'none') _apShow('iframe'); }, 2000);
  } else if (['doc','docx'].includes(ext)) {
    const iframe = document.getElementById('apIframe');
    iframe.onload = () => _apShow('iframe');
    iframe.src = `docx_to_pdf.php?submission_id=${id}`;
    setTimeout(() => { if (document.getElementById('apLoading').style.display !== 'none') _apShow('iframe'); }, 5000);
  } else if (ext === 'txt') {
    const iframe = document.getElementById('apIframe');
    iframe.onload = () => _apShow('iframe');
    iframe.src = `file_preview.php?submission_id=${id}`;
  } else if (!fileName) {
    _apShow('unsupported', 'No file attached to this submission.');
  } else {
    _apShow('unsupported', `".${ext}" files cannot be previewed inline.`);
  }
}

function _apShow(panel, msg) {
  document.getElementById('apLoading').style.display     = panel === 'loading'     ? 'flex'  : 'none';
  document.getElementById('apError').style.display       = panel === 'error'       ? 'flex'  : 'none';
  document.getElementById('apIframe').style.display      = panel === 'iframe'      ? 'block' : 'none';
  document.getElementById('apImage').style.display       = panel === 'image'       ? 'block' : 'none';
  document.getElementById('apUnsupported').style.display = panel === 'unsupported' ? 'flex'  : 'none';
  if (panel === 'error'       && msg) document.getElementById('apErrorMsg').textContent       = msg;
  if (panel === 'unsupported' && msg) document.getElementById('apUnsupportedMsg').textContent = msg;
}

function closeArchivePreview() {
  document.getElementById('apIframe').src = 'about:blank';
  document.getElementById('archivePreviewModal').style.display = 'none';
}

window.addEventListener('click', e => {
  if (e.target === document.getElementById('archivePreviewModal')) closeArchivePreview();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeArchivePreview(); });

function toggleNotificationPanel() {
  const p = document.getElementById('notificationPanel');
  if (p) p.style.display = p.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
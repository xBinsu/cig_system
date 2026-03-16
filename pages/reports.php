<?php
/**
 * CIG Admin Dashboard - Reports & Analytics
 * Org rankings, point system, certificate eligibility, activity stats
 */

session_start();
require_once '../db/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$db   = new Database();
$user = ['full_name' => $_SESSION['admin_email'] ?? 'Admin'];

// ── POINT SYSTEM CONFIG ──────────────────────────────────────────
define('PTS_APPROVED',  10);  // per approved submission
define('PTS_SUBMITTED',  5);  // per any submission
define('PTS_IN_REVIEW',  3);  // per in_review submission
define('PTS_REJECTED',  -2);  // per rejected submission
define('BONUS_RATE',    15);  // bonus if approval rate >= 80%
define('BONUS_ACTIVE',  10);  // bonus if >= 5 submissions this month
define('CERT_GOLD',     80);  // gold certificate threshold
define('CERT_SILVER',   50);  // silver certificate threshold
define('CERT_BRONZE',   25);  // bronze certificate threshold

try {
    // Global stats
    $global = $db->fetchRow("
        SELECT
            COUNT(*) as total_submissions,
            SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status='approved'  THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status='rejected'  THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status='in_review' THEN 1 ELSE 0 END) as in_review
        FROM submissions
    ");

    $user_stats = $db->fetchRow("SELECT COUNT(*) as total_users FROM users");

    $approval_rate = ($global && $global['total_submissions'] > 0)
        ? round(($global['approved'] / $global['total_submissions']) * 100)
        : 0;

    // Per-org stats
    $this_month = date('Y-m');

    $org_rows = $db->fetchAll("
        SELECT
            org.user_id                                          AS org_id,
            COALESCE(org.org_name, org.full_name)               AS org_name,
            org.org_code,
            COUNT(s.submission_id)                               AS total_subs,
            SUM(CASE WHEN s.status='approved'  THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN s.status='rejected'  THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN s.status='in_review' THEN 1 ELSE 0 END) AS in_review,
            SUM(CASE WHEN s.status='pending'   THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN DATE_FORMAT(s.submitted_at,'%Y-%m') = ? THEN 1 ELSE 0 END) AS this_month,
            MAX(s.submitted_at) AS last_activity
        FROM users org
        LEFT JOIN submissions s ON s.org_id = org.user_id
        WHERE org.org_code IS NOT NULL
        GROUP BY org.user_id
        ORDER BY total_subs DESC
    ", [$this_month]);

    // Compute points & cert per org
    foreach ($org_rows as &$org) {
        $pts  = 0;
        $pts += ($org['approved']  ?? 0) * PTS_APPROVED;
        $pts += ($org['total_subs']?? 0) * PTS_SUBMITTED;
        $pts += ($org['in_review'] ?? 0) * PTS_IN_REVIEW;
        $pts += ($org['rejected']  ?? 0) * PTS_REJECTED;
        $rate  = $org['total_subs'] > 0 ? ($org['approved'] / $org['total_subs']) * 100 : 0;
        if ($rate >= 80)                    $pts += BONUS_RATE;
        if (($org['this_month'] ?? 0) >= 5) $pts += BONUS_ACTIVE;

        $org['points']        = max(0, $pts);
        $org['approval_rate'] = round($rate);

        if ($pts >= CERT_GOLD)       { $org['cert'] = 'gold';   $org['cert_label'] = 'Gold';   }
        elseif ($pts >= CERT_SILVER) { $org['cert'] = 'silver'; $org['cert_label'] = 'Silver'; }
        elseif ($pts >= CERT_BRONZE) { $org['cert'] = 'bronze'; $org['cert_label'] = 'Bronze'; }
        else                         { $org['cert'] = 'none';   $org['cert_label'] = 'None';   }

        $org['last_activity_fmt'] = $org['last_activity']
            ? date('M d, Y', strtotime($org['last_activity'])) : 'No activity';
    }
    unset($org);

    // Sort by points descending
    usort($org_rows, fn($a,$b) => $b['points'] <=> $a['points']);

    // Monthly trend (last 6 months)
    $monthly = $db->fetchAll("
        SELECT DATE_FORMAT(submitted_at,'%b %Y') as label,
               DATE_FORMAT(submitted_at,'%Y-%m') as ym,
               COUNT(*) as count
        FROM submissions
        GROUP BY DATE_FORMAT(submitted_at,'%Y-%m')
        ORDER BY ym DESC
        LIMIT 6
    ");
    $monthly = array_reverse($monthly);

    // Recent activity
    $recent_activity = $db->fetchAll("
        SELECT al.*, u.full_name, u.username
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        ORDER BY al.created_at DESC
        LIMIT 15
    ");

} catch (Exception $e) {
    error_log('Reports Error: ' . $e->getMessage());
    $global = null; $user_stats = null;
    $org_rows = []; $monthly = []; $recent_activity = [];
    $approval_rate = 0;
}

$total_subs    = $global['total_submissions'] ?? 0;
$total_users   = $user_stats['total_users']   ?? 0;
$cert_eligible = count(array_filter($org_rows, fn($o) => $o['cert'] !== 'none'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports &amp; Analytics - Admin</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="../css/reports.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>
<body>

<?php $current_page = 'reports'; $user_name = $user['full_name'] ?? ''; ?>
<?php include 'navbar.php'; ?>

<div class="page active">

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div>
      <h2><i class="fas fa-chart-bar"></i> Reports &amp; Analytics</h2>
      <p class="page-subtitle">Organization performance, rankings &amp; certificate eligibility</p>
    </div>
    <button class="btn-export" onclick="window.print()">
      <i class="fas fa-print"></i> Print Report
    </button>
  </div>

  <!-- GLOBAL STAT CARDS -->
  <div class="stat-grid">
    <div class="stat-card accent-blue">
      <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
      <div class="stat-body"><span class="stat-num"><?php echo $total_subs; ?></span><span class="stat-lbl">Total Submissions</span></div>
    </div>
    <div class="stat-card accent-green">
      <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
      <div class="stat-body"><span class="stat-num"><?php echo $approval_rate; ?>%</span><span class="stat-lbl">Approval Rate</span></div>
    </div>
    <div class="stat-card accent-purple">
      <div class="stat-icon"><i class="fas fa-building"></i></div>
      <div class="stat-body"><span class="stat-num"><?php echo count($org_rows); ?></span><span class="stat-lbl">Organizations</span></div>
    </div>
    <div class="stat-card accent-gold">
      <div class="stat-icon"><i class="fas fa-certificate"></i></div>
      <div class="stat-body"><span class="stat-num"><?php echo $cert_eligible; ?></span><span class="stat-lbl">Cert-Eligible Orgs</span></div>
    </div>
    <div class="stat-card accent-red">
      <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
      <div class="stat-body"><span class="stat-num"><?php echo $global['rejected'] ?? 0; ?></span><span class="stat-lbl">Rejected</span></div>
    </div>
    <div class="stat-card accent-teal">
      <div class="stat-icon"><i class="fas fa-users"></i></div>
      <div class="stat-body"><span class="stat-num"><?php echo $total_users; ?></span><span class="stat-lbl">Registered Users</span></div>
    </div>
  </div>

  <!-- CHARTS ROW -->
  <div class="two-col">
    <div class="panel">
      <div class="panel-header">
        <i class="fas fa-chart-line"></i>
        <h3>Monthly Submissions</h3>
        <span class="panel-badge">Last 6 months</span>
      </div>
      <div style="padding:16px 20px;">
        <canvas id="monthlyChart" height="160"></canvas>
      </div>
    </div>
    <div class="panel">
      <div class="panel-header">
        <i class="fas fa-chart-pie"></i>
        <h3>Status Breakdown</h3>
      </div>
      <div style="padding:16px 20px; display:flex; flex-direction:column; align-items:center; gap:14px;">
        <div style="max-width:200px; width:100%;"><canvas id="statusChart"></canvas></div>
        <div class="donut-legend">
          <span class="legend-dot" style="background:#f59e0b;"></span>Pending <strong><?php echo $global['pending'] ?? 0; ?></strong>
          <span class="legend-dot" style="background:#10b981;"></span>Approved <strong><?php echo $global['approved'] ?? 0; ?></strong>
          <span class="legend-dot" style="background:#ef4444;"></span>Rejected <strong><?php echo $global['rejected'] ?? 0; ?></strong>
          <span class="legend-dot" style="background:#3b82f6;"></span>In Review <strong><?php echo $global['in_review'] ?? 0; ?></strong>
        </div>
      </div>
    </div>
  </div>

  <!-- POINT SYSTEM KEY -->
  <div class="panel">
    <div class="panel-header">
      <i class="fas fa-star"></i>
      <h3>Point System &amp; Certificate Thresholds</h3>
    </div>
    <div class="points-key-grid">
      <div class="pts-rule gain"><i class="fas fa-check"></i>Approved submission<span>+<?php echo PTS_APPROVED; ?> pts</span></div>
      <div class="pts-rule gain"><i class="fas fa-upload"></i>Any submission<span>+<?php echo PTS_SUBMITTED; ?> pts</span></div>
      <div class="pts-rule gain"><i class="fas fa-search"></i>In-review submission<span>+<?php echo PTS_IN_REVIEW; ?> pts</span></div>
      <div class="pts-rule loss"><i class="fas fa-times"></i>Rejected submission<span><?php echo PTS_REJECTED; ?> pts</span></div>
      <div class="pts-rule bonus"><i class="fas fa-fire"></i>≥80% approval rate bonus<span>+<?php echo BONUS_RATE; ?> pts</span></div>
      <div class="pts-rule bonus"><i class="fas fa-bolt"></i>≥5 submissions this month<span>+<?php echo BONUS_ACTIVE; ?> pts</span></div>
    </div>
    <div class="cert-thresholds">
      <div class="cert-thresh gold"><i class="fas fa-award"></i>Gold Certificate — <?php echo CERT_GOLD; ?>+ pts</div>
      <div class="cert-thresh silver"><i class="fas fa-award"></i>Silver Certificate — <?php echo CERT_SILVER; ?>+ pts</div>
      <div class="cert-thresh bronze"><i class="fas fa-award"></i>Bronze Certificate — <?php echo CERT_BRONZE; ?>+ pts</div>
    </div>
  </div>

  <!-- ORG RANKINGS -->
  <div class="panel">
    <div class="panel-header">
      <i class="fas fa-trophy"></i>
      <h3>Organization Rankings</h3>
      <span class="panel-badge"><?php echo count($org_rows); ?> organizations</span>
    </div>

    <?php if (!empty($org_rows)): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th style="width:60px; text-align:center;">Rank</th>
            <th>Organization</th>
            <th style="width:110px; text-align:center;">Points</th>
            <th style="width:60px; text-align:center;">Total</th>
            <th style="width:70px; text-align:center;">Approved</th>
            <th style="width:70px; text-align:center;">Rejected</th>
            <th style="width:100px; text-align:center;">Approval %</th>
            <th style="width:90px; text-align:center;">This Month</th>
            <th>Last Active</th>
            <th style="width:110px; text-align:center;">Certificate</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($org_rows as $rank => $org):
            $rank1   = $rank + 1;
            $rankCls = $rank1 === 1 ? 'rank-1' : ($rank1 === 2 ? 'rank-2' : ($rank1 === 3 ? 'rank-3' : ''));
            $medal   = $rank1 === 1 ? '🥇' : ($rank1 === 2 ? '🥈' : ($rank1 === 3 ? '🥉' : "#$rank1"));
            $bar_w   = ($org_rows[0]['points'] > 0) ? round(($org['points'] / $org_rows[0]['points']) * 100) : 0;
          ?>
          <tr class="rank-row <?php echo $rankCls; ?>">
            <td style="text-align:center;"><span class="medal"><?php echo $medal; ?></span></td>
            <td>
              <div class="org-name-cell">
                <?php
                  $lf = false;
                  if ($org['org_code']) {
                    foreach (['jpg','jpeg','png','gif','webp'] as $ex) {
                      if (file_exists(__DIR__ . "/../assets/{$org['org_code']}.{$ex}")) {
                        echo '<img src="../assets/' . htmlspecialchars($org['org_code']) . '.' . $ex . '" class="rank-logo">';
                        $lf = true; break;
                      }
                    }
                  }
                  if (!$lf) echo '<div class="rank-logo-placeholder"><i class="fas fa-building"></i></div>';
                ?>
                <div>
                  <strong><?php echo htmlspecialchars($org['org_name']); ?></strong>
                  <?php if ($org['org_code']): ?>
                    <span class="org-code-badge"><?php echo htmlspecialchars($org['org_code']); ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td style="text-align:center;">
              <div class="pts-display <?php echo $org['cert']; ?>"><?php echo $org['points']; ?></div>
              <div class="pts-bar-track"><div class="pts-bar-fill <?php echo $org['cert']; ?>" style="width:<?php echo $bar_w; ?>%"></div></div>
            </td>
            <td style="text-align:center;"><?php echo $org['total_subs']; ?></td>
            <td style="text-align:center;" class="text-green"><?php echo $org['approved']; ?></td>
            <td style="text-align:center;" class="text-red"><?php echo $org['rejected']; ?></td>
            <td style="text-align:center;">
              <span class="rate-pill <?php echo $org['approval_rate']>=80?'rate-high':($org['approval_rate']>=50?'rate-mid':'rate-low'); ?>">
                <?php echo $org['approval_rate']; ?>%
              </span>
            </td>
            <td style="text-align:center;">
              <?php if ($org['this_month'] > 0): ?>
                <span class="month-badge"><?php echo $org['this_month']; ?></span>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td class="text-muted" style="font-size:.85em;"><?php echo $org['last_activity_fmt']; ?></td>
            <td style="text-align:center;">
              <?php if ($org['cert'] !== 'none'): ?>
                <span class="cert-badge <?php echo $org['cert']; ?>">
                  <i class="fas fa-award"></i> <?php echo $org['cert_label']; ?>
                </span>
              <?php else: ?><span class="cert-none">Not yet</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-building"></i>
        <p>No organization data yet</p>
        <span>Rankings will appear once submissions are received.</span>
      </div>
    <?php endif; ?>
  </div>

  <!-- RECENT ACTIVITY -->
  <div class="panel">
    <div class="panel-header">
      <i class="fas fa-history"></i>
      <h3>Recent System Activity</h3>
      <span class="panel-badge">Last 15 actions</span>
    </div>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>User</th><th>Action</th><th>Details</th><th>IP Address</th><th>Date &amp; Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($recent_activity)): ?>
            <?php foreach ($recent_activity as $act): ?>
              <tr>
                <td><?php echo htmlspecialchars($act['full_name'] ?? 'Unknown'); ?></td>
                <td><span class="action-tag"><?php echo htmlspecialchars(ucfirst($act['action'] ?? '')); ?></span></td>
                <td class="text-muted" style="font-size:.85em;"><?php echo htmlspecialchars($act['details'] ?? '—'); ?></td>
                <td class="text-muted" style="font-size:.85em;"><?php echo htmlspecialchars($act['ip_address'] ?? 'N/A'); ?></td>
                <td class="text-muted" style="font-size:.85em;"><?php echo date('M d, Y H:i', strtotime($act['created_at'])); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">No recent activity logged.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php include 'footer.php'; ?>

<style>
/* PAGE HEADER */
.page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:26px; padding-bottom:18px; border-bottom:3px solid #0d4a2d; }
.page-subtitle { margin:4px 0 0; color:#4a5568; font-size:.9em; }
.btn-export {
  padding:9px 18px; background:linear-gradient(135deg,#10b981,#059669); color:white;
  border:none; border-radius:9px; font-weight:600; font-size:.88em; cursor:pointer;
  display:inline-flex; align-items:center; gap:7px; box-shadow:0 3px 10px rgba(16,185,129,.3);
  transition:.2s; flex-shrink:0;
}
.btn-export:hover { transform:translateY(-1px); box-shadow:0 5px 16px rgba(16,185,129,.4); }

/* STAT GRID */
.stat-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:14px; margin-bottom:22px; }
.stat-card {
  background:white; border-radius:14px; padding:16px 18px;
  display:flex; align-items:center; gap:13px;
  box-shadow:0 2px 12px rgba(0,0,0,.07); border:1px solid #f0f0f0;
  border-top:3px solid transparent; transition:transform .2s,box-shadow .2s;
}
.stat-card:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,.1); }
.accent-blue   { border-top-color:#3b82f6; } .accent-blue   .stat-icon { color:#3b82f6; }
.accent-green  { border-top-color:#10b981; } .accent-green  .stat-icon { color:#10b981; }
.accent-purple { border-top-color:#8b5cf6; } .accent-purple .stat-icon { color:#8b5cf6; }
.accent-gold   { border-top-color:#f59e0b; } .accent-gold   .stat-icon { color:#f59e0b; }
.accent-red    { border-top-color:#ef4444; } .accent-red    .stat-icon { color:#ef4444; }
.accent-teal   { border-top-color:#06b6d4; } .accent-teal   .stat-icon { color:#06b6d4; }
.stat-icon { font-size:1.4em; opacity:.8; flex-shrink:0; }
.stat-body { display:flex; flex-direction:column; }
.stat-num  { font-size:1.6em; font-weight:800; color:#1a202c; line-height:1; }
.stat-lbl  { font-size:.73em; color:#718096; font-weight:600; margin-top:3px; }

/* TWO COL */
.two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
.donut-legend { display:flex; flex-wrap:wrap; gap:8px 16px; font-size:.82em; color:#4a5568; align-items:center; }
.legend-dot { display:inline-block; width:10px; height:10px; border-radius:50%; }
.donut-legend strong { font-weight:700; color:#1a202c; }

/* PANEL */
.panel { background:white; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.08); border:1px solid #f0f0f0; margin-bottom:22px; overflow:hidden; }
.panel-header { display:flex; align-items:center; gap:10px; padding:15px 22px; border-bottom:1px solid #f4f4f5; background:#fafbfc; }
.panel-header i { color:#10b981; font-size:1.05em; }
.panel-header h3 { margin:0; font-size:1em; font-weight:700; color:#1a202c; flex:1; }
.panel-badge { background:#e8f5e9; color:#2e7d32; font-size:.74em; font-weight:700; padding:3px 10px; border-radius:20px; }

/* POINTS KEY */
.points-key-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; padding:16px 22px; }
.pts-rule { display:flex; align-items:center; gap:8px; padding:10px 14px; border-radius:10px; font-size:.84em; font-weight:600; }
.pts-rule i { font-size:.9em; flex-shrink:0; }
.pts-rule span { margin-left:auto; font-weight:800; }
.pts-rule.gain   { background:#f0fdf4; color:#15803d; } .pts-rule.gain i { color:#10b981; }
.pts-rule.loss   { background:#fef2f2; color:#b91c1c; } .pts-rule.loss i { color:#ef4444; }
.pts-rule.bonus  { background:#fffbeb; color:#92400e; } .pts-rule.bonus i{ color:#f59e0b; }
.cert-thresholds { display:flex; gap:12px; padding:0 22px 20px; flex-wrap:wrap; }
.cert-thresh { display:inline-flex; align-items:center; gap:8px; padding:8px 16px; border-radius:10px; font-size:.83em; font-weight:700; }
.cert-thresh.gold   { background:linear-gradient(135deg,#fef3c7,#fde68a); color:#92400e; }
.cert-thresh.silver { background:linear-gradient(135deg,#f1f5f9,#e2e8f0); color:#475569; }
.cert-thresh.bronze { background:linear-gradient(135deg,#fff7ed,#fed7aa); color:#9a3412; }

/* TABLE */
.table-container { overflow-x:auto; }
table { width:100%; border-collapse:collapse; }
th,td { padding:13px 16px; text-align:left; font-size:.9em; }
thead tr th { background:linear-gradient(90deg,#10b981,#059669); color:white; font-weight:700; font-size:.78em; text-transform:uppercase; letter-spacing:.5px; }
tbody tr { border-bottom:1px solid #f4f4f5; transition:background .2s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:nth-child(odd)  { background:#fafbfc; }
tbody tr:nth-child(even) { background:white; }
tbody tr:hover { background:#f0fdf4; }

.rank-row.rank-1 { background:linear-gradient(90deg,#fffde7,#fefce8) !important; }
.rank-row.rank-2 { background:linear-gradient(90deg,#f9fafb,#f1f5f9) !important; }
.rank-row.rank-3 { background:linear-gradient(90deg,#fff8f5,#fef0e6) !important; }
.rank-row.rank-1:hover { background:linear-gradient(90deg,#fef9c3,#fef3c7) !important; }
.medal { font-size:1.3em; }

.org-name-cell { display:flex; align-items:center; gap:10px; }
.rank-logo { width:34px; height:34px; border-radius:50%; object-fit:cover; flex-shrink:0; border:2px solid #e2e8f0; }
.rank-logo-placeholder { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#d1fae5,#a7f3d0); display:flex; align-items:center; justify-content:center; color:#10b981; font-size:.85em; flex-shrink:0; }
.org-code-badge { background:#f1f5f9; color:#64748b; font-size:.72em; font-weight:700; padding:2px 6px; border-radius:4px; margin-left:4px; }

.pts-display { font-size:1.15em; font-weight:800; text-align:center; }
.pts-display.gold   { color:#92400e; } .pts-display.silver { color:#475569; }
.pts-display.bronze { color:#b45309; } .pts-display.none   { color:#9ca3af; }
.pts-bar-track { height:4px; background:#f1f5f9; border-radius:2px; margin-top:5px; }
.pts-bar-fill  { height:100%; border-radius:2px; }
.pts-bar-fill.gold   { background:linear-gradient(90deg,#f59e0b,#d97706); }
.pts-bar-fill.silver { background:linear-gradient(90deg,#94a3b8,#64748b); }
.pts-bar-fill.bronze { background:linear-gradient(90deg,#fb923c,#ea580c); }
.pts-bar-fill.none   { background:#e2e8f0; }

.rate-pill { display:inline-block; padding:3px 10px; border-radius:20px; font-size:.8em; font-weight:700; }
.rate-high { background:#dcfce7; color:#15803d; } .rate-mid { background:#fef9c3; color:#92400e; } .rate-low { background:#fee2e2; color:#b91c1c; }
.month-badge { background:#e0e7ff; color:#4338ca; font-size:.8em; font-weight:700; padding:3px 9px; border-radius:20px; }
.cert-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:.78em; font-weight:700; }
.cert-badge.gold   { background:linear-gradient(135deg,#fef3c7,#fde68a); color:#92400e; }
.cert-badge.silver { background:linear-gradient(135deg,#f1f5f9,#e2e8f0); color:#475569; }
.cert-badge.bronze { background:linear-gradient(135deg,#fff7ed,#fed7aa); color:#9a3412; }
.cert-none { font-size:.78em; color:#cbd5e0; font-weight:600; }
.action-tag { background:#e0e7ff; color:#4338ca; font-size:.8em; font-weight:700; padding:3px 9px; border-radius:6px; }
.text-green { color:#16a34a; font-weight:700; } .text-red { color:#dc2626; font-weight:700; } .text-muted { color:#94a3b8; }
.empty-state { text-align:center; padding:50px 20px; color:#94a3b8; }
.empty-state i { font-size:3em; display:block; margin-bottom:12px; color:#d1d5db; }
.empty-state p { margin:0 0 6px; font-weight:600; color:#6b7280; }

@media print {
  .btn-export, nav { display:none; }
  .page { padding:10px; background:white !important; animation:none !important; }
  .panel { box-shadow:none; border:1px solid #ddd; break-inside:avoid; }
}
@media(max-width:1100px) { .stat-grid { grid-template-columns:repeat(3,1fr); } }
@media(max-width:800px)  { .two-col { grid-template-columns:1fr; } .points-key-grid { grid-template-columns:repeat(2,1fr); } .stat-grid { grid-template-columns:repeat(2,1fr); } }
</style>

<script src="../js/navbar.js"></script>
<script>
const monthlyLabels = <?php echo json_encode(array_column($monthly, 'label')); ?>;
const monthlyCounts = <?php echo json_encode(array_map('intval', array_column($monthly, 'count'))); ?>;

if (monthlyLabels.length > 0) {
  new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
      labels: monthlyLabels,
      datasets: [{ label: 'Submissions', data: monthlyCounts,
        backgroundColor: 'rgba(16,185,129,0.75)', borderColor:'#059669',
        borderWidth:1.5, borderRadius:6 }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display:false } },
      scales: {
        y: { beginAtZero:true, ticks:{ stepSize:1 }, grid:{ color:'#f0f0f0' } },
        x: { grid:{ display:false } }
      }
    }
  });
}

const pending   = <?php echo (int)($global['pending']   ?? 0); ?>;
const approved  = <?php echo (int)($global['approved']  ?? 0); ?>;
const rejected  = <?php echo (int)($global['rejected']  ?? 0); ?>;
const in_review = <?php echo (int)($global['in_review'] ?? 0); ?>;

if (pending + approved + rejected + in_review > 0) {
  new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
      labels: ['Pending','Approved','Rejected','In Review'],
      datasets: [{ data:[pending,approved,rejected,in_review],
        backgroundColor:['#f59e0b','#10b981','#ef4444','#3b82f6'],
        borderWidth:2, borderColor:'#fff', hoverOffset:6 }]
    },
    options: { responsive:true, cutout:'68%', plugins:{ legend:{ display:false } } }
  });
}

function toggleNotificationPanel() {
  const p = document.getElementById('notificationPanel');
  if (p) p.style.display = p.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
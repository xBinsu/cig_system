<?php
session_start();
require_once '../db/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$db = new Database();

$accredDocs = [
    ['key'=>'letter_of_intent',        'seq'=>1,  'label'=>'Letter of Intent',                    'hint'=>'OSLS Form 1 s. 24–25'],
    ['key'=>'constitution_bylaws',     'seq'=>2,  'label'=>'Constitution and By-Laws',             'hint'=>'Signed by all officers & advisers'],
    ['key'=>'resolution_ratification', 'seq'=>3,  'label'=>'Resolution / Ratification',            'hint'=>'For amendments if applicable'],
    ['key'=>'list_of_officers',        'seq'=>4,  'label'=>'List of Officers',                     'hint'=>'With photo, position, college/program'],
    ['key'=>'list_of_members',         'seq'=>5,  'label'=>'List of Members',                      'hint'=>'Name, program, year/section, contact'],
    ['key'=>'list_of_representatives', 'seq'=>6,  'label'=>'List of Representatives',              'hint'=>'Gender Dev, Mental Health, Anti-Hazing, etc.'],
    ['key'=>'pledge_against_hazing',   'seq'=>7,  'label'=>'Pledge Against Hazing',                'hint'=>'Signed by President and Chief Adviser'],
    ['key'=>'adviser_acceptance',      'seq'=>8,  'label'=>'Adviser Letter of Acceptance',         'hint'=>'At least 2 teacher-advisers required'],
    ['key'=>'calendar_activities',     'seq'=>9,  'label'=>'Proposed Calendar of Activities',      'hint'=>'Must not conflict with institutional activities'],
    ['key'=>'calendar_plan',           'seq'=>10, 'label'=>'Proposed Calendar Plan of Activities', 'hint'=>'Include partners, objectives, budget source'],
    ['key'=>'jpia_audited_report',     'seq'=>11, 'label'=>'JPIA Audited Report',                  'hint'=>'Previous A.Y. financial statement'],
];
$totalRequired = count($accredDocs);

$orgs = $db->fetchAll("
    SELECT u.user_id, u.org_name, u.org_code, u.full_name, u.contact_person,
           u.phone, u.description, u.credentials_verified, u.logo_path, u.created_at,
           COUNT(DISTINCT CASE WHEN d.doc_status IN ('submitted','revision','approved') THEN d.doc_key END) AS docs_submitted,
           COUNT(DISTINCT CASE WHEN d.doc_status = 'approved'  THEN d.doc_key END) AS docs_approved,
           COUNT(DISTINCT CASE WHEN d.doc_status = 'revision'  THEN d.doc_key END) AS docs_revision
    FROM users u
    LEFT JOIN documents d ON d.user_id = u.user_id AND d.doc_key IS NOT NULL
    WHERE u.role = 'user'
    GROUP BY u.user_id
    ORDER BY docs_submitted DESC, u.created_at ASC
") ?? [];

$totalOrgs = count($orgs);
$fullyApproved = $pendingReview = $needsRevision = $notStarted = 0;
foreach ($orgs as $o) {
    if     ($o['credentials_verified'])       $fullyApproved++;
    elseif ((int)$o['docs_revision']  > 0)    $needsRevision++;
    elseif ((int)$o['docs_submitted'] > 0)    $pendingReview++;
    else                                       $notStarted++;
}

// Logo resolver — same logic as before
function resolveLogoSrc($logoPath) {
    if (empty($logoPath)) return '';
    $sep   = DIRECTORY_SEPARATOR;
    $parts = explode($sep, rtrim(__DIR__, $sep));
    array_pop($parts); array_pop($parts);
    $htdocs = implode($sep, $parts);
    $tries  = [
        $htdocs . $sep . 'cig_user' . $sep . 'org-dashboard' . $sep . ltrim(str_replace(['/','\\'],[  $sep,$sep],$logoPath),$sep),
        __DIR__ . $sep . ltrim(str_replace(['/','\\'],[$sep,$sep],$logoPath),$sep),
    ];
    foreach ($tries as $p) {
        if ($p && file_exists($p) && is_file($p)) {
            $ext  = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            $mime = in_array($ext,['jpg','jpeg']) ? 'image/jpeg' : 'image/png';
            return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($p));
        }
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Accreditation Review — CIG Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="../css/dashboard.css">
<style>
/* ── Accreditation-specific overrides on top of dashboard.css ── */

.accred-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.2rem;
    margin-top: 1.5rem;
}

/* Org card — inherits .chart-card look */
.org-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e8f5e9;
    box-shadow: 0 2px 12px rgba(0,50,0,0.06);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: box-shadow .2s, border-color .2s, transform .2s;
}
.org-card:hover {
    box-shadow: 0 6px 24px rgba(0,50,0,0.12);
    border-color: #a5d6a7;
    transform: translateY(-2px);
}

/* Color strip top */
.org-card-strip { height: 4px; flex-shrink: 0; }
.org-card-strip.s-approved  { background: linear-gradient(90deg,#22c55e,#16a34a); }
.org-card-strip.s-pending   { background: linear-gradient(90deg,#3b82f6,#1d4ed8); }
.org-card-strip.s-revision  { background: linear-gradient(90deg,#f97316,#ea580c); }
.org-card-strip.s-unstarted { background: linear-gradient(90deg,#d1d5db,#9ca3af); }

/* Card header */
.org-card-head {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1rem 1.2rem 0.6rem;
}
.org-logo-wrap {
    width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0;
    overflow: hidden; background: #e8f5ee;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem; font-weight: 800; color: #2e7d32;
    border: 1.5px solid #c8e6c9;
}
.org-logo-wrap img { width: 100%; height: 100%; object-fit: cover; }
.org-name  { font-size: 0.93rem; font-weight: 700; color: #1b5e20; margin: 0 0 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.org-meta  { font-size: 0.72rem; color: #81a888; }

/* Status badge — reuse dashboard .status style */
.org-status-pill {
    font-size: 0.67rem; font-weight: 700;
    padding: 0.18rem 0.6rem; border-radius: 20px;
    white-space: nowrap; flex-shrink: 0;
}
.org-status-pill.approved  { background: #d1fae5; color: #065f46; }
.org-status-pill.pending   { background: #dbeafe; color: #1e3a8a; }
.org-status-pill.revision  { background: #ffedd5; color: #7c2d12; }
.org-status-pill.unstarted { background: #f3f4f6; color: #374151; }

/* Progress bar */
.org-prog-wrap { padding: 0 1.2rem 0.75rem; }
.org-prog-label {
    display: flex; justify-content: space-between;
    font-size: 0.7rem; color: #9ab5ac; font-weight: 600; margin-bottom: 4px;
}
.org-prog-track { height: 6px; background: #e8f5e9; border-radius: 20px; overflow: hidden; }
.org-prog-fill  { height: 100%; border-radius: 20px; transition: width .5s; }

/* Doc list */
.org-doc-list { padding: 0 1.2rem; border-top: 1px solid #f0f7f0; }
.org-doc-row {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.38rem 0; border-bottom: 1px solid #f4faf4;
    font-size: 0.76rem;
}
.org-doc-row:last-child { border-bottom: none; }
.doc-num {
    width: 19px; height: 19px; border-radius: 5px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.6rem; font-weight: 800;
}
.doc-num.pending   { background: #f0f5f2; color: #9ab5ac; }
.doc-num.submitted { background: #dbeafe; color: #1d4ed8; }
.doc-num.revision  { background: #ffedd5; color: #b45309; }
.doc-num.approved  { background: #d1fae5; color: #065f46; }
.doc-lbl { flex: 1; color: #374151; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.doc-lbl.done { color: #b0c8b0; text-decoration: line-through; }
.doc-actions { display: flex; gap: 0.28rem; flex-shrink: 0; }
.dbtn {
    padding: 0.15rem 0.5rem; border-radius: 5px; font-size: 0.68rem; font-weight: 700;
    cursor: pointer; border: none; font-family: inherit;
    display: inline-flex; align-items: center; gap: 0.2rem; transition: .15s;
}
.dbtn.view    { background: #f0fdf4; color: #15803d; }
.dbtn.view:hover    { background: #dcfce7; }
.dbtn.approve { background: #dcfce7; color: #15803d; }
.dbtn.approve:hover { background: #bbf7d0; }
.dbtn.reject  { background: #fee2e2; color: #dc2626; }
.dbtn.reject:hover  { background: #fecaca; }
.doc-none { font-size: 0.76rem; color: #b0c8b0; font-style: italic; padding: 0.6rem 0; }

/* Card footer */
.org-card-foot {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.75rem 1.2rem; border-top: 1px solid #f0f7f0; margin-top: 0.5rem;
    flex-wrap: wrap; gap: 0.5rem;
}
.foot-meta { font-size: 0.7rem; color: #9ab5ac; }

/* Action buttons — match dashboard .edit-btn / .delete-btn style */
.accred-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.4rem 0.9rem; border-radius: 8px;
    font-size: 0.78rem; font-weight: 700; cursor: pointer; border: none;
    font-family: inherit; transition: .15s;
}
.accred-btn.grant {
    background: linear-gradient(135deg,#1b5e20,#2e7d32);
    color: #fff; box-shadow: 0 2px 8px rgba(27,94,32,.25);
}
.accred-btn.grant:hover { filter: brightness(1.12); transform: translateY(-1px); }
.accred-btn.grant:disabled { opacity: 0.38; cursor: not-allowed; filter: none; transform: none; }
.accred-btn.revoke {
    background: #fff0f0; color: #dc2626; border: 1.5px solid #fecaca;
}
.accred-btn.revoke:hover { background: #fee2e2; }

/* Search bar */
.accred-search-wrap {
    position: relative;
    width: 100%;
    max-width: 520px;
}
.accred-search-wrap .search-icon {
    position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
    color: #81a888; font-size: 0.95rem; pointer-events: none;
    transition: color .2s;
}
.accred-search-wrap:focus-within .search-icon { color: #2e7d32; }
.accred-search-input {
    width: 100%; padding: 0.72rem 3rem 0.72rem 2.8rem;
    border: 1.5px solid #e0f2e0; border-radius: 12px;
    background: #fff; font-size: 0.9rem; color: #1b5e20;
    font-family: inherit; outline: none; box-sizing: border-box;
    box-shadow: 0 2px 8px rgba(0,60,0,0.06);
    transition: border-color .2s, box-shadow .2s;
}
.accred-search-input::placeholder { color: #a8c5a8; }
.accred-search-input:focus {
    border-color: #2e7d32;
    box-shadow: 0 0 0 3.5px rgba(46,125,50,0.12), 0 2px 8px rgba(0,60,0,0.08);
}
.accred-search-clear {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: #e8f5e9; border: none; border-radius: 50%;
    width: 22px; height: 22px; cursor: pointer;
    display: none; align-items: center; justify-content: center;
    color: #2e7d32; font-size: 0.7rem; transition: background .15s;
}
.accred-search-clear:hover { background: #c8e6c9; }
.accred-search-count {
    font-size: 0.78rem; color: #81a888; font-weight: 500;
    white-space: nowrap; margin-left: 0.75rem;
}

.accred-empty { grid-column: 1/-1; text-align: center; padding: 4rem 2rem; color: #9ab5ac; }
.accred-empty i { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; }

/* ── Modals — match dashboard modal style ── */
.ar-modal-bg {
    display: none; position: fixed; inset: 0; z-index: 3000;
    background: rgba(0,30,0,0.55); backdrop-filter: blur(4px);
    align-items: center; justify-content: center;
}
.ar-modal-bg.open { display: flex; }
.ar-modal {
    background: #fff; border-radius: 16px;
    width: 94vw; max-width: 780px; max-height: 92vh;
    display: flex; flex-direction: column; overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,30,0,0.25);
    animation: arPop .25s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes arPop { from{opacity:0;transform:scale(.94) translateY(16px)} to{opacity:1;transform:scale(1) translateY(0)} }
.ar-modal-hd {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1rem 1.4rem; background: #1b5e20; color: #fff; flex-shrink: 0;
}
.ar-modal-hd i  { color: #81c784; font-size: 1.05rem; }
.ar-modal-hd h2 { font-size: 0.97rem; font-weight: 700; margin: 0; flex: 1; }
.ar-modal-x {
    width: 28px; height: 28px; border-radius: 50%; border: none; cursor: pointer;
    background: rgba(255,255,255,0.12); color: #fff; font-size: 0.95rem;
    display: flex; align-items: center; justify-content: center; transition: .18s;
}
.ar-modal-x:hover { background: rgba(255,255,255,0.22); }
.ar-modal-bd { flex: 1; overflow-y: auto; padding: 1.3rem 1.4rem; }
.ar-modal-ft {
    padding: 0.8rem 1.4rem; border-top: 1px solid #e8f5e9;
    display: flex; justify-content: flex-end; gap: 0.5rem;
    background: #f9fdf9; flex-shrink: 0;
}
.ar-iframe {
    width: 100%; height: 370px; border: none;
    border-radius: 8px; display: block;
    border: 1.5px solid #c8e6c9; background: #f5f5f5; margin-bottom: 1rem;
}
.ar-notes-lbl {
    display: block; font-size: 0.75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em; color: #1b5e20; margin-bottom: 0.3rem;
}
.ar-notes-ta {
    width: 100%; padding: 0.6rem 0.9rem; border: 1.5px solid #c8e6c9;
    border-radius: 8px; font-size: 0.87rem; font-family: inherit;
    background: #f9fdf9; color: #1b4020; outline: none;
    box-sizing: border-box; resize: vertical; min-height: 75px;
}
.ar-notes-ta:focus { border-color: #2e7d32; background: #fff; box-shadow: 0 0 0 3px rgba(46,125,50,.09); }
.ar-mbtn {
    padding: 0.5rem 1.15rem; border-radius: 8px; font-size: 0.86rem; font-weight: 700;
    cursor: pointer; border: none; font-family: inherit;
    display: inline-flex; align-items: center; gap: 0.38rem; transition: .15s;
}
.ar-mbtn.cancel     { background: #f1f5f1; color: #4a7a4a; border: 1.5px solid #c8e6c9; }
.ar-mbtn.cancel:hover { background: #e8f5e9; }
.ar-mbtn.do-approve { background: linear-gradient(135deg,#1b5e20,#2e7d32); color: #fff; box-shadow: 0 3px 10px rgba(27,94,32,.25); }
.ar-mbtn.do-approve:hover { filter: brightness(1.1); }
.ar-mbtn.do-reject  { background: linear-gradient(135deg,#b71c1c,#d32f2f); color: #fff; box-shadow: 0 3px 10px rgba(183,28,28,.2); }
.ar-mbtn.do-reject:hover  { filter: brightness(1.1); }
.ar-mbtn:disabled   { opacity: 0.45; cursor: not-allowed; filter: none; }

/* Toast */
.ar-toast {
    position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
    padding: 0.7rem 1.2rem; border-radius: 10px; font-size: 0.86rem; font-weight: 600;
    display: flex; align-items: center; gap: 0.5rem;
    box-shadow: 0 6px 20px rgba(0,0,0,.18); opacity: 0;
    transform: translateY(10px); transition: all .3s; pointer-events: none;
}
.ar-toast.show { opacity: 1; transform: translateY(0); }
.ar-toast.ok  { background: #1b5e20; color: #fff; }
.ar-toast.err { background: #c62828; color: #fff; }

@media(max-width:900px) {
    .accred-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<?php
$current_page = 'accreditation';
$user_name    = $_SESSION['admin_email'] ?? 'Admin';
?>
<?php include 'navbar.php'; ?>

<div class="main">
<div style="padding: 30px;">

    <!-- Page header -->
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
        <div>
            <h2 style="margin:0;"><i class="fas fa-certificate"></i> Accreditation Review</h2>
            <p style="margin:0.2rem 0 0;font-size:0.84rem;color:#5a875a;">Review documents · Grant system access once all <?= $totalRequired ?> requirements are met</p>
        </div>
        <div style="display:inline-flex;align-items:center;gap:0.5rem;
                    background:#fff;border:1.5px solid #e0f2e0;border-radius:10px;
                    padding:0.4rem 1rem;font-size:0.84rem;font-weight:600;color:#2e7d32;">
                <i class="fas fa-calendar-alt" style="font-size:0.8rem;color:#81a888;"></i>
                <span><?= date('l, F j, Y') ?></span>
                <span style="color:#c8e6c9;">|</span>
                <i class="fas fa-clock" style="font-size:0.8rem;color:#81a888;"></i>
                <span class="live-clock-span"><?= date('h:i:s A') ?></span>
            </div>
    </div>

    <!-- KPI cards — same class names as dashboard -->
    <div class="kpi-cards">
        <div class="kpi-card total">
            <div class="kpi-icon"><i class="fas fa-building"></i></div>
            <h3 class="kpi-title">Total Organizations</h3>
            <p class="kpi-value"><?= $totalOrgs ?></p>
            <div class="kpi-change">Registered orgs</div>
        </div>
        <div class="kpi-card approved">
            <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
            <h3 class="kpi-title">Fully Accredited</h3>
            <p class="kpi-value"><?= $fullyApproved ?></p>
            <div class="kpi-change">Access granted</div>
        </div>
        <div class="kpi-card pending">
            <div class="kpi-icon"><i class="fas fa-hourglass-half"></i></div>
            <h3 class="kpi-title">Pending Review</h3>
            <p class="kpi-value"><?= $pendingReview ?></p>
            <div class="kpi-change">Awaiting assessment</div>
        </div>
        <div class="kpi-card rejected">
            <div class="kpi-icon"><i class="fas fa-rotate-right"></i></div>
            <h3 class="kpi-title">Needs Revision</h3>
            <p class="kpi-value"><?= $needsRevision ?></p>
            <div class="kpi-change">Flagged for changes</div>
        </div>
    </div>

    <!-- Toolbar -->
    <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-top:1.5rem;">
        <div class="accred-search-wrap">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="arSearch" class="accred-search-input"
                   placeholder="Search by organization name or code…"
                   autocomplete="off">
            <button class="accred-search-clear" id="arClear" title="Clear search">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <span class="accred-search-count" id="arCount"></span>
    </div>

    <!-- Org cards grid -->
    <div class="accred-grid" id="arGrid">

    <?php foreach ($orgs as $org):
        $uid            = (int)$org['user_id'];
        $verified       = !empty($org['credentials_verified']);
        $approvedCount  = (int)$org['docs_approved'];
        $submittedCount = (int)$org['docs_submitted'];
        $revisionCount  = (int)$org['docs_revision'];
        $pct            = $totalRequired > 0 ? round(($approvedCount / $totalRequired) * 100) : 0;

        if     ($verified)           { $stripC='s-approved'; $pillTxt='✓ Accredited';    $pillC='approved'; }
        elseif ($revisionCount > 0)  { $stripC='s-revision'; $pillTxt='⚠ Needs Revision'; $pillC='revision'; }
        elseif ($submittedCount > 0) { $stripC='s-pending';  $pillTxt='⏳ Under Review';  $pillC='pending'; }
        else                         { $stripC='s-unstarted';$pillTxt='Not Started';      $pillC='unstarted'; }

        $barClr = $pct===100 ? '#22c55e' : ($pct>=50 ? '#3b82f6' : ($pct>0 ? '#f97316' : '#d1d5db'));
        $logoSrc  = resolveLogoSrc($org['logo_path'] ?? '');
        $initials = strtoupper(substr($org['org_code'] ?? $org['org_name'] ?? 'ORG', 0, 3));

        $credFields = ['org_name','org_code','contact_person','phone','description'];
        $credFilled = 0;
        foreach ($credFields as $cf) { if (!empty(trim($org[$cf]??''))) $credFilled++; }

        $orgDocs = [];
        $docRows = $db->fetchAll("SELECT doc_key,doc_label,doc_status,file_name,file_path,admin_notes,document_id FROM documents WHERE user_id=? AND doc_key IS NOT NULL", [$uid]) ?? [];
        foreach ($docRows as $dr) $orgDocs[$dr['doc_key']] = $dr;

        $canGrant = ($approvedCount === $totalRequired && $credFilled === 5 && !$verified);
        $filterKey = rtrim($stripC, 's-');
        // derive filter key properly
        $fkey = str_replace('s-','',$stripC);
    ?>
    <div class="org-card"
         data-org="<?= htmlspecialchars(strtolower(($org['org_name']??'').' '.($org['org_code']??''))) ?>"
         data-filter="<?= $fkey ?>">

        <div class="org-card-strip <?= $stripC ?>"></div>

        <div class="org-card-head">
            <div class="org-logo-wrap">
                <?php if ($logoSrc): ?>
                    <img src="<?= $logoSrc ?>" alt="">
                <?php else: ?>
                    <?= $initials ?>
                <?php endif; ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div class="org-name"><?= htmlspecialchars($org['org_name'] ?? 'Unnamed Organization') ?></div>
                <div class="org-meta">
                    <?= htmlspecialchars($org['org_code'] ?? '—') ?>
                    &nbsp;·&nbsp;
                    <?= htmlspecialchars($org['contact_person'] ?? 'No contact') ?>
                </div>
            </div>
            <span class="org-status-pill <?= $pillC ?>"><?= $pillTxt ?></span>
        </div>

        <!-- Progress bar -->
        <div class="org-prog-wrap">
            <div class="org-prog-label">
                <span>Document Approval</span>
                <span><?= $approvedCount ?> / <?= $totalRequired ?></span>
            </div>
            <div class="org-prog-track">
                <div class="org-prog-fill" style="width:<?= $pct ?>%;background:<?= $barClr ?>;"></div>
            </div>
        </div>

        <!-- Doc list -->
        <div class="org-doc-list">
            <?php if (empty($orgDocs)): ?>
                <p class="doc-none"><i class="fas fa-inbox"></i> No documents uploaded yet.</p>
            <?php else: ?>
            <?php foreach ($accredDocs as $adoc):
                $dk     = $adoc['key'];
                $sub    = $orgDocs[$dk] ?? null;
                $dstat  = $sub['doc_status'] ?? 'pending';
                $docId  = (int)($sub['document_id'] ?? 0);
                $hasF   = !empty($sub['file_path']);
                $seqHtml = ($dstat==='approved')
                    ? '<i class="fas fa-check" style="font-size:.48rem"></i>'
                    : $adoc['seq'];
            ?>
            <div class="org-doc-row">
                <div class="doc-num <?= $dstat ?>"><?= $seqHtml ?></div>
                <div class="doc-lbl <?= $dstat==='approved'?'done':'' ?>"><?= htmlspecialchars($adoc['label']) ?></div>
                <div class="doc-actions">
                    <?php if ($hasF): ?>
                    <button class="dbtn view" onclick="previewDoc(<?= $docId ?>,'<?= addslashes(htmlspecialchars($adoc['label'])) ?>')">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php endif; ?>
                    <?php if ($sub && $dstat!=='approved' && $hasF): ?>
                    <button class="dbtn approve" onclick="openReview(<?= $docId ?>,<?= $uid ?>,'approve','<?= addslashes(htmlspecialchars($adoc['label'])) ?>')">
                        <i class="fas fa-check"></i> OK
                    </button>
                    <button class="dbtn reject" onclick="openReview(<?= $docId ?>,<?= $uid ?>,'reject','<?= addslashes(htmlspecialchars($adoc['label'])) ?>')">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php elseif ($dstat==='approved'): ?>
                    <button class="dbtn reject" title="Undo approval" onclick="openReview(<?= $docId ?>,<?= $uid ?>,'reject','<?= addslashes(htmlspecialchars($adoc['label'])) ?>')">
                        <i class="fas fa-undo"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Card footer -->
        <div class="org-card-foot">
            <div class="foot-meta">
                <i class="fas fa-shield-check" style="color:#2e7d32;margin-right:.3rem"></i>
                Profile: <?= $credFilled ?>/5 &nbsp;·&nbsp; Docs: <?= $approvedCount ?>/<?= $totalRequired ?>
            </div>
            <div>
                <?php if ($verified): ?>
                <button class="accred-btn revoke" onclick="toggleAccred(<?= $uid ?>,0,this)">
                    <i class="fas fa-ban"></i> Revoke
                </button>
                <?php else: ?>
                <button class="accred-btn grant"
                    <?= $canGrant ? '' : 'disabled title="All '.$totalRequired.' docs must be approved &amp; profile complete"' ?>
                    onclick="toggleAccred(<?= $uid ?>,1,this)">
                    <i class="fas fa-unlock"></i> Grant Access
                </button>
                <?php endif; ?>
            </div>
        </div>

    </div>
    <?php endforeach; ?>

    <div class="accred-empty" id="arEmpty" style="display:none;">
        <i class="fas fa-search"></i>
        <p>No organizations match your filter.</p>
    </div>

    </div><!-- /accred-grid -->

</div>
</div><!-- /main -->

<?php include 'footer.php'; ?>

<!-- Review modal -->
<div class="ar-modal-bg" id="arReviewBg">
    <div class="ar-modal">
        <div class="ar-modal-hd">
            <i class="fas fa-file-check"></i>
            <h2 id="arRevTitle">Review Document</h2>
            <button class="ar-modal-x" onclick="closeReview()"><i class="fas fa-times"></i></button>
        </div>
        <div class="ar-modal-bd">
            <iframe class="ar-iframe" id="arRevFrame" src="" title="Document Preview"></iframe>
            <label class="ar-notes-lbl">
                <i class="fas fa-comment-dots" style="color:#2e7d32;margin-right:.3rem"></i>
                Admin Notes <span style="font-weight:400;color:#9ab5ac;text-transform:none;letter-spacing:0;">(optional)</span>
            </label>
            <textarea class="ar-notes-ta" id="arNotes" placeholder="Add notes for the organization…"></textarea>
        </div>
        <div class="ar-modal-ft">
            <button class="ar-mbtn cancel"     onclick="closeReview()">Cancel</button>
            <button class="ar-mbtn do-reject"  id="arRejectBtn"  onclick="submitReview('revision')"><i class="fas fa-rotate-right"></i> Request Revision</button>
            <button class="ar-mbtn do-approve" id="arApproveBtn" onclick="submitReview('approve')"><i class="fas fa-check"></i> Approve</button>
        </div>
    </div>
</div>

<!-- Preview modal -->
<div class="ar-modal-bg" id="arPreviewBg">
    <div class="ar-modal" style="max-width:900px;">
        <div class="ar-modal-hd">
            <i class="fas fa-eye"></i>
            <h2 id="arPrevTitle">View Document</h2>
            <button class="ar-modal-x" onclick="closePreview()"><i class="fas fa-times"></i></button>
        </div>
        <div class="ar-modal-bd">
            <iframe class="ar-iframe" id="arPrevFrame" src="" title="Preview" style="height:500px;margin-bottom:0;"></iframe>
        </div>
        <div class="ar-modal-ft">
            <button class="ar-mbtn cancel" onclick="closePreview()">Close</button>
        </div>
    </div>
</div>

<div class="ar-toast" id="arToast"></div>

<script src="../js/navbar.js"></script>
<script>
var _docId = null, _orgId = null;

// Search
var _allCards = document.querySelectorAll('.org-card');
var searchEl  = document.getElementById('arSearch');
var clearBtn  = document.getElementById('arClear');
var countEl   = document.getElementById('arCount');

function updateCount(vis) {
    countEl.textContent = searchEl.value.trim()
        ? vis + ' of ' + _allCards.length + ' org' + (_allCards.length !== 1 ? 's' : '')
        : _allCards.length + ' organization' + (_allCards.length !== 1 ? 's' : '');
}
updateCount(_allCards.length);

searchEl.addEventListener('input', function() {
    var q   = this.value.toLowerCase().trim();
    var vis = 0;
    clearBtn.style.display = q ? 'flex' : 'none';
    _allCards.forEach(function(c) {
        var match = !q || (c.dataset.org || '').includes(q);
        c.style.display = match ? '' : 'none';
        if (match) vis++;
    });
    document.getElementById('arEmpty').style.display = (vis === 0 && q) ? 'block' : 'none';
    updateCount(vis);
});

clearBtn.addEventListener('click', function() {
    searchEl.value = '';
    clearBtn.style.display = 'none';
    _allCards.forEach(function(c) { c.style.display = ''; });
    document.getElementById('arEmpty').style.display = 'none';
    updateCount(_allCards.length);
    searchEl.focus();
});

// Review modal
function openReview(docId, orgId, action, label) {
    _docId = docId; _orgId = orgId;
    document.getElementById('arRevTitle').textContent = 'Review: ' + label;
    document.getElementById('arNotes').value = '';
    document.getElementById('arRevFrame').src = 'accreditation_file.php?doc_id=' + docId;
    document.getElementById('arApproveBtn').style.opacity = action==='approve' ? '1' : '0.6';
    document.getElementById('arRejectBtn').style.opacity  = action==='reject'  ? '1' : '0.6';
    document.getElementById('arReviewBg').classList.add('open');
}
function closeReview() {
    document.getElementById('arReviewBg').classList.remove('open');
    document.getElementById('arRevFrame').src = '';
    _docId = _orgId = null;
}
function submitReview(action) {
    if (!_docId) return;
    var btn = action==='approve' ? document.getElementById('arApproveBtn') : document.getElementById('arRejectBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';
    var fd = new FormData();
    fd.append('action',action); fd.append('doc_id',_docId);
    fd.append('org_id',_orgId); fd.append('notes',document.getElementById('arNotes').value.trim());
    fetch('accreditation_review_action.php',{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(d) {
        if (d.success) { toast(d.message,true); closeReview(); setTimeout(function(){location.reload();},900); }
        else {
            toast(d.message||'Error.',false); btn.disabled=false;
            btn.innerHTML = action==='approve' ? '<i class="fas fa-check"></i> Approve' : '<i class="fas fa-rotate-right"></i> Request Revision';
        }
    }).catch(function(e){toast('Error: '+e.message,false);btn.disabled=false;});
}

// Preview modal
function previewDoc(docId, label) {
    document.getElementById('arPrevTitle').textContent = label;
    document.getElementById('arPrevFrame').src = 'accreditation_file.php?doc_id=' + docId;
    document.getElementById('arPreviewBg').classList.add('open');
}
function closePreview() {
    document.getElementById('arPreviewBg').classList.remove('open');
    document.getElementById('arPrevFrame').src = '';
}

// Grant / revoke
function toggleAccred(orgId, grant, btn) {
    if (!confirm(grant ? 'Grant full system access to this organization?' : 'Revoke this organization\'s accreditation?')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    var fd = new FormData();
    fd.append('action', grant?'grant':'revoke'); fd.append('org_id', orgId);
    fetch('accreditation_review_action.php',{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(d) {
        if (d.success) { toast(d.message,true); setTimeout(function(){location.reload();},900); }
        else { toast(d.message||'Error.',false); btn.disabled=false; btn.innerHTML = grant?'<i class="fas fa-unlock"></i> Grant Access':'<i class="fas fa-ban"></i> Revoke'; }
    }).catch(function(e){toast('Error: '+e.message,false);btn.disabled=false;});
}

// Backdrop / Escape
['arReviewBg','arPreviewBg'].forEach(function(id){
    document.getElementById(id).addEventListener('click',function(e){if(e.target===this){closeReview();closePreview();}});
});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeReview();closePreview();}});

// Toast
function toast(msg,ok){
    var t=document.getElementById('arToast');
    t.textContent=msg; t.className='ar-toast '+(ok?'ok':'err');
    void t.offsetWidth; t.classList.add('show');
    setTimeout(function(){t.classList.remove('show');},3400);
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
</body>
</html>
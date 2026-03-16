<?php
session_start();
require_once '../db/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$db = new Database();

// ── The 11 required accreditation documents ──────────────────────────────────
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

// ── Load all org users with their accreditation document counts ──────────────
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

// ── Summary counts ────────────────────────────────────────────────────────────
$totalOrgs = count($orgs);
$fullyApproved = $pendingReview = $needsRevision = $notStarted = 0;
foreach ($orgs as $o) {
    if     ($o['credentials_verified'])       $fullyApproved++;
    elseif ((int)$o['docs_revision']  > 0)    $needsRevision++;
    elseif ((int)$o['docs_submitted'] > 0)    $pendingReview++;
    else                                       $notStarted++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Accreditation Review — CIG Admin</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── Page layout ── */
.ar-page { padding: 1.6rem 2rem 4rem; max-width: 1380px; margin: 0 auto; }

.ar-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.6rem; }
.ar-header-left { display:flex; align-items:center; gap:0.9rem; }
.ar-header-icon {
    width:50px; height:50px; border-radius:14px; flex-shrink:0;
    background:linear-gradient(135deg,#1a3d2b,#2d6a4f);
    display:flex; align-items:center; justify-content:center;
    font-size:1.2rem; color:#fff; box-shadow:0 4px 14px rgba(45,106,79,0.3);
}
.ar-header h1 { font-size:1.3rem; font-weight:800; color:#1a3d2b; margin:0 0 0.15rem; }
.ar-header p  { font-size:0.82rem; color:#6b8f7a; margin:0; }

/* ── Stats ── */
.ar-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.6rem; }
.ar-stat {
    background:#fff; border-radius:16px; padding:1.1rem 1.3rem;
    border:1px solid #e9f0ec; box-shadow:0 2px 10px rgba(0,30,0,0.04);
    display:flex; align-items:center; gap:0.9rem; border-left:4px solid transparent;
}
.ar-stat-icon { width:42px; height:42px; border-radius:12px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:1.05rem; }
.ar-stat-num  { font-size:1.6rem; font-weight:800; color:#1a3d2b; line-height:1.1; display:block; }
.ar-stat-lbl  { font-size:0.73rem; color:#6b9080; font-weight:500; display:block; }

/* ── Toolbar ── */
.ar-toolbar { display:flex; align-items:center; gap:0.75rem; margin-bottom:1.2rem; flex-wrap:wrap; }
.ar-search {
    display:flex; align-items:center; gap:0.5rem;
    background:#fff; border:1px solid #e9f0ec; border-radius:40px;
    padding:0.45rem 1rem; min-width:260px;
}
.ar-search:focus-within { border-color:#2d6a4f; }
.ar-search i { color:#9ab5ac; font-size:0.85rem; }
.ar-search input { border:none; background:transparent; outline:none; font-size:0.88rem; color:#1e3a3a; font-family:inherit; width:100%; }
.ar-filter-pill {
    padding:0.42rem 1rem; border-radius:40px; border:1.5px solid #e9f0ec;
    background:#fff; font-size:0.82rem; font-weight:600; color:#6b8f7a;
    cursor:pointer; transition:.18s; font-family:inherit;
}
.ar-filter-pill:hover  { border-color:#2d6a4f; color:#2d6a4f; }
.ar-filter-pill.active { background:#1a3d2b; border-color:#1a3d2b; color:#fff; }

/* ── Cards ── */
.ar-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(370px,1fr)); gap:1.2rem; }
.ar-card {
    background:#fff; border-radius:18px; overflow:hidden;
    border:1.5px solid #e9f0ec; box-shadow:0 3px 14px rgba(0,20,10,0.05);
    display:flex; flex-direction:column; transition:box-shadow .18s,border-color .18s;
}
.ar-card:hover { box-shadow:0 6px 24px rgba(0,20,10,0.1); border-color:#c8ddd5; }
.ar-strip { height:5px; flex-shrink:0; }
.ar-strip.approved  { background:linear-gradient(90deg,#22c55e,#16a34a); }
.ar-strip.pending   { background:linear-gradient(90deg,#3b82f6,#1d4ed8); }
.ar-strip.revision  { background:linear-gradient(90deg,#f97316,#ea580c); }
.ar-strip.unstarted { background:linear-gradient(90deg,#d1d5db,#9ca3af); }

.ar-card-head { display:flex; align-items:center; gap:0.85rem; padding:1rem 1.2rem 0.7rem; }
.ar-org-logo {
    width:46px; height:46px; border-radius:12px; flex-shrink:0; overflow:hidden;
    background:#e8f5ee; display:flex; align-items:center; justify-content:center;
    font-size:1rem; color:#2d6a4f; font-weight:800; border:2px solid #e8f0eb;
}
.ar-org-logo img { width:100%; height:100%; object-fit:cover; }
.ar-org-name { font-size:0.95rem; font-weight:800; color:#1a3d2b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.ar-org-code { font-size:0.75rem; color:#6b9080; margin-top:1px; }
.ar-badge { font-size:0.68rem; font-weight:700; border-radius:20px; padding:0.22rem 0.6rem; white-space:nowrap; flex-shrink:0; }
.ar-badge.approved  { background:#dcfce7; color:#14532d; }
.ar-badge.pending   { background:#dbeafe; color:#1e3a8a; }
.ar-badge.revision  { background:#ffedd5; color:#7c2d12; }
.ar-badge.unstarted { background:#f3f4f6; color:#374151; }

/* Progress */
.ar-prog { padding:0 1.2rem 0.8rem; }
.ar-prog-meta { display:flex; justify-content:space-between; font-size:0.72rem; color:#9ab5ac; margin-bottom:0.35rem; font-weight:600; }
.ar-prog-track { height:7px; background:#e9f0ec; border-radius:20px; overflow:hidden; }
.ar-prog-fill  { height:100%; border-radius:20px; transition:width .5s; }

/* Doc rows */
.ar-docs { padding:0 1.2rem; }
.ar-doc-row { display:flex; align-items:center; gap:0.6rem; padding:0.42rem 0; border-bottom:1px solid #f4f7f5; font-size:0.78rem; }
.ar-doc-row:last-child { border-bottom:none; }
.ar-doc-seq { width:20px; height:20px; border-radius:5px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:0.62rem; font-weight:800; }
.ar-doc-seq.pending   { background:#f0f5f2; color:#9ab5ac; }
.ar-doc-seq.submitted { background:#dbeafe; color:#1d4ed8; }
.ar-doc-seq.revision  { background:#ffedd5; color:#b45309; }
.ar-doc-seq.approved  { background:#dcfce7; color:#15803d; }
.ar-doc-lbl { flex:1; color:#374151; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.ar-doc-lbl.done { color:#b0c4ba; text-decoration:line-through; }
.ar-doc-btns { display:flex; gap:0.3rem; flex-shrink:0; }
.ar-db {
    padding:0.18rem 0.55rem; border-radius:6px; font-size:0.7rem; font-weight:700;
    cursor:pointer; border:none; font-family:inherit;
    display:inline-flex; align-items:center; gap:0.25rem; transition:.15s;
}
.ar-db.view    { background:#f0f5f2; color:#2d6a4f; }
.ar-db.view:hover    { background:#e8f5ee; }
.ar-db.approve { background:#dcfce7; color:#15803d; }
.ar-db.approve:hover { background:#bbf7d0; }
.ar-db.reject  { background:#fee2e2; color:#dc2626; }
.ar-db.reject:hover  { background:#fecaca; }
.ar-doc-none { font-size:0.78rem; color:#b0c4ba; font-style:italic; padding:0.5rem 0; }

/* Card footer */
.ar-card-foot {
    padding:0.85rem 1.2rem; border-top:1px solid #f0f5f2; margin-top:0.6rem;
    display:flex; align-items:center; justify-content:space-between; gap:0.6rem; flex-wrap:wrap;
}
.ar-foot-meta { font-size:0.72rem; color:#9ab5ac; }
.ar-foot-btns { display:flex; gap:0.5rem; }
.ar-btn {
    padding:0.45rem 1rem; border-radius:8px; font-size:0.8rem; font-weight:700;
    cursor:pointer; border:none; font-family:inherit;
    display:inline-flex; align-items:center; gap:0.35rem; transition:.15s;
}
.ar-btn.grant { background:linear-gradient(135deg,#1a3d2b,#2d6a4f); color:#fff; box-shadow:0 2px 8px rgba(26,61,43,0.25); }
.ar-btn.grant:hover { filter:brightness(1.12); transform:translateY(-1px); }
.ar-btn.grant:disabled { opacity:0.4; cursor:not-allowed; filter:none; transform:none; box-shadow:none; }
.ar-btn.revoke { background:#fff0f0; color:#dc2626; border:1.5px solid #fecaca; }
.ar-btn.revoke:hover { background:#fee2e2; }

.ar-empty { grid-column:1/-1; text-align:center; padding:4rem 2rem; color:#9ab5ac; }
.ar-empty i { font-size:2.5rem; margin-bottom:0.8rem; display:block; }

/* ── Modals ── */
.ar-modal-bg {
    display:none; position:fixed; inset:0; z-index:3000;
    background:rgba(4,14,9,0.65); backdrop-filter:blur(4px);
    align-items:center; justify-content:center;
}
.ar-modal-bg.open { display:flex; }
.ar-modal {
    background:#fff; border-radius:22px; width:94vw; max-width:780px; max-height:92vh;
    display:flex; flex-direction:column; overflow:hidden;
    box-shadow:0 28px 70px rgba(0,20,10,0.3);
    animation:arPop .28s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes arPop { from{opacity:0;transform:scale(.93) translateY(18px)} to{opacity:1;transform:scale(1) translateY(0)} }

.ar-modal-hd { display:flex; align-items:center; gap:0.8rem; padding:1.1rem 1.5rem; background:#1a3d2b; color:#fff; flex-shrink:0; }
.ar-modal-hd i  { font-size:1.1rem; color:#52b788; }
.ar-modal-hd h2 { font-size:1rem; font-weight:700; margin:0; flex:1; }
.ar-modal-x {
    width:30px; height:30px; border-radius:50%; border:none; cursor:pointer;
    background:rgba(255,255,255,0.12); color:#fff; font-size:1rem; line-height:1;
    display:flex; align-items:center; justify-content:center; transition:.18s;
}
.ar-modal-x:hover { background:rgba(255,255,255,0.22); }
.ar-modal-bd { flex:1; overflow-y:auto; padding:1.4rem 1.5rem; }
.ar-modal-ft { padding:0.9rem 1.5rem; border-top:1px solid #eef2ef; display:flex; justify-content:flex-end; gap:0.6rem; background:#fafcfc; flex-shrink:0; }

.ar-iframe { width:100%; height:380px; border:none; border-radius:10px; display:block; border:1.5px solid #e2ece6; background:#f0f0f0; margin-bottom:1rem; }
.ar-notes-lbl { display:block; font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#1a3d2b; margin-bottom:0.35rem; }
.ar-notes-ta {
    width:100%; padding:0.6rem 0.9rem; border:1.5px solid #dde8e3; border-radius:9px;
    font-size:0.88rem; font-family:inherit; background:#f6faf7; color:#1e3a2e;
    outline:none; box-sizing:border-box; resize:vertical; min-height:80px;
}
.ar-notes-ta:focus { border-color:#2d6a4f; background:#fff; box-shadow:0 0 0 3px rgba(45,106,79,.09); }

.ar-mbtn {
    padding:0.55rem 1.25rem; border-radius:9px; font-size:0.88rem; font-weight:700;
    cursor:pointer; border:none; font-family:inherit;
    display:inline-flex; align-items:center; gap:0.4rem; transition:.15s;
}
.ar-mbtn.cancel  { background:#f1f5f9; color:#475569; border:1.5px solid #e2e8f0; }
.ar-mbtn.cancel:hover { background:#e2e8f0; }
.ar-mbtn.do-approve { background:linear-gradient(135deg,#166534,#16a34a); color:#fff; box-shadow:0 3px 10px rgba(22,101,52,.25); }
.ar-mbtn.do-approve:hover { filter:brightness(1.1); }
.ar-mbtn.do-reject  { background:linear-gradient(135deg,#991b1b,#dc2626); color:#fff; box-shadow:0 3px 10px rgba(153,27,27,.22); }
.ar-mbtn.do-reject:hover  { filter:brightness(1.1); }
.ar-mbtn:disabled { opacity:0.45; cursor:not-allowed; filter:none; }

/* Toast */
.ar-toast {
    position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999;
    padding:0.75rem 1.2rem; border-radius:12px; font-size:0.88rem; font-weight:600;
    display:flex; align-items:center; gap:0.6rem;
    box-shadow:0 6px 22px rgba(0,0,0,.18); opacity:0;
    transform:translateY(10px); transition:all .3s; pointer-events:none;
}
.ar-toast.show { opacity:1; transform:translateY(0); }
.ar-toast.ok  { background:#1a3d2b; color:#fff; }
.ar-toast.err { background:#dc2626; color:#fff; }

@media(max-width:900px) {
    .ar-stats { grid-template-columns:repeat(2,1fr); }
    .ar-grid  { grid-template-columns:1fr; }
    .ar-page  { padding:1rem 1rem 3rem; }
}
</style>
</head>
<body>

<?php $current_page = 'accreditation'; ?>
<?php include 'navbar.php'; ?>

<div id="page-content" class="page-background">
<div class="ar-page">

    <!-- Header -->
    <div class="ar-header">
        <div class="ar-header-left">
            <div class="ar-header-icon"><i class="fas fa-certificate"></i></div>
            <div>
                <h1>Accreditation Review</h1>
                <p>Review and approve organization documents. Grant system access once all <?= $totalRequired ?> requirements are met.</p>
            </div>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="ar-stats">
        <div class="ar-stat" style="border-left-color:#2d6a4f;">
            <div class="ar-stat-icon" style="background:#e8f5ee;color:#2d6a4f;"><i class="fas fa-building"></i></div>
            <div><span class="ar-stat-num"><?= $totalOrgs ?></span><span class="ar-stat-lbl">Total Organizations</span></div>
        </div>
        <div class="ar-stat" style="border-left-color:#22c55e;">
            <div class="ar-stat-icon" style="background:#dcfce7;color:#15803d;"><i class="fas fa-check-circle"></i></div>
            <div><span class="ar-stat-num"><?= $fullyApproved ?></span><span class="ar-stat-lbl">Fully Accredited</span></div>
        </div>
        <div class="ar-stat" style="border-left-color:#3b82f6;">
            <div class="ar-stat-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="fas fa-hourglass-half"></i></div>
            <div><span class="ar-stat-num"><?= $pendingReview ?></span><span class="ar-stat-lbl">Pending Review</span></div>
        </div>
        <div class="ar-stat" style="border-left-color:#f97316;">
            <div class="ar-stat-icon" style="background:#ffedd5;color:#ea580c;"><i class="fas fa-rotate-right"></i></div>
            <div><span class="ar-stat-num"><?= $needsRevision ?></span><span class="ar-stat-lbl">Needs Revision</span></div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="ar-toolbar">
        <div class="ar-search">
            <i class="fas fa-search"></i>
            <input type="text" id="arSearch" placeholder="Search organization…">
        </div>
        <button class="ar-filter-pill active" data-filter="all">All</button>
        <button class="ar-filter-pill" data-filter="pending">Pending Review</button>
        <button class="ar-filter-pill" data-filter="revision">Needs Revision</button>
        <button class="ar-filter-pill" data-filter="approved">Accredited</button>
        <button class="ar-filter-pill" data-filter="unstarted">Not Started</button>
    </div>

    <!-- Org grid -->
    <div class="ar-grid" id="arGrid">

    <?php foreach ($orgs as $org):
        $uid            = (int)$org['user_id'];
        $verified       = !empty($org['credentials_verified']);
        $approvedCount  = (int)$org['docs_approved'];
        $submittedCount = (int)$org['docs_submitted'];
        $revisionCount  = (int)$org['docs_revision'];
        $pct            = $totalRequired > 0 ? round(($approvedCount / $totalRequired) * 100) : 0;

        if     ($verified)             { $strip='approved';  $badge='✓ Accredited';    $bCls='approved'; }
        elseif ($revisionCount > 0)    { $strip='revision';  $badge='⚠ Needs Revision'; $bCls='revision'; }
        elseif ($submittedCount > 0)   { $strip='pending';   $badge='⏳ Under Review';  $bCls='pending'; }
        else                           { $strip='unstarted'; $badge='Not Started';      $bCls='unstarted'; }

        $barClr = $pct===100 ? '#22c55e' : ($pct>=50 ? '#3b82f6' : ($pct>0 ? '#f97316' : '#d1d5db'));

        // Logo path resolution
        $logoSrc = '';
        if (!empty($org['logo_path'])) {
            $base = realpath(__DIR__ . '/../../cig_user/org-dashboard');
            $abs  = $base ? realpath($base . '/' . ltrim($org['logo_path'], './')) : null;
            if (!$abs || !file_exists($abs))
                $abs = realpath(dirname(dirname(__DIR__)) . '/' . ltrim($org['logo_path'], './'));
            if ($abs && file_exists($abs)) {
                $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
                $mime = in_array($ext,['jpg','jpeg']) ? 'image/jpeg' : 'image/png';
                $logoSrc = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($abs));
            }
        }
        $initials = strtoupper(substr($org['org_code'] ?? $org['org_name'] ?? 'ORG', 0, 3));

        // Profile completeness
        $credFields = ['org_name','org_code','contact_person','phone','description'];
        $credFilled = 0;
        foreach ($credFields as $cf) { if (!empty(trim($org[$cf]??''))) $credFilled++; }

        // Load org's uploaded docs
        $orgDocs = [];
        $rows = $db->fetchAll("SELECT doc_key, doc_label, doc_status, file_name, file_path, admin_notes, document_id FROM documents WHERE user_id = ? AND doc_key IS NOT NULL", [$uid]) ?? [];
        foreach ($rows as $dr) $orgDocs[$dr['doc_key']] = $dr;

        $canGrant = ($approvedCount === $totalRequired && $credFilled === 5 && !$verified);
    ?>
    <div class="ar-card"
         data-org="<?= htmlspecialchars(strtolower(($org['org_name']??'').' '.($org['org_code']??''))) ?>"
         data-filter="<?= $strip ?>">

        <div class="ar-strip <?= $strip ?>"></div>

        <div class="ar-card-head">
            <div class="ar-org-logo">
                <?php if ($logoSrc): ?><img src="<?= $logoSrc ?>" alt=""><?php else: ?><?= $initials ?><?php endif; ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div class="ar-org-name"><?= htmlspecialchars($org['org_name'] ?? 'Unnamed Organization') ?></div>
                <div class="ar-org-code"><?= htmlspecialchars($org['org_code'] ?? '—') ?> · <?= htmlspecialchars($org['contact_person'] ?? 'No contact') ?></div>
            </div>
            <span class="ar-badge <?= $bCls ?>"><?= $badge ?></span>
        </div>

        <div class="ar-prog">
            <div class="ar-prog-meta">
                <span>Document Approval Progress</span>
                <span><?= $approvedCount ?> / <?= $totalRequired ?></span>
            </div>
            <div class="ar-prog-track">
                <div class="ar-prog-fill" style="width:<?= $pct ?>%;background:<?= $barClr ?>;"></div>
            </div>
        </div>

        <div class="ar-docs">
            <?php if (empty($orgDocs)): ?>
            <p class="ar-doc-none"><i class="fas fa-inbox"></i> No documents uploaded yet.</p>
            <?php else: ?>
            <?php foreach ($accredDocs as $adoc):
                $dk    = $adoc['key'];
                $sub   = $orgDocs[$dk] ?? null;
                $dstat = $sub['doc_status'] ?? 'pending';
                $docId = (int)($sub['document_id'] ?? 0);
                $hasF  = !empty($sub['file_path']);
                $seqHtml = ($dstat==='approved')
                    ? '<i class="fas fa-check" style="font-size:.5rem"></i>'
                    : $adoc['seq'];
            ?>
            <div class="ar-doc-row">
                <div class="ar-doc-seq <?= $dstat ?>"><?= $seqHtml ?></div>
                <div class="ar-doc-lbl <?= $dstat==='approved' ? 'done' : '' ?>"><?= htmlspecialchars($adoc['label']) ?></div>
                <div class="ar-doc-btns">
                    <?php if ($hasF): ?>
                    <button class="ar-db view" onclick="previewDoc(<?= $docId ?>,'<?= addslashes(htmlspecialchars($adoc['label'])) ?>')">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php endif; ?>
                    <?php if ($sub && $dstat!=='approved' && $hasF): ?>
                    <button class="ar-db approve" onclick="openReview(<?= $docId ?>,<?= $uid ?>,'approve','<?= addslashes(htmlspecialchars($adoc['label'])) ?>')">
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button class="ar-db reject" onclick="openReview(<?= $docId ?>,<?= $uid ?>,'reject','<?= addslashes(htmlspecialchars($adoc['label'])) ?>')">
                        <i class="fas fa-times"></i> Reject
                    </button>
                    <?php elseif ($dstat==='approved'): ?>
                    <button class="ar-db reject" title="Undo approval" onclick="openReview(<?= $docId ?>,<?= $uid ?>,'reject','<?= addslashes(htmlspecialchars($adoc['label'])) ?>')">
                        <i class="fas fa-undo"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="ar-card-foot">
            <div class="ar-foot-meta">
                <i class="fas fa-shield-check" style="color:#2d6a4f;margin-right:.3rem"></i>
                Profile: <?= $credFilled ?>/5 &nbsp;·&nbsp; Docs: <?= $approvedCount ?>/<?= $totalRequired ?>
            </div>
            <div class="ar-foot-btns">
                <?php if ($verified): ?>
                <button class="ar-btn revoke" onclick="toggleAccred(<?= $uid ?>,0,this)">
                    <i class="fas fa-ban"></i> Revoke Access
                </button>
                <?php else: ?>
                <button class="ar-btn grant"
                    <?= $canGrant ? '' : 'disabled title="All '.$totalRequired.' docs must be approved &amp; profile complete"' ?>
                    onclick="toggleAccred(<?= $uid ?>,1,this)">
                    <i class="fas fa-unlock"></i> Grant Access
                </button>
                <?php endif; ?>
            </div>
        </div>

    </div>
    <?php endforeach; ?>

    <div class="ar-empty" id="arEmpty" style="display:none;">
        <i class="fas fa-search"></i>
        <p>No organizations match your filter.</p>
    </div>

    </div><!-- /ar-grid -->
</div><!-- /ar-page -->

<?php include 'footer.php'; ?>
</div><!-- /page-content -->

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
                <i class="fas fa-comment-dots" style="color:#2d6a4f;margin-right:.3rem"></i>
                Admin Notes <span style="font-weight:400;color:#9ab5ac;text-transform:none;letter-spacing:0;">(optional)</span>
            </label>
            <textarea class="ar-notes-ta" id="arNotes" placeholder="Add notes for the organization…"></textarea>
        </div>
        <div class="ar-modal-ft">
            <button class="ar-mbtn cancel"      onclick="closeReview()">Cancel</button>
            <button class="ar-mbtn do-reject"  id="arRejectBtn"  onclick="submitReview('revision')"><i class="fas fa-rotate-right"></i> Request Revision</button>
            <button class="ar-mbtn do-approve" id="arApproveBtn" onclick="submitReview('approve')"><i class="fas fa-check"></i> Approve Document</button>
        </div>
    </div>
</div>

<!-- Preview-only modal -->
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

<!-- Toast -->
<div class="ar-toast" id="arToast"></div>

<script src="../js/navbar.js"></script>
<script>
var _docId = null, _orgId = null;

// Filter / search
var _activeF = 'all';
document.querySelectorAll('.ar-filter-pill').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.ar-filter-pill').forEach(function(b){ b.classList.remove('active'); });
        this.classList.add('active');
        _activeF = this.dataset.filter;
        applyF();
    });
});
document.getElementById('arSearch').addEventListener('input', applyF);
function applyF() {
    var q = document.getElementById('arSearch').value.toLowerCase().trim();
    var vis = 0;
    document.querySelectorAll('.ar-card').forEach(function(c) {
        var ok = (!q||(c.dataset.org||'').includes(q)) && (_activeF==='all'||c.dataset.filter===_activeF);
        c.style.display = ok ? '' : 'none';
        if (ok) vis++;
    });
    document.getElementById('arEmpty').style.display = vis===0 ? 'block' : 'none';
}

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
    fd.append('action', action); fd.append('doc_id', _docId);
    fd.append('org_id', _orgId); fd.append('notes', document.getElementById('arNotes').value.trim());
    fetch('accreditation_review_action.php', {method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(d) {
        if (d.success) { toast(d.message,true); closeReview(); setTimeout(function(){location.reload();},900); }
        else {
            toast(d.message||'Error.',false); btn.disabled=false;
            btn.innerHTML = action==='approve' ? '<i class="fas fa-check"></i> Approve Document' : '<i class="fas fa-rotate-right"></i> Request Revision';
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
    if (!confirm(grant ? 'Grant full system access to this organization?' : 'Revoke this organization\'s accreditation and system access?')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    var fd = new FormData();
    fd.append('action', grant ? 'grant' : 'revoke'); fd.append('org_id', orgId);
    fetch('accreditation_review_action.php', {method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(d) {
        if (d.success) { toast(d.message,true); setTimeout(function(){location.reload();},900); }
        else {
            toast(d.message||'Error.',false); btn.disabled=false;
            btn.innerHTML = grant ? '<i class="fas fa-unlock"></i> Grant Access' : '<i class="fas fa-ban"></i> Revoke Access';
        }
    }).catch(function(e){toast('Error: '+e.message,false);btn.disabled=false;});
}

// Backdrop / Escape
['arReviewBg','arPreviewBg'].forEach(function(id){
    document.getElementById(id).addEventListener('click', function(e){ if(e.target===this){closeReview();closePreview();} });
});
document.addEventListener('keydown', function(e){ if(e.key==='Escape'){closeReview();closePreview();} });

// Toast
function toast(msg, ok) {
    var t = document.getElementById('arToast');
    t.textContent = msg; t.className='ar-toast '+(ok?'ok':'err');
    void t.offsetWidth; t.classList.add('show');
    setTimeout(function(){t.classList.remove('show');},3400);
}
</script>
</body>
</html>
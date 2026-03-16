<?php
/**
 * accreditation_review_action.php
 * AJAX handler — approve/reject docs, grant/revoke org accreditation.
 *
 * POST params:
 *   action  => 'approve' | 'revision' | 'grant' | 'revoke'
 *   doc_id  => int
 *   org_id  => int
 *   notes   => string (optional)
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit;
}

require_once '../db/config.php';
$db = new Database();

$action  = trim($_POST['action'] ?? '');
$docId   = (int)($_POST['doc_id'] ?? 0);
$orgId   = (int)($_POST['org_id'] ?? 0);
$notes   = trim($_POST['notes']  ?? '');
$adminId = (int)($_SESSION['user_id'] ?? 1);

// ── Helpers ───────────────────────────────────────────────────────────────────
function sendNotif($db, $userId, $title, $message, $type) {
    $db->insert('notifications', [
        'user_id' => $userId,
        'title'   => $title,
        'message' => $message,
        'type'    => $type
    ]);
}

function logAct($db, $userId, $action, $details) {
    $db->insert('activity_logs', [
        'user_id'    => $userId,
        'action'     => $action,
        'details'    => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR']     ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

// ── Document review: approve or request revision ──────────────────────────────
if ($action === 'approve' || $action === 'revision') {

    if ($docId <= 0 || $orgId <= 0) {
        echo json_encode(['success'=>false,'message'=>'Invalid document or organization ID.']); exit;
    }

    $doc = $db->fetchRow(
        "SELECT document_id, doc_key, doc_label, doc_status, user_id
         FROM documents
         WHERE document_id = ? AND user_id = ?",
        [$docId, $orgId]
    );

    if (!$doc) {
        echo json_encode(['success'=>false,'message'=>'Document not found or access denied.']); exit;
    }

    $newStatus = ($action === 'approve') ? 'approved' : 'revision';

    $db->update(
        'documents',
        ['doc_status' => $newStatus, 'admin_notes' => $notes, 'reviewed_at' => date('Y-m-d H:i:s')],
        'document_id = ?',
        [$docId]
    );

    $docLabel = $doc['doc_label'] ?? 'Document';

    if ($action === 'approve') {
        $notifTitle = 'Document Approved';
        $notifMsg   = "Your document \"{$docLabel}\" has been approved." . ($notes ? " Remarks: {$notes}" : '');
        $notifType  = 'success';
        $logDetail  = "Approved document ID {$docId} ({$docLabel}) for org ID {$orgId}";
        $resMsg     = "\"{$docLabel}\" approved successfully.";
    } else {
        $notifTitle = 'Revision Required';
        $notifMsg   = "Your document \"{$docLabel}\" requires revision." . ($notes ? " Notes: {$notes}" : '');
        $notifType  = 'warning';
        $logDetail  = "Requested revision on document ID {$docId} ({$docLabel}) for org ID {$orgId}";
        $resMsg     = "Revision requested for \"{$docLabel}\".";
    }

    sendNotif($db, $orgId, $notifTitle, $notifMsg, $notifType);
    logAct($db, $adminId, 'accreditation_doc_' . $action, $logDetail);

    echo json_encode(['success'=>true, 'message'=>$resMsg, 'new_status'=>$newStatus]); exit;
}

// ── Grant full system access ──────────────────────────────────────────────────
if ($action === 'grant') {

    if ($orgId <= 0) {
        echo json_encode(['success'=>false,'message'=>'Invalid organization ID.']); exit;
    }

    // All 11 docs must be approved
    $totalRequired = 11;
    $row = $db->fetchRow(
        "SELECT COUNT(*) AS cnt
         FROM documents
         WHERE user_id = ? AND doc_key IS NOT NULL AND doc_status = 'approved'",
        [$orgId]
    );
    $approvedCount = (int)($row['cnt'] ?? 0);

    if ($approvedCount < $totalRequired) {
        $rem = $totalRequired - $approvedCount;
        echo json_encode(['success'=>false,
            'message'=>"Cannot grant access yet — {$rem} document(s) still need approval."]); exit;
    }

    // Profile fields must all be filled
    $profile = $db->fetchRow(
        "SELECT org_name, org_code, contact_person, phone, description
         FROM users WHERE user_id = ?",
        [$orgId]
    );
    $missing = [];
    $checks  = [
        'org_name'       => 'Organization Name',
        'org_code'       => 'Organization Code',
        'contact_person' => 'Contact Person',
        'phone'          => 'Phone',
        'description'    => 'Tagline/Mission'
    ];
    foreach ($checks as $f => $lbl) {
        if (empty(trim($profile[$f] ?? ''))) $missing[] = $lbl;
    }
    if (!empty($missing)) {
        echo json_encode(['success'=>false,
            'message'=>'Incomplete org profile: ' . implode(', ', $missing) . ' must be filled.']); exit;
    }

    $db->update('users', ['credentials_verified' => 1], 'user_id = ?', [$orgId]);

    $orgName = $profile['org_name'] ?? 'Your organization';
    sendNotif($db, $orgId,
        '🎉 Accreditation Approved!',
        "Congratulations! {$orgName} has been fully accredited and now has full access to the CIG system.",
        'success'
    );
    logAct($db, $adminId, 'accreditation_granted',
        "Granted full access to org ID {$orgId} ({$orgName})");

    echo json_encode(['success'=>true,
        'message'=>"Access granted to {$orgName}. They can now use the full system."]); exit;
}

// ── Revoke accreditation ──────────────────────────────────────────────────────
if ($action === 'revoke') {

    if ($orgId <= 0) {
        echo json_encode(['success'=>false,'message'=>'Invalid organization ID.']); exit;
    }

    $db->update('users', ['credentials_verified' => 0], 'user_id = ?', [$orgId]);

    $nr      = $db->fetchRow("SELECT org_name FROM users WHERE user_id = ?", [$orgId]);
    $orgName = $nr['org_name'] ?? 'Your organization';

    sendNotif($db, $orgId,
        'Accreditation Revoked',
        "Your organization ({$orgName}) system access has been revoked by the CIG admin. Please contact the office.",
        'error'
    );
    logAct($db, $adminId, 'accreditation_revoked',
        "Revoked access for org ID {$orgId} ({$orgName})");

    echo json_encode(['success'=>true, 'message'=>"Access revoked for {$orgName}."]); exit;
}

echo json_encode(['success'=>false, 'message'=>'Unknown action.']);
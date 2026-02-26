<?php
/**
 * CIG Admin Dashboard - Organizations Page
 * Displays and manages organizations
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

// Get all organizations
$organizations = [];
try {
    $organizations = $db->fetchAll("
        SELECT o.*,
               (SELECT COUNT(*) FROM submissions WHERE org_id = o.org_id) as submission_count
        FROM organizations o
        ORDER BY o.org_name ASC
    ");
} catch (Exception $e) {
    error_log('Organizations Error: ' . $e->getMessage());
    $organizations = [];
}

// Handle organization creation
$error_message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        // Validate required fields
        if (empty($_POST['org_name']) || empty($_POST['org_code'])) {
            $error_message = 'Organization name and code are required!';
        } else {
            $db->insert('organizations', [
                'org_name' => trim($_POST['org_name']),
                'org_code' => trim($_POST['org_code']),
                'description' => trim($_POST['description'] ?? ''),
                'contact_person' => trim($_POST['contact_person'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'status' => 'active',
                'created_by' => 1 // Admin user_id = 1
            ]);
            header('Location: organizations.php?success=1');
            exit();
        }
    } catch (Exception $e) {
        $error_message = 'Error creating organization: ' . $e->getMessage();
        error_log('Organization Creation Error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Organizations - Admin</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="../css/components.css">
<link rel="stylesheet" href="../css/organizations.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php 
$current_page = 'organizations';
$user_name = $user['full_name'] ?? '';
?>
<?php include 'navbar.php'; ?>

  <!-- ORGANIZATIONS -->
  <div class="page active">
    <div class="page-header">
      <h2><i class="fas fa-building"></i> Organizations</h2>
    </div>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="success-alert">
      <i class="fas fa-check-circle"></i>
      <span>Organization created successfully!</span>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="error-alert">
      <i class="fas fa-exclamation-circle"></i>
      <span><?php echo htmlspecialchars($error_message); ?></span>
    </div>
    <?php endif; ?>

    <button onclick="showCreateOrg()" class="btn-action btn-view" style="margin-bottom: 20px;"><i class="fas fa-plus"></i> Create Organization</button>
    
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th><i class="fas fa-building"></i> Organization Name</th>
            <th><i class="fas fa-code"></i> Code</th>
            <th><i class="fas fa-envelope"></i> Email</th>
            <th><i class="fas fa-info-circle"></i> Status</th>
            <th><i class="fas fa-file-alt"></i> Submissions</th>
            <th><i class="fas fa-user"></i> Created By</th>
            <th><i class="fas fa-cog"></i> Action</th>
          </tr>
        </thead>
        <tbody id="orgTable">
          <?php if (!empty($organizations)): ?>
            <?php foreach ($organizations as $org): ?>
              <tr>
                <td class="title-cell"><strong><?php echo htmlspecialchars($org['org_name']); ?></strong></td>
                <td><span style="font-weight: 600; color: #10b981;"><?php echo htmlspecialchars($org['org_code']); ?></span></td>
                <td><?php echo htmlspecialchars($org['email'] ?? 'N/A'); ?></td>
                <td>
                  <span class="status-badge <?php echo strtolower($org['status']); ?>">
                    <i class="fas fa-circle"></i> <?php echo ucfirst($org['status']); ?>
                  </span>
                </td>
                <td><span class="badge-count"><?php echo $org['submission_count'] ?? 0; ?></span></td>
                <td><?php echo $org['created_by'] === 1 ? 'Admin' : 'System'; ?></td>
                <td>
                  <div class="action-buttons">
                    <a href="organizations.php?view=<?php echo $org['org_id']; ?>" class="btn-action btn-view"><i class="fas fa-eye"></i> View</a>
                    <a href="organizations.php?edit=<?php echo $org['org_id']; ?>" class="btn-action btn-download"><i class="fas fa-edit"></i> Edit</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="empty-row">
                <div class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>No organizations found</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- CREATE ORG MODAL -->
  <div id="createOrgModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px);">
    <div class="modal-content" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); margin: 3% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 600px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); overflow: hidden; animation: slideIn 0.3s ease-out;">
      
      <!-- Modal Header -->
      <div style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); padding: 25px 30px; color: white; display: flex; justify-content: space-between; align-items: center;">
        <div>
          <h2 style="margin: 0; font-size: 24px; font-weight: 600;">Create New Organization</h2>
          <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;">Fill in the details below to add a new organization</p>
        </div>
        <button type="button" onclick="closeCreateOrg()" style="background: rgba(255,255,255,0.2); border: none; color: white; font-size: 24px; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s;">✕</button>
      </div>

      <!-- Modal Body -->
      <form id="createOrgForm" method="POST" style="padding: 30px;">
        <input type="hidden" name="action" value="create">
        
        <!-- Name and Code in a row -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
          <div>
            <label for="org_name" style="display: block; font-weight: 600; color: #333; margin-bottom: 8px; font-size: 14px;">Organization Name <span style="color: #dc3545;">*</span></label>
            <input type="text" id="org_name" name="org_name" required placeholder="e.g., Student Government Association" style="width: 100%; padding: 11px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: all 0.3s; box-sizing: border-box;" 
            onmouseover="this.style.borderColor='#b0b0b0'" onmouseout="this.style.borderColor='#e0e0e0'"
            onfocus="this.style.borderColor='#007bff'; this.style.boxShadow='0 0 0 3px rgba(0,123,255,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
          </div>
          <div>
            <label for="org_code" style="display: block; font-weight: 600; color: #333; margin-bottom: 8px; font-size: 14px;">Code <span style="color: #dc3545;">*</span></label>
            <input type="text" id="org_code" name="org_code" required placeholder="e.g., SGA" style="width: 100%; padding: 11px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: all 0.3s; box-sizing: border-box;" 
            onmouseover="this.style.borderColor='#b0b0b0'" onmouseout="this.style.borderColor='#e0e0e0'"
            onfocus="this.style.borderColor='#007bff'; this.style.boxShadow='0 0 0 3px rgba(0,123,255,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
          </div>
        </div>

        <!-- Description -->
        <div style="margin-bottom: 20px;">
          <label for="description" style="display: block; font-weight: 600; color: #333; margin-bottom: 8px; font-size: 14px;">Description</label>
          <textarea id="description" name="description" placeholder="Enter a brief description of the organization..." style="width: 100%; height: 90px; padding: 11px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; resize: none; font-family: inherit; transition: all 0.3s; box-sizing: border-box;" 
          onmouseover="this.style.borderColor='#b0b0b0'" onmouseout="this.style.borderColor='#e0e0e0'"
          onfocus="this.style.borderColor='#007bff'; this.style.boxShadow='0 0 0 3px rgba(0,123,255,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'"></textarea>
        </div>

        <!-- Contact and Email in a row -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
          <div>
            <label for="contact_person" style="display: block; font-weight: 600; color: #333; margin-bottom: 8px; font-size: 14px;">Contact Person</label>
            <input type="text" id="contact_person" name="contact_person" placeholder="John Doe" style="width: 100%; padding: 11px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: all 0.3s; box-sizing: border-box;" 
            onmouseover="this.style.borderColor='#b0b0b0'" onmouseout="this.style.borderColor='#e0e0e0'"
            onfocus="this.style.borderColor='#007bff'; this.style.boxShadow='0 0 0 3px rgba(0,123,255,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
          </div>
          <div>
            <label for="email" style="display: block; font-weight: 600; color: #333; margin-bottom: 8px; font-size: 14px;">Email</label>
            <input type="email" id="email" name="email" placeholder="contact@organization.com" style="width: 100%; padding: 11px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: all 0.3s; box-sizing: border-box;" 
            onmouseover="this.style.borderColor='#b0b0b0'" onmouseout="this.style.borderColor='#e0e0e0'"
            onfocus="this.style.borderColor='#007bff'; this.style.boxShadow='0 0 0 3px rgba(0,123,255,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
          </div>
        </div>

        <!-- Phone -->
        <div style="margin-bottom: 25px;">
          <label for="phone" style="display: block; font-weight: 600; color: #333; margin-bottom: 8px; font-size: 14px;">Phone Number</label>
          <input type="tel" id="phone" name="phone" placeholder="+63-123-456-7890" style="width: 100%; padding: 11px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: all 0.3s; box-sizing: border-box;" 
          onmouseover="this.style.borderColor='#b0b0b0'" onmouseout="this.style.borderColor='#e0e0e0'"
          onfocus="this.style.borderColor='#007bff'; this.style.boxShadow='0 0 0 3px rgba(0,123,255,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
        </div>

        <!-- Action Buttons -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
          <button type="button" class="cancel-btn" onclick="closeCreateOrg()" style="padding: 12px 20px; background-color: #f0f0f0; color: #333; border: 2px solid #e0e0e0; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s;" 
          onmouseover="this.style.backgroundColor='#e0e0e0'" onmouseout="this.style.backgroundColor='#f0f0f0'">
            Cancel
          </button>
          <button type="submit" class="save-btn" style="padding: 12px 20px; background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s;" 
          onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(40, 167, 69, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
            Create Organization
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php include 'footer.php'; ?>
</div>


<!-- Slide-in animation for modal -->
<style>
  @keyframes slideIn {
    from {
      opacity: 0;
      transform: translateY(-30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
</style>

<script src="../js/navbar.js"></script>
<script src="../js/organizations.js"></script>
<script>
function toggleNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function showCreateOrg() {
    document.getElementById('createOrgModal').style.display = 'block';
}

function closeCreateOrg() {
    document.getElementById('createOrgModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('createOrgModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>
</body>
</html>

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


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Organizations - Admin</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
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
    
<<<<<<< Updated upstream
    <?php if (isset($_GET['success'])): ?>
    <div style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
      ✓ Organization created successfully!
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
      ✗ <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <button onclick="showCreateOrg()" class="create-btn" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin-bottom: 20px;">+ Create Organization</button>
=======

>>>>>>> Stashed changes
    
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Organization Name</th>
            <th>Code</th>
            <th>Email</th>
            <th>Status</th>
            <th>Submissions</th>
            <th>Created By</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="orgTable">
          <?php if (!empty($organizations)): ?>
            <?php foreach ($organizations as $org): ?>
              <tr>
                <td><?php echo htmlspecialchars($org['org_name']); ?></td>
                <td><?php echo htmlspecialchars($org['org_code']); ?></td>
                <td><?php echo htmlspecialchars($org['email'] ?? 'N/A'); ?></td>
                <td>
                  <span style="padding: 4px 8px; border-radius: 4px; background-color: <?php echo $org['status'] === 'active' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $org['status'] === 'active' ? '#155724' : '#721c24'; ?>;">
                    <?php echo ucfirst($org['status']); ?>
                  </span>
                </td>
                <td><?php echo $org['submission_count'] ?? 0; ?></td>
                <td><?php echo $org['created_by'] === 1 ? 'Admin' : 'System'; ?></td>
                <td>
                  <a href="organizations.php?view=<?php echo $org['org_id']; ?>" style="color: #007bff; text-decoration: none; margin-right: 10px;">View</a>
                  <a href="organizations.php?edit=<?php echo $org['org_id']; ?>" style="color: #ffc107; text-decoration: none;">Edit</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" style="text-align: center; color: #999;">No organizations found</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>


  <?php include 'footer.php'; ?>
</div>


<script src="../js/navbar.js"></script>
<script src="../js/organizations.js"></script>
<script>
function toggleNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>

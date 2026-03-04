<?php
include '../db/config.php';
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!empty($_POST['email']) && !empty($_POST['password'])) {

        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        try {
            $db = new Database();

            // Fetch admin user by email, role, and active status
            $user = $db->fetchRow(
                "SELECT * FROM users WHERE email = ? AND role = 'admin' AND status = 'active' LIMIT 1",
                [$email]
            );

            if ($user && password_verify($password, $user['password_hash'])) {

                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = $user['user_id'];
                $_SESSION['admin_email']     = $user['email'];
                $_SESSION['admin_name']      = $user['full_name'];
                $_SESSION['admin_role']      = $user['role'];

                // Update last_login timestamp
                $db->execute(
                    "UPDATE users SET last_login = NOW() WHERE user_id = ?",
                    [$user['user_id']]
                );

                $db->close();
                header("Location: index.php");
                exit();

            } else {
                $error = "Invalid admin credentials.";
            }

            $db->close();

        } catch (Exception $e) {
            $error = "A system error occurred. Please try again.";
            if (DEBUG_MODE) {
                $error .= " (" . $e->getMessage() . ")";
            }
        }

    } else {
        $error = "Please enter both email and password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CIG Admin - Login</title>
  <link rel="stylesheet" href="../css/login.css">

  <style>
    .error-message {
      color: #d32f2f;
      background-color: #ffebee;
      padding: 10px;
      border-radius: 4px;
      margin-bottom: 15px;
      text-align: center;
    }
  </style>
</head>

<body>
  <div class="login-container">
    <div class="login-background">
      <div class="gradient-blob blob-1"></div>
      <div class="gradient-blob blob-2"></div>
    </div>

    <div class="login-card">
      <div class="login-header">
        <div class="login-header-logos">
          <img src="../assets/osas2.png" alt="OSAS Logo" class="logo-placeholder">
          <img id="cig-logo" src="../assets/cigorig.png" alt="CIG Logo" class="logo-placeholder">
          <img src="../assets/plsplogo.png" alt="PLSP Logo" class="logo-placeholder">
        </div>

        <div class="header-text">
          <h2>Council of Internal Governance</h2>
          <p class="subtitle">Office of Student Affairs and Services</p>
        </div>
      </div>

      <div class="login-content">
        <h2><center>Welcome Back!</center></h2>
        <p class="description">Sign in to your account to continue</p>

        <!-- Login Form -->
        <form class="login-form" method="POST" action="">

          <?php if (!empty($error)) : ?>
            <div class="error-message">
              <?php echo htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>

          <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required
              value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
          </div>

          <div class="form-options">
            <label class="remember-me">
              <input type="checkbox" name="remember_me">
              <span>Remember me</span>
            </label>
            <a href="#" class="forgot-password">Forgot Password?</a>
          </div>

          <button type="submit" class="btn-primary">Log In</button>

        </form>
      </div>

      <footer class="footer">
        <div class="footer-content">
          <p>&copy; 2026 Council of Internal Governance. All rights reserved.</p>
          <p class="footer-subtext">
            Designed and developed for efficient governance operations
          </p>
        </div>
      </footer>
    </div>
  </div>

</body>
</html>
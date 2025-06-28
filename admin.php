<?php
require_once 'config.php';

// Check admin session
if (!isAdmin() || !checkAdminSession()) {
    header('Location: login.php');
    exit;
}

// Handle admin actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_rate_limit':
            $newLimit = intval($_POST['rate_limit']);
            if ($newLimit > 0) {
                // Update rate limit in config (you might want to use a database for this)
                file_put_contents(TEMP_DIR . 'rate_limit_config.txt', $newLimit);
                $message = "Rate limit updated to $newLimit downloads per hour";
                $messageType = 'success';
            }
            break;
            
        case 'add_admin':
            $newUsername = trim($_POST['username']);
            $newPassword = trim($_POST['password']);
            if (!empty($newUsername) && !empty($newPassword)) {
                // Add new admin (you might want to use a database for this)
                $admins = json_decode(file_get_contents(TEMP_DIR . 'admins.json') ?: '[]', true);
                $admins[] = ['username' => $newUsername, 'password' => password_hash($newPassword, PASSWORD_DEFAULT)];
                file_put_contents(TEMP_DIR . 'admins.json', json_encode($admins));
                $message = "Admin user '$newUsername' added successfully";
                $messageType = 'success';
            }
            break;
            
        case 'logout':
            logoutAdmin();
            header('Location: login.php');
            exit;
    }
}

// Get server status
$serverStatus = getServerStatus();

// Get current rate limit
$currentRateLimit = file_get_contents(TEMP_DIR . 'rate_limit_config.txt') ?: MAX_DOWNLOADS_PER_HOUR;

// Get admin users
$admins = json_decode(file_get_contents(TEMP_DIR . 'admins.json') ?: '[]', true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: #ffffff;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #00d4ff, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .admin-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 10px;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
            border: none;
            border-radius: 5px;
            color: white;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card h3 {
            color: #00d4ff;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-value {
            font-weight: bold;
        }

        .status-good {
            color: #4caf50;
        }

        .status-warning {
            color: #ff9800;
        }

        .status-error {
            color: #f44336;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #e0e0e0;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
        }

        .form-group input:focus {
            outline: none;
            border-color: #00d4ff;
        }

        .btn-primary {
            background: linear-gradient(45deg, #00d4ff, #0099cc);
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid rgba(76, 175, 80, 0.5);
            color: #4caf50;
        }

        .message.error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid rgba(244, 67, 54, 0.5);
            color: #f44336;
        }

        .admin-list {
            list-style: none;
        }

        .admin-list li {
            padding: 10px;
            background: rgba(255, 255, 255, 0.03);
            margin: 5px 0;
            border-radius: 5px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .admin-nav {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Panel</h1>
            <p>Server Management & Configuration</p>
        </div>

        <?php if (isset($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="admin-nav">
            <div class="admin-info">
                <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></strong></span>
                <span>|</span>
                <a href="index.php" class="btn">Back to Site</a>
            </div>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn">Logout</button>
            </form>
        </div>

        <div class="grid">
            <!-- Server Status -->
            <div class="card">
                <h3>🖥️ Server Status</h3>
                <div class="status-item">
                    <span>CPU Usage:</span>
                    <span class="status-value <?php echo $serverStatus['cpu_usage'] > 80 ? 'status-warning' : 'status-good'; ?>">
                        <?php echo $serverStatus['cpu_usage']; ?>%
                    </span>
                </div>
                <div class="status-item">
                    <span>Memory Usage:</span>
                    <span class="status-value <?php echo $serverStatus['memory_usage'] > 80 ? 'status-warning' : 'status-good'; ?>">
                        <?php echo $serverStatus['memory_usage']; ?>%
                    </span>
                </div>
                <div class="status-item">
                    <span>Disk Usage:</span>
                    <span class="status-value <?php echo $serverStatus['disk_usage'] > 80 ? 'status-warning' : 'status-good'; ?>">
                        <?php echo $serverStatus['disk_usage']; ?>%
                    </span>
                </div>
                <div class="status-item">
                    <span>Uptime:</span>
                    <span class="status-value"><?php echo $serverStatus['uptime']; ?></span>
                </div>
                <div class="status-item">
                    <span>Load Average:</span>
                    <span class="status-value"><?php echo implode(', ', array_map('round', $serverStatus['load_average'])); ?></span>
                </div>
            </div>

            <!-- Service Status -->
            <div class="card">
                <h3>🔧 Service Status</h3>
                <div class="status-item">
                    <span>yt-dlp:</span>
                    <span class="status-value <?php echo $serverStatus['yt_dlp_status'] ? 'status-good' : 'status-error'; ?>">
                        <?php echo $serverStatus['yt_dlp_status'] ? '✅ Online' : '❌ Offline'; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span>ffmpeg:</span>
                    <span class="status-value <?php echo $serverStatus['ffmpeg_status'] ? 'status-good' : 'status-error'; ?>">
                        <?php echo $serverStatus['ffmpeg_status'] ? '✅ Online' : '❌ Offline'; ?>
                    </span>
                </div>
            </div>

            <!-- Rate Limit Management -->
            <div class="card">
                <h3>⚡ Rate Limit Settings</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_rate_limit">
                    <div class="form-group">
                        <label for="rate_limit">Downloads per Hour:</label>
                        <input type="number" id="rate_limit" name="rate_limit" value="<?php echo $currentRateLimit; ?>" min="1" max="100">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Rate Limit</button>
                </form>
            </div>

            <!-- Add Admin User -->
            <div class="card">
                <h3>👤 Add Admin User</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_admin">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Admin</button>
                </form>
            </div>

            <!-- Current Admins -->
            <div class="card">
                <h3>👥 Current Admins</h3>
                <ul class="admin-list">
                    <li><strong><?php echo ADMIN_USERNAME; ?></strong> (Primary Admin)</li>
                    <?php foreach ($admins as $admin): ?>
                        <li><strong><?php echo htmlspecialchars($admin['username']); ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</body>
</html> 
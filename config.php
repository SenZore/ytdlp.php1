<?php
// YT-DLP PHP Web Interface Configuration for VPS

// Basic Settings
define('APP_NAME', 'YT-DLP Downloader');
define('APP_VERSION', '2.0.0');
define('DEBUG_MODE', false);

// Directory Settings
define('DOWNLOAD_DIR', 'downloads/');
define('TEMP_DIR', 'temp/');
define('LOG_DIR', 'logs/');

// File Settings
define('MAX_FILE_SIZE', 1024 * 1024 * 1024 * 2); // 2GB
define('ALLOWED_EXTENSIONS', ['mp4', 'webm', 'mkv', 'avi', 'mov', 'm4a', 'mp3']);
define('MAX_DOWNLOADS_PER_HOUR', 10);

// yt-dlp Settings - Linux Configuration
define('YT_DLP_PATH', 'yt-dlp');
define('YT_DLP_TIMEOUT', 300); // 5 minutes timeout
define('YT_DLP_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36');

// Security Settings
define('ENABLE_RATE_LIMITING', true);
define('ENABLE_FILE_VALIDATION', true);
define('ALLOWED_DOMAINS', [
    'youtube.com',
    'youtu.be',
    'vimeo.com',
    'dailymotion.com',
    'twitch.tv',
    'bilibili.com'
]);

// Admin Settings
define('ADMIN_USERNAME', 'senzore');
define('ADMIN_PASSWORD', 'DeIr48ToCfKKJwp');
define('ADMIN_SESSION_TIMEOUT', 3600); // 1 hour

// UI Settings
define('THEME_COLOR', '#00d4ff');
define('THEME_SECONDARY', '#ff6b6b');
define('ENABLE_DARK_MODE', true);

// Create necessary directories
$directories = [DOWNLOAD_DIR, TEMP_DIR, LOG_DIR];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Error reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Session management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Logging function
function logMessage($message, $level = 'INFO') {
    if (!DEBUG_MODE) return;
    
    $logFile = LOG_DIR . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Rate limiting function
function checkRateLimit($ip) {
    if (!ENABLE_RATE_LIMITING) return true;
    
    // Admins bypass rate limiting
    if (isAdmin()) return true;
    
    $rateLimitFile = TEMP_DIR . 'rate_limit_' . md5($ip) . '.txt';
    $currentTime = time();
    
    if (file_exists($rateLimitFile)) {
        $data = json_decode(file_get_contents($rateLimitFile), true);
        if ($data && $currentTime - $data['timestamp'] < 3600) {
            if ($data['count'] >= MAX_DOWNLOADS_PER_HOUR) {
                return false;
            }
            $data['count']++;
        } else {
            $data = ['timestamp' => $currentTime, 'count' => 1];
        }
    } else {
        $data = ['timestamp' => $currentTime, 'count' => 1];
    }
    
    file_put_contents($rateLimitFile, json_encode($data));
    return true;
}

// Admin authentication
function isAdmin() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function loginAdmin($username, $password) {
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
        return true;
    }
    return false;
}

function logoutAdmin() {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_login_time']);
    session_destroy();
}

function checkAdminSession() {
    if (!isset($_SESSION['admin_login_time'])) {
        return false;
    }
    
    if (time() - $_SESSION['admin_login_time'] > ADMIN_SESSION_TIMEOUT) {
        logoutAdmin();
        return false;
    }
    
    return true;
}

// Domain validation
function validateDomain($url) {
    if (!ENABLE_FILE_VALIDATION) return true;
    
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return false;
    
    foreach (ALLOWED_DOMAINS as $domain) {
        if (strpos($host, $domain) !== false) {
            return true;
        }
    }
    
    return false;
}

// File size formatting
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Server status functions
function getServerStatus() {
    $status = [
        'cpu_usage' => getCPUUsage(),
        'memory_usage' => getMemoryUsage(),
        'disk_usage' => getDiskUsage(),
        'uptime' => getUptime(),
        'load_average' => getLoadAverage(),
        'yt_dlp_status' => checkYtDlpStatus(),
        'ffmpeg_status' => checkFfmpegStatus()
    ];
    
    return $status;
}

function getCPUUsage() {
    $load = sys_getloadavg();
    return round($load[0] * 100 / 4, 2); // Assuming 4 cores
}

function getMemoryUsage() {
    $free = shell_exec('free');
    $free = (string)trim($free);
    $free_arr = explode("\n", $free);
    $mem = explode(" ", $free_arr[1]);
    $mem = array_filter($mem);
    $mem = array_merge($mem);
    $memory_usage = $mem[2]/$mem[1]*100;
    return round($memory_usage, 2);
}

function getDiskUsage() {
    $disk_total = disk_total_space('/');
    $disk_free = disk_free_space('/');
    $disk_used = $disk_total - $disk_free;
    return round(($disk_used / $disk_total) * 100, 2);
}

function getUptime() {
    $uptime = shell_exec('uptime -p');
    return trim($uptime);
}

function getLoadAverage() {
    $load = sys_getloadavg();
    return $load;
}

function checkYtDlpStatus() {
    $output = shell_exec('which yt-dlp 2>/dev/null');
    return !empty($output);
}

function checkFfmpegStatus() {
    $output = shell_exec('which ffmpeg 2>/dev/null');
    return !empty($output);
}

// Clean old files
function cleanupOldFiles() {
    $files = glob(DOWNLOAD_DIR . '*');
    $currentTime = time();
    $maxAge = 24 * 60 * 60; // 24 hours
    
    foreach ($files as $file) {
        if (is_file($file) && ($currentTime - filemtime($file)) > $maxAge) {
            unlink($file);
        }
    }
}

// Run cleanup periodically
if (rand(1, 100) <= 5) { // 5% chance to run cleanup
    cleanupOldFiles();
}
?> 
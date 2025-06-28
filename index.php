<?php
session_start();

// catatan :v
// Include configuration
require_once 'config.php';

// Get server status for display
$serverStatus = getServerStatus();

// Handle form submission
$message = '';
$messageType = '';

if ($_POST && isset($_POST['url'])) {
    $url = trim($_POST['url']);
    $format = $_POST['format'] ?? 'best';
    
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        try {
            $output = downloadVideo($url, $format);
            $message = "Download completed successfully!";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = "Please enter a valid URL";
        $messageType = 'error';
    }
}

function downloadVideo($url, $format) {
    // Sanitize filename
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', parse_url($url, PHP_URL_HOST));
    $outputPath = DOWNLOAD_DIR . $filename . '_%(title)s.%(ext)s';
    
    // Build yt-dlp command with cookies if available
    $command = YT_DLP_PATH . " --format $format --output \"$outputPath\"";
    if (USE_COOKIES && file_exists(YT_DLP_COOKIES)) {
        $command .= " --cookies \"" . YT_DLP_COOKIES . "\"";
    }
    $command .= " \"$url\" 2>&1";
    
    // Execute command
    $output = shell_exec($command);
    
    if (strpos($output, 'ERROR') !== false) {
        throw new Exception("Download failed: " . $output);
    }
    
    return $output;
}

function getAvailableFormats($url) {
    // Build yt-dlp command with cookies if available
    $command = YT_DLP_PATH . " --list-formats";
    if (USE_COOKIES && file_exists(YT_DLP_COOKIES)) {
        $command .= " --cookies \"" . YT_DLP_COOKIES . "\"";
    }
    $command .= " \"$url\" 2>&1";
    
    $output = shell_exec($command);
    
    $formats = [];
    $lines = explode("\n", $output);
    
    foreach ($lines as $line) {
        if (preg_match('/^(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(.+)$/', $line, $matches)) {
            $formats[] = [
                'id' => $matches[1],
                'extension' => $matches[2],
                'resolution' => $matches[3],
                'fps' => $matches[4],
                'description' => trim($matches[5])
            ];
        }
    }
    
    return $formats;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YT-DLP Video Downloader</title>
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

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 15px 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .server-status {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4caf50;
        }

        .status-indicator.warning {
            background: #ff9800;
        }

        .status-indicator.error {
            background: #f44336;
        }

        .admin-link {
            color: #00d4ff;
            text-decoration: none;
            padding: 8px 15px;
            border: 1px solid #00d4ff;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .admin-link:hover {
            background: #00d4ff;
            color: #000;
        }

        .container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 800px;
            width: 100%;
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

        .header p {
            color: #b0b0b0;
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #e0e0e0;
        }

        input[type="url"], select {
            width: 100%;
            padding: 15px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        input[type="url"]:focus, select:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
        }

        input[type="url"]::placeholder {
            color: #888;
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #00d4ff, #0099cc);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 212, 255, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn.secondary {
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
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

        .video-info {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }

        .video-info h3 {
            margin-bottom: 15px;
            color: #00d4ff;
        }

        .video-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .video-detail {
            background: rgba(255, 255, 255, 0.03);
            padding: 10px;
            border-radius: 5px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .video-detail strong {
            color: #00d4ff;
        }

        .format-selection {
            margin-top: 20px;
        }

        .format-tabs {
            display: flex;
            margin-bottom: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 5px;
        }

        .format-tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .format-tab.active {
            background: rgba(0, 212, 255, 0.2);
            color: #00d4ff;
        }

        .format-options {
            display: none;
        }

        .format-options.active {
            display: block;
        }

        .format-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 5px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .format-option:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: #00d4ff;
        }

        .format-option.selected {
            background: rgba(0, 212, 255, 0.2);
            border-color: #00d4ff;
        }

        .format-details {
            font-size: 0.9rem;
            color: #b0b0b0;
        }

        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
        }

        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid #00d4ff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            color: #888;
            font-size: 0.9rem;
        }

        .footer .credits {
            margin-bottom: 10px;
        }

        .footer .credits:hover::after {
            content: "SenZ lav lav burden :3";
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: #00d4ff;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            margin-left: 10px;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                gap: 15px;
            }
            
            .server-status {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .container {
                padding: 20px;
                margin: 10px;
            }
            
            .header h1 {
                font-size: 2rem;
            }

            .video-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="top-bar">
            <div class="server-status">
                <div class="status-item">
                    <div class="status-indicator <?php echo $serverStatus['cpu_usage'] > 80 ? 'warning' : ''; ?>"></div>
                    <span>CPU: <?php echo $serverStatus['cpu_usage']; ?>%</span>
                </div>
                <div class="status-item">
                    <div class="status-indicator <?php echo $serverStatus['memory_usage'] > 80 ? 'warning' : ''; ?>"></div>
                    <span>RAM: <?php echo $serverStatus['memory_usage']; ?>%</span>
                </div>
                <div class="status-item">
                    <div class="status-indicator <?php echo $serverStatus['yt_dlp_status'] ? '' : 'error'; ?>"></div>
                    <span>yt-dlp: <?php echo $serverStatus['yt_dlp_status'] ? 'Online' : 'Offline'; ?></span>
                </div>
                <div class="status-item">
                    <div class="status-indicator <?php echo $serverStatus['ffmpeg_status'] ? '' : 'error'; ?>"></div>
                    <span>ffmpeg: <?php echo $serverStatus['ffmpeg_status'] ? 'Online' : 'Offline'; ?></span>
                </div>
            </div>
            <a href="login.php" class="admin-link">🔐 Admin</a>
        </div>

        <div class="container">
            <div class="header">
                <h1>YT-DLP Downloader</h1>
                <p>Download videos from YouTube and other platforms</p>
            </div>

            <div id="message"></div>

            <form id="urlForm">
                <div class="form-group">
                    <label for="url">Video URL:</label>
                    <input type="url" id="url" name="url" placeholder="https://www.youtube.com/watch?v=..." required>
                </div>
                <button type="submit" class="btn" id="checkBtn">Check Video</button>
            </form>

            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Checking video information... Please wait</p>
            </div>

            <div class="video-info" id="videoInfo">
                <h3>Video Information</h3>
                <div class="video-details" id="videoDetails">
                    <!-- Video details will be populated here -->
                </div>

                <div class="format-selection">
                    <div class="format-tabs">
                        <div class="format-tab active" data-format="video">Video (MP4)</div>
                        <div class="format-tab" data-format="audio">Audio (MP3)</div>
                    </div>

                    <div class="format-options active" id="videoFormats">
                        <h4>Available Video Qualities</h4>
                        <div id="videoFormatList">
                            <!-- Video formats will be populated here -->
                        </div>
                    </div>

                    <div class="format-options" id="audioFormats">
                        <h4>Available Audio Qualities</h4>
                        <div id="audioFormatList">
                            <!-- Audio formats will be populated here -->
                        </div>
                    </div>

                    <button type="button" class="btn secondary" id="downloadBtn" style="margin-top: 20px;">
                        Download Selected Format
                    </button>
                </div>
            </div>

            <div class="footer">
                <div class="credits">Made by Senz with Love</div>
                <p>Powered by yt-dlp • Dark Theme Interface</p>
                <p>&copy; 2025 All Rights Reserved</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlForm = document.getElementById('urlForm');
            const urlInput = document.getElementById('url');
            const checkBtn = document.getElementById('checkBtn');
            const loading = document.getElementById('loading');
            const videoInfo = document.getElementById('videoInfo');
            const videoDetails = document.getElementById('videoDetails');
            const videoFormats = document.getElementById('videoFormats');
            const audioFormats = document.getElementById('audioFormats');
            const videoFormatList = document.getElementById('videoFormatList');
            const audioFormatList = document.getElementById('audioFormatList');
            const downloadBtn = document.getElementById('downloadBtn');
            const formatTabs = document.querySelectorAll('.format-tab');
            const message = document.getElementById('message');

            let currentFormats = [];
            let selectedFormat = null;

            // Format tab switching
            formatTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    formatTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    const format = this.dataset.format;
                    document.querySelectorAll('.format-options').forEach(opt => opt.classList.remove('active'));
                    document.getElementById(format + 'Formats').classList.add('active');
                });
            });

            // URL form submission
            urlForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const url = urlInput.value.trim();
                if (!url) {
                    showMessage('Please enter a valid URL', 'error');
                    return;
                }

                // Show loading
                loading.style.display = 'block';
                videoInfo.style.display = 'none';
                checkBtn.disabled = true;
                checkBtn.textContent = 'Checking...';

                // Get video info
                const formData = new FormData();
                formData.append('action', 'get_video_info');
                formData.append('url', url);

                fetch('download.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    checkBtn.disabled = false;
                    checkBtn.textContent = 'Check Video';

                    if (data.error) {
                        showMessage(data.error, 'error');
                        return;
                    }

                    // Display video info
                    displayVideoInfo(data);
                    currentFormats = data.formats;
                })
                .catch(error => {
                    loading.style.display = 'none';
                    checkBtn.disabled = false;
                    checkBtn.textContent = 'Check Video';
                    showMessage('Network error: ' + error.message, 'error');
                });
            });

            // Download button
            downloadBtn.addEventListener('click', function() {
                if (!selectedFormat) {
                    showMessage('Please select a format first', 'error');
                    return;
                }

                const url = urlInput.value.trim();
                const activeTab = document.querySelector('.format-tab.active');
                const formatType = activeTab.dataset.format;

                // Start streaming download
                const downloadUrl = `download.php?action=stream&url=${encodeURIComponent(url)}&format=${encodeURIComponent(selectedFormat)}&type=${formatType}`;
                
                // Create a temporary link and click it
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = 'download';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                showMessage('Download started! Check your browser downloads.', 'success');
            });

            function displayVideoInfo(data) {
                // Display video details
                videoDetails.innerHTML = `
                    <div class="video-detail">
                        <strong>Title:</strong><br>
                        ${data.title || 'Unknown'}
                    </div>
                    <div class="video-detail">
                        <strong>Duration:</strong><br>
                        ${data.duration || 'Unknown'}
                    </div>
                    <div class="video-detail">
                        <strong>Uploader:</strong><br>
                        ${data.uploader || 'Unknown'}
                    </div>
                    <div class="video-detail">
                        <strong>Views:</strong><br>
                        ${data.view_count || 'Unknown'}
                    </div>
                `;

                // Display video formats
                const videoFormats = data.formats.filter(f => !f.description.includes('audio only'));
                const audioFormats = data.formats.filter(f => f.description.includes('audio only'));

                videoFormatList.innerHTML = '';
                videoFormats.forEach(format => {
                    const option = createFormatOption(format, 'video');
                    videoFormatList.appendChild(option);
                });

                audioFormatList.innerHTML = '';
                audioFormats.forEach(format => {
                    const option = createFormatOption(format, 'audio');
                    audioFormatList.appendChild(option);
                });

                videoInfo.style.display = 'block';
            }

            function createFormatOption(format, type) {
                const div = document.createElement('div');
                div.className = 'format-option';
                div.innerHTML = `
                    <span><strong>${format.resolution} ${format.fps}</strong></span>
                    <span class="format-details">${format.description}</span>
                `;
                
                div.addEventListener('click', function() {
                    document.querySelectorAll('.format-option').forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedFormat = format.id;
                });

                return div;
            }

            function showMessage(text, type) {
                message.innerHTML = `<div class="message ${type}">${text}</div>`;
                
                setTimeout(() => {
                    const messages = document.querySelectorAll('.message');
                    messages.forEach(msg => {
                        msg.style.opacity = '0';
                        msg.style.transition = 'opacity 0.5s ease';
                        setTimeout(() => msg.remove(), 500);
                    });
                }, 5000);
            }
        });
    </script>
</body>
</html> 

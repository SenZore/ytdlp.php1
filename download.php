<?php
header('Content-Type: application/json');
session_start();

// Include configuration and utility functions
require_once 'config.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_video_info':
            getVideoInfo($_POST['url']);
            break;
        case 'get_formats':
            getAvailableFormats($_POST['url']);
            break;
        case 'download':
            downloadVideo($_POST['url'], $_POST['format']);
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

// Handle streaming downloads
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'stream') {
    streamDownload($_GET['url'], $_GET['format'], $_GET['type']);
    exit;
}

function getVideoInfo($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['error' => 'Invalid URL']);
        return;
    }
    $escapedUrl = escapeshellarg($url);
    $command = YT_DLP_PATH . " --dump-json";
    if (USE_COOKIES && file_exists(YT_DLP_COOKIES)) {
        $escapedCookies = escapeshellarg(YT_DLP_COOKIES);
        $command .= " --cookies $escapedCookies";
    }
    $command .= " $escapedUrl 2>&1";
    $output = shell_exec($command);
    if (strpos($output, 'ERROR') !== false || !$output) {
        echo json_encode(['error' => 'Failed to fetch video info: ' . $output]);
        return;
    }
    $videoData = json_decode($output, true);
    if (!$videoData) {
        echo json_encode(['error' => 'Failed to parse video information']);
        return;
    }
    $formats = getFormatsFromVideoData($videoData);
    $response = [
        'title' => $videoData['title'] ?? 'Unknown',
        'duration' => formatDuration($videoData['duration'] ?? 0),
        'uploader' => $videoData['uploader'] ?? 'Unknown',
        'view_count' => number_format($videoData['view_count'] ?? 0),
        'formats' => $formats
    ];
    echo json_encode($response);
}

function getFormatsFromVideoData($videoData) {
    $formats = [];
    if (isset($videoData['formats'])) {
        foreach ($videoData['formats'] as $format) {
            $formats[] = [
                'id' => $format['format_id'] ?? '',
                'extension' => $format['ext'] ?? '',
                'resolution' => $format['resolution'] ?? 'Unknown',
                'fps' => $format['fps'] ?? '',
                'description' => $format['format_note'] ?? '',
                'filesize' => $format['filesize'] ?? 0,
                'vcodec' => $format['vcodec'] ?? '',
                'acodec' => $format['acodec'] ?? ''
            ];
        }
    }
    // Sort formats by resolution (highest first)
    usort($formats, function($a, $b) {
        $resA = intval(preg_replace('/[^0-9]/', '', $a['resolution']));
        $resB = intval(preg_replace('/[^0-9]/', '', $b['resolution']));
        return $resB - $resA;
    });
    return $formats;
}

function formatDuration($seconds) {
    if ($seconds == 0) return 'Unknown';
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    } else {
        return sprintf('%02d:%02d', $minutes, $secs);
    }
}

function getAvailableFormats($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['error' => 'Invalid URL']);
        return;
    }
    $escapedUrl = escapeshellarg($url);
    $command = YT_DLP_PATH . " --list-formats";
    if (USE_COOKIES && file_exists(YT_DLP_COOKIES)) {
        $escapedCookies = escapeshellarg(YT_DLP_COOKIES);
        $command .= " --cookies $escapedCookies";
    }
    $command .= " $escapedUrl 2>&1";
    $output = shell_exec($command);
    if (strpos($output, 'ERROR') !== false || !$output) {
        echo json_encode(['error' => 'Failed to fetch formats: ' . $output]);
        return;
    }
    $formats = [];
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, 'ID') !== false) continue;
        if (preg_match('/^(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(.+)$/', $line, $matches)) {
            $formatId = $matches[1];
            $extension = $matches[2];
            $resolution = $matches[3];
            $fps = $matches[4];
            $description = trim($matches[5]);
            $formats[] = [
                'id' => $formatId,
                'extension' => $extension,
                'resolution' => $resolution,
                'fps' => $fps,
                'description' => $description,
                'display' => "$resolution $fps ($extension)"
            ];
        }
    }
    usort($formats, function($a, $b) {
        $resA = intval(preg_replace('/[^0-9]/', '', $a['resolution']));
        $resB = intval(preg_replace('/[^0-9]/', '', $b['resolution']));
        return $resB - $resA;
    });
    echo json_encode(['formats' => $formats]);
}

function streamDownload($url, $format, $type) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo 'Invalid URL';
        return;
    }
    $escapedUrl = escapeshellarg($url);
    $escapedFormat = escapeshellarg($format);
    $outputFormat = ($type === 'audio') ? 'mp3' : 'mp4';
    $command = YT_DLP_PATH . " --format $escapedFormat --output -";
    if (USE_COOKIES && file_exists(YT_DLP_COOKIES)) {
        $escapedCookies = escapeshellarg(YT_DLP_COOKIES);
        $command .= " --cookies $escapedCookies";
    }
    $command .= " $escapedUrl";
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="download.' . $outputFormat . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    $handle = popen($command, 'r');
    if ($handle) {
        while (!feof($handle)) {
            $buffer = fread($handle, 8192);
            echo $buffer;
            flush();
        }
        pclose($handle);
    } else {
        http_response_code(500);
        echo 'Failed to start download';
    }
}

function downloadVideo($url, $format) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['error' => 'Invalid URL']);
        return;
    }
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', parse_url($url, PHP_URL_HOST));
    $outputPath = DOWNLOAD_DIR . $filename . '_%(title)s.%(ext)s';
    $escapedFormat = escapeshellarg($format);
    $escapedOutput = escapeshellarg($outputPath);
    $escapedUrl = escapeshellarg($url);
    $command = YT_DLP_PATH . " --format $escapedFormat --output $escapedOutput --newline";
    if (USE_COOKIES && file_exists(YT_DLP_COOKIES)) {
        $escapedCookies = escapeshellarg(YT_DLP_COOKIES);
        $command .= " --cookies $escapedCookies";
    }
    $command .= " $escapedUrl 2>&1";
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    if ($returnCode !== 0) {
        echo json_encode(['error' => 'Download failed: ' . implode("\n", $output)]);
        return;
    }
    $files = glob(DOWNLOAD_DIR . '*');
    $latestFile = null;
    $latestTime = 0;
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) > $latestTime) {
            $latestTime = filemtime($file);
            $latestFile = $file;
        }
    }
    if ($latestFile) {
        $fileSize = filesize($latestFile);
        $fileName = basename($latestFile);
        echo json_encode([
            'success' => true,
            'file' => $fileName,
            'size' => formatBytes($fileSize),
            'path' => $latestFile
        ]);
    } else {
        echo json_encode(['error' => 'Download completed but file not found']);
    }
}

// Handle direct file download
if (isset($_GET['file'])) {
    $file = $_GET['file'];
    $filePath = DOWNLOAD_DIR . basename($file);
    if (file_exists($filePath) && is_file($filePath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        http_response_code(404);
        echo 'File not found';
    }
}
?> 

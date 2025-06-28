<?php
header('Content-Type: application/json');
session_start();

// Include configuration and utility functions
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

use YoutubeDl\Options;
use YoutubeDl\YoutubeDl;

function getYoutubeDlInstance() {
    $yt = new YoutubeDl();
    // Set binary path if needed: $yt->setBinPath(YT_DLP_PATH);
    $yt->setBinPath(YT_DLP_PATH);
    return $yt;
}

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
    $yt = getYoutubeDlInstance();
    $options = Options::create()
        ->url($url)
        ->noPlaylist(true)
        ->dumpSingleJson(true);
    if (USE_COOKIES && file_exists(YT_DLP_COOKIES)) {
        $options = $options->cookies(YT_DLP_COOKIES);
    }
    try {
        $collection = $yt->download($options);
        $video = $collection->getVideos()[0];
        if ($video->getError() !== null) {
            echo json_encode(['error' => $video->getError()]);
            return;
        }
        $formats = [];
        foreach ($video->getFormats() as $format) {
            $formats[] = [
                'id' => $format->getFormatId(),
                'extension' => $format->getExt(),
                'resolution' => $format->getResolution() ?? 'Unknown',
                'fps' => $format->getFps(),
                'description' => $format->getFormatNote(),
                'filesize' => $format->getFilesize(),
                'vcodec' => $format->getVcodec(),
                'acodec' => $format->getAcodec()
            ];
        }
        echo json_encode([
            'title' => $video->getTitle(),
            'duration' => $video->getDuration(),
            'uploader' => $video->getUploader(),
            'view_count' => $video->getViewCount(),
            'formats' => $formats
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getAvailableFormats($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['error' => 'Invalid URL']);
        return;
    }
    $yt = getYoutubeDlInstance();
    $options = Options::create()
        ->url($url)
        ->listFormats(true);
    if (USE_COOKIES && file_exists(YT_DLP_COOKIES)) {
        $options = $options->cookies(YT_DLP_COOKIES);
    }
    try {
        $collection = $yt->download($options);
        $video = $collection->getVideos()[0];
        $formats = [];
        foreach ($video->getFormats() as $format) {
            $formats[] = [
                'id' => $format->getFormatId(),
                'extension' => $format->getExt(),
                'resolution' => $format->getResolution() ?? 'Unknown',
                'fps' => $format->getFps(),
                'description' => $format->getFormatNote(),
                'filesize' => $format->getFilesize(),
                'vcodec' => $format->getVcodec(),
                'acodec' => $format->getAcodec()
            ];
        }
        echo json_encode(['formats' => $formats]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
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
    $yt = getYoutubeDlInstance();
    $options = Options::create()
        ->url($url)
        ->downloadPath(DOWNLOAD_DIR)
        ->format($format)
        ->output('%(title)s.%(ext)s');
    if (USE_COOKIES && file_exists(YT_DLP_COOKIES)) {
        $options = $options->cookies(YT_DLP_COOKIES);
    }
    try {
        $collection = $yt->download($options);
        $video = $collection->getVideos()[0];
        if ($video->getError() !== null) {
            echo json_encode(['error' => $video->getError()]);
            return;
        }
        $file = $video->getFile();
        echo json_encode([
            'success' => true,
            'file' => $file ? $file->getFilename() : null,
            'size' => $file ? formatBytes($file->getSize()) : null,
            'path' => $file ? $file->getPathname() : null
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
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

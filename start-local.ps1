# YT-DLP PHP Web Interface - Local Development Server
Write-Host "Starting YT-DLP PHP Web Interface..." -ForegroundColor Green
Write-Host ""

# Set PHP path
$phpPath = "C:\php-8.4.8-nts-Win32-vs17-x64\php.exe"

# Check if PHP exists
if (-not (Test-Path $phpPath)) {
    Write-Host "Error: PHP not found at $phpPath" -ForegroundColor Red
    Write-Host "Please update the phpPath variable in this script to match your installation" -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}

# Create necessary directories
$directories = @("downloads", "temp", "logs")
foreach ($dir in $directories) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir | Out-Null
        Write-Host "Created directory: $dir" -ForegroundColor Yellow
    }
}

Write-Host "PHP found at: $phpPath" -ForegroundColor Green
Write-Host "Starting PHP development server..." -ForegroundColor Green
Write-Host ""
Write-Host "Access the web interface at: http://localhost:8000" -ForegroundColor Cyan
Write-Host "Press Ctrl+C to stop the server" -ForegroundColor Yellow
Write-Host ""

# Start PHP development server
try {
    & $phpPath -S localhost:8000
} catch {
    Write-Host "Error starting PHP server: $_" -ForegroundColor Red
    Read-Host "Press Enter to exit"
} 